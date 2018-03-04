<?php
/**
 * Created by PhpStorm.
 * User: varcher
 * Date: 2018/3/4
 * Time: 下午10:07
 */

class RedisConnector
{

    /**
     * @var Redis $conn
     */
    private $conn = null;

    public function __construct(string $host, string $port, string $password = "")
    {
        $this->conn = new Redis();
        if (!$this->conn->connect($host, $port)) {
            throw new Exception("Redis Connect failed, Reason = {$this->conn->getLastError()}");
        }
        if ($password != "") {
            if (!$this->conn->auth($password)) {
                throw new Exception("Redis Authenticate failed, Reason = {$this->conn->getLastError()}");
            }
        }
        $this->conn->ping();
    }

    public function selectDB(int $db)
    {
        if (!$this->conn->select($db)) {
            throw new Exception("Redis select DB to {$db} Failed, Reason = {$this->conn->getLastError()}");
        }
    }

    public function set(string $key, string $value, int $timeout = 0)
    {
        if (!$this->conn->set($key, $value, $timeout)) {
            throw new Exception("Redis set {$key} to {$value} Failed, Reason = {$this->conn->getLastError()}");
        }
    }

    public function rpush(string $key, string $value)
    {
        if (!$this->conn->rpush($key, $value)) {
            throw new Exception("Redis push {$value} to {$key} Failed, Reason = {$this->conn->getLastError()}");
        }
    }
}