<?php
class CConstString
{
    const DL_PROC = 1; //ダウンロード
    
    //年齢
    function StringAge($intAge) {
        switch($intAge)
        {
        case 1: $strString = '20代'; break;
        case 2: $strString = '30代'; break;
        case 3: $strString = '40代'; break;
        case 4: $strString = '50代'; break;
        case 5: $strString = '60歳以上'; break;
        }
        return $strString;
    }
    
    //チェック
    function StringCheckFlag($intValue) {
        switch($intValue)
        {
        case 1: $strString = '○'; break;
        }
        return $strString;
    }
    
    //はい、いいえ
    function StringYesNo($intValue) {
        switch($intValue)
        {
        case 0: $strString = 'いいえ'; break;
        case 1: $strString = 'はい'; break;
        }
        return $strString;
    }
}
?>
