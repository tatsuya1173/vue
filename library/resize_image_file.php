<?php
//■画像ファイルリサイズ処理
function ResizeImageFile($strSrcPath, $strDestPath, $intDestSize, $intOrienFlag = 0) {
    
    if ($intOrienFlag == 1) {
        $strSrcPathOrien = $strSrcPath.'_orien';
        
        //画像の向きを補正
        orientationFixedImage($strSrcPathOrien, $strSrcPath);
        
        // 画像の形式と縦横サイズを取得
        list($srcWidth, $srcHeight, $type) = getimagesize($strSrcPathOrien);
        
        if ($type == IMAGETYPE_GIF) {
            $srcImage = imagecreatefromgif($strSrcPathOrien);
        } else if ($type == IMAGETYPE_JPEG) {
            $srcImage = imagecreatefromjpeg($strSrcPathOrien);
        } else if ($type == IMAGETYPE_PNG) {
            $srcImage = imagecreatefrompng($strSrcPathOrien);
        }
    } else {
        // 画像の形式と縦横サイズを取得
        list($srcWidth, $srcHeight, $type) = getimagesize($strSrcPath);
        
        if ($type == IMAGETYPE_GIF) {
            $srcImage = imagecreatefromgif($strSrcPath);
        } else if ($type == IMAGETYPE_JPEG) {
            $srcImage = imagecreatefromjpeg($strSrcPath);
        } else if ($type == IMAGETYPE_PNG) {
            $srcImage = imagecreatefrompng($strSrcPath);
        }
    }
    
    if ($srcImage) {
        // 大きい方のサイズをリサイズ対象とする
        if ($srcHeight > $srcWidth) {
            // 指定のサイズより大きい場合にリサイズする
            if ($srcHeight > $intDestSize) {
                // 縦横比を保ちつつリサイズ後のサイズを計算する
                $dstHeight = $intDestSize;
                $dstWidth  = floor($srcWidth * ($intDestSize / $srcHeight));
                $dstWidth  = $dstWidth>0 ? $dstWidth : 1;
            } else {
                // リサイズ不要のため元画像のサイズを格納
                $dstWidth  = $srcWidth;
                $dstHeight = $srcHeight;
            }
        } else {
            // 指定のサイズより大きい場合にリサイズする
            if ($srcWidth > $intDestSize) {
                // 縦横比を保ちつつリサイズ後のサイズを計算する
                $dstWidth  = $intDestSize;
                $dstHeight = floor($srcHeight * ($intDestSize / $srcWidth));
                $dstHeight = $dstHeight>0 ? $dstHeight : 1;
            } else {
                // リサイズ不要のため元画像のサイズを格納
                $dstWidth  = $srcWidth;
                $dstHeight = $srcHeight;
            }
        }
        // リサイズ後の大きさで画像を作成
        $dstImage = imagecreatetruecolor($dstWidth, $dstHeight);
        
        //リサイズ＆再サンプリング処理
        imagecopyresampled($dstImage, $srcImage, 0, 0, 0, 0, $dstWidth, $dstHeight, $srcWidth, $srcHeight);
        
        imagejpeg($dstImage, $strDestPath, 100);
        
        // リソースの解放
        imagedestroy($srcImage);
        imagedestroy($dstImage);
    }
}

//■画像の左右反転
function fImageFlop($image){
    // 画像の幅を取得
    $w = imagesx($image);
    // 画像の高さを取得
    $h = imagesy($image);
    // 変換後の画像の生成（元の画像と同じサイズ）
    $destImage = @imagecreatetruecolor($w,$h);
    // 逆側から色を取得
    for($i=($w-1);$i>=0;$i--){
        for($j=0;$j<$h;$j++){
            $color_index = imagecolorat($image,$i,$j);
            $colors = imagecolorsforindex($image,$color_index);
            imagesetpixel($destImage,abs($i-$w+1),$j,imagecolorallocate($destImage,$colors["red"],$colors["green"],$colors["blue"]));
        }
    }
    return $destImage;
}

//■上下反転
function fImageFlip($image){
    // 画像の幅を取得
    $w = imagesx($image);
    // 画像の高さを取得
    $h = imagesy($image);
    // 変換後の画像の生成（元の画像と同じサイズ）
    $destImage = @imagecreatetruecolor($w,$h);
    // 逆側から色を取得
    for($i=0;$i<$w;$i++){
        for($j=($h-1);$j>=0;$j--){
            $color_index = imagecolorat($image,$i,$j);
            $colors = imagecolorsforindex($image,$color_index);
            imagesetpixel($destImage,$i,abs($j-$h+1),imagecolorallocate($destImage,$colors["red"],$colors["green"],$colors["blue"]));
        }
    }
    return $destImage;
}

//■画像を回転
function fImageRotate($image, $angle, $bgd_color){
     return imagerotate($image, $angle, $bgd_color, 0);
}

//■画像の方向を正す
function orientationFixedImage($output,$input){
    $image = ImageCreateFromJPEG($input);
    $exif_datas = @exif_read_data($input);
    if (isset($exif_datas['Orientation'])) {
        $orientation = $exif_datas['Orientation'];
        if ($image) {
            // 未定義
            if ($orientation == 0) {
            // 通常
            } else if($orientation == 1) {
            // 左右反転
            } else if($orientation == 2) {
                fImageFlop($image);
            // 180°回転
            } else if($orientation == 3) {
                fImageRotate($image,180, 0);
            // 上下反転
            } else if($orientation == 4) {
                fImageFlip($image);
            // 反時計回りに90°回転 上下反転
            } else if($orientation == 5) {
                fImageRotate($image,270, 0);
                fImageFlip($image);
            // 時計回りに90°回転
            } else if($orientation == 6) {
                fImageRotate($image,90, 0);
            // 時計回りに90°回転 上下反転
            } else if($orientation == 7) {
                fImageRotate($image,90, 0);
                fImageFlip($image);
            // 反時計回りに90°回転
            } else if($orientation == 8) {
                fImageRotate($image,270, 0);
            }
        }
    }
    // 画像の書き出し
    ImageJPEG($image ,$output);
    return false;
}
?>
