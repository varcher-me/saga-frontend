<?php
include "log4php/Logger.php";
include "Exception/SagaException.php";
include "Connector/MySQLiConnector.php";
include "Connector/RedisConnector.php";
include "constant.php";

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
$logger->info("==========Transaction START for function {$function}==========");
switch ($function) {
    case "initialize":
        $retMsg = initialize();
        break;
    case "singleUpload":
        $retMsg = singleUpload();
        break;
    case "transform":
        $retMsg = transform();
        break;
    case "showList":
        $retMsg = showList();
        break;
    case "downFile":
        $retMsg = downFile();
        break;
    case "downFilePack":
        $retMsg = downFilePack();
        break;
    default:
        $retMsg = buildReturnMsg(__EXCEPTION_FUNCTION_UNKNOWN__, "CALL_FUNCTION_UNKNOWN");
        $logMsg = sprintf("Unknown Call Function %s From IP:[%s]", $function, $_SERVER['REMOTE_ADDR']);
        $logger->warn($logMsg);
        $retMsg = "Unknown Call Function, operation has been logged.";
        break;
}
echo $retMsg;

$logger->info("==========Transaction END for function {$function}==========");

function initialize()
{
    global $logger;
    global $dbConn;
    global $_CFG;
    try {
        $dbConn = createDbConn($_CFG['mysql']['host'], $_CFG['mysql']['port'], $_CFG['mysql']['user'], $_CFG['mysql']['pass'], $_CFG['mysql']['database']);
        insertPostList();
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
            $returnMsg = $e->getMessage();
            $logger->info("INITIALIZE FAILED.");
        } else {
            $returnCode = __EXCEPTION_SUCCESS__;
            $returnMsg = "SUCCESSFULLY";
            $logger->info("INITIALIZE SUCCESSFULLY.");
        }
        return buildReturnMsg($returnCode, $returnMsg);
    }
}

function singleUpload()
{
    global $logger;
    global $dbConn;
    global $_CFG;
    $uuid = $_REQUEST['uuid'];
    $userid = $_SERVER['REMOTE_ADDR'];
    try {
        $dbConn = createDbConn($_CFG['mysql']['host'], $_CFG['mysql']['port'], $_CFG['mysql']['user'], $_CFG['mysql']['pass'], $_CFG['mysql']['database']);
        verifyUserByUUID($uuid, $userid);
        uploadFileCheck();
        uploadFileMove($_CFG['path']['init']);
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
        $dbConn = createDbConn($_CFG['mysql']['host'], $_CFG['mysql']['port'], $_CFG['mysql']['user'], $_CFG['mysql']['pass'], $_CFG['mysql']['database']);
        if (!verifyUserByUUID($uuid, $userId)) {
            $logger->error("UnMatched user for UUID {$uuid}, current user is {$userId}.");
            throw new Exception("Unmatched user.", __EXCEPTION_USER_UNMATCH__);
        }
        try {
            $redis = new RedisConnector($_CFG['redis']['host'], $_CFG['redis']['port'], $_CFG['redis']['auth']);
            $redis->selectDB($_CFG['redis']['db']);
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
        $output = "<table><tr><td>文件名</td><td>处理状态</td><td>说明</td><td>下载</td></tr>\n"; // TODO:增加css
        $result = getListByUUID($uuid);
        while ($line = $result->fetch_array(MYSQLI_ASSOC)) {
            $status = $line['process_status'];
            if ($status == 4 or $status == 5) {
                $link = "<a href='file.php?function=downFile&uuid={$uuid}&seqNo={$line['seq_no']}'>下载</a>";
                $comment = "转换成功";
            }else{
                $link = "";
                $comment = $line['process_comment'];
            }
            $output .= "<tr><td>{$line['filename_secure']}</td>
<td>{$status}</td>
<td>{$comment}</td>
<td>{$link}</td></tr>";
        }
        $output .= "
        <tr><td colspan=4><a href='file.php?function=downFilePack&uuid={$uuid}'>打包下载</a></td></tr></table>";
        return $output;
    } catch (Exception $e) {
        $logger->error($e->getMessage());
        $logger->error($e->getTraceAsString());
        return $e->getMessage();
    } catch (Error $e){
        $logger->fatal($e->getMessage());
        $logger->fatal($e->getTraceAsString());
        return $e->getMessage();
    }
}

function downFile() //todo: 异常返回控制
{
    global $logger;
    global $dbConn;
    global $_CFG;
    try {
        $uuid = $_REQUEST['uuid'];
        $seqNo = $_REQUEST['seqNo'];
        $userId = $_SERVER['REMOTE_ADDR'];
        $dbConn = createDbConn($_CFG['mysql']['host'], $_CFG['mysql']['port'], $_CFG['mysql']['user'], $_CFG['mysql']['pass'], $_CFG['mysql']['database']); //todo 参数化
        if (!verifyUserByUUID($uuid, $userId)) {
            $logger->error("UnMatched user for UUID {$uuid}, current user is {$userId}.");
            throw new Exception("Unmatched user.", __EXCEPTION_USER_UNMATCH__);
        }
        $result = getFileByKey($uuid, $seqNo);
        if ($result->num_rows != 1) {
            throw New MySQLException("Get File for {$uuid}-{$seqNo} Failed.", __EXCEPTION_DBER__);
        }
        $line = $result->fetch_array(MYSQLI_ASSOC);
            $filePathName = $_CFG['path']['result'] . $line['filename_server']. ".pdf";
            $fileDownName = $line['filename_secure']. ".pdf";
            downloadFile($filePathName, $fileDownName);
    } catch (Exception $e) {
        $logger->error($e->getMessage());
        $logger->error($e->getTraceAsString());
    } catch (Error $e){
        $logger->fatal($e->getMessage());
        $logger->fatal($e->getTraceAsString());
    }
}

function downFilePack()
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
        $result = getListByUUID($uuid);
        $fileArray = Array();
        if ($result->num_rows < 1) {
            throw New MySQLException("Get FileList for {$uuid} Failed.", __EXCEPTION_DBER__);
        }
        while ($line = $result->fetch_array(MYSQLI_ASSOC)) {
            if ($line['process_status'] == 4 or $line['process_status'] == 5) {
                $filePathName = $_CFG['path']['result'] . $line['filename_server'] . ".pdf";
                $fileDownName = $line['filename_secure'] . ".pdf";
                if (file_exists($filePathName)) {
                    $fileArray[] = ['pathName' => $filePathName, 'downName' => $fileDownName];
                }
            }
        }
        downloadFilePacked($uuid, $fileArray);
        return "";
    } catch (Exception $e) {
        $logger->error($e->getMessage());
        $logger->error($e->getTraceAsString());
        return $e->getMessage();
    } catch (Error $e){
        $logger->fatal($e->getMessage());
        $logger->fatal($e->getTraceAsString());
        return $e->getMessage();
    }
}

function downloadFile(string $filePathName, string $fileDownName)
{
    if (!file_exists($filePathName)) {
        throw new FileException("Result File {$filePathName} not existed.", __EXCEPTION_FILE_ERROR__);
    }
    $file=fopen($filePathName,"r");
    $fileSize = filesize($filePathName);
    header("Content-Type: application/octet-stream");
    header("Accept-Ranges: bytes");
    header("Accept-Length: ".$fileSize);
    header("Content-Disposition: attachment; filename={$fileDownName}");
    echo fread($file,$fileSize);
    fclose($file);
}

function downloadFilePacked(string $uuid, array $fileList)
{
    global $_CFG;
    $zip  = new ZipArchive();
    $file = $_CFG['path']['temp']."{$uuid}.zip";
    if($zip->open($file, ZipArchive::CREATE)=== TRUE){
        foreach ($fileList as $infile) {
            $zip->addFile($infile['pathName'], $infile['downName']);
        }
        $zip->close();
    }
    if (!file_exists($file)) {
        throw new NoFilePackedException("No file packed.", __EXCEPTION_UNKNOWN__);
    }
    header("Cache-Control: public");
    header("Content-Description: File Transfer");
    header("Content-disposition: attachment; filename={$file}"); //文件名
    header("Content-Type: application/zip"); //zip格式的
    header("Content-Transfer-Encoding: binary"); //告诉浏览器，这是二进制文件
    header('Content-Length: '. filesize($file)); //告诉浏览器，文件大小
    @readfile($file);
    unlink($file);
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
    $fileName = $_FILES['file']['name'];
    $fileAlias = $_FILES["file"]["tmp_name"];
    $filename_safe      = preg_replace("&[\\\/:\*<>\|\?~$]&", "_", $fileName);
    if (!move_uploaded_file($fileAlias, $uploadPath . $filename_safe)) {
        throw new FileException("Move File to destiny directory failed.");
    }

}

function insertPostList()
{
    global $dbConn;
    global $logger;

    $uuid               = $_REQUEST['uuid'];
    $status             = 1;
    $appid              = "00";
    $userid             = $_SERVER['REMOTE_ADDR'] ;
    $userip             = $_SERVER['REMOTE_ADDR'] ;
    $time_post          = date("Y-m-d H:i:s", time());

    try {
        $sql = "INSERT INTO postlist (uuid, appid, userid, userip, time_post, process_status) VALUES
                            (?,?,?,?,?,?)";
        $dbConn->insert($sql, 'ssssss', $uuid, $appid, $userid, $userip, $time_post, $status);
    } catch (Exception $e) {
        $logger->error($e->getMessage());
        $logger->error($e->getTraceAsString());
        throw new MySQLException("Insert PostList Failed.", __EXCEPTION_DBER__);
    }
}

function insertHistory()
{
    global $dbConn;
    global $logger;

    $uuid               = $_REQUEST['uuid'];
    $seqNo              = $_REQUEST['seq_no'];
    $status             = 1;
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
        $sql = "INSERT INTO history (uuid, seq_no, process_status, time_post, filename_post, filename_secure) VALUES
                            (?,?,?,?,?,?)";
        $dbConn->insert($sql, 'siisss', $uuid, $seqNo, $status, $time_post, $filename_post, $filename_safe);
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
    $returnArray['function'] = $_REQUEST['function'];
    return json_encode($returnArray);
}

function verifyUserByUUID(string $uuid, string $inUser):bool
{
    global $dbConn;
    global $logger;

    try {
        $sql = "SELECT userid FROM postlist WHERE uuid = ? LIMIT 1";
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
        $sql = "SELECT seq_no, process_status, time_post, time_process, filename_secure, filename_server, process_phase, process_comment 
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

function getFileByKey(string $uuid, int $seqNo): mysqli_result
{
    global $dbConn;
    global $logger;

    try {
        $sql = "SELECT filename_secure, filename_server
                FROM history WHERE uuid = ? AND seq_no = ?";
        $stmt = $dbConn->select($sql, 'si', $uuid, $seqNo);
        $resultArray = $stmt->get_result();
        return $resultArray;
    } catch (Exception $e) {
        $logger->error($e->getMessage());
        $logger->error($e->getTraceAsString());
        throw new MySQLException("SELECT Failed.", __EXCEPTION_DBER__);
    }
}

?>