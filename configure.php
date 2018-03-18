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
        "init"      => "d:/temp/init/",
//        "processed" => "/tmp/saga/processed/",
//        "error"     => "/tmp/saga/error/",
        "result"    => "d:/temp/result/",
        "temp"      => "d:/temp/",
    ),
);