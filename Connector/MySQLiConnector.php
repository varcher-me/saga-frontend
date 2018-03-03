<?php
/**
 * Created by PhpStorm.
 * User: varcher
 * Date: 2018/3/3
 * Time: 下午5:48
 */

class MySQLiConnector
{
    private $conn = null;

    public function __construct($host, $port, $user, $password, $db)
    {
        $this->conn = mysqli_connect($host, $user, $password, $db, $port);
        if (!$this->conn) {
            throw new Exception(sprintf("Connect to mysql failed, errno = %d, errmsg = %s",
                mysqli_connect_errno(), mysqli_connect_error()));
        }
    }

    public function execSQL($sql)
    {
        return $this->conn->query($sql);
    }

    public function getConn():mysqli
    {
        return $this->conn;
    }

}