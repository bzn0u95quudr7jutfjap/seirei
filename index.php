<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

set_error_handler(
  fn ($errno, $errstr, $errfile, $errline) => throw new ErrorException($errstr, $errno, 0, $errfile, $errline)
);

// ========================================================================================================================
// QOL
// ========================================================================================================================

class Stream {
  public function __construct(private $collection = []) {
  }

  private function set($collection): Stream {
    $this->collection = $collection;
    return $this;
  }

  public function map($function): Stream {
    return $this->set(array_map($function, $this->collection));
  }

  public function mapKeyValues($function): Stream {
    return $this->set(array_map($function, array_keys($this->collection), $this->collection));
  }

  public function filter($function): Stream {
    return $this->set(array_filter($this->collection, $function));
  }

  public function get(): array {
    return $this->collection;
  }

  public function getValues(): array {
    return array_values($this->collection);
  }
}

function stream($collection) {
  return new Stream($collection);
}

function map($function, $collection) {
  return array_map($function, $collection);
}

function zip($a0, $a1) {
  $a0 = array_values($a0);
  $a1 = array_values($a1);
  $a = [];
  $len = [count($a0), count($a1)];
  for ($i = 0; $i < $len[0] || $i < $len[1]; $i += 1) {
    $a[] = [$a0[$i], $a1[$i]];
  }
  return $a;
}

function filter($function, $collection) {
  return array_filter($collection, $function);
}

function ls() {
  return array_values(
    filter(
      function ($f) {
        return !is_dir($f);
      },
      glob('*')
    )
  );
}

function indicizzafiles($i, $a) {
  $k = array_keys($a);
  $b = [];
  for ($j = 0; $j < count($a); $j += 1, $i += 1) {
    $b["file_$i"] = $a[$k[$i]];
  }
  return $b;
}

function maxindice($a) {
  return count($a) == 0 ? 0 : max(
    map(
      function ($k) {
        return (int)(explode("_", $k)[1]);
      },
      array_keys($a)
    )
  );
}

function all_true($array) {
  return array_reduce(
    $array,
    function ($a, $b) {
      return $a && $b;
    },
    true
  );
}

// ========================================================================================================================
// DISPLAY DEL CONTENUTO DEI FILE
// ========================================================================================================================

session_start();

function display_text($file) {
  header("Content-Type: text/plain; charset=utf-8");
  readfile($file);
}
function display_other($mime, $file) {
  header("Content-Type: " . $mime);
  readfile($file);
}

if (array_key_exists("file", $_GET)) {
  $file = $_SESSION['files'][$_GET["file"]];
  $mime = mime_content_type($file);
  match (true) {
    strpos($mime, 'text') !== false => display_text($file),
    default => display_other($mime, $file),
  };
}

if (count($_GET) != 0) {
  die();
}

// ========================================================================================================================
// COMANDI POST
// ========================================================================================================================

const CONFIGFILEJSON = ".seireidire.json";

function save() {
  header("Location: /");
  return file_put_contents(CONFIGFILEJSON, json_encode($_SESSION));
}

function apply() {
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

  file_put_contents(CONFIGFILEJSON, json_encode($_SESSION));
?>
  <!DOCTYPE html>
  <html>

  <body>
    <table>
      <tr>
        <th>Risultato</th>
        <th>Sorgente</th>
        <th>Destinazione</th>
        <?php echo $res; ?>
      </tr>
    </table>
  </body>

  </html>
<?php
}

function new_etichetta() {
  try {
    $etichetta = $_POST['etichetta'];
    $key = "etichetta_" . count($_SESSION['etichette']);
    $success = ($etichetta != "" && !in_array($etichetta, $_SESSION['etichette']));
    if ($success) {
      $_SESSION['etichette'][$key] = $etichetta;
      save();
    }
    echo json_encode([$success, $key]);
  } catch (Exception) {
    echo json_encode([false, null]);
  }
}

function new_associazione() {
  try {
    ["file" => $file, "etichetta" => $etichetta] = $_POST;
    $primo_check = !array_key_exists($file, $_SESSION['associazioni']);
    $_SESSION['associazioni'][$file] = $etichetta;
    $res = [true, $primo_check];
  } catch (Exception) {
    $res = [false, false];
  } finally {
    echo json_encode($res);
  }
}

function get_associazione() {
  try {
    echo json_encode([true, $_SESSION['associazioni'][$_POST['file']]]);
  } catch (Exception) {
    echo json_encode([false, ""]);
  }
}

function set_etichetta() {
  try {
    $ret = $_SESSION['etichette'][$_POST['etichetta']] = $_POST['nome'];
    echo json_encode([true, $ret]);
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

// ========================================================================================================================
// PAGINA PRINCIPALE
// ========================================================================================================================

const htmlminiatura = "
  <a target='contenuto' href='./?file={{ID}}'>
    <img class='miniatura {{EVIDENZIATURA}}' id='{{ID}}' alt='{{FILENAME}}' onclick='phpGetAssociazione(this);'>
  </a>
";

const htmletichetta = "
  <div class='etichetta' >
    <input class='radio' type='radio' name='etichetta' value='{{ID}}' onclick='phpNewAssociazione(this)'>
    <input class='text'  type='text'  name='{{ID}}'     id='{{ID}}' onchange='phpAggiornaNomeEtichetta(\"{{ID}}\",this)' value='{{ETICHETTA}}'>
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
    // ===========================================================================================================================
    // GLOBAL VARS
    // ===========================================================================================================================
    let MINIATURE = null;
    let FILE = null;
    let ETICHETTERADIO = null;
    let NEWASSOCIAZIONE = null;
    let BTN_NEW_ASSOCIAZIONE = null;

    function clickPrimoNonEvidenziato() {
      MINIATURE.filter(
        (elem) => !elem.classList.contains('evidenziatura')
      ).slice(0, 1).forEach(
        (elem) => elem.click()
      );
    }

    function selezionaFile(elem) {
      const selezione = 'selezione';
      MINIATURE.filter(
        (elem) => elem.classList.contains(selezione)
      ).forEach(
        (elem) => elem.classList.remove(selezione)
      );
      NEWASSOCIAZIONE.file.value = elem.id;
      FILE = elem;
      elem.classList.add(selezione);
    }

    function main() {
      NEWASSOCIAZIONE = {
        form: document.getElementById('newAssociazioneForm'),
        btn: document.getElementById('newAssociazioneBtn'),
        file: document.getElementById('newAssociazioneFile'),
      };
      ETICHETTERADIO = Object.values(document.getElementsByClassName('radio'));
      MINIATURE = Object.values(document.getElementsByClassName('miniatura'));
      clickPrimoNonEvidenziato();
    }

    function callPhp(data, func) {
      const xmlhttp = new XMLHttpRequest();
      xmlhttp.open("POST", "index.php", true);
      xmlhttp.onload = function() {
        func(JSON.parse(this.responseText));
      };
      xmlhttp.send(data);
    }

    function phpAggiornaNomeEtichetta(id, elem) {
      let data = new FormData();
      data.append("command", "setEtichetta");
      data.append("etichetta", id);
      data.append("nome", elem.value);
      callPhp(data,
        function([success, oldname]) {
          if (!success) {
            elem.value = oldname;
          }
        }
      );
    }

    function phpNewAssociazione(elem) {
      callPhp(new FormData(
          NEWASSOCIAZIONE.form,
          NEWASSOCIAZIONE.btn
        ),
        function([success, primocheck]) {
          if (success) {
            FILE.classList.add('evidenziatura');
            if (primocheck) {
              clickPrimoNonEvidenziato();
            }
          } else {
            alert("ERRORE ASSOCIAZIONE");
            console.log("ERRORE ASSOCIAZIONE");
          }
        }
      )
    }

    function phpGetAssociazione(elem) {
      selezionaFile(elem);
      let data = new FormData();
      data.append("command", "getAssociazione");
      data.append("file", elem.id);
      callPhp(data,
        function([success, radiovalue]) {
          if (success) {
            ETICHETTERADIO.filter(
              (radio) => radio.value == radiovalue
            ).forEach(
              (radio) => radio.checked = true
            );
          } else {
            ETICHETTERADIO.forEach(
              (radio) => radio.checked = false
            );
          }
        }
      );
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
      height: 90%;
      width: 90%;
    }

    #miniature {
      margin: 20px;
      overflow: scroll;
      height: 90%;
      width: 200px;
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

    form {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }
  </style>
</head>

<body onload='main()'>
  <div id="miniature">
    <?php echo $miniature; ?>
  </div>
  <iframe name="contenuto" id="contenuto"></iframe>
  <div id="controlli">
    <form action="./" method="post">
      <button type="submit" name="command" value="save">Salva</button>
      <button type="submit" name="command" value="newEtichetta">Nuova directory</button>
      <input name="etichetta" type="text">
    </form>
    <form action="./" method="post" target="devnull" id="newAssociazioneForm">
      <?php echo $etichette; ?>
      <input hidden id='newAssociazioneFile' type="text" name="file">
      <button hidden id='newAssociazioneBtn' name='command' value='newAssociazione'>
    </form>
    <form action="./" method="post">
      <button type="submit" name="command" value="apply">Applica modifiche</button>
    </form>

  </div>
</body>

</html>

<?php
// ========================================================================================================================
// ROUTER PER LA STAMPA DELLE IMMAGINI
// ========================================================================================================================

$req = $_SERVER['REQUEST_URI'];
if (strpos($req, '?') !== false) {
  return false;
}
