<?php
/*
  LGPL http://www.gnu.org/licenses/lgpl.html
© 2017 frederic.glorieux@fictif.org et LABEX OBVIL
*/
error_reporting(E_ALL);
include dirname(__FILE__).'/Web.php';
include dirname(__FILE__).'/Depub.php';
// Post submit
$upload = Phips_Web::upload();
if ($upload) {
  // if ($upload['extension'] == 'xml' || $upload['extension'] == 'tei')
  $srcfile = $upload['tmp_name'];
  $depub = new Depub( $srcfile );
  if ( isset($_REQUEST['html']) ) {
    header ("Content-Type: text/plain; charset=UTF-8");
    echo $depub->html();
  }
  else {
    header ("Content-Type: text/xml; charset=UTF-8");
    echo $depub->tei();
  }
  exit();
}

$action='index.php';
$lang = Phips_Web::lang();
// $teinte = '../Teinte/';

?><!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8"/>
    <title>Debook, délier un livre (électronique)</title>
    </head>
  <body>
    <h1>Debook, délier un livre (électronique)</h1>
    <p>Convertir un livre électronique dans un format structuré</p>
    <form class="center"
      action="<?php echo $action; ?>"
      enctype="multipart/form-data" method="POST" name="upload" target="_blank"
    >
      <script type="text/javascript">
function changeAction(form, ext) {
  var filename=form.file.value;var pos=filename.lastIndexOf('.'); if(pos>0) filename=filename.substring(0, pos); form.action='index.php/'+filename+ext;
}
      </script>
      <input type="file" size="70" name="file" accept="application/epub+zip"/>
      <button name="html" onmousedown="changeAction(this.form, '.html'); " title="Transformation vers HTML" type="submit">HTML</button>
      <button name="tei" onmousedown="changeAction(this.form, '.xml'); " title="Transformation vers XML/TEI" type="submit">XML/TEI</button>
    </form>
  </body>
</html>
