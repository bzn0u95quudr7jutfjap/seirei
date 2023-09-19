<?php

$len = count($_POST);

if ($len == 2) {
  var_dump($_POST);
  echo $_POST["etichette"] . "\n";
  echo $_POST["associazioni"] . "\n";
}

if ($len != 0) {
  die();
}

$CONFIGJSON = ".immagio.json";
$etichette = null;
$associazioni = null;
if (file_exists($CONFIGJSON)) {
  $conf = json_decode(file_get_contents($CONFIGJSON));
  $etichette = $conf->etichette;
  $associazioni = $conf->associazioni;
}

?>

<!DOCTYPE html>
<html>

<head>
  <script>
    var currentindex = 0;
    var images;
    var current_image;
    var newdir;
    var dirs;
    var fileslist;
    var etichette;

    function main() {
      newdir = document.getElementById("new-directory");
      dirs = document.getElementById("target-directories");
      fileslist = document.getElementById("pick-directory");

      current_image = document.getElementById("current-image");
      images = document.getElementById("images");
      etichette = {};

      var associazioni = {};

      function create_etichetta(nome) {
        var e = make_etichetta(nome);
        associazioni[nome] = e.children[0];
        dirs.append(e);
      }
      console.log(associazioni);

      <?php
      foreach ($etichette as $label) {
        echo 'create_etichetta("' . $label->nome . '");' . "\n";
      }
      ?>

      <?php
      $files = array_values(array_filter(glob('*'), function ($f) {
        return !is_dir($f);
      }));
      foreach ($files as $i => $file) {
        echo "images.append(make_miniatura($i,'$file','$file'));\n";
        echo "etichette['$file'] = null;\n";
      }
      ?>

      function set_associazione(path, label) {
        if (etichette.hasOwnProperty(path)) {
          etichette[path] = associazioni[label];
        }
      };

      <?php
      foreach ($associazioni as $a) {
        echo "set_associazione('" . $a->path . "','" . $a->label . "');\n";
      }
      ?>
      set_current_image(0);
    }

    function select_image(image, border, check) {
      image.style.border = border;
      var label = etichette[image.alt];
      if (label != null) {
        label.checked = check;
      }
    }

    function set_current_image(i) {
      select_image(images.children[currentindex], "none", false);

      currentindex = i;

      var image = images.children[currentindex];
      select_image(image, "thick solid #6666FF", true);

      current_image.src = image.src;
      current_image.alt = image.alt;
    }

    function make_miniatura(i, path, alt) {
      var img = document.createElement("img");
      img.src = path;
      img.alt = alt;
      img.onclick = function() {
        set_current_image(i);
      }
      return img;
    }

    function load_images(elem) {
      var i = 0;
      etichette = {};
      for (const file of elem.files) {
        var path = URL.createObjectURL(file);
        images.append(make_miniatura(i, path, file.name));
        etichette[file.name] = null;
        console.log(file.webkitRelativePath);
        i += 1;
      }
      set_current_image(0);
    }

    function make_etichetta(label_name) {
      var etichetta = document.createElement("input");
      etichetta.type = "radio";
      etichetta.name = "etichetta";
      etichetta.value = label_name;
      etichetta.onclick = function() {
        var img = images.children[currentindex].alt
        var etichetta_vecchia = etichette[img];
        etichette[img] = etichetta;
        if (etichetta_vecchia == null) {
          set_current_image((currentindex + 1) % images.children.length);
        }
      };
      var name = document.createElement("label");
      name.append(etichetta);
      name.append(label_name);
      return name;
    }

    function add_target_directory() {
      dirs.append(make_etichetta(newdir.value));
      newdir.value = "";
    }

    function save_to_file() {
      var salvatore = [];
      for (var i = 0; i < fileslist.files.length; i += 1) {
        const img = images.children[i];
        const lbl = etichette[img.alt];

        if (etichette[img.alt] != null) {
          salvatore.push({
            path: img.alt,
            label: etichette[img.alt].value
          });
        }

      }
      var cartelle = [];
      var labls = document.getElementsByName("etichetta");
      for (var i = 0; i < labls.length; i += 1) {
        cartelle.push({
          nome: labls[i].value
        });
      }

      var data = JSON.stringify({
        etichette: cartelle,
        associazioni: salvatore
      });

      console.log(data);

      var data_post = new FormData();
      data_post.append("etichette", cartelle);
      data_post.append("associazioni", salvatore);

      const xmlhttp = new XMLHttpRequest();
      xmlhttp.onload = function() {
        console.log(this.responseText);
      }
      xmlhttp.open("POST", "index.php", true);
      xmlhttp.send(data_post);

      //var file = new Blob([data], {
      //  type: "text/plain"
      //});
      //
      //var a = document.createElement("a");
      //a.href = URL.createObjectURL(file);
      //a.download = "immagiosorter.json";
      //a.click();
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

    #current-image {
      max-width: 90%;
      max-height: 90%;
    }

    #images {
      overflow: scroll;
      height: 90%;
    }

    #images img {
      width: 160px;
      margin: 10px;
      margin-right: 20px;
    }

    #images,
    #controls {
      margin: 20px;
    }

    #controls {
      height: 90%;
      display: grid;
      grid-template-rows: min-content min-content min-content min-content auto min-content;
      grid-gap: 10px;
    }

    #target-directories {
      overflow: scroll;
      height: 100%;
      display: flex;
      flex-direction: column;
    }

    #target-directories label {
      width: 100%;
      font-size: 22px;
    }

    #target-directories label input {
      height: 20px;
      width: 20px;
    }
  </style>
</head>


<body onload=main()>
  <div id="images"> </div>
  <img id="current-image" alt="IMMAGINE" />
  <div id="controls">
    <input type=file accept="image/*" webkitdirectory directory id=pick-directory onchange="load_images(this)"></input>
    <button onclick=save_to_file()>Salva</button>
    <button onclick=add_target_directory()>Nuova directory</button>
    <input id=new-directory type=text></input>
    <div id="target-directories"> </div>
    <button>Muovi</button>
  </div>
</body>

</html>
