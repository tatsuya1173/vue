<?php
//■ページャー作成
function MakePager($intNowPage, $intTotalPage, $intAllCount, $intBlock, $intRange, $strURL) {
    
    $intStart = ($intNowPage - 1) * $intBlock + 1;
    $intEnd   = $intNowPage * $intBlock;
    
	if ($intEnd > $intAllCount) {
		$intEnd = $intAllCount;
	}
	
    //$strPager = $intStart.' - '.$intEnd.'件 （全'.$intAllCount.'件） ';
    
    if ($intNowPage > 1) {
        $strPager .= "<li><a href=\"".$strURL.($intNowPage - 1)."\">&laquo; 前</a></li>";
    }

    if ($intNowPage <= $intRange) {
        $intWork = $intRange - $intNowPage + 1;
    }
    
    for ($i = $intNowPage - $intRange; ($i <= $intNowPage + $intRange + $intWork) && ($i <= $intTotalPage); $i++) {
        if ($i < 1) continue;
        if ($i == $intNowPage) {
            $strPreTag = "<li><span>";
            $strAftTag = "</span></li>";
        } else {
            $strPreTag = "<li><a href=\"".$strURL.$i."\">";
            $strAftTag = "</a></li>";
        }
        $links .= $strPreTag.$i.$strAftTag;
    }

    $strPager .= $links;
    if ($intNowPage < $intTotalPage) {
        $strPager .= "<li><a href=\"".$strURL.($intNowPage + 1)."\">次 &raquo;</a></li>";
    }
    
    return $strPager;
}

//■ページャー作成(スマートフォン)
function MakePagerSP($intNowPage, $intTotalPage, $intAllCount, $intBlock, $intRange, $strURL) {
    
    $intStart = ($intNowPage - 1) * $intBlock + 1;
    $intEnd   = $intNowPage * $intBlock;
    
	if ($intEnd > $intAllCount) {
		$intEnd = $intAllCount;
	}
	
    //<li><a href="1.html">&laquo; 前</a></li>
    //<li><a href="1.html">110</a></li>
    //<li class="active">1120</li>
    //<li><a href="3.html">1130</a></li>
    //<li><a href="4.html">1140</a></li>
    //<li><a href="10.html">次 &raquo;</a></li>
    
    if ($intNowPage > 1) {
        $strPager .= "<li><a href=\"".$strURL.($intNowPage - 1)."\">&laquo; 前</a></li>";
    }

    if ($intNowPage <= $intRange) {
        //$intWork = $intRange - $intNowPage + 1;
        if ($intNowPage == 1) {
            $intWork = $intRange - $intNowPage + 2;
        } else {
            $intWork = $intRange - $intNowPage + 1;
        }
    }
    
    for ($i = $intNowPage - $intRange; ($i <= $intNowPage + $intRange + $intWork) && ($i <= $intTotalPage); $i++) {
        if ($i < 1) continue;
        if ($i == $intNowPage) {
            $strPreTag = "<li class=\"active\">";
            $strAftTag = "</li>";
        } else {
            $strPreTag = "<li><a href=\"".$strURL.$i."\">";
            $strAftTag = "</a></li>";
        }
        $links .= $strPreTag.$i.$strAftTag;
    }

    $strPager .= $links;
    if ($intNowPage < $intTotalPage) {
        $strPager .= "<li><a href=\"".$strURL.($intNowPage + 1)."\">次 &raquo;</a></li>";
    }
    
    return $strPager;
}

//■ページャー作成(管理画面用)
function MakePagerManager($intNowPage, $intTotalPage, $intAllCount, $intBlock, $intRange, $strURL) {
    
    $intStart = ($intNowPage - 1) * $intBlock + 1;
    $intEnd   = $intNowPage * $intBlock;
    
	if ($intEnd > $intAllCount) {
		$intEnd = $intAllCount;
	}
	
    $strPager = $intStart.' - '.$intEnd.'件 （全'.$intAllCount.'件） ';
    
    if ($intNowPage > 1) {
        $strPager .= "<a href=\"".$strURL.($intNowPage - 1)."\">&lt; 前の".$intBlock."件</a> | ";
    }

    if ($intNowPage <= $intRange) {
        $intWork = $intRange - $intNowPage + 1;
    }
    
    for ($i = $intNowPage - $intRange; ($i <= $intNowPage + $intRange + $intWork) && ($i <= $intTotalPage); $i++) {
        if ($i < 1) continue;
        if ($i == $intNowPage) {
            $strPreTag = "";
            $strAftTag = " | ";
        } else {
            $strPreTag = "<a href=\"".$strURL.$i."\">";
            $strAftTag = "</a> | ";
        }
        $links .= $strPreTag.$i.$strAftTag;
    }

    $strPager .= $links;
    if ($intNowPage < $intTotalPage) {
        $strPager .= " <a href=\"".$strURL.($intNowPage + 1)."\">次の".$intBlock."件 &gt;</a> ";
    }
    
    return $strPager;
}
?>
