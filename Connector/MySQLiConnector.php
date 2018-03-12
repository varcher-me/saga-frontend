<?php
/**
 * Created by PhpStorm.
 * User: varcher
 * Date: 2018/3/3
 * Time: 下午5:48
 */

class MySQLiConnector
{
    /**
     * @var mysqli $conn MYSQL数据库连接
     */
    private $conn = null;

    public function connect($host, $port, $user, $password, $db)
    {
        $this->conn = mysqli_connect($host, $user, $password, $db, $port);
        mysqli_query($this->conn,'set names utf8');
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

    public function insert($stmt, $type, ...$param)
    {
        if (!$bind = $this->conn->prepare($stmt)) {
            throw new Exception(sprintf("prepare for SQL %s failed, errno = %d, errmsg = %s",
                $stmt, mysqli_errno($this->conn), mysqli_error($this->conn)));
        }
        if (!$bind->bind_param($type, ...$param)) {
            throw new Exception(sprintf("bind for SQL %s failed, errno = %d, errmsg = %s",
                $stmt, mysqli_errno($this->conn), mysqli_error($this->conn)));
        }
        if (!$bind->execute()) {
            throw new Exception(sprintf("execute for SQL %s failed, errno = %d, errmsg = %s",
                $stmt, mysqli_errno($this->conn), mysqli_error($this->conn)));
        }
    }

    public function select($stmt, $type, ...$param):mysqli_stmt
    {
        if (!$bind = $this->conn->prepare($stmt)) {
            throw new Exception(sprintf("prepare for SQL %s failed, errno = %d, errmsg = %s",
                $stmt, mysqli_errno($this->conn), mysqli_error($this->conn)));
        }
        if (!$bind->bind_param($type, ...$param)) {
            throw new Exception(sprintf("bind for SQL %s failed, errno = %d, errmsg = %s",
                $stmt, mysqli_errno($this->conn), mysqli_error($this->conn)));
        }
        if (!$bind->execute()) {
            throw new Exception(sprintf("execute for SQL %s failed, errno = %d, errmsg = %s",
                $stmt, mysqli_errno($this->conn), mysqli_error($this->conn)));
        }
        return $bind;
    }

}