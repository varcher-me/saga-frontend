<?php
/**
 * Created by PhpStorm.
 * User: Vagrant Archer
 * Date: 2018/3/18
 * Time: 22:50
 */

define("__EXCEPTION_SUCCESS__", 0);
define("__EXCEPTION_DBER__", 1);
define("__EXCEPTION_FILE_ERROR__", 2);
define("__EXCEPTION_FUNCTION_UNKNOWN__", 3);
define("__EXCEPTION_USER_UNMATCH__", 4);
define("__EXCEPTION_REDIS_ERR__", 5);
define("__EXCEPTION_NO_UUID__", 6);
define("__EXCEPTION_UNKNOWN__", 9999);

define("__STATUS_INIT__", 0);
define("__STATUS_UPLOAD__", 1);
define("__STATUS_LOADED__", 2);
define("__STATUS_PROCESSED__", 4);
define("__STATUS_FAILED__", 9);