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

if (array_key_exists("file", $_GET)) {
  $file = $_SESSION['files'][$_GET["file"]];
  header("Content-Type: " . mime_content_type($file));
  readfile($file);
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
}

function apply()
{
  $etichette = filter(
    function ($e) {
      $b = file_exists($e);
      return ($b && is_dir($e)) || (!$b && mkdir($e));
    },
    $_SESSION['etichette']
  );
  $etichette_keys = array_keys($etichette);
  $associazioni = filter(
    function ($e) use ($etichette_keys) {
      return in_array($e, $etichette_keys);
    },
    $_SESSION['associazioni']
  );
  $associazioni = zip(map(
    function ($f) {
      return $_SESSION['files'][$f];
    },
    array_keys($associazioni)
  ), map(
    function ($e) {
      return $_SESSION['etichette'][$e];
    },
    $associazioni
  ));

  //TODO
  //trycatch in caso di eccezzione
  $res = implode(
    "\n",
    map(
      function ($coll) {
        [$file, $dir] = $coll;
        $from = "./$file";
        $to = "./$dir/$file";
        $res = json_encode(rename($from, $to));
        return "
          <tr>
            <td>$res</td>
            <td>$from</td>
            <td>$to</td>
          </tr>
          ";
      },
      $associazioni
    )
  );

  //TODO
  //rimuovere solo i file e le associazioni che hanno avuto successo
  $_SESSION['files'] = [];
  $_SESSION['associazioni'] = [];

  echo "<!DOCTYPE html><html><body>";
  echo "<table><tr> <th>Risultato</th> <th>Sorgente</th> <th>Destinazione</th> </tr>";
  echo $res;
  echo "</table>";
  echo "<p>";
  save();
  echo "</p>";
  echo "</body></html>";
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

function get_associazione()
{
  try {
    echo json_encode([true, $_SESSION['associazioni'][$_POST['file']]]);
  } catch (Exception) {
    echo json_encode([false, ""]);
  }
}

function set_etichetta()
{
  try {
    $_SESSION['etichette'][$_POST['etichetta']] = $_POST['nome'];
    echo json_encode([true, ""]);
  } catch (Exception) {
    echo json_encode([false, $_SESSION['etichette'][$_POST['etichetta']]]);
  }
}

if (array_key_exists('command', $_POST)) {
  match ($_POST['command']) {
    "save" => save(),
    "apply" => apply(),
    "newEtichetta" => new_etichetta(),
    "setEtichetta" => set_etichetta(),
    "newAssociazione" => new_associazione(),
    "getAssociazione" => get_associazione(),
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
    <img class='miniatura {{EVIDENZIATURA}}' id='{{ID}}' alt='{{FILENAME}}' onclick='phpGetAssociazione(this);'>
  </a>
";

const htmletichetta = "
  <div class='etichetta' >
    <input class='radio' type='radio' name='label_radio' value='{{ID}}' onclick='phpNewAssociazione(this,fileAttuale)'>
    <input class='text'  type='text'  name='label_text'     id='{{ID}}' onchange='phpAggiornaNomeEtichetta(\"{{ID}}\",this)' value='{{ETICHETTA}}'>
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
      $res = str_replace("{{EVIDENZIATURA}}", in_array($id, array_keys($_SESSION['associazioni'])) ? "evidenziatura" : "", $res);
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
    function main() {
      Object.values(
        document.getElementsByClassName('miniatura')
      ).filter(
        (elem) => !elem.classList.contains('evidenziatura')
      ).slice(0, 1).forEach(
        (elem) => elem.click()
      );
    }

    var fileAttuale = "";

    function callPhp(data, func) {
      const xmlhttp = new XMLHttpRequest();
      xmlhttp.open("POST", "index.php", true);
      xmlhttp.onload = func;
      xmlhttp.send(data);
    }

    function phpAggiornaNomeEtichetta(id, elem) {
      let data = new FormData();
      data.append("command", "setEtichetta");
      data.append("etichetta", id);
      data.append("nome", elem.value);
      callPhp(data,
        function() {
          const [success, oldname] = JSON.parse(this.responseText);
          if (success) {
            return;
          }
          elem.value = oldname;
        }
      );
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
            Object.values(
              document.getElementsByClassName('miniatura')
            ).filter(
              (elem) => !elem.classList.contains('evidenziatura')
            ).slice(0, 1).forEach(
              (elem) => elem.click()
            );
          }
        }
      )
    }

    function phpGetAssociazione(elem) {
      const selezione = 'selezione';
      Object.values(
        document.getElementsByClassName(selezione)
      ).forEach(
        (elem) => elem.classList.remove(selezione)
      );
      elem.classList.add(selezione);
      fileAttuale = elem.id;
      console.log(fileAttuale);
      const radioboxes = document.getElementsByClassName('radio');
      let data = new FormData();
      data.append("command", "getAssociazione");
      data.append("file", elem.id);
      callPhp(data,
        function() {
          const [success, radiovalue] = JSON.parse(this.responseText);
          if (success) {
            Object.values(radioboxes).find(
              (radio) => radio.value == radiovalue
            ).checked = true;
          } else {
            Object.values(radioboxes).forEach(
              (radio) => radio.checked = false
            );
          }
        }
      );
    }

    function phpSalva() {
      let data = new FormData();
      data.append("command", "save");
      callPhp(data,
        function() {}
      );
    }

    function phpApplica() {
      let data = new FormData();
      data.append("command", "apply");
      callPhp(data,
        function() {
          document.write(this.responseText);
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
      width: 300px;
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

    .selezione {
      border: solid lime 2px;
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

<?php
// =======================================================================================================================================================
// ROUTER PER LA STAMPA DELLE IMMAGINI
// =======================================================================================================================================================

$req = $_SERVER['REQUEST_URI'];
if (strpos($req, '?') !== false) {
  return false;
}
