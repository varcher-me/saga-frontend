<?php
	$fileName = $_FILES['file']['name'];
	$type = $_FILES['file']['type'];
	$size = $_FILES['file']['size'];
	$fileAlias = $_FILES["file"]["tmp_name"];

	if($fileAlias){
		move_uploaded_file($fileAlias, "uploadfile/" . $fileName);
	}
//    header('HTTP/1.1 500 66666');
	echo 'fileName: ' . $fileName . ', fileType: ' . $type . ', fileSize: ' . ($size / 1024) . 'KB';
?>