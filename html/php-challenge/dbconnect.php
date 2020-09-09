<?php
$option = [PDO::ATTR_EMULATE_PREPARES=>false];
try {
    $db = new PDO('mysql:dbname=test;host=mysql;charset=utf8', 'root', 'root', $option);
} catch (PDOException $e) {
    echo 'DB接続エラー： ' . $e->getMessage();
}
