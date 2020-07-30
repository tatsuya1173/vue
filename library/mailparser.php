<?php
/**
* メール添付画像の取得
* @param object $parts MIMEパート
* @return array 画像データ
*/
function getImage($parts)
{
    //画像データ格納用
    $images = array();
    
    foreach ($parts as $part) {
        //タイプ判別
        if (isset($part->disposition) &&
            strtolower($part->disposition) == "attachment") {
            //添付ファイル取得
            $images[] = array(
                'type' => strtolower(sprintf("%s/%s", $part->ctype_primary, $part->ctype_secondary)),
                'name' => $part->ctype_parameters['name'],
                'body' => $part->body,
            );
        } else {
            switch (strtolower($part->ctype_primary)) {
            case "image":
                $images[] = array(
                    'type' => strtolower(sprintf("%s/%s", $part->ctype_primary, $part->ctype_secondary)),
                    'name' => $part->ctype_parameters['name'],
                    'body' => $part->body,
                );
                break;
            //マルチパートの場合は再起処理
            case "multipart":
                $images = array_merge($images, getImage($part->parts));
                break;
            //その他の形式は無視する
            default:
                break;
            }
        }
    }
    return $images;
}

/**
* 差出人(FROM)の取得
* @param string $from FROM文字列
* @return string 差出人(FROM)
*/
function getFrom($from)
{
    //署名付きの場合の対応
    if (preg_match('/<(.*?)>$/', $from, $match)) {
        $from = $match[1];
    }
    $from = trim($from);
    $from = strtolower($from);
	return $from;
}
?>
