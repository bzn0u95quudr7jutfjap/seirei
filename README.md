# 清麗ディレクトリ (seirei directori)

## Disclaimer 「免責」

Il problema che voglio risolvere con questo tool mi impedisce
di separlo in più file.

## Come usare　「使い方」

1. Aprire un terminale nella directory.
2. Eseguire il comando riportato qui sotto.
3. Collegarsi a $IP:$PORTA dal proprio browser.

## Comando 「命令」

```shell
php --server $IP:$PORTA $INDEXPHP --docroot .
```

$IP e $PORTA scelti arbitrari.
$INDEXPHP è il path di index.php  
Esempio:

```shell
php --server localhost:8888 ~/.local/bin/index.php --docroot .
```
