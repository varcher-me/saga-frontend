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

$_CFG = include "configure.php";

Logger::configure(dirname(__FILE__).'/logger.xml');
$logger = Logger::getLogger('Saga');
$dbConn = new MySQLiConnector();

if (isset($_REQUEST['function'])) {
    $function = $_REQUEST['function'];
}
else{
    $function = "";
}

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
    global $_CFG;
    try {
        $dbConn = createDbConn($_CFG['mysql']['host'], $_CFG['mysql']['port'], $_CFG['mysql']['user'], $_CFG['mysql']['pass'], $_CFG['mysql']['database']); //todo 参数化
        uploadFileCheck();
        uploadFileMove("d:/temp/init/");   // todo:目标文件夹改为参数
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
    global $_CFG;
    try {
        $uuid = $_REQUEST['uuid'];
        $userId = $_SERVER['REMOTE_ADDR'];
        $dbConn = createDbConn($_CFG['mysql']['host'], $_CFG['mysql']['port'], $_CFG['mysql']['user'], $_CFG['mysql']['pass'], $_CFG['mysql']['database']); //todo 参数化
        if (!verifyUserByUUID($uuid, $userId)) {
            $logger->error("UnMatched user for UUID {$uuid}, current user is {$userId}.");
            throw new Exception("Unmatched user.", __EXCEPTION_USER_UNMATCH__);
        }
        try {
            $redis = new RedisConnector($_CFG['redis']['host'], $_CFG['redis']['port'], $_CFG['redis']['auth']);   //todo 参数化
            $redis->selectDB($_CFG['redis']['db']);    //todo 参数化
            $redis->rpush("INIT_QUEUE", $uuid);
            //todo 增加防重复提交处理
        } catch (Exception $e) {
            $logger->error($e->getMessage());
            $logger->error($e->getTraceAsString());
            throw new Exception("REDIS operate failed.", __EXCEPTION_REDIS_ERR__);
        } catch (Error $e){
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
            $logger->info("Transforming job pushed into initial Q for UUID {$uuid} Failed by {$returnMsg}");
        } else {
            $returnCode = __EXCEPTION_SUCCESS__;
            $returnMsg  = "SUCCESSFULLY";
            $logger->info("Transforming job pushed into initial Q for UUID {$uuid} Successfully.");
        }

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
    $filename_safe      = preg_replace("&[\\\/:\*<>\|\?~$]&", "_", $fileName);
    if (!move_uploaded_file($fileAlias, $uploadPath . $filename_safe)) {
        throw new Exception("Move File to destiny directory failed.");
    }

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
//    $filename_post      = $_FILES['file']['name'];
    $filename_safe      = preg_replace("&[\\\/:\*<>\|\?~$]&", "_", $filename_post);

    if (mb_detect_encoding($filename_post) != "UTF-8") {
        $filename_post = iconv(mb_detect_encoding($filename_post), "UTF-8", $filename_post);
    }
    if (mb_detect_encoding($filename_safe) != "UTF-8") {
        $filename_safe = iconv(mb_detect_encoding($filename_safe), "UTF-8", $filename_safe);
    }

    try {
        $sql = "INSERT INTO history (uuid, seq_no, process_status, appid, userid, userip, time_post, filename_post, filename_secure) VALUES
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