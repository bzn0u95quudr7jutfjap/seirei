<?php

// =======================================================================================================================================================
// DISPLAY DEL CONTENUTO DEI FILE
// =======================================================================================================================================================

function dispaly_text($file)
{
  header("Content-Type: text/plain; charset=utf-8");
  readfile($file);
}

function display_image($file)
{
?>
  <!DOCTYPE html>
  <html>
  <style>
    html,
    body {
      margin: 0px;
      padding: 0px;
      width: 100%;
      height: 100%;
    }

    body {
      display: grid;
      grid-template-columns: auto;
      grid-template-rows: auto;
      justify-items: center;
      align-content: center;
    }

    img {
      max-width: 100%;
      max-height: 100%;
    }
  </style>

  <body>
    <img src="<?php echo $file; ?>">
  </body>

  </html>
<?php
}

function display_default($file)
{
  header("Location: http://localhost:8888/$file");
}

if (array_key_exists("file", $_GET)) {
  $filename = $_GET["file"];
  $mime = mime_content_type($filename);
  match (true) {
    strpos($mime, "text") !== false => dispaly_text($filename),
    strpos($mime, "image") !== false => display_image($filename),
    default => display_default($filename),
  };
}

if (count($_GET) != 0) {
  die();
}

// =======================================================================================================================================================
// COMANDI POST
// =======================================================================================================================================================

// $CONFIG_FILE_JSON = ".immagio.json";
// 
// if (array_key_exists("command", $_POST) && strcmp($_POST["command"], "save") == 0) {
//   if (file_put_contents($CONFIG_FILE_JSON, $_POST['data'])) {
//     echo "Salvato con successo\n";
//   } else {
//     echo "Errore\n";
//   }
// }
// 
// if (array_key_exists("command", $_POST) && strcmp($_POST["command"], "apply") == 0) {
//   $data = json_decode($_POST['data']);
// 
//   $bad_dirs = [];
//   foreach ($data->etichette as $dir) {
//     $e = file_exists($dir);
//     if (($e && is_dir($dir)) || (!$e && mkdir($dir))) {
//       echo "Creazione di '$dir' : SUCCESSO\n";
//     } else {
//       echo "Creazione di '$dir' : ERRORE\n";
//       $bad_dirs[] = $dir;
//     }
//   }
//   echo "\n";
// 
//   $bad_files = array_filter($data->associazioni, function ($i) use ($bad_dirs) {
//     return in_array($i->label, $bad_dirs);
//   });
//   $associazioni = array_filter($data->associazioni, function ($i) use ($bad_dirs) {
//     return !in_array($i->label, $bad_dirs);
//   });
// 
//   foreach ($associazioni as $o) {
//     $from = $o->path;
//     $to = $o->label . '/' . $o->path;
//     $e = file_exists($to);
//     if (!$e && rename($from, $to)) {
//       echo "Spostamento di '$from' in '$to' : SUCCESSO\n";
//     } else {
//       $bad_files[] = $o;
//       $bad_dirs[] = $o->label;
//       echo "Spostamento di '$from' in '$to' : " . ($e ? "FILE GIÀ ESISTENTE" : "ERRORE") . "\n";
//     }
//   }
// 
//   $associazioni = (object)["etichette" => array_unique($bad_dirs), "associazioni" => $bad_files];
// 
//   if (file_put_contents($CONFIG_FILE_JSON, json_encode($associazioni))) {
//     echo "Salvato con successo\n";
//   } else {
//     echo "Errore\n";
//   }
// }

function save()
{
  echo "save funciton";
}

function apply()
{
  echo "apply funciton";
}

if (array_key_exists('command', $_POST)) {
  match ($_POST['command']) {
    "save" => save(),
    "apply" => apply(),
    default => null,
  };
}

if (count($_POST) != 0) {
  die();
}

// =======================================================================================================================================================
// PAGINA PRINCIPALE
// =======================================================================================================================================================


function map($function, $collection)
{
  return array_map($function, $collection);
}
function filter($function, $collection)
{
  return array_filter($collection, $function);
}

$files = filter(
  function ($f) {
    return !is_dir($f) && $f != "index.php";
  },
  glob('*')
);


const htmlminiatura = "
  <a target='contenuto' href='./?file={{FILENAME}}'>
    <img class='miniatura' src='{{FILENAME}}' alt='{{FILENAME}}' onclick='displayFile(this);'>
  </a>
";

$miniature = implode(
  "\n",
  map(
    function ($filename) {
      return str_replace("{{FILENAME}}", $filename, htmlminiatura);
    },
    $files
  )
);

const htmletichetta = "
  <div class='bersaglio' >
    <input class='radio' type='radio' name='label_radio' onclick='etichettaFile(this)'>
    <input class='text'  type='text'  name='label_text'  value='{{ETICHETTA}}'>
  </div>
";

$etichette = '';
$associazioni = '';

const CONFIGFILEJSON = ".immagio.json";
if (file_exists(CONFIGFILEJSON)) {
  $conf = json_decode(file_get_contents(CONFIGFILEJSON));
  $etichette = implode(
    "\n",
    map(
      function ($etichetta) {
        return str_replace("{{ETICHETTA}}", $etichetta, htmletichetta);
      },
      $conf->etichette
    )
  );
  $associazioni = json_encode(
    filter(
      function ($elem) use ($files) {
        return in_array($elem->path, $files);
      },
      $conf->associazioni
    )
  );
}

?>

<!DOCTYPE html>
<html>

<head>
  <script>
    function caricaPrimo() {
      document.getElementsByClassName("miniatura")[0].click();
    }

    var associazioni = {};
    var filenameAttuale = "";

    function displayFile(elem) {
      filenameAttuale = elem.alt;
      elem.scrollIntoView({
        behavior: 'auto',
        block: 'center',
        inline: 'center'
      });
    }

    function etichettaFile(elem) {
      const primoCheck = !associazioni.hasOwnProperty(filenameAttuale);
      associazioni[filenameAttuale] = elem.value;

      const miniature = document.getElementsByClassName("miniatura");
      Object.values(miniature).forEach(
        function(elem) {
          classname = "evidenziatura";
          if (associazioni.hasOwnProperty(elem.alt)) {
            elem.classList.add(classname);
          } else {
            elem.classList.remove(classname);
          }
        }
      );

      if (primoCheck) {
        elem.checked = false;
        next();
      }
    }

    function next() {
      if (this.miniature === undefined || this.idx === undefined) {
        this.idx = 0;
        this.miniature = document.getElementsByClassName("miniatura");
      }
      this.idx = ((this.idx + 1) % this.miniature.length);
      this.miniature[this.idx].click();
    }

    const etichettaTemplate = `
        <div class='bersaglio'>
          <input class='radio' type='radio' name='label_radio' onclick='etichettaFile(this)'>
          <input class='text'  type='text'  name='label_text'  value='{{ETICHETTA}}'>
        </div>`;

    function aggiungiEtichetta() {
      const nuovaetichetta = document.getElementById("nuovo-bersaglio");
      if (nuovaetichetta.value == "") {
        return;
      }
      const etichette = document.getElementById("bersagli");
      etichette.innerHTML += etichettaTemplate.replace("{{ETICHETTA}}", nuovaetichetta.value);
      nuovaetichetta.value = "";
    }

    var currentindex = 0;
    var current_image;
    var newdir;
    var dirs;
    var etichette;

    function select_image(image, border, check) {
      image.style.border = border;
      if (etichette.hasOwnProperty(image.alt)) {
        etichette[image.alt].radio.checked = check;
      }
    }

    function setCurrentImage(i) {
      if (this.images == undefined) {
        this.images = document.getElementById("images");
      }
      images.children[i].click();
    }

    function make_etichetta(label_name) {
      var radio = document.createElement("input");
      radio.classList.add("radio");
      radio.type = "radio";
      radio.name = "label_radio";
      var text = document.createElement("input");
      text.classList.add("text");
      text.type = "text";
      text.name = "label_text";
      text.value = label_name;
      var row = document.createElement("div");
      row.append(radio);
      row.append(text);
      radio.onclick = function() {
        var b = !etichette.hasOwnProperty(current_image.alt);
        etichette[current_image.alt] = {
          radio: radio,
          text: text
        };

        if (b) {
          set_current_image((currentindex + 1) % images.children.length);
        }
      };
      return ({
        row: row,
        radio: radio,
        text: text
      });
    }

    function add_target_directory() {
      const label = newdir.value;
      const duplicates = Object.values(dirs.children)
        .map(i => i.children[0])
        .filter(i => i.value == label)
        .length;
      if (label != "" && duplicates == 0) {
        dirs.append(make_etichetta(label).row);
        newdir.value = "";
      }
    }

    function get_labels_text() {
      return Object.values(document.getElementsByName("label_text")).map(i => i.value);
    }

    function get_pair_image_label() {
      return Object.values(images.children)
        .map(i => i.alt)
        .filter(i => etichette.hasOwnProperty(i))
        .map(i => ({
          path: i,
          label: etichette[i].text.value
        }));
    }

    function call_php(data, func) {
      const xmlhttp = new XMLHttpRequest();
      xmlhttp.open("POST", "index.php", true);
      xmlhttp.onload = func;
      xmlhttp.send(data);
    }

    function save() {
      var data = new FormData();
      data.append("command", "save");
      // data.append("data", JSON.stringify({
      //   etichette: get_labels_text(),
      //   associazioni: get_pair_image_label()
      // }));
      call_php(data, function() {
        alert(this.responseText);
      });
    }

    function apply() {
      var data = new FormData();
      data.append("command", "apply");
      // data.append("data", JSON.stringify({
      //   etichette: get_labels_text(),
      //   associazioni: get_pair_image_label()
      // }));
      call_php(data, function() {
        alert(this.responseText);
        window.location.reload(false);
      });
    }
  </script>

  <style>
    html {
      height: 100%;
    }

    body {
      height: 100%;
      margin: 0px;
      display: grid;
      grid-template-columns: min-content auto min-content;
      grid-template-rows: 100%;
      justify-items: center;
      align-items: center;
    }

    #contenuto {
      max-width: 90%;
      max-height: 90%;
      height: 90%;
      width: 90%;
    }

    #miniature {
      margin: 20px;
      overflow: scroll;
      height: 90%;
      display: flex;
      flex-direction: column;
    }

    .miniatura {
      width: 160px;
      margin: 10px;
      margin-right: 20px;
      border: none;
    }

    .evidenziatura {
      border: solid blue 2px;
    }

    #controlli {
      margin: 20px;
      height: 90%;
      display: grid;
      grid-template-rows: min-content min-content min-content auto min-content;
      grid-gap: 10px;
    }

    #bersagli {
      overflow: scroll;
      height: 100%;
      display: flex;
      flex-direction: column;
    }

    .bersaglio .text {
      display: inline-block;
      width: 70%;
    }

    .bersaglio .radio {
      display: inline-block;
      height: 20px;
      width: 20px;
    }
  </style>
</head>

<body onload='caricaPrimo()'>
  <div id="miniature">
    <?php echo $miniature; ?>
  </div>
  <iframe name="contenuto" id="contenuto"></iframe>
  <div id="controlli">
    <button onclick="save()">Salva</button>
    <button onclick="aggiungiEtichetta()">Nuova directory</button>
    <input id="nuovo-bersaglio" type="text">
    <div id="bersagli">
      <?php echo $etichette; ?>
    </div>
    <button onclick="apply()">Applica modifiche</button>
  </div>
</body>

</html>
