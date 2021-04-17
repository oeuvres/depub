<?php
$bmlFile = $_SERVER['argv'][1];
$bmlParts = pathinfo($bmlFile);
$xmlFile = $bmlParts['dirname'].'/'.$bmlParts['filename'].".xml";
$bml = file_get_contents($bmlFile);
$xml = file_get_contents($xmlFile);


preg_match_all( '@<pagenum( v="v(\d+)")? num="(\d+)"[^>/]*>([^<]+)</pagenum>@', $bml, $matches, PREG_SET_ORDER);
$search = array();
$replace = array();
foreach($matches as $pagenum) {
  /*
  // <pb n="2" xml:id="v2p2"/>
  $s ='<pb n="'.$pagenum[3].'" xml:id="v'.$pagenum[2].'p'.$pagenum[3].'"/>';
  $r = $pagenum[4].$s;
  */
  $s ='<pb n="'.$pagenum[3].'"/>';
  $r = $pagenum[4].$s;
  echo $s, " ", $r, "\n";
  $search[] = $s;
  $replace[] = $r;
}
$xml = str_replace ($search, $replace, $xml);
if (isset($_SERVER['argv'][2]) and $_SERVER['argv'][2] == 'write') file_put_contents($xmlFile, $xml);

?>
