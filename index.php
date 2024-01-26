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

const map = 0;
const filter = 3;
const join = 6;
const values = 7;

function stream($collection, $pipeline) {
  $ris = [];
  foreach ($collection as $k => $c) {
    foreach ($pipeline as $line) {
      [$op, $arg] = $line;
      switch ($op) {
        case map:
          $c = $arg($k, $c);
          break;
        case filter:
          if ($arg($k, $c)) {
            break;
          } else {
            continue 3;
          }
        default:
          break;
      }
    }
    $ris[$k] = $c;
  }
  [$op, $arg] = $pipeline[array_key_last($pipeline)];
  return match ($op) {
    join => implode($arg, $ris),
    values => array_values($ris),
    default => $ris,
  };
}

// ========================================================================================================================
// DISPLAY DEL CONTENUTO DEI FILE
// ========================================================================================================================

session_start();

function id($a) {
  return $a;
}

if (array_key_exists("file", $_GET)) {
  $file = htmlspecialchars_decode($_GET["file"]);
  $mime = mime_content_type($file);

  [$mime, $parse] = match (true) {
    strpos($mime, 'text') !== false => ['text/plain; charset=utf-8', 'id'],
    strpos($mime, 'application') !== false => ['text/plain; charset=utf-8', 'htmlspecialchars'],
    default => [$mime, 'id']
  };

  header('Content-Type: ' . $mime);
  echo $parse(file_get_contents($file));
}

if (count($_GET) > 0) {
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
  $etichette = stream($_SESSION['etichette'], [
    [filter, (fn ($e) => (($b = file_exists($e)) && is_dir($e)) || (!$b && mkdir($e)))],
  ]);

  $successo = stream($_SESSION['associazioni'], [
    [filter, (fn ($e) => array_key_exists($e, $etichette))],
    [map, (fn ($k, $v) => [$_SESSION['files'][$k], $_SESSION['etichette'][$v], $k])],
    [filter, (function ($coll) {
      try {
        [$file, $dir] = $coll;
        [$src, $dst] = ["./$file", "./$dir/$file"];
        return json_encode(rename($src, $dst));
      } catch (Exception) {
        return false;
      }
    })],
  ]);

  $_SESSION['files'] = array_diff($_SESSION['files'], array_map(fn ($c) => $c[0], $successo));
  foreach (array_map(fn ($c) => $c[2], $successo) as $k) {
    unset($_SESSION['associazioni'][$k]);
  }

  $ris = stream($successo, [
    [map, (fn ($k, $coll) => ["true", './' . ($coll[0]), './' . ($coll[1]) . '/' . ($coll[0])])],
    [map, (fn ($k, $coll) => str_replace(mTableRow, $coll, htmlTableRow))],
    [join, ("\n")],
  ])
    . "\n"
    . stream($_SESSION['associazioni'], [
      [map, (fn ($k, $v) => [$_SESSION['files'][$k], $_SESSION['etichette'][$v]])],
      [map, (fn ($k, $coll) => ["false", './' . ($coll[0]), './' . ($coll[1]) . '/' . ($coll[0])])],
      [map, (fn ($k, $coll) => str_replace(mTableRow, $coll, htmlTableRow))],
      [join, ("\n")]
    ]);

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

$files = stream(glob('*'), [
  [filter, (fn ($f) => !is_dir($f))],
  [map, fn ($k, $v) => htmlspecialchars($v)],
]);

try {
  $_SESSION = json_decode(file_get_contents(CONFIGFILEJSON), true);
  foreach (array_diff($_SESSION['files'], $files) as $file) {
    unset($_SESSION['associazioni'][$file]);
  }
  $_SESSION['files'] = $files;
} catch (Exception | ValueError) {
  $_SESSION = [
    'etichette' => [],
    'associazioni' => [],
    'files' => $files
  ];
};

$miniature = stream($_SESSION['files'], [
  [map,  fn ($k, $v) => htmlspecialchars($v)],
  [map, fn ($k, $v) => sprintf('
  <a target="contenuto" class="miniatura %s" href="./?file=%s">%s</a>
  ', array_key_exists($v, $_SESSION['associazioni']) ? 'evidenziatura' : '', $v, $v)],
  [join, "\n"]
]);

$etichette = stream($_SESSION['etichette'], [
  [map, fn ($k, $v) => sprintf('
  <span class="etichetta">
    <input type="radio" name="etichetta" value="%d" onclick="phpNewAssociazione(this)">
    <input type="text" value="%s" onchange="phpAggiornaNomeEtichetta(this,%d)" >
  </span>
', $k, $v, $k)],
  [join, "\n"]
]);

?>

<!DOCTYPE html>
<html>

<head>
  <style>
    html,
    body,
    input {
      width: 100%;
      height: 100%;
      margin: 0px;
    }

    body {
      display: grid;
      grid-template:
        'miniature contenuto controlli' min-content
        'miniature contenuto etichette' 1fr
        / 200px 1fr 200px;
      gap: 20px;
      justify-content: space-around;
      align-content: space-around;
    }

    #miniature {
      grid-area: miniature;
      padding: 10px;
    }

    #contenuto {
      grid-area: contenuto;
      width: 100%;
      height: 96%;
    }

    #controlli {
      grid-area: controlli;
      padding: 10px;
    }

    #etichette {
      grid-area: etichette;
      padding: 10px;
    }

    #miniature,
    #etichette,
    #controlli {
      overflow-y: scroll;
      overflow-x: hidden;
      display: flex;
      flex-direction: column;
      gap: 6px;
    }

    .evidenziatura {
      border: solid blue 2px;
    }

    .selezione {
      border: solid lime 2px;
    }

    .etichetta {
      display: grid;
      grid-template-columns: 30px 1fr;
      grid-template-rows: 30px;
      gap: 6px;
    }
  </style>
</head>

<body onload='main()'>
  <div id="miniature" class="list">
    <?php echo $miniature; ?>
  </div>
  <iframe name="contenuto" id="contenuto"></iframe>
  <form id="etichette" action="./" method="post" target="devnull" id="newAssociazioneForm">
    <?php echo $etichette; ?>
    <input hidden id='newAssociazioneFile' type="text" name="file">
    <button hidden id='newAssociazioneBtn' name='command' value='newAssociazione'></button>
  </form>
  <form id="controlli" action="./" method="post">
    <button type="submit" name="command" value="apply">Applica modifiche</button>
    <button type="submit" name="command" value="save">Salva</button>
    <button type="submit" name="command" value="newEtichetta">Nuova directory</button>
    <input name="etichetta" type="text">
  </form>
</body>

<script>
  // ===========================================================================================================================
  // GLOBAL VARS
  // ===========================================================================================================================
  let FILE = null;
  const MINIATURE = Object.values(document.getElementsByClassName('miniatura'));
  const ETICHETTERADIO = Object.values(document.getElementsByClassName('radio'));
  const NEWASSOCIAZIONE = {
    form: document.getElementById('newAssociazioneForm'),
    btn: document.getElementById('newAssociazioneBtn'),
    file: document.getElementById('newAssociazioneFile'),
  };

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

  function print_e() {
    document.write(e.map((a) => '<p>' + a + '</p>').join('\n'));
  }

  function main() {
    clickPrimoNonEvidenziato();
  }

  function callPhp(data, successFunc, failFunc) {
    const xmlhttp = new XMLHttpRequest();
    xmlhttp.open("POST", "index.php", true);
    xmlhttp.onload = function() {
      let data;
      try {
        data = JSON.parse(this.responseText);
        const success = data.shift();
        (success ? successFunc : failFunc)(data);
      } catch (e) {
        print_e([e, data, successFunc, failFunc]);
      }
    };
    xmlhttp.send(data);
  }

  function phpAggiornaNomeEtichetta(id, elem) {
    let data = new FormData();
    data.append("command", "setEtichetta");
    data.append("etichetta", id);
    data.append("nome", elem.value);
    callPhp(data, () => null, ([prev]) => elem.value = prev);
  }

  function phpNewAssociazione(elem) {
    callPhp(new FormData(NEWASSOCIAZIONE.form, NEWASSOCIAZIONE.btn),
      function([primocheck]) {
        FILE.classList.add('evidenziatura');
        if (primocheck) {
          clickPrimoNonEvidenziato();
        }
      },
      print_e
    );
  }

  function phpGetAssociazione(elem) {
    selezionaFile(elem);
    let data = new FormData();
    data.append("command", "getAssociazione");
    data.append("file", elem.id);
    callPhp(data,
      function([radiovalue]) {
        ETICHETTERADIO.filter(
          (radio) => radio.value == radiovalue
        ).forEach(
          (radio) => radio.checked = true
        );
      },
      function(_) {
        ETICHETTERADIO.forEach(
          (radio) => radio.checked = false
        );
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
