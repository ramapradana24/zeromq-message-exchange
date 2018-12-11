<?php

$host = '127.0.0.1';
$username = 'rama';
$password = 'ramapradana24';
$db = 'db_server_mca';

$connection = new mysqli($host, $username, $password, $db);
date_default_timezone_set("Asia/Singapore");
if($connection->connect_error){
    die('Connection Failed: ' . $connection->connect_error);
}