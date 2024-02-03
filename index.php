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

if (array_key_exists('command', $_POST)) {
  match ($_POST['command']) {
    "save" => save(true),
    "apply" => apply(),
    "newEtichetta" => new_etichetta(),
  };
}

if (count($_POST) != 0) {
  die();
}

// ========================================================================================================================
// PAGINA PRINCIPALE
// ========================================================================================================================

$files = array_map(
  'htmlspecialchars',
  array_filter(
    glob('*'),
    fn ($f) => !is_dir($f)
  )
);

try {
  [
    'associazioni' => $associazioni,
    'etichette' => $etichette,
  ] = json_decode(file_get_contents(CONFIGFILEJSON), true);
  foreach (array_diff(array_unique(array_keys($associazioni)), $files) as $file) {
    unset($associazioni[$file]);
  }
} catch (Exception | ValueError) {
  $associazioni = [];
  $etichette = [];
};

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
    <?php
    echo implode("\n", array_map(
      fn ($v) => sprintf('
        <a id="%s" target="contenuto" class="miniatura %s"
        onclick="selezionaFile(this);phpGetAssociazione(\'%s\')"
        href="./?file=%s">%s</a>
        ', $v, array_key_exists($v, $associazioni) ? 'evidenziatura' : '', $v, $v, $v),
      $files
    ));
    ?>
  </div>
  <iframe name="contenuto" id="contenuto"></iframe>
  <form id="controlli" action="./" method="post">
    <button type="submit" name="command" value="apply">Applica modifiche</button>
    <button type="submit" name="command" value="save">Salva</button>
    <fieldset hidden id="associazioni">
      <?php
      foreach ($files as $f) {
        foreach ($etichette as $k => $e) {
          $selezione = array_key_exists($f, $associazioni) ? 'selected' : '';
          echo "<input type='radio' name='$file' value='$k' $selezione>\n";
        }
      }
      ?>
    </fieldset>
    <button type="submit" name="command" value="newEtichetta">Nuova directory</button>
    <input name="etichetta" type="text">
  </form>
  <form action="./" method="post" id="newAssociazioneForm">
    <fieldset id="etichette">
      <?php
      echo implode("\n", array_map(
        fn ($v, $k) => sprintf('
        <span class="etichetta">
          <input type="radio" name="etichetta" value="%s" onclick="phpNewAssociazione(\'%s\')">
          <input type="text" value="%s">
        </span>
        ', $k, $k, $v),
        $etichette,
        array_keys($etichette)
      ));
      ?>
    </fieldset>
    <input hidden id='fileattuale' type="text" name="file">
  </form>
</body>

<script>
  function clickPrimoNonEvidenziato() {
    Object.values(miniature.children).filter(
      (elem) => !elem.classList.contains('evidenziatura')
    ).slice(0, 1).forEach(
      (elem) => elem.click()
    );
  }

  function main() {
    clickPrimoNonEvidenziato();
  }

  function phpNewAssociazione(etichetta) {
    const etichettefile = Object.values(associazioni.children)
      .filter((a) => a.name == fileattuale.value);
    const primocheck = !etichettefile
      .map((a) => a.checked)
      .reduce((a, b) => a || b, false);
    etichettefile
      .filter((a) => a.value == etichetta)
      .forEach((a) => a.checked = true);
    document.getElementById(fileattuale.value).classList.add('evidenziatura');
    if (primocheck) {
      clickPrimoNonEvidenziato();
    }
  }

  const etichette = Object.values(document.getElementsByName('etichetta'));

  function phpGetAssociazione(file) {
    etichette.forEach((e) => e.checked = false);
    Object.values(associazioni.children)
      .filter((a) => a.checked && a.name == file)
      .forEach((a) => etichette
        .filter((e) => e.value == a.value)
        .forEach((e) => e.checked = true)
      );
  }

  function selezionaFile(elem) {
    const selezione = 'selezione';
    Object.values(miniature.children).filter(
      (elem) => elem.classList.contains(selezione)
    ).forEach(
      (elem) => elem.classList.remove(selezione)
    );
    fileattuale.value = elem.id;
    elem.classList.add(selezione);
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
