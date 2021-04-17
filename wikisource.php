<?php 

if ( php_sapi_name() == "cli" ) Wikisource::cli();


class Wikisource
{
  /** Config for tidy html, used for inserted fragments: http://tidy.sourceforge.net/docs/quickref.html */
  public static $tidyconf = array(
    // will strip all unknown tags like <svg> or <section>
    'clean' => false, 
    'doctype' => "omit",
    // 'force-output' => true, // let tidy complain
    // 'indent' => true, // xsl done
    // 'input-encoding' => "utf8", // ?? OK ?
    // 'newline' => "LF",
    'numeric-entities' => true,
    // 'new-blocklevel-tags' => 'section',
    // 'char-encoding' => "utf8",
    // 'output-encoding' => "utf8", // ?? OK ?
    'output-xhtml' => true,
    // 'output-xml' => true, // show-body-only will bug with <svg> => <html>
    // 'preserve-entities' => false, // unknown
    // 'quote-nbsp' => false,
    'wrap' => 0,
    // 'show-body-only' => true,
  );
  public static function clean($htmlfile)
  {
    $html = file_get_contents($htmlfile);
    /*
    // indent some blocks
    $html = preg_replace(
      array( '@(</(div|h1|h2|h3|h4|h5|h6|p)>)([^\n])@', '@(<body)@'),
      array( "$1\n$3", "\n$1" ),
      $html
    );
    // preserve some critic XML entities before transcoding
    $html = preg_replace( "@&(amp|lt|gt);@", "£$1;", $html );
    $html = html_entity_decode( $html, ENT_QUOTES | ENT_HTML5 , 'UTF-8' );
    $html = preg_replace( "@£(amp|lt|gt);@", "&$1;", $html );
    // restore some entities before transcoding
    // $html = preg_replace( self::$rehtml[0], self::$rehtml[1], $html );
    */
    // html usually need to be repaired, because of bad html fragments
    $html = tidy_repair_string ( $html, self::$tidyconf );
    
    $xsl = new DOMDocument();
    $xsl->load( dirname(__FILE__)."/wikisource.xsl" );
    $trans = new XSLTProcessor();
    $trans->importStyleSheet($xsl);
    
    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false;
    // $dom->formatOutput=true;
    $dom->substituteEntities=true;
    $dom->encoding = "UTF-8";
    $dom->recover = true;
    libxml_clear_errors();
    // libxml_use_internal_errors(true);
    // recover could break section structure
    // $dom->loadHTML( $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOBLANKS | LIBXML_NOENT | LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOXMLDECL );
    // LIBXML_NOERROR  | LIBXML_NSCLEAN  | LIBXML_NOCDATA
    $dom->loadXML($html, LIBXML_NOBLANKS | LIBXML_NOENT | LIBXML_NONET );
    $dom->encoding = "UTF-8";
    $errors = libxml_get_errors();
    // faut-il faire quelque chose des erreurs ici ?
    libxml_clear_errors();
    $dom->preserveWhiteSpace = false;
    $html = $trans->transformToXML($dom);

    
    
    file_put_contents($htmlfile.".xhtml", $html);

  }


  public static function cli()
  {
    $count = count($_SERVER['argv']);
    if ($count < 2) {
      exit('
    usage     : php -f wikisource.php "*.html"
    *.html    : glob patterns are allowed, with or without quotes, win or nix
    
    tidy release: '.tidy_get_release ().'
');
    }
    for ($i = 1; $i < $count; $i++) {
      $glob = $_SERVER['argv'][$i];
      foreach(glob($glob) as $srcfile) {
        fwrite (STDERR, $srcfile."\n");
        self::clean($srcfile);
      }
    }
  }
}
?>
