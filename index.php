<?php
// =======================================================================================================================================================
// QOL
// =======================================================================================================================================================

function exception_error_handler($errno, $errstr, $errfile, $errline)
{
  throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
}
set_error_handler("exception_error_handler");

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

function map($function, $collection)
{
  return array_map($function, $collection);
}

function zip($a0, $a1)
{
  $a0 = array_values($a0);
  $a1 = array_values($a1);
  $a = [];
  $len = [count($a0), count($a1)];
  for ($i = 0; $i < $len[0] || $i < $len[1]; $i += 1) {
    $a[] = [$a0[$i], $a1[$i]];
  }
  return $a;
}

function filter($function, $collection)
{
  return array_filter($collection, $function);
}

function ls()
{
  return array_values(
    filter(
      function ($f) {
        return !is_dir($f) && $f != "index.php";
      },
      glob('*')
    )
  );
}

function indicizzafiles($i, $a)
{
  $k = array_keys($a);
  $b = [];
  for ($j = 0; $j < count($a); $j += 1, $i += 1) {
    $b["file_$i"] = $a[$k[$i]];
  }
  return $b;
}

function maxindice($a)
{
  return count($a) == 0 ? 0 : max(
    map(
      function ($k) {
        return (int)(explode("_", $k)[1]);
      },
      array_keys($a)
    )
  );
}

function all_true($array)
{
  return array_reduce(
    $array,
    function ($a, $b) {
      return $a && $b;
    },
    true
  );
}

// =======================================================================================================================================================
// DISPLAY DEL CONTENUTO DEI FILE
// =======================================================================================================================================================

session_start();

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
  $filename = $_SESSION['files'][$_GET["file"]];
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

const CONFIGFILEJSON = ".seireidire.json";

function save()
{
  if (file_put_contents(CONFIGFILEJSON, json_encode($_SESSION))) {
    echo "Salvato con successo\n";
  } else {
    echo "Errore";
  }
  var_dump($_SESSION);
}

function apply()
{
  $content = json_decode(file_get_contents(CONFIGFILEJSON));
  $etichette = filter(
    function ($dir) {
      $e = file_exists($dir);
      return ($e && is_dir($e)) || (!$e && mkdir($dir));
    },
    json_decode($content->etichette)
  );

  $files = ls();

  $res = implode(
    "\n",
    map(
      function ($coll) {
        [$f, $b, $bool] = $coll;
        return "<p>" . json_encode($bool) . " : $f -> $b" . "</p>";
      },
      map(
        function ($associazione) {
          $etichetta = "./" . $associazione->etichetta . "/" . $associazione->filename;
          return [$associazione->filename, $etichetta, rename($associazione->filename, $etichetta)];
        },
        filter(
          function ($associazione) use ($etichette, $files) {
            return in_array($associazione->etichetta, $etichette) && in_array($associazione->filename, $files);
          },
          json_decode($content->associazioni)
        )
      )
    )
  );
  echo "<html><body>$res</body></html>";
}

function new_etichetta()
{
  try {
    $etichetta = $_POST['etichetta'];
    $key = "etichetta_" . count($_SESSION['etichette']);
    $success = ($etichetta != "" && !in_array($etichetta, $_SESSION['etichette']));
    if ($success) {
      $_SESSION['etichette'][$key] = $etichetta;
    }
    echo json_encode([$success, $key]);
  } catch (Exception) {
    echo json_encode([false, null]);
  }
}

function new_associazione()
{
  try {
    ["file" => $file, "etichetta" => $etichetta] = $_POST;
    $primo_check = !array_key_exists($file, $_SESSION['associazioni']);
    $_SESSION['associazioni'][$file] = $etichetta;
    echo json_encode([true, $primo_check]);
  } catch (Exception) {
    echo json_encode([false, false]);
  }
}

if (array_key_exists('command', $_POST)) {
  match ($_POST['command']) {
    "save" => save(),
    "apply" => apply(),
    "newEtichetta" => new_etichetta(),
    "newAssociazione" => new_associazione(),
    default => null,
  };
}

if (count($_POST) != 0) {
  die();
}

// =======================================================================================================================================================
// PAGINA PRINCIPALE
// =======================================================================================================================================================

const htmlminiatura = "
  <a target='contenuto' href='./?file={{ID}}'>
    <img class='miniatura' id='{{ID}}' src='./?file={{ID}}' alt='{{FILENAME}}' onclick='displayFile(this);'>
  </a>
";

const htmletichetta = "
  <div class='etichetta' >
    <input class='radio' type='radio' name='label_radio' value='{{ID}}' onclick='phpNewAssociazione(this,fileAttuale)'>
    <input class='text'  type='text'  name='label_text'     id='{{ID}}' value='{{ETICHETTA}}'>
  </div>
";

$files = ls();

try {
  $_SESSION = (array) json_decode(file_get_contents(CONFIGFILEJSON));
  $_SESSION['etichette'] = !isset($_SESSION['etichette']) ? [] : (array) $_SESSION['etichette'];
  $_SESSION['associazioni'] = !isset($_SESSION['associazioni']) ? [] : (array) $_SESSION['associazioni'];
  $_SESSION['files'] = !isset($_SESSION['files']) ? [] : (array) $_SESSION['files'];

  $diff = indicizzafiles(
    maxindice($_SESSION['files']),
    array_values(array_diff(
      $files,
      $_SESSION['files']
    ))
  );

  if (count($diff) > 0) {
    $common = array_intersect($_SESSION['files'], $files);
    $_SESSION['associazioni'] = filter(
      function ($coll) use ($common) {
        [$filename,] = $coll;
        return in_array($filename, $common);
      },
      $_SESSION['associazioni']
    );
    $_SESSION['files'] = array_merge($common, $diff);
  }
} catch (Exception) {
  $_SESSION = [];
  $_SESSION['etichette']    = [];
  $_SESSION['associazioni'] = [];
  $_SESSION['files'] = indicizzafiles(0, $files);
};

$miniature = implode(
  "\n",
  map(
    function ($coll) {
      [$id, $filename] = $coll;
      $res = str_replace("{{FILENAME}}", filter_var($filename, FILTER_SANITIZE_FULL_SPECIAL_CHARS), htmlminiatura);
      $res = str_replace("{{ID}}", $id, $res);
      return $res;
    },
    zip(array_keys($_SESSION['files']), $_SESSION['files'])
  )
);

$etichette = implode(
  "\n",
  array_map(
    function ($coll) {
      [$id, $etichetta] = $coll;
      return str_replace(
        '{{ID}}',
        $id,
        str_replace(
          '{{ETICHETTA}}',
          $etichetta,
          htmletichetta
        )
      );
    },
    zip(array_keys($_SESSION['etichette']), $_SESSION['etichette'])
  )
);

?>

<!DOCTYPE html>
<html>

<head>
  <script>
    var associazioni = "";
    var filenameAttuale = "";

    function main() {
      return;
      const iniziali = init();
      const etichette = iniziali[0];
      const associazioniLocali = iniziali[1];

      const miniature = document.getElementsByClassName("miniatura");
      miniature[0].click();

      const inputbox = document.getElementById("nuovo-etichetta");
      const bottone = document.getElementById("aggiungi-etichetta");
      Object.values(etichette).forEach(
        function(etichetta) {
          inputbox.value = etichetta;
          bottone.click();
        });

      const radio = document.getElementsByClassName("radio");
      Object.values(radio).map(
        function(radio) {
          return [radio, document.getElementById(radio.value)];
        }).forEach(
        function([radio, text]) {
          associazioniLocali.filter(
            function(associazione) {
              return associazione.etichetta == text.value;
            }
          ).forEach(
            function(associazione) {
              associazioni[associazione.filename] = radio;
            });
        });

      Object.values(miniature).forEach(
        function(elem) {
          const classname = "evidenziatura";
          if (associazioni.hasOwnProperty(elem.alt)) {
            elem.classList.add(classname);
          } else {
            elem.classList.remove(classname);
          }
        }
      );
      const daEtichettare = Object.values(miniature).filter(
        (elem) => !elem.classList.contains("evidenziatura")
      );
      if (daEtichettare.length > 0) {
        daEtichettare[0].click();
      }

    }

    function displayFile(elem) {
      filenameAttuale = elem.alt;
      fileAttuale = elem.id;
      elem.scrollIntoView({
        behavior: 'auto',
        block: 'center',
        inline: 'center'
      });

      if (associazioni.hasOwnProperty(filenameAttuale)) {
        associazioni[filenameAttuale].checked = true;
      } else {
        const etichette = document.getElementsByClassName("radio");
        Object.values(etichette).forEach(
          function(etichetta) {
            etichetta.checked = false;
          }
        );
      }
    }

    function etichettaFile(elem) {
      const primoCheck = !associazioni.hasOwnProperty(filenameAttuale);
      associazioni[filenameAttuale] = elem;

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
        const miniature = document.getElementsByClassName("miniatura");
        const daEtichettare = Object.values(miniature).filter(
          function(elem) {
            return !elem.classList.contains("evidenziatura");
          }
        );
        if (daEtichettare.length > 0) {
          daEtichettare[0].click();
        }
      }
    }

    // ===========================================================================================================================
    //  NUOVO JS
    // ===========================================================================================================================

    var fileAttuale = "";

    function callPhp(data, func) {
      const xmlhttp = new XMLHttpRequest();
      xmlhttp.open("POST", "index.php", true);
      xmlhttp.onload = func;
      xmlhttp.send(data);
    }

    function phpNewEtichetta() {
      const nuovaetichetta = document.getElementById("nuovo-etichetta");
      if (nuovaetichetta.value == "") {
        return;
      }
      let data = new FormData();
      data.append("command", "newEtichetta");
      data.append("etichetta", nuovaetichetta.value);
      callPhp(data,
        function() {
          console.log(this.responseText);
          const [success, id] = JSON.parse(this.responseText);
          if (!success) {
            return;
          }
          const etichette = document.getElementById("etichette");
          etichette.innerHTML += `
        <div class='etichetta'>
          <input class='radio' type='radio' name='label_radio' value='${id}' onclick='phpNewAssociazione(this,fileAttuale)'>
          <input class='text'  type='text'  name='label_text'     id='${id}' value='${nuovaetichetta.value}'>
        </div>`;
          //TODO SORTING DELLE ETICHETTE
          nuovaetichetta.value = "";
        }
      );
    }

    function phpNewAssociazione(elem, file) {
      let data = new FormData();
      data.append("command", "newAssociazione");
      data.append("etichetta", elem.value);
      data.append("file", file);
      callPhp(data,
        function() {
          const [success, primocheck] = JSON.parse(this.responseText);
          if (!success) {
            alert("ERRORE ASSOCIAZIONE");
            console.log("ERRORE ASSOCIAZIONE");
            return;
          }
          document.getElementById(file).classList.add('evidenziatura');
          if (primocheck) {
            const miniature = document.getElementsByClassName('miniatura');
            const daEtichettare = Object.values(miniature).filter(
              function(elem) {
                return !elem.classList.contains('evidenziatura');
              }
            );
            if (daEtichettare.length > 0) {
              daEtichettare[0].click();
            }
          }
        }
      )
    }

    function phpGetAssociazione() {}

    function phpSetAssociazione() {}

    function phpSalva() {
      let data = new FormData();
      data.append("command", "save");
      callPhp(data,
        function() {
          console.log(this.responseText);
        }
      );
    }

    function phpApplica() {
      let data = new FormData();
      data.append("command", "apply");
      callPhp(data,
        function() {
          console.log(this.responseText);
          window.location.reload(false);
          window.open('', '_blank').document.write(this.responseText);
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

    #etichette {
      overflow: scroll;
      height: 100%;
      display: flex;
      flex-direction: column;
    }

    .etichetta .text {
      display: inline-block;
      width: 70%;
    }

    .etichetta .radio {
      display: inline-block;
      height: 20px;
      width: 20px;
    }
  </style>
</head>

<body onload='main()'>
  <div id="miniature">
    <?php echo $miniature; ?>
  </div>
  <iframe name="contenuto" id="contenuto"></iframe>
  <div id="controlli">
    <button onclick="phpSalva()">Salva</button>
    <button onclick="phpNewEtichetta()" id="aggiungi-etichetta">Nuova directory</button>
    <input id="nuovo-etichetta" type="text">
    <div id="etichette">
      <?php echo $etichette; ?>
    </div>
    <button onclick="phpApplica()">Applica modifiche</button>

  </div>
</body>

</html>
