<?php
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


  /**
   * Constructeur, autour d‘un fichier epub local
   * On va dans le labyrinthe du zip pour trouver la toc
   * — attraper META-INF/container.xml
   * — dans container.xml, trouver le chemin vers content.opf
   * — dans content.opf chercher le lien vers
   */
  public function __construct( $epubfile )
  {
    $this->_filemtime = filemtime( $epubfile );
    $this->_zip = new ZipArchive();
    $this->_basename = basename( $epubfile);
    if ( ($err=$this->_zip->open( $epubfile )) !== TRUE ) {
      // http://php.net/manual/fr/ziparchive.open.php
      if ( $err == ZipArchive::ER_NOZIP ) throw new Exception( $basename." n’est pas un zip" );
      else throw new Exception( $this->_basename." impossible à ouvrir" );
    }
    if ( ($cont = $this->_zip->getFromName('META-INF/container.xml')) === FALSE ) {
      throw new Exception( $this->_basename.', container.xml introuvable' );
    }
    if ( !preg_match( '@full-path="([^"]+)"@', $cont, $matches ) ) {
      throw new Exception( $this->_basename.', pas de lien au fichier opf' );
    }
    if ( ($cont = $this->_zip->getFromName( $matches[1]) ) === FALSE ) {
      throw new Exception( $this->_basename.'#'.$matches[1].' introuvable (container opf)' );
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
    $this->meta( $opf ); // ou bien xpath ?
    $this->_html[] = "  </head>";
    $this->_html[] = "  <body>";

    // aller chercher une toc
    // attantion, 2 formats possibles, *.ncx, ou bien xhtml
    // <item href="toc.ncx" id="ncx" media-type="application/x-dtbncx+xml"/>
    $nl = $xpath->query("//opf:item[@media-type='application/x-dtbncx+xml']");
    if ($nl->length) {
      $tochref = $nl->item(0)->getAttribute("href");
      if ( $tochref[0] != "/") $tochref = $opfdir.$tochref;
      if ( ($cont = $this->_zip->getFromName( $tochref ) ) === FALSE ) {
        throw new Exception( $this->_basename.'#'.$tochref.' introuvable (toc ncx)' );
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
   * Sortir le fichier html
   */
  public function html()
  {
    return implode( "\n", $this->_html);
  }
  /**
   * Extraire les métadonnées en html
   */
  public function meta( $opf )
  {
    // TODO, charger les métadonnées quelque part

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
        $title = preg_replace("/\s+/", " ", $title);
        $this->_html[] = $margin.'<section title="'.$title.'" class="toc">';
      }
      else if ( $name == "content" ) {
        $src = $node->getAttribute("src");
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
    // TODO
    if ( ( $html = $this->_zip->getFromName( $this->_tocdir.$fromfile ) ) === FALSE ) {
      // throw new Exception( $this->_basename.'#'.$fromfile.' dans la toc mais introuvable' );
      return "<!-- ERROR ".$this->_tocdir.$fromfile." indiqué dans la toc mais introuvable -->";
    }
    //
    if ( $fromanchor ) {
      if ( !preg_match( '@<([^ >]+)[^>]*id="'.$fromanchor.'"[^>]*>@', $html, $matches, PREG_OFFSET_CAPTURE) )
        throw new Exception( $this->_basename.'#'.$fromfile.' '.$fromanchor.' ancre non trouvée' );
      $startpos = $matches[0][1];
    }
    else {
      if ( !preg_match( '@<body[^>]*>@', $html, $matches, PREG_OFFSET_CAPTURE) )
        throw new Exception( $this->_basename.'#'.$fromfile.' pas de balise <body>' );
      $startpos = $matches[0][1]+strlen( $matches[0][0] );
    }
    if ( $fromfile == $tofile ) {
      if ( !$toanchor )
        throw new Exception( $this->_basename.'#'.$fromfile.' incohérence d’ancre dans la toc' );
      if ( !preg_match( '@<([^ >]+)[^>]*id="'.$toanchor.'"[^>]*>@', $html, $matches, PREG_OFFSET_CAPTURE) )
        throw new Exception( $this->_basename.'#'.$fromfile.' '.$fromanchor.' ancre non trouvée' );
      $endpos = $matches[0][1];
    }
    // fin de fichier
    else {
      if ( !preg_match( '@</body>@', $html, $matches, PREG_OFFSET_CAPTURE) )
        $endpos = strlen( $html );
      else
        $endpos = $matches[0][1];
    }

    // même fichier, ancres d
    return "<!-- ".$from." -> ".$to." -->\n".substr( $html, $startpos, $endpos - $startpos );
  }
  /**
   * From an xml String, build a good dom with right options
   */
  static function dom( $xml ) {
    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput=true;
    $dom->substituteEntities=true;
    $dom->loadXML($xml, LIBXML_NOENT | LIBXML_NONET | LIBXML_NSCLEAN | LIBXML_NOCDATA | LIBXML_COMPACT | LIBXML_PARSEHUGE | LIBXML_NOWARNING);
    return $dom;
  }


  public static function cli()
  {
    $depub = new Depub("test.epub");
    echo $depub->html();
  }
}

?>
