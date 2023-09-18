<?php

$len = count($_POST);

if($len == 1){
  $path = $_POST["path"];
  if(file_put_contents($path, $path)){
    echo "SUCCESSO\n";
  }else{
    echo "FALLITO\n";
  }
    
}

if($len != 0){
  die();
}

?>
<html>
  <head>
    <script>
      function save_file() {
        var data = new FormData();
        data.append("path",document.getElementById("path").value);
        const xmlhttp = new XMLHttpRequest();
        xmlhttp.onload = function() {
          alert(this.responseText);
        }
        xmlhttp.open("POST", "index.php", true);
        xmlhttp.send(data);
      }
    </script>
    <style>
    </style>
  </head>
  <body>
    <h1> text </h1>
    <input id=path type=text> </input> <br />
    <button onclick=save_file()> post </button>
  </body>
</html>
