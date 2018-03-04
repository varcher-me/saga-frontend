<?php
include "log4php/Logger.php";
include "Connector/MySQLiConnector.php";
include "Connector/RedisConnector.php";

define("__EXCEPTION_SUCCESS__", 0);
define("__EXCEPTION_DBER__", 1);
define("__EXCEPTION_FILE_ERROR__", 2);
define("__EXCEPTION_FUNCTION_UNKNOWN__", 3);
define("__EXCEPTION_USER_UNMATCH__", 4);
define("__EXCEPTION_REDIS_ERR__", 4);
define("__EXCEPTION_UNKNOWN__", 9999);

date_default_timezone_set('PRC');
ini_set("display_errors", "On");

Logger::configure(dirname(__FILE__).'/logger.xml');
$logger = Logger::getLogger('Saga');
$dbConn = new MySQLiConnector();

$function = $_REQUEST['function'];

switch ($function) {
    case "singleUpload":
        $retMsg = singleUpload();
        break;
    case "transform":
        $retMsg = transform();
        break;
    default:
        $retMsg = buildReturnMsg(__EXCEPTION_FUNCTION_UNKNOWN__, "CALL_FUNCTION_UNKNOWN");
        $logMsg = sprintf("Unknown Call Function %s From IP:[%s]", $function, $_SERVER['REMOTE_ADDR']);
        $logger->warn($logMsg);
        $retMsg = "Unknown Call Function, operation has been logged.";
        break;
}
echo $retMsg;

function singleUpload()
{
    global $logger;
    global $dbConn;
    try {
        $dbConn = createDbConn("192.168.10.10", "3306", "homestead", "secret", "saga"); //todo 参数化
        uploadFileCheck();
        uploadFileMove("uploadfile/");   // todo:目标文件夹改为参数
        insertHistory();
    } catch (Exception $e) {
        $logger->error($e->getMessage());
        $logger->error($e->getTraceAsString());
//    throw $e;
    } finally {
        if (isset($e)) {
            $returnCode = $e->getCode() > 0 ? $e->getCode() : __EXCEPTION_UNKNOWN__;
            $returnMsg  = $e->getMessage();
        } else {
            $returnCode = __EXCEPTION_SUCCESS__;
            $returnMsg  = "SUCCESSFULLY";
        }

        $fileName   = $_FILES['file']['name'];
        $type       = $_FILES['file']['type'];
        $size       = $_FILES['file']['size'] / 1048576;
        $uuid       = $_REQUEST['uuid'];
        $seqNo      = $_REQUEST['seq_no'];
        $logMsg     = sprintf("UUID=%s, SEQ=%s, filename=%s, type=%s, size=%.1f MB, ReturnCode=%d, ReturnMsg=%s",
                                      $uuid,   $seqNo, $fileName,   $type,   $size,        $returnCode,   $returnMsg);
        $logger->info($logMsg);
        return buildReturnMsg($returnCode, $returnMsg);
    }
}

function transform()
{
    global $logger;
    global $dbConn;
    try {
        $uuid = $_REQUEST['uuid'];
        $userId = $_SERVER['REMOTE_ADDR'];
        $dbConn = createDbConn("192.168.10.10", "3306", "homestead", "secret", "saga"); //todo 参数化
        if (!verifyUserByUUID($uuid, $userId)) {
            $logger->error("UnMatched user for UUID {$uuid}, current user is {$userId}.");
            throw new Exception("Unmatched user.", __EXCEPTION_USER_UNMATCH__);
        }
        try {
            $redis = new RedisConnector("127.0.0.1", "6379", "");   //todo 参数化
            $redis->selectDB(2);    //todo 参数化
            $redis->rpush("QUEUE_INITIAL", $uuid);
            //todo 增加防重复提交处理
        } catch (Exception $e) {
            $logger->error($e->getMessage());
            $logger->error($e->getTraceAsString());
            throw new Exception("REDIS operate failed.", __EXCEPTION_REDIS_ERR__);
        }
    } catch (Exception $e) {
        $logger->error($e->getMessage());
        $logger->error($e->getTraceAsString());
    } finally {
        if (isset($e)) {
            $returnCode = $e->getCode() > 0 ? $e->getCode() : __EXCEPTION_UNKNOWN__;
            $returnMsg  = $e->getMessage();
        } else {
            $returnCode = __EXCEPTION_SUCCESS__;
            $returnMsg  = "SUCCESSFULLY";
        }

        $logger->info("Transforming job pushed into initial Q for UUID {$uuid}");
        return buildReturnMsg($returnCode, $returnMsg);
    }

}

function createDbConn($host, $port, $user, $password, $db)
{
    global $logger;
    global $dbConn;
    try {
        $dbConn->connect($host, $port, $user, $password, $db);
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
    $filename_safe      = preg_replace("&[\\\/:\*<>\|\?~$]&", "_", $fileAlias);
    move_uploaded_file($fileAlias, $uploadPath . $filename_safe);

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

function buildReturnMsg(int $returnCode, string $returnMsg): String
{
    $returnArray['returnCode'] = $returnCode;
    $returnArray['returnMsg']  = $returnMsg;
    return json_encode($returnArray);
}

function verifyUserByUUID(string $uuid, string $inUser):bool
{
    global $dbConn;
    global $logger;

    try {
        $sql = "SELECT userid FROM history WHERE uuid = ? LIMIT 1";
        $stmt = $dbConn->select($sql, 's', $uuid);
        $stmt->bind_result($userId);
        $stmt->fetch();
        return $userId == $inUser;
    } catch (Exception $e) {
        $logger->error($e->getMessage());
        $logger->error($e->getTraceAsString());
        throw new Exception("SELECT Failed.", __EXCEPTION_DBER__);
    }
}
?>