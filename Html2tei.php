<?php
if (php_sapi_name() == "cli") {
  Html2tei::docli();
}
class Html2tei {
  public static function transform($srcfile, $destfile) {
    $xsl=new DOMDocument("1.0", "UTF-8");
    $xsl->load(dirname(__FILE__).'/html2tei.xsl');
    $trans=new XSLTProcessor();
    $trans->importStyleSheet($xsl);

    // try to load html as XML
    $doc = new DOMDocument("1.0", "UTF-8");
    $doc->preserveWhiteSpace = false;
    libxml_use_internal_errors(true); // don’t mind of warnings on <a id="page305" name="page305">
    $html = file_get_contents( $srcfile );
    $html = preg_replace(
      array( '@xmlns="http://www.w3.org/1999/xhtml"@', '@xml:lang="[^"]*"@'),
      array('', ''),
      $html
    );
    $doc->loadHTML( $html ); // PHP 5.4 LIBXML_NOCDATA | LIBXML_NOENT | LIBXML_NONET | LIBXML_NOBLANKS
    libxml_use_internal_errors(false); // restore errors
    // will it set namespace properly ?
    $doc->encoding = 'UTF-8';
    if ( !$doc->documentElement->getAttribute("xmlns") ) {
      $doc->documentElement->setAttribute("xmlns", "http://www.w3.org/1999/xhtml");
    }
    // xml should be reloaded in DOM to get xmlns working
    $xml = $doc->saveXML(null, LIBXML_NOENT | LIBXML_NOBLANKS);
    // try one sentence by line

    $re=array(
    /* Specific Gutenberg

<p><a id="footnote450" name="footnote450"></a>
<b><a href="#footnotetag450">450</a></b>:

<p><a id="note360" name="note360"></a>
<b>Note 360:</b>
    */
      '@&#13;@' => '', // found but why ?
      '@<p><a id="(foot)?note([0-9]+)"[^>]*>(</a>)?@' => '<p class="note" n="$2" id="fn$2">',
      '@(<p class="note"[^>]+>)\s*<b><a[^>]*>[^<]*</a></b>: *@' => '$1',
      '@(<p class="note"[^>]+>)\s*<b>[^<]*:</b> *@' => '$1',
      '@<ref[^>]*>[\(\[]retour[^<]*[\)\]]</ref>@i' => '',
      '@<a href="#(foot)?note@' => '<a href="#fn',
      // Generic
      '@\n@' => ' ', // suppress line breaks
      '@<(address|article|aside|blockquote|div|p)(\s[^>]+)?>@' => '<$1$2>'."\n", // line break after opening block tag, with possible attributes
      '@</(address|article|aside|blockquote|div|p)>@' => "\n".'</$1>', // line break before closing block tag
        // french punctuation
      '@\.\.\.@' => '…',
      '@ ([;:?!»])@u' => ' $1', // unbreak space
      '@« @u' => '$1 ', // unbreak space
      '@([^  <])([;:?!])@u' => '$1 $2',
      '@([^  <])([»])@u' => '$1 $2', // redo for yo?»
      '@([«])([^  ])@u' => '$1 $2',
      '@([^  ])([–—])([^  ])@u' => '$1 $2 $3',
      '@(\.)([–—])([^  ])@u' => '$1 $2 $3',
      '@ :/@' => ':/', // https ://
      '@( [a-z]+) (:[a-z]+=")@u' => '$1$2',// xml :lang=",
      '@ \?>@' => '?>',// encoding="UTF-8"? >
      '@(&[a-z]+) ;@'  => '$1;', // &gt ;
      '@\'@' => '’', // oriented apos
      /*
       // one sentence by line
      '@(av|ch|etc|fr|ms|mss|St)\.@u' => '$1&#46;', // abbr, non period dots protected by &#46
      '@(\W\p{Lu}\p{L}*)\.\s+(\p{Lu}\p{L}*\.)@u' => '$1&#46; $2', // Vit. Lud. Gross.
      '@(\W\p{Lu}\p{L}*)\.\s+(\p{Lu}\p{L}*\.)@u' => '$1&#46; $2', // Vit. Lud. Gross. (multiple)
      '@(\W\p{L})\.@u' => '$1&#46;', // p., m., M.
      '@( +)([.)][) »]*)@u' => '$2$1', // ?
      '@([\.\?\!…»]) *([-–—])@u' => '$1'."\n".'$2', // carrets
      '@([\.\?\!…»]) *<(pb|lb)@u' => '$1'."\n".'<$2', // tags
      '@([\?\.\!…] *) ([\p{Lu}\[\({])@u' => "\$1\n\$2", // simple case, hard punct followed by uppercape
      '@([\.…!?:][\)]?) ( *[«"“][  ]*\p{Lu})@u' => "\$1\n\$2", // : «…
      '@([\.\?…!][  ]*[»”"]) *(\p{Lu})@u' => "\$1\n\$2", // . »
      '@&#46;@' => '.', //restore protected periods
      */
    );
    // $xml = preg_replace(array_keys($re), array_values($re), $xml);

    $doc->loadXML($xml);
    $xml= '<?xml version="1.0" encoding="UTF-8"?>
<?xml-stylesheet type="text/xsl" href="../Teinte/tei2html.xsl"?>
<?xml-model href="http://oeuvres.github.io/Teinte/teinte.rng" type="application/xml" schematypens="http://relaxng.org/ns/structure/1.0"?>
' . $trans->transformToXML($doc);
    // $xml = $doc->saveXML(null, LIBXML_NOENT | LIBXML_NOBLANKS);

    if ( !file_exists( dirname( $destfile ) ) ) mkdir( dirname( $destfile ), null, true );
    file_put_contents( $destfile, $xml );
  }

  public static function docli() {
    array_shift($_SERVER['argv']); // shift first arg, the script filepath
    if (!count($_SERVER['argv'])) exit('
      usage    : php -f Html2tei.php src/*.html
');
    // $srcglob=array_shift($_SERVER['argv']);
    /*
    $dstdir=rtrim(array_shift($_SERVER['argv']), '/');
    if($dstdir) $dstdir = $dstdir . '/';
    */
    $destdir = "./";
    foreach ($_SERVER['argv'] as $srcglob) {
      foreach( glob( $srcglob) as $srcfile) {
        $destfile = $destdir.pathinfo($srcfile, PATHINFO_FILENAME).'.xml';
        echo $srcfile, ' > ', $destfile, "\n";
        self::transform($srcfile, $destfile);
      }
    }
  }
}

?>
