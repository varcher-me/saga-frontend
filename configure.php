<?php
/**
 * Created by PhpStorm.
 * User: Vagrant Archer
 * Date: 2018/3/11
 * Time: 23:31
 */

return Array(
    'mysql' => array(
        "host" => "127.0.0.1",
        "port" => "3306",
        "user" => "saga",
        "pass" => "saga",
        "database" => "saga",
    ),
    'redis' => array(
        "host" => "127.0.0.1",
        "port" => "6379",
        "auth" => "",
        "db"   => 2,
    ),
    'path' => array(
        "init"      => "/tmp/saga/init/",
        "processed" => "/tmp/saga/processed/",
        "error"     => "/tmp/saga/error/",
        "result"    => "/tmp/saga/result/",
    ),
);