<?php
//メニュー作成
function MakeMenu($intAdminLevel, $intMenuID = 0) {
    
    $aryActive = array();
    $aryActive[$intMenuID] = ' class="active"';
    
    $strMenu = '<li'.$aryActive[1].'><a href="download.php">CSVダウンロード</a></li>'."\n";
    
    return $strMenu;
}
?>
