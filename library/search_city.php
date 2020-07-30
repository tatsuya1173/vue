<?php
function SearchCity($intAllCityID) {
    global $aryAllCityID, $aryAllCityName;
    
    $intIndex = array_search($intAllCityID, $aryAllCityID);
    
    if ($intIndex !== FALSE) {
        return $aryAllCityName[$intIndex];
    }
}
?>
