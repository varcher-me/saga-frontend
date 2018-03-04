<?php
include "log4php/Logger.php";
include "Connector/MySQLiConnector.php";

define("__EXCEPTION_SUCCESS__", 0);
define("__EXCEPTION_DBER__", 1);
define("__EXCEPTION_FILE_ERROR__", 2);
define("__EXCEPTION_UNKNOWN__", 9999);

date_default_timezone_set('PRC');
ini_set("display_errors", "Off");

Logger::configure(dirname(__FILE__).'/logger.xml');
$logger = Logger::getLogger('Saga');

try {
    $dbConn = createDbConn("192.168.10.10", "3306", "homestead", "secret1", "saga"); //todo 参数化
    uploadFileCheck();
    uploadFileMove("uploadfile/");   // todo:目标文件夹改为参数
    insertHistory();
} catch (Exception $e) {
    $logger->error($e->getMessage());
    $logger->error($e->getTraceAsString());
//    throw $e;
} finally {
    $returnArray = array();
    if (isset($e)) {
        $returnArray['returnCode'] = $e->getCode() > 0 ? $e->getCode(): __EXCEPTION_UNKNOWN__;
        $returnArray['returnMsg']  = $e->getMessage();
    } else {
        $returnArray['returnCode'] = __EXCEPTION_SUCCESS__;
        $returnArray['returnMsg']  = "SUCCESSFULLY";
    }
    $return = json_encode($returnArray);

    $fileName   = $_FILES['file']['name'];
    $type       = $_FILES['file']['type'];
    $size       = $_FILES['file']['size'] / 1048576;
    $uuid       = $_REQUEST['uuid'];
    $seqNo      = $_REQUEST['seq_no'];
    $logMsg     = sprintf("UUID=%s, SEQ=%s, filename=%s, type=%s, size=%.1f MB, ReturnCode=%d, ReturnMsg=%s",
                                  $uuid,   $seqNo, $fileName,   $type,   $size,      $returnArray['returnCode'], $returnArray['returnMsg']);
    $logger->info($logMsg);
    echo $return;
}


function createDbConn($host, $port, $user, $password, $db)
{
    global $logger;
    try {
        $dbConn = new MySQLiConnector($host, $port, $user, $password, $db);
        return $dbConn;
    } catch (Exception $e) {
        $logger->error($e->getMessage());
        $logger->error($e->getTraceAsString());
        throw new Exception("Connection to Database failed.", __EXCEPTION_DBER__);
    }
}

function uploadFileCheck()
{
    global $logger;
    if (!isset ($_FILES['file'])) {
        throw new Exception("No File uploaded.", __EXCEPTION_FILE_ERROR__);
    }
    elseif ($_FILES['file']['error'] != 0) {
        $msg = getUploadErrorMsg($_FILES['file']['error']);
        throw new Exception($msg, __EXCEPTION_FILE_ERROR__);
    }
}

function uploadFileMove(string $uploadPath)
{
    global $logger;
    $fileName = $_FILES['file']['name'];
    $fileAlias = $_FILES["file"]["tmp_name"];
    move_uploaded_file($fileAlias, $uploadPath . $fileName);

}

function insertHistory()
{
    global $dbConn;
    global $logger;

    $uuid               = $_REQUEST['uuid'];
    $seqNo              = $_REQUEST['seq_no'];
    $status             = 1;
    $appid              = "00";
    $userid             = $_SERVER['REMOTE_ADDR'] ;
    $userip             = $_SERVER['REMOTE_ADDR'] ;
    $time_post          = date("Y-m-d H:i:s", time());
    $filename_post      = rawurldecode($_FILES['file']['name']);
    $filename_safe      = preg_replace("&[\\\/:\*<>\|\?~$]&", "_", $filename_post);

    if ($uuid == "") {      // todo: 测试代码
        $uuid = $time_post;
    }

    try {
        $sql = "INSERT INTO history (uuid, seq_no, status, appid, userid, userip, time_post, filename_post, filename_secure) VALUES
                            (?,?,?,?,?,?,?,?,?)";
        $dbConn->insert($sql, 'siissssss', $uuid, $seqNo, $status, $appid, $userid, $userip, $time_post, $filename_post, $filename_safe);
    } catch (Exception $e) {
        $logger->error($e->getMessage());
        $logger->error($e->getTraceAsString());
        throw new Exception("Insert History Failed.", __EXCEPTION_DBER__);
    }
}


function getUploadErrorMsg(int $errorNo): string
{
    static $error = array(
        "UPLOAD_ERR_OK",
        "UPLOAD_ERR_INI_SIZE",
        "UPLOAD_ERR_FORM_SIZE",
        "UPLOAD_ERR_PARTIAL",
        "UPLOAD_ERR_NO_FILE",
        "UPLOAD_ERR_NO_TMP_DIR",
        "UPLOAD_ERR_CANT_WRITE",
    );
    if ($errorNo >= sizeof($error)) {
        return sprintf("UPLOAD_ERR_UNKNOWN %d", $errorNo);
    }
    else {
        return $error[$errorNo];
    }
}
?>