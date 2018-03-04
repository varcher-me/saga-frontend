<?php
/**
 * Created by PhpStorm.
 * User: varcher
 * Date: 2018/3/2
 * Time: 下午11:18
 */

$host = "127.0.0.1";
$port = "6379";
$password = "";



$redis = new Redis();

$redis->connect($host, $port);
if ($password != "") {
    $redis->auth($password);
}
echo "Server is running: " . $redis->ping();

echo "hello!";