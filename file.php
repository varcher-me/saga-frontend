<?php
include "log4php/Logger.php";
include "Exception/SagaException.php";
include "Connector/MySQLiConnector.php";
include "Connector/RedisConnector.php";

define("__EXCEPTION_SUCCESS__", 0);
define("__EXCEPTION_DBER__", 1);
define("__EXCEPTION_FILE_ERROR__", 2);
define("__EXCEPTION_FUNCTION_UNKNOWN__", 3);
define("__EXCEPTION_USER_UNMATCH__", 4);
define("__EXCEPTION_REDIS_ERR__", 5);
define("__EXCEPTION_NO_UUID__", 6);
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
    case "showList":
        $retMsg = showList();
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
//        uploadFileMove("d:/temp/init/");   // todo:目标文件夹改为参数
        insertHistory();
    } catch (Exception $e) {
        $logger->error($e->getMessage());
        $logger->error($e->getTraceAsString());
    } catch (Error $e){
        $logger->fatal($e->getMessage());
        $logger->fatal($e->getTraceAsString());
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
            throw new SagaRedisException("REDIS operate failed.", __EXCEPTION_REDIS_ERR__);
        }

    } catch (Exception $e) {
        $logger->error($e->getMessage());
        $logger->error($e->getTraceAsString());
    } catch (Error $e){
        $logger->fatal($e->getMessage());
        $logger->fatal($e->getTraceAsString());
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

function showList()
{
    global $logger;
    global $dbConn;
    global $_CFG;
    try {
        // todo:增加排队状态检查
        $uuid = $_REQUEST['uuid'];
        $userId = $_SERVER['REMOTE_ADDR'];
        $dbConn = createDbConn($_CFG['mysql']['host'], $_CFG['mysql']['port'], $_CFG['mysql']['user'], $_CFG['mysql']['pass'], $_CFG['mysql']['database']); //todo 参数化
        if (!verifyUserByUUID($uuid, $userId)) {
            $logger->error("UnMatched user for UUID {$uuid}, current user is {$userId}.");
            throw new Exception("Unmatched user.", __EXCEPTION_USER_UNMATCH__);
        }
        $output = "<table><tr><td>文件名</td><td>处理状态</td><td>说明</td></tr>\n"; // TODO:增加css
        $result = getListByUUID($uuid);
        while ($line = $result->fetch_array(MYSQLI_ASSOC)) {
        /*
         *   'process_status' => int 1
  'time_post' => string '2018-03-03 22:49:05' (length=19)
  'time_process' => null
  'filename_secure' => string 'sheet_google_64.png' (length=19)
  'filename_server' => string '/tmp/phpJVzphr' (length=14)
  'process_phase' => null
  'process_comment' => null
         */
            $output .= "<tr><td>{$line['filename_secure']}</td><td>{$line['process_status']}</td><td>{$line['process_comment']}</td></tr>";
        }
        $output .= "</table>";


    } catch (Exception $e) {
        $logger->error($e->getMessage());
        $logger->error($e->getTraceAsString());
    } catch (Error $e){
        $logger->fatal($e->getMessage());
        $logger->fatal($e->getTraceAsString());
    } finally {
        if (isset($e)) {
            $returnCode = $e->getCode() > 0 ? $e->getCode() : __EXCEPTION_UNKNOWN__;
            $returnMsg  = $e->getMessage();
            $logger->info("show list for UUID {$uuid} Failed by {$returnMsg}");
        } else {
            $returnCode = __EXCEPTION_SUCCESS__;
            $returnMsg  = "SUCCESSFULLY";
            $logger->info("show list for UUID {$uuid} successfully.");
            return $output;
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
        throw new MySQLException("Connection to Database failed.", __EXCEPTION_DBER__);
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
        throw new FileException($msg, __EXCEPTION_FILE_ERROR__);
    }
}

function uploadFileMove(string $uploadPath)
{
    global $logger;
    $fileName = $_FILES['file']['name'];
    $fileAlias = $_FILES["file"]["tmp_name"];
    $filename_safe      = preg_replace("&[\\\/:\*<>\|\?~$]&", "_", $fileName);
    if (!move_uploaded_file($fileAlias, $uploadPath . $filename_safe)) {
        throw new FileException("Move File to destiny directory failed.");
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
        throw new MySQLException("Insert History Failed.", __EXCEPTION_DBER__);
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
        $result = $stmt->fetch();
        if ($result == null) {
            throw new Exception("NO UUID {$uuid}.", __EXCEPTION_NO_UUID__);
        }
        return $userId == $inUser;
    } catch (Exception $e) {
        $logger->error($e->getMessage());
        $logger->error($e->getTraceAsString());
        throw new MySQLException("SELECT Failed.", __EXCEPTION_DBER__);
    }
}

function getListByUUID(string $uuid): mysqli_result
{
    global $dbConn;
    global $logger;

    try {
        $sql = "SELECT process_status, time_post, time_process, filename_secure, filename_server, process_phase, process_comment 
                FROM history WHERE uuid = ?";
        $stmt = $dbConn->select($sql, 's', $uuid);
        $resultArray = $stmt->get_result();
        return $resultArray;
    } catch (Exception $e) {
        $logger->error($e->getMessage());
        $logger->error($e->getTraceAsString());
        throw new MySQLException("SELECT Failed.", __EXCEPTION_DBER__);
    }
}
?>