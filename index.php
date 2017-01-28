<?php
/*
  LGPL http://www.gnu.org/licenses/lgpl.html
© 2017 frederic.glorieux@fictif.org et LABEX OBVIL
*/
error_reporting(E_ALL);
include dirname(__FILE__).'/Depub.php';

// Post submit
$upload = upload();
if ($upload) {
  // if ($upload['extension'] == 'xml' || $upload['extension'] == 'tei')
  $depub = new Depub( $upload['tmp_name'] );
  $depub->basename( $upload['name'] );
  // headers
  if ( isset($_REQUEST['download'] ) ) {
    if ( isset($_REQUEST['html']) ) {
      header ("Content-Type: text/html; charset=UTF-8");
      $ext="html";
    }
    else {
      header ("Content-Type: text/xml; charset=UTF-8");
      $ext='xml';
    }
    header('Content-Disposition: attachment; filename="'.$upload['filename'].'.'.$ext.'"');
    header('Content-Description: File Transfer');
    header('Expires: 0');
    header('Cache-Control: ');
    header('Pragma: ');
    flush();
  }


  if ( isset($_REQUEST['html']) ) {
    if ( !isset($_REQUEST['download'] ) ) header ("Content-Type: text/plain; charset=UTF-8");
    echo "\n<!--";
    $text = $depub->html();
    echo "\n-->";
    echo $text;
  }
  else {
    if ( !isset($_REQUEST['download'] ) ) header ("Content-Type: text/xml; charset=UTF-8");
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo "\n<!--";
    $text = $depub->tei();
    echo "\n-->";
    echo $text;
  }
  exit();
}

$action='index.php';

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
      <button name="tei" onmousedown="changeAction(this.form, '.xml'); " title="Transformation vers XML/TEI" type="submit">TEI</button>
      <label>Télécharger
        <input type="checkbox" name="download"<?php if ( isset($_REQUEST['download']) ) echo ' checked="checked"' ?>/>
      </label>
    </form>
  </body>
</html>
<?php
/**
 * Get link to un upload file, by key or first one if no key
 * return a file record like ine $_FILES
 * http://php.net/manual/features.file-upload.post-method.php
 */
function upload( $key=null, $lang="fr" ) {
  // no post, return nothing
  if ($_SERVER['REQUEST_METHOD'] != 'POST') return false;
  $mess = array(
    UPLOAD_ERR_INI_SIZE => array(
      'en' => 'The uploaded file exceeds a directive in php.ini; upload_max_filesize='.ini_get('upload_max_filesize').', post_max_size='.ini_get('post_max_size'),
      "fr" => 'Le fichier téléchargé dépasse la limite acceptée par la configuration du serveur (php.ini) ; upload_max_filesize='.ini_get('upload_max_filesize').', post_max_size='.ini_get('post_max_size'),
    ),
    UPLOAD_ERR_FORM_SIZE => array(
      'en' => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.',
      'fr' => 'Le fichier téléchargé dépasse la directive MAX_FILE_SIZE spécifiée dans le formulaire.',
    ),
    UPLOAD_ERR_PARTIAL => array(
      'en' => 'The uploaded file was only partially uploaded. ',
      'fr' => 'Le fichier téléchargé est incomplet',
    ),
    UPLOAD_ERR_NO_FILE => array(
      'en' => 'No file was uploaded.',
      'fr' => 'Pas de fichier téléchargé.',
    ),
    UPLOAD_ERR_NO_TMP_DIR => array(
      'en' => 'Server configuration error, missing a temporary folder.',
      'fr' => 'Erreur de configuration serveur, pas de dossier temporaire.',
    ),
    UPLOAD_ERR_CANT_WRITE => array(
      'en' => 'Server system error, failed to write file to disk.',
      'fr' => 'Erreur système sur le serveur, impossible d’écrire le fichier sur le disque.',
    ),
    UPLOAD_ERR_EXTENSION => array(
      'en' => 'PHP server problem, a PHP extension stopped the file upload.',
      'fr' => 'Erreur de configuration PHP, une extension a arrêté le téléchargement du fichier.',
    ),
    'nokey' => array(
      'en' => "Phips_Web::upload(), no field $key in submitted form.",
      'fr' => "Phips_Web::upload(), pas de champ $key dans le formulaire soumis.",
    ),
    'nofile' => array(
      'en' => 'Phips_Web::upload(), no file found. Too big ? Directives in php.ini: upload_max_filesize='.ini_get('upload_max_filesize').', post_max_size='.ini_get('post_max_size'),
      'fr' => 'Phips_Web::upload(), pas de fichier trouvé. Trop gros ? Directives php.ini: upload_max_filesize='.ini_get('upload_max_filesize').', post_max_size='.ini_get('post_max_size'),
    ),
  );
  if ($key && !isset($_FILES[$key])) throw new Exception($mess['nokey'][$lang]);
  if ($key) $file = $_FILES[$key];
  else $file = reset($_FILES);
  if (!$file || !is_array($file) || !isset($file['error'])) throw new Exception($mess['nofile'][$lang]);
  // validation, no matter for an exception
  if ($file['error'] == UPLOAD_ERR_NO_FILE) return false;
  if ($file['error']) throw new Exception($mess[$file['error']][$lang]);
  // return the array to have the tmp link, and the original name of the file, and some more useful fields
  $file["filename"] = pathinfo($file['name'], PATHINFO_FILENAME);
  $file["extension"] = pathinfo($file['name'], PATHINFO_EXTENSION);
  return $file;
}
?>
