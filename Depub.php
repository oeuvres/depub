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
  /** table des identifiants de manifest */
  private $_manifest = array();
  /** vérifier que la toc passe à travers tous les fichiers html */
  private $_spine = array();
  /** Files inserted count */
  private $_chops = 0;
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
   * — dans content.opf chercher le lien vers toc.ncx
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
        $this->log( E_USER_ERROR, $this->_basename." is not a zip file" );
        return;
      }
      $this->log( E_USER_ERROR, $this->_basename." impossible ton open" );
      return;
    }
    if ( ($cont = $this->_zip->getFromName('META-INF/container.xml')) === FALSE ) {
      $this->log( E_USER_ERROR, $this->_basename.', container.xml not found' );
      return;
    }
    if ( !preg_match( '@full-path="([^"]+)"@', $cont, $matches ) ) {
      $this->log( E_USER_ERROR, $this->_basename.', no link to an opf file' );
      return;
    }
    if ( ($cont = $this->_zip->getFromName( urldecode( $matches[1] ) ) ) === FALSE ) {
      $this->log( E_USER_ERROR, $this->_basename.'#'.$matches[1].' opf container not found' );
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
    $this->_html[] = '<html xmlns="http://www.w3.org/1999/xhtml"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  xmlns:dcterms="http://purl.org/dc/terms/"
  xmlns:epub="http://www.idpf.org/2007/ops"
  xmlns:opf="http://www.idpf.org/2007/opf"
>'; // dc:, dcterms:, opf: maybe not set in <metadata>
    $this->_html[] = '  <head>';
    $this->_html[] = '  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';
    // $this->meta( $opf ); // ou bien xpath ?
    $metadata = $opf->getElementsByTagName('metadata')->item(0);
    $this->_html[] = $opf->saveXML( $metadata );
    $this->_html[] = '  </head>';
    $this->_html[] = '  <body>';
    // get path by id in manifest
    $nl = $opf->getElementsByTagName('manifest');
    if ( !$nl ) {
      $this->log( E_USER_ERROR, $this->_basename.'#content.opf <manifest> not found' );
    }
    else {
      foreach ( $nl->item(0)->childNodes as $node ) {
        if ( $node->nodeType != XML_ELEMENT_NODE ) continue;
        // test media-type ?
        $id = $node->getAttribute("id");
        $href = $node->getAttribute("href");
        $this->_manifest[$id] = $href;
      }
    }
    // keep the flow of <spine>
    $nl = $opf->getElementsByTagName('spine');
    if ( !$nl ) {
      $this->log( E_USER_ERROR, $this->_basename.'#content.opf <spine> not found' );
    }
    else {
      foreach ( $nl->item(0)->childNodes as $node ) {
        if ( $node->nodeType != XML_ELEMENT_NODE ) continue;
        $idref = $node->getAttribute("idref");
        if ( !isset( $this->_manifest[ $idref ] ) ) {
          $this->log( E_USER_ERROR, $this->_basename.'#content.opf <spine>, idref="'.$idref.'" not found' );
          continue;
        }
        // if content.opf is not in same folder as toc.ncx, possible problems
        $path = $this->_manifest[ $idref ];
        $this->_spine[ basename( $path ) ] = $path;
      }
    }
    // aller chercher une toc
    // attention, 2 formats possibles, *.ncx, ou bien xhtml
    // <item href="toc.ncx" id="ncx" media-type="application/x-dtbncx+xml"/>
    $nl = $xpath->query("//opf:item[@media-type='application/x-dtbncx+xml']");
    if ( $nl->length ) {
      $tochref = $nl->item(0)->getAttribute("href");
      if ( $tochref[0] != "/") $tochref = $opfdir.$tochref;
      if ( ($cont = $this->_zip->getFromName( urldecode( $tochref ) ) ) === FALSE ) {
        $this->log( E_USER_ERROR, $this->_basename.'#'.$tochref.' (toc ncx) not found' );
        return;
      }
      $this->_tocdir = dirname( $tochref );
      if ( $this->_tocdir == ".") $this->_tocdir = "";
      else $this->_tocdir.="/";
      $toc = self::dom( $cont );
      $this->ncxrecurs( $toc->getElementsByTagName("navMap") );
      // no items found in toc, insert all spine
      if ( !$this->_chops ) {
        foreach ( $this->_spine as $href ) {
          $this->_html[] = $this->chop( $href, null );
        }
        $msg = "  — WARNING ".$this->_basename.' no entry found in toc, <spine> insertion';
        $this->log( E_USER_WARNING, $msg );
      }
    }
    else {
      $this->log( E_USER_ERROR, $this->_basename.' no toc found' );
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
   * Recursive parse of a nav point
   */
  public function ncxrecurs( $nl, $margin="", $root=true )
  {
    $indent="  ";
    if ( !$nl->length ) return;
    $title = "";
    foreach ($nl as $node ) {
      if ( $node->nodeType != XML_ELEMENT_NODE ) continue;
      $name = $node->tagName;
      if ( $name == "navMap" ) {
        $this->ncxrecurs( $node->childNodes, $margin.$indent, false );
      }
      else if ( $name == "navPoint" ) {
        $this->ncxrecurs( $node->childNodes, $margin.$indent, false );
      }
      // open a section
      if ( $name == "navLabel" ) {
        $title = substr( trim( $node->textContent ), 0, 1000);
        if ( $title ) {
          $title = preg_replace(array("/\s+/u", '@&([A-Za-z]*[^;])@', '/"/u'), array(" ", '&amp;$1', "&quot;"), $title);
          $this->_html[] = $margin.'<section title="'.$title.'" class="toc">';
        }
        else {
          $this->_html[] = $margin.'<section class="toc">';
          $title = true;
        }
      }
      else if ( $name == "content" ) {
        $src = $node->getAttribute("src");
        // @src is empty, trick found to open a hierarchical section with no content
        if ( !$src ) continue;
        $srcfile = basename( $src );
        if ( $pos = strpos($srcfile, '#') ) $srcfile = substr( $srcfile, 0, $pos);
        // first point in navigation, check if there are files before in the spine
        if ( !$this->_lastpoint ) {
          $cont = array(); // html content to compile
          reset( $this->_spine );
          while( $entry = each( $this->_spine ) ) {
            if ( $entry[0] == $srcfile ) break;
            $name = strtolower( pathinfo( $entry[0], PATHINFO_FILENAME) );
            if ( $name == "titlepage" || $name == "cover" || $name == "colophon" || $name == "toc" ) continue;
            // here we can have a problem in link resolution if content.opf and toc.ncx in different folder
            $cont[] =  $this->chop( $entry[1], null );
          }
          if ( count( $cont ) ) $this->_html[] = implode( $cont, "\n" );
        }
        // keep memory of this href
        $this->_html[] = $src;
        $lastpoint = count( $this->_html ) - 1;
        // insert now from last href to current href
        if ( $this->_lastpoint ) {
          $lasthref = $this->_html[ $this->_lastpoint ];
          $lastfile = basename( $lasthref );
          if ( $pos = strpos($lastfile, '#') ) $lastfile = substr( $lastfile, 0, $pos);
          $cont = array(); // html content to compile
          // if nextfile is different from lastfile, search in spine if there are files between the 2 toc entries
          if ( $lastfile != $srcfile ) {
            reset( $this->_spine );
            // forward cursor in spine to the last file pointed
            while( $entry = each( $this->_spine ) ) {
              if ( $entry[0] == $lastfile ) break;
            }
            // search for interstitial files
            while( $entry = each( $this->_spine ) ) {
              if ( $entry[0] == $srcfile ) break;
              // here we can have a problem in link resolution if content.opf and toc.ncx in different folder
              $cont[] =  $this->chop( $lasthref, $entry[1] );
              $lasthref = $entry[1];
            }
          }
          // multiple sections may have been open here
          $cont[] = $this->chop( $lasthref, $src );
          $this->_html[ $this->_lastpoint ] = implode( $cont, "\n" );
        }
        $this->_lastpoint = $lastpoint;
      }
    }
    // a section has been opened in this call, close it
    if ($title) $this->_html[] = $margin.'</section>';
    // root call, lasthref has not been inserted
    if ( $root )  {
      // finish work on last link
      $lasthref = $this->_html[ $this->_lastpoint ];
      $cont = array();
      $cont[] = $this->chop( $lasthref, null );
      // check if it is end if spine
      reset( $this->_spine );
      // forward cursor in spine to the last file pointed
      $lastfile = basename( $lasthref );
      if ( $pos = strpos($lastfile, '#') ) $lastfile = substr( $lastfile, 0, $pos);
      while( $entry = each( $this->_spine ) ) {
        if ( $entry[0] == $lastfile ) break;
      }
      // insert last file
      while( $entry = each( $this->_spine ) ) {
        $cont[] =  $this->chop( $entry[1], null );
      }
      $this->_html[ $this->_lastpoint ] = implode( $cont, "\n" );
    }
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
      $msg = "  — WARNING ".$this->_tocdir.$fromfile." file mentioned but not found";
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
    $this->_chops++;
    // chercher l’index de début dans le fichier HTML
    $startpos = 0;
    if ( !preg_match( '@<body[^>]*>@', $html, $matches, PREG_OFFSET_CAPTURE) ) {
      $msg = "  — WARNING ".$this->_basename.'#'.$fromfile.' no <body> tag';
      $this->log( E_USER_WARNING, $msg );
      $chop[] = "<!-- $msg -->";
    }
    else $startpos = $matches[0][1]+strlen( $matches[0][0] );
    if ( $fromanchor ) {
      // take start of line
      // <h1 class="P_Heading_1"><span><a id="auto_bookmark_1"/>PROLOGUE</span></h1>
      if ( !preg_match( '@\n.*id="'.$fromanchor.'"@', $html, $matches, PREG_OFFSET_CAPTURE) ) {
        $msg = "  — WARNING ".$this->_basename.'#'.$fromfile.' '.$fromanchor.' anchor not found, some text maybe replicated';
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
        $msg = "  — WARNING ".$this->_basename.'#'.$fromfile.' some text maybe replicated ';
        $this->log( E_USER_WARNING, $msg );
        $chop[] = "<!-- $msg -->";
      }
      else if ( !preg_match( '@<([^ >]+)[^>]*id="'.$toanchor.'"[^>]*>@', $html, $matches, PREG_OFFSET_CAPTURE) ) {
        $msg = "  — WARNING ".$this->_basename.'#'.$fromfile.' '.$fromanchor.' anchor not found, some text may be replicated';
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
    $html = tidy_repair_string ( $html, self::$tidyconf);

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
