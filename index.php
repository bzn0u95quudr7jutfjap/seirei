<?php
// =======================================================================================================================================================
// QOL
// =======================================================================================================================================================

function map($function, $collection)
{
  return array_map($function, $collection);
}

function filter($function, $collection)
{
  return array_filter($collection, $function);
}

function ls()
{
  return filter(
    function ($f) {
      return !is_dir($f) && $f != "index.php";
    },
    glob('*')
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

const CONFIGFILEJSON = ".seireidire.json";

function save()
{
  ["data" => $data] = $_POST;
  if (file_put_contents(CONFIGFILEJSON, $data)) {
    echo "Salvato con successo\n";
  } else {
    echo "Errore";
  }
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

$files = ls();

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
  <div class='etichetta' >
    <input class='radio' type='radio' name='label_radio' onclick='etichettaFile(this)'>
    <input class='text'  type='text'  name='label_text'  value='{{ETICHETTA}}'>
  </div>
";

try {
  $conf = json_decode(file_get_contents(CONFIGFILEJSON));
  $etichette = $conf->etichette;
  $associazioni = $conf->associazioni;
} catch (Exception) {
  $etichette = "[]";
  $associazioni = "[]";
};

?>

<!DOCTYPE html>
<html>

<head>
  <script>
    function init() {
      return [<?php echo $etichette; ?>, <?php echo $associazioni; ?>];
    }
  </script>
  <script>
    var associazioni = {};
    var filenameAttuale = "";

    function main() {
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

    function aggiungiEtichetta() {
      const nuovaetichetta = document.getElementById("nuovo-etichetta");
      if (nuovaetichetta.value == "") {
        return;
      }
      const etichette = document.getElementById("etichette");
      const id = etichette.children.length;
      etichette.innerHTML += `
        <div class='etichetta'>
          <input class='radio' type='radio' name='label_radio' value='label${id}' onclick='etichettaFile(this)'>
          <input class='text'  type='text'  name='label_text'  id='label${id}' value='${nuovaetichetta.value}'>
        </div>`;
      //TODO SORTING DELLE ETICHETTE
      nuovaetichetta.value = "";
    }

    function callPhp(data, func) {
      const xmlhttp = new XMLHttpRequest();
      xmlhttp.open("POST", "index.php", true);
      xmlhttp.onload = func;
      xmlhttp.send(data);
    }

    function phpSalvaAssociazioni() {
      const zip = (a, b) => a.map((v, k) => [v, b[k]]);
      const etichetteRadio = document.getElementsByClassName("radio");
      const etichetteTesto = Object.values(etichetteRadio).map(
        function(etichetta) {
          return document.getElementById(etichetta.value).value;
        }
      );
      const etichetteRadioTesto = new Map(zip(Object.values(etichetteRadio), Object.values(etichetteTesto)));
      const etichetteDaSalvare = JSON.stringify(etichetteTesto);
      const associazioniDaSalvare = JSON.stringify(
        zip(
          Object.keys(associazioni),
          Object.values(associazioni)).map(
          ([filename, radio]) => ({
            filename: filename,
            etichetta: etichetteRadioTesto.get(radio)
          })
        ));
      let data = new FormData();
      data.append("command", "save");
      data.append("data", JSON.stringify({
        etichette: etichetteDaSalvare,
        associazioni: associazioniDaSalvare
      }));
      callPhp(data,
        function() {
          console.log(this.responseText);
        }
      );
    }

    function phpApplicaModifiche() {
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
    <button onclick="phpSalvaAssociazioni()">Salva</button>
    <button onclick="aggiungiEtichetta()" id="aggiungi-etichetta">Nuova directory</button>
    <input id="nuovo-etichetta" type="text">
    <div id="etichette">
    </div>
    <button onclick="phpApplicaModifiche()">Applica modifiche</button>

  </div>
</body>

</html>
