<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

set_error_handler(
  fn ($errno, $errstr, $errfile, $errline) => throw new ErrorException($errstr, $errno, 0, $errfile, $errline)
);

const TEXT_MODE = 'Content-Type: text/plain; charset=utf-8';

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

  public function filter($function, $mode = 0): Stream {
    return $this->set(array_filter($this->collection, $function, $mode));
  }

  public function get(): array {
    return $this->collection;
  }

  public function getValues(): array {
    return array_values($this->collection);
  }

  public function join($delimiter): string {
    return implode($delimiter, $this->collection);
  }
}

function stream($collection) {
  return new Stream($collection);
}

function ls() {
  return stream(glob('*'))
    ->filter(fn ($f) => !is_dir($f))
    ->getValues();
}

// ========================================================================================================================
// DISPLAY DEL CONTENUTO DEI FILE
// ========================================================================================================================

session_start();

function display_text($file) {
  header("Content-Type: text/plain; charset=utf-8");
  echo filter_var(file_get_contents($file), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
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
    strpos($mime, 'application') !== false => display_text($file),
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

function save($refresh = false) {
  file_put_contents(CONFIGFILEJSON, json_encode($_SESSION, JSON_FORCE_OBJECT));
  if ($refresh) {
    header("Location: /");
    die();
  }
}

const htmlTableRow = '
      <tr>
        <td>{{RIS}}</td>
        <td>{{SRC}}</td>
        <td>{{DST}}</td>
      </tr>
  ';
const mTableRow = ['{{RIS}}', '{{SRC}}', '{{DST}}'];

function apply() {
  $etichette = stream($_SESSION['etichette'])
    ->filter(fn ($e) => (($b = file_exists($e)) && is_dir($e)) || (!$b && mkdir($e)))
    ->get();

  $successo = stream($_SESSION['associazioni'])
    ->filter(fn ($e) => array_key_exists($e, $etichette))
    ->mapKeyValues(fn ($k, $v) => [$_SESSION['files'][$k], $_SESSION['etichette'][$v], $k])
    ->filter(function ($coll) {
      try {
        [$file, $dir] = $coll;
        [$src, $dst] = ["./$file", "./$dir/$file"];
        return json_encode(rename($src, $dst));
      } catch (Exception) {
        return false;
      }
    })
    ->get();

  $_SESSION['files'] = array_diff($_SESSION['files'], stream($successo)->map(fn ($c) => $c[0])->get());
  foreach (stream($successo)->map(fn ($c) => $c[2])->get() as $k) {
    unset($_SESSION['associazioni'][$k]);
  }

  $ris = stream($successo)
    ->map(fn ($coll) => ["true", './' . ($coll[0]), './' . ($coll[1]) . '/' . ($coll[0])])
    ->map(fn ($coll) => str_replace(mTableRow, $coll, htmlTableRow))
    ->join("\n")
    . "\n"
    . stream($_SESSION['associazioni'])
    ->mapKeyValues(fn ($k, $v) => [$_SESSION['files'][$k], $_SESSION['etichette'][$v]])
    ->map(fn ($coll) => ["false", './' . ($coll[0]), './' . ($coll[1]) . '/' . ($coll[0])])
    ->map(fn ($coll) => str_replace(mTableRow, $coll, htmlTableRow))
    ->join("\n");

  save();
?>
  <!DOCTYPE html>
  <html>

  <body>
    <table>
      <tr>
        <th>Risultato</th>
        <th>Sorgente</th>
        <th>Destinazione</th>
        <?php echo $ris; ?>
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
      save(true);
    }
  } catch (Exception) {
    echo "<p>Errore di un nuova etichetta</p>";
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
    "save" => save(true),
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

$files = ls();

try {
  $_SESSION = stream((array) json_decode(file_get_contents(CONFIGFILEJSON)))
    ->map(fn ($a) => (array) $a)
    ->get();

  $common = array_intersect($_SESSION['files'], $files);
  $max = max(array_keys($_SESSION['files'])) + 1;
  $diff = array_diff($files, $_SESSION['files']);
  $diff = count($diff) == 0 ? [] : array_combine(range($max, $max + count($diff) - 1), $diff);
  $_SESSION['files'] = $diff + $common;

  $_SESSION['associazioni'] = stream($_SESSION['associazioni'])
    ->filter(fn ($file) => array_key_exists($file, $_SESSION['files']), ARRAY_FILTER_USE_KEY)
    ->get();
} catch (Exception | ValueError) {
  $_SESSION = [
    'etichette' => [],
    'associazioni' => [],
    'files' => $files
  ];
};

const htmlminiatura = '
  <a target="contenuto" href="./?file={{ID}}">
    <img class="miniatura {{EVIDENZIATURA}}" id="{{ID}}" alt="{{FILENAME}}" onclick="phpGetAssociazione(this);">
  </a>
';
const mMarcatori = ['{{ID}}', '{{FILENAME}}', '{{EVIDENZIATURA}}'];
$miniature = stream($_SESSION['files'])
  ->map(fn ($file) => filter_var($file, FILTER_SANITIZE_FULL_SPECIAL_CHARS))
  ->mapKeyValues(fn ($k, $v) => [$k, $v, array_key_exists($k, $_SESSION['associazioni']) ? 'evidenziatura' : ''])
  ->map(fn ($coll) => str_replace(mMarcatori, $coll, htmlminiatura))
  ->join("\n");

const htmletichetta = '
  <div class="etichetta" >
    <input class="radio" type="radio" name="etichetta" value="{{ID}}" onclick="phpNewAssociazione(this)">
    <input class="text"  type="text"  name="{{ID}}"     id="{{ID}}" onchange="phpAggiornaNomeEtichetta(\'{{ID}}\',this)" value="{{ETICHETTA}}">
  </div>
';
const eMarcatori = ['{{ID}}', '{{ETICHETTA}}'];
$etichette = stream($_SESSION['etichette'])
  ->mapKeyValues(fn ($k, $v) => str_replace(eMarcatori, [$k, $v], htmletichetta))
  ->join("\n");

?>

<!DOCTYPE html>
<html>

<head>
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
      <button hidden id='newAssociazioneBtn' name='command' value='newAssociazione'></button>
    </form>
    <form action="./" method="post">
      <button type="submit" name="command" value="apply">Applica modifiche</button>
    </form>

  </div>
</body>

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

</html>

<?php
// ========================================================================================================================
// ROUTER PER LA STAMPA DELLE IMMAGINI
// ========================================================================================================================

$req = $_SERVER['REQUEST_URI'];
if (strpos($req, '?') !== false) {
  return false;
}
