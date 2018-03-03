<?php
include "log4php/Logger.php";
include "Connector/MySQLiConnector.php";

date_default_timezone_set('PRC');
Logger::configure(dirname(__FILE__).'/logger.xml');
$logger = Logger::getLogger('Saga');

try {

    $dbConn = new MySQLiConnector("192.168.10.10", "3306", "homestead", "secret", "saga");
} catch (Exception $e) {
    $logger->error($e->getMessage());
    $logger->error($e->getTraceAsString());
    header('HTTP/1.1 500 MYSQL CONNECT FAILED');
}

$fileName = $_FILES['file']['name'];
$type = $_FILES['file']['type'];
$size = $_FILES['file']['size'];
$fileAlias = $_FILES["file"]["tmp_name"];

$uuid               = $_REQUEST['uuid'];
$seqNo              = $_REQUEST['seq_no'];
$status             = 1;
$appid              = "00";
$userid             = $_SERVER['REMOTE_ADDR'] ;
$time_post          = date("Y-m-d H:i:s", time());
$filename_post      = rawurldecode($fileName);
$filename_safe      = preg_replace("&[\\\/:\*<>\|\?~$]&", "_", $filename_post);
$filename_server    = $fileAlias;

if ($uuid == "") {
    $uuid = $time_post;
}

try {
    $sql = "INSERT INTO history (uuid, seq_no, status, appid, userid, time_post, filename_post, filename_secure) VALUES
                            (?,?,?,?,?,?,?,?)";
    if (!$bind = $dbConn->getConn()->prepare($sql)) {
        throw new Exception(sprintf("prepare failed, errno = %d, errmsg = %s",
            mysqli_errno($dbConn->getConn()), mysqli_error($dbConn->getConn())));
    }
    if (!$bind->bind_param('siisisss', $uuid, $seqNo, $status, $appid, $userid, $time_post, $filename_post, $filename_safe)) {
        throw new Exception(sprintf("bind failed, errno = %d, errmsg = %s",
            mysqli_errno($dbConn->getConn()), mysqli_error($dbConn->getConn())));
    }
    if (!$bind->execute()) {
        throw new Exception(sprintf("execute failed, errno = %d, errmsg = %s",
            mysqli_errno($dbConn->getConn()), mysqli_error($dbConn->getConn())));
    }
} catch (Exception $e) {
    $logger->error($e->getMessage());
    $logger->error($e->getTraceAsString());
    throw $e;
}


if($fileAlias){
    move_uploaded_file($fileAlias, "uploadfile/" . $fileName);
}
//    header('HTTP/1.1 500 66666'); todo: 返回值json化

header('content-type:text/html;charset=utf-8');
$logger->info( 'fileName: ' . $fileName . ', fileType: ' . $type . ', fileSize: ' . ($size / 1024) . 'KB');
$logger->info(sprintf("UUID = %s SEQ = %s", $uuid, $seqNo));
echo "HAHAHA UPLOAD SUCCESS";
?>