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
  /** file freshness */
  private $_filemtime;
  /** buffer html */
  private $_html;
  /** ??  */
  private $_navDir;
  /** las nav point */
  private $_navPoint;

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
    $basename = basename( $epubfile);
    if ( ($err=$this->_zip->open( $epubfile )) !== TRUE ) {
      // http://php.net/manual/fr/ziparchive.open.php
      if ( $err == ZipArchive::ER_NOZIP ) throw new Exception( $basename." n’est pas un zip" );
      else throw new Exception( $basename." impossible à ouvrir" );
    }
    if ( ($cont = $this->_zip->getFromName('META-INF/container.xml')) === FALSE ) {
      throw new Exception( $basename.', container.xml introuvable' );
    }
    if ( !preg_match( '@full-path="([^"]+)"@', $cont, $matches ) ) {
      throw new Exception( $basename.', pas de lien au fichier opf' );
    }
    if ( ($cont = $this->_zip->getFromName( $matches[1]) ) === FALSE ) {
      throw new Exception( $basename.'#'.$matches[1].' introuvable (container opf)' );
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
    $this->_html[] = "<!DOCTYPE html>";
    $this->_html[] = "<html>";
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
        throw new Exception( $basename.'#'.$tochref.' introuvable (toc ncx)' );
      }
      $toc = self::dom( $cont );
      $this->_html[] = "    <article>";
      $this->ncxrecurs( $toc->getElementsByTagName("navMap") );
      $this->_html[] = "    </article>";
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
  public function ncxrecurs( $nl, $margin="    " )
  {
    $indent="  ";
    if ( !$nl->length ) return;
    $title = "";
    foreach ($nl as $node ) {
      if ( $node->nodeType != XML_ELEMENT_NODE ) continue;
      $name = $node->tagName;
      $this->_html[] = "<!-- ".$name." -->";
      if ( $name == "navLabel" ) {
        $title = trim( $node->textContent );
        $title = preg_replace("/\s+/", " ", $title);
        $this->_html[] = $margin.'<section title="'.$title.'">';
      }
      else if ( $name == "content" ) {
        $this->_html[] = $margin.$indent.'<!-- '.$node->getAttribute("src").' -->';
      }
      else if ( $name == "navMap" ) {
        $this->ncxrecurs( $node->childNodes, $margin.$indent );
      }
      else if ( $name == "navPoint" ) {
        $this->ncxrecurs( $node->childNodes, $margin.$indent );
      }
    }
    // une section a été ouverte, la refermer
    if ($title) $this->_html[] = $margin.'</section>';
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
