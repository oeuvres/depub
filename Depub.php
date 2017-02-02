<?php
Depub::init();
// pour appel en ligne de commande
if ( php_sapi_name() == "cli" ) Depub::cli();
/**
 * Cette classe mange un epub pour essayer d'en sortir un htmll le plus propre possible
 * avec des <section> imbriquée, récupérables ensuite en TEI (ou autre chose)
 */
class Depub
{

  /** Pointeur sur l’objet zip, privé ou public ? */
  private $_zip;
  /** epub freshness */
  private $_filemtime;
  /** epub file name */
  private $_basename;
  /** toc basedir to resolve html links */
  private $_tocdir;
  /** buffer html */
  private $_html;
  /** keep memory of where to insert html content */
  private $_lastpoint;
  /** TODO, vérifier que la toc passe à travers tous les fichiers html */
  private $_tocfiles = array();
  /** Log level for web, do not output  */
  public $loglevel = E_ALL;
  /** A logger, maybe a stream or a callable, used by $this->log() */
  private $_logger;
  /** Initialisation done */
  private static $_init;
  /** To wash html */
  public static $rehtml;
  /** Config for tidy html, used for inserted fragments */
  public static $tidyconf = array(
    'clean' => true,
    'doctype' => "omit",
    'force-output' => true,
    // 'indent' => true, // fait pas xsl
    'input-encoding' => "utf8", // ?? OK ?
    'newline' => "LF",
    // 'new-blocklevel-tags' => 'section',
    // 'numeric-entities' => false,
    'output-encoding' => "utf8",
    'output-xhtml' => true, // will strip unknown html5 tags, use new-blocklevel-tags
    // 'output-xml' => true,
    'quote-nbsp' => false,
    'wrap' => false,
    'show-body-only' => true,
  );

  /**
   * Constructeur, autour d‘un fichier epub local
   * On va dans le labyrinthe du zip pour trouver la toc
   * — attraper META-INF/container.xml
   * — dans container.xml, trouver le chemin vers content.opf
   * — dans content.opf chercher le lien vers
   */
  public function __construct( $epubfile, $logger="php://output" )
  {
    if (is_string($logger)) $logger = fopen($logger, 'w');
    $this->_logger = $logger;

    $this->_filemtime = filemtime( $epubfile );
    $this->_zip = new ZipArchive();
    $this->_basename = basename( $epubfile);
    if ( ($err=$this->_zip->open( $epubfile )) !== TRUE ) {
      // http://php.net/manual/fr/ziparchive.open.php
      if ( $err == ZipArchive::ER_NOZIP ) {
        $this->log( E_USER_ERROR, $this->_basename." n’est pas un zip" );
        return;
      }
      $this->log( E_USER_ERROR, $this->_basename." impossible à ouvrir" );
      return;
    }
    if ( ($cont = $this->_zip->getFromName('META-INF/container.xml')) === FALSE ) {
      $this->log( E_USER_ERROR, $this->_basename.', container.xml introuvable' );
      return;
    }
    if ( !preg_match( '@full-path="([^"]+)"@', $cont, $matches ) ) {
      $this->log( E_USER_ERROR, $this->_basename.', pas de lien au fichier opf' );
      return;
    }
    if ( ($cont = $this->_zip->getFromName( urldecode( $matches[1] ) ) ) === FALSE ) {
      $this->log( E_USER_ERROR, $this->_basename.'#'.$matches[1].' introuvable (container opf)' );
      return;
    }
    $opfdir = dirname( $matches[1] );
    if ( $opfdir == ".") $opfdir = "";
    else $opfdir.="/";
    // charger le container opf, contient les métadonnées et autres liens
    $opf = self::dom( $cont );
    $xpath = new DOMXpath($opf);
    $xpath->registerNamespace("opf", "http://www.idpf.org/2007/opf" );
    // pas de concaténation de String
    $this->_html = array();
    $this->_html[] = '<?xml version="1.0" encoding="utf-8"?>';
    $this->_html[] = '<!DOCTYPE html>';
    $this->_html[] = '<html xmlns="http://www.w3.org/1999/xhtml">';
    $this->_html[] = "  <head>";
    $this->_html[] = '  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';
    // $this->meta( $opf ); // ou bien xpath ?
    $metadata = $opf->getElementsByTagName('metadata')->item(0);
    $this->_html[] = $opf->saveXML( $metadata );
    $this->_html[] = "  </head>";
    $this->_html[] = "  <body>";

    // aller chercher une toc
    // attantion, 2 formats possibles, *.ncx, ou bien xhtml
    // <item href="toc.ncx" id="ncx" media-type="application/x-dtbncx+xml"/>
    $nl = $xpath->query("//opf:item[@media-type='application/x-dtbncx+xml']");
    if ($nl->length) {
      $tochref = $nl->item(0)->getAttribute("href");
      if ( $tochref[0] != "/") $tochref = $opfdir.$tochref;
      if ( ($cont = $this->_zip->getFromName( urldecode( $tochref ) ) ) === FALSE ) {
        $this->log( E_USER_ERROR, $this->_basename.'#'.$tochref.' introuvable (toc ncx)' );
        return;
      }
      $this->_tocdir = dirname( $tochref );
      if ( $this->_tocdir == ".") $this->_tocdir = "";
      else $this->_tocdir.="/";
      $toc = self::dom( $cont );
      $this->ncxrecurs( $toc->getElementsByTagName("navMap") );

    }
    else {
      // toc xhtml ?
    }
    $this->_html[] = "  </body>";
    $this->_html[] = "</html>";
    $this->_html[] = "";
  }
  /**
   * Output the concatenate html
   */
  public function html()
  {
    return implode( "\n", $this->_html );
  }
  /**
   * Sortir le fichier Tei
   */
  public function tei()
  {
    $html =  implode( "\n", $this->_html );
    $xsl = new DOMDocument();
    $xsl->load( dirname(__FILE__)."/html2tei.xsl" );
    $trans = new XSLTProcessor();
    $trans->importStyleSheet( $xsl );
    return $trans->transformToXML( self::dom($html) );
  }

  /**
   * Produire les info en
   */
  public function ncxrecurs( $nl, $margin="", $first=true )
  {
    $indent="  ";
    if ( !$nl->length ) return;
    $title = "";
    foreach ($nl as $node ) {
      if ( $node->nodeType != XML_ELEMENT_NODE ) continue;
      $name = $node->tagName;
      if ( $name == "navLabel" ) {
        $title = trim( $node->textContent );
        $title = preg_replace("/\s+/u", " ", $title);
        $this->_html[] = $margin.'<section title="'.$title.'" class="toc">';
      }
      else if ( $name == "content" ) {
        $src = $node->getAttribute("src");
        // @src is empty, trick to open a hierarchical section with no content
        if ( !$src ) continue;
        $this->_html[] = $src;
        // on connait la fin du précédent chunk, on peut choper le bout de html
        if ( $this->_lastpoint ) {
          $this->_html[ $this->_lastpoint ] = $this->chop( $this->_html[ $this->_lastpoint ], $src );
        }
        $this->_lastpoint = count( $this->_html )-1;
      }
      else if ( $name == "navMap" ) {
        $this->ncxrecurs( $node->childNodes, $margin.$indent, false );
      }
      else if ( $name == "navPoint" ) {
        $this->ncxrecurs( $node->childNodes, $margin.$indent, false );
      }
    }
    // une section a été ouverte, la refermer
    if ($title) $this->_html[] = $margin.'</section>';
    // finir le travail sur le dernier fichier
    if ( $first ) $this->_html[ $this->_lastpoint ] = $this->chop( $this->_html[ $this->_lastpoint ], null );
  }
  /**
   * Choper un bout de html dans le zip
   */
  public function chop( $from, $to )
  {
    $chop = array(); // html à retourner
    $chop[] = "<!-- ".$from." -> ".$to." -->";
    $fromfile = $from;
    $fromanchor = "";
    if ( $pos = strpos($from, '#') )
      list ( $fromfile, $fromanchor ) = explode( "#", $from );
    $tofile = $to;
    $toanchor = "";
    if ( $pos = strpos($to, '#') )
      list ( $tofile, $toanchor ) = explode( "#", $to );
    // normalement, le texte à insérer est contenu dans un seul fichier
    // sauf dans le cas où il y a des fichiers dans le <spine> (ordre de navigation)
    // qui ne sont pas dans la toc
    // TODO ? lire le spine
    if ( ( $html = $this->_zip->getFromName( $this->_tocdir.urldecode( $fromfile ) ) ) === FALSE ) {
      $msg = "  — WARNING ".$this->_tocdir.$fromfile." fichier indiqué mais introuvable";
      $this->log( E_USER_WARNING, $msg );
      return "<!-- $msg -->";
    }
    // indent blocks with possible ids ?
    /*
    $html = preg_replace(
      array(),
      array(),
      $html
    )
    */

    // chercher l’index de début dans le fichier HTML
    $startpos = 0;
    if ( !preg_match( '@<body[^>]*>@', $html, $matches, PREG_OFFSET_CAPTURE) ) {
      $msg = "  — WARNING ".$this->_basename.'#'.$fromfile.' pas de balise <body>';
      $this->log( E_USER_WARNING, $msg );
      $chop[] = "<!-- $msg -->";
    }
    else $startpos = $matches[0][1]+strlen( $matches[0][0] );
    if ( $fromanchor ) {
      // take start of line
      // <h1 class="P_Heading_1"><span><a id="auto_bookmark_1"/>PROLOGUE</span></h1>
      if ( !preg_match( '@\n.*id="'.$fromanchor.'"@', $html, $matches, PREG_OFFSET_CAPTURE) ) {
        $msg = "  — WARNING ".$this->_basename.'#'.$fromfile.' '.$fromanchor.' risque de texte répliqué, ancre indiquée dans la toc mais non trouvée';
        $this->log( E_USER_WARNING, $msg );
        $chop[] = "<!-- $msg -->";
      }
      else $startpos = $matches[0][1];
    }
    // chercher l‘index de fin dans le fichier HTML
    $endpos = strlen( $html );
    if ( preg_match( '@</body>@', $html, $matches, PREG_OFFSET_CAPTURE) )
      $endpos = $matches[0][1];
    // le chapitre suivant commence dans le même fichier, il faut une ancre de fin
    if ( $fromfile == $tofile ) {
      // pas d’ancre de fin, risque de répliquer du texte
      if ( !$toanchor ) {
        $msg = "  — WARNING ".$this->_basename.'#'.$fromfile.' risque de texte répliqué ';
        $this->log( E_USER_WARNING, $msg );
        $chop[] = "<!-- $msg -->";
      }
      else if ( !preg_match( '@<([^ >]+)[^>]*id="'.$toanchor.'"[^>]*>@', $html, $matches, PREG_OFFSET_CAPTURE) ) {
        $msg = "  — WARNING ".$this->_basename.'#'.$fromfile.' risque de texte répliqué, '.$fromanchor.' ancre non trouvée';
        $this->log( E_USER_WARNING, $msg );
        $chop[] = "<!-- $msg -->";
      }
      else {
        $endpos = $matches[0][1];
      }
    }
    $html = substr( $html, $startpos, $endpos - $startpos );
    // entités HTML4 pourries
    $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5 | ENT_XML1, 'UTF-8');
    $html = preg_replace( self::$rehtml[0], self::$rehtml[1], $html );
    $html = tidy_repair_string ( $html , self::$tidyconf);

    $chop[] = $html;
    return implode( $chop, "\n");
  }
  /**
   * Change basename after upload, for better comments
   */
  public function basename( $basename )
  {
    $this->_basename = $basename;
  }

  /**
   * Custom error handler
   * May be used for xsl:message coming from transform()
   */
  function log( $errno, $errstr, $errfile=null, $errline=null, $errcontext=null)
  {
    if ( !$this->loglevel & $errno ) return false;
    $errstr=preg_replace("/XSLTProcessor::transform[^:]*:/", "", $errstr, -1, $count);
    if ( !$this->_logger );
    else if ( is_resource($this->_logger) ) fwrite( $this->_logger, $errstr."\n");
    else if ( is_string($this->_logger) && function_exists( $this->_logger ) ) call_user_func( $this->_logger, $errstr );
  }

  /**
   *
   */
  public static function init()
  {
    if (self::$_init) return;
    self::$rehtml = self::sed2preg( dirname(__FILE__)."/html.sed" );
    self::$_init = true;
  }


  /**
   * Build a search/replace regexp table from a sed script
   */
  public static function sed2preg( $file ) {
    $search=array();
    $replace=array();
    $handle = fopen( $file, "r");
    while (( $l = fgets($handle)) !== false) {
      $l = trim($l);
      if ( !$l ) continue;
      if ($l[0] == '#') continue;
      if ($l[0] != 's') continue;
      list($a,$s,$r)=explode($l[1], $l);
      $search[]=$l[1].$s.$l[1].'u';
      $replace[]=preg_replace('/\\\\([0-9]+)/', '\\$$1', $r);
        // process the line read.
    }
    fclose($handle);
    return array($search, $replace);
  }


  /**
   * From an xml String, build a good dom with right options
   */
  static function dom( $xml , $html=false )
  {
    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput=true;
    $dom->substituteEntities=true;
    $dom->encoding = "UTF-8";
    // libxml_use_internal_errors(true);
    // recover could break section structure
    // $dom->recover = true;
    // if ( $html ) $dom->loadHTML($xml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOBLANKS | LIBXML_NOENT | LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOXMLDECL );
    // LIBXML_NOERROR  | LIBXML_NSCLEAN  | LIBXML_NOCDATA
    $dom->loadXML($xml, LIBXML_NOBLANKS | LIBXML_NOENT | LIBXML_NONET );
    $errors = libxml_get_errors();
    // faut-il faire quelque chose des erreurs ici ?
    libxml_clear_errors();
    $dom->encoding = "UTF-8";
    return $dom;
  }

  public static function cli()
  {
    $actions = array( "html", "tei" );
    array_shift($_SERVER['argv']); // shift first arg, the script filepath
    if (!count($_SERVER['argv'])) exit('
    usage     : php -f Depub.php ('.implode( $actions, "|").') destdir/? "*.epub"
    format    : dest format
    destdir/? : optional destdir
    *.epub    : glob patterns are allowed, with or without quotes, win or nix

');

    $format="tei";
    $ext = ".xml";
    $test = trim($_SERVER['argv'][0], '- ');
    if ( in_array( $test, $actions ) ) {
      $format = $test;
      array_shift($_SERVER['argv']);
    }
    if ( $format == "html" ) $ext = ".html";

    $destdir = "";
    $lastc = substr($_SERVER['argv'][0], -1);
    if ('/' == $lastc || '\\' == $lastc) {
      $destdir = array_shift($_SERVER['argv']);
      $destdir = rtrim($destdir, '/\\').'/';
      if (!file_exists($destdir)) {
        mkdir($destdir, 0775, true);
        @chmod($dir, 0775);  // let @, if www-data is not owner but allowed to write
      }
    }

    while($glob = array_shift($_SERVER['argv']) ) {
      foreach(glob($glob) as $srcfile) {
        $destname = pathinfo( $srcfile, PATHINFO_FILENAME ).$ext;
        // if ( !$destdir ) $destfile = dirname( $srcfile )."/".$destname;
        $destfile = $destfile = $destdir.$destname;
        fwrite (STDERR, $srcfile." => ".$destfile."\n");
        $depub = new Depub( $srcfile );
        if ( $format == "html" ) file_put_contents( $destfile, $depub->html() );
        else file_put_contents( $destfile, $depub->tei() );
        fwrite(STDERR, "\n");
      }
    }
  }
}

?>
