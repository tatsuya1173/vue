<?php
if (strlen($strStartDate) == 8) {
	$strNowYMD = date('Ymd');
} else {
	$strNowYMD = date('YmdHis');
}

//キャンペーン期間外の場合
if ($strStartDate > $strNowYMD || $strEndDate < $strNowYMD) {
    header('Location: term_error.php');
    exit();
}
?>