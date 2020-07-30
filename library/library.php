<?php
//デバッグ専用
function DebugPrint($a)
{
	print '<pre>';
	print_r($a);
	print '</pre>';
}

class CModelUser
{
    function __construct()
    {
        return true;
    }
    function __destruct()
    {
        return true;
    }
    
    //-------↓処理系↓--------
    //共通前処理
    function PreProcess()
    {
        //セッションID
        $strSessionID = session_id();
        
        //アクセススタンプ
        CFile::AccessStamp($strSessionID);
        return true;
    }
}

//〓〓〓〓CLASS::DB操作〓〓〓〓//
class CDB
{
	function __construct()
	{
		return true;
	}
	function __destruct()
	{
		return true;
	}
	
	//--日本語文字列加工：DB挿入時
	function MbConvertDBIn($pszString)
	{
		return mb_convert_encoding($pszString, 'UTF-8', 'SJIS');
	}
	
	//--日本語文字列加工：DB出力時
	function MbConvertDBOut($pszString)
	{
		return mb_convert_encoding($pszString, 'SJIS', 'UTF-8');
	}
	
    //日本語文字列加工：EXCEL出力時
    function MbConvertEXCELOut($pszString)
    {
        return mb_convert_encoding($pszString, 'sjis-win', 'UTF-8');
        return $pszString;
    }
	
	//--DB挿入前加工：文字列全般に使用
	function ConvertDBIn($pszString)
	{
		return '"'.addslashes($pszString).'"';
	}

	//--MySQL接続
	//$g_xxxは外部から読み込み　通常はconfig.phpに記載
	function DBConnect()
	{
        global $g_DBName, $g_DBHost, $g_DBUser, $g_DBPW;

        //MySQL 接続
        $resConnect = mysqli_connect($g_DBHost, $g_DBUser, $g_DBPW, $g_DBName);
        if (mysqli_connect_errno() > 0) {
            die('DBが存在しません。');
        }
        //★ローカルサーバーで何故か文字化けしてしまうので下記を実行。本番移行時は削除する予定
        mysqli_query($resConnect, "SET NAMES UTF8;");
        mysqli_query($resConnect, "SET GLOBAL sql_mode='', SESSION sql_mode='';");
        
        return $resConnect;
	}
    
    function DBClose() {
        global $resConnect;
        mysqli_close($resConnect);
    }
	
	//トランザクション開始
    function BeginTran() {
        global $resConnect;
        
        $resResult = mysqli_query($resConnect, "begin");
        if (!$result){
            return false;
        }else{
            return true;
        }
    }
	
	//トランザクションコミット
    function CommitTran() {
        global $resConnect;
        
        $resResult = mysqli_query($resConnect, "commit");
        if (!$result){
            return false;
        }else{
            return true;
        }
    }
	
	//トランザクションロールバック
    function RollbackTran() {
        global $resConnect;
        
        $resResult = mysqli_query($resConnect, "rollback");
        if (!$result){
            return false;
        }else{
            return true;
        }
    }
	
	//SQL実行
    function ExecSQL($strSQL) {
        global $resConnect;
        
        $resResult = mysqli_query($resConnect, $strSQL);
        if (!$resResult){
            return false;
        }else{
            return true;
        }
    }
    
	//--アクセス履歴　※アクセス履歴を残したいページで読み込むこと
	//$pszUnique　：使い方は自由
	function AccessStamp($pszUnique = '')
	{
		//データを取得
		$aData = array(
		'ip'           => strval($_SERVER['REMOTE_ADDR']),        //IPアドレス
		'domain'       => gethostbyaddr($_SERVER['REMOTE_ADDR']), //ホスト
		'user_agent'   => $_SERVER['HTTP_USER_AGENT'],            //ユーザーエージェント
		'self'         => $_SERVER['PHP_SELF'],                   //閲覧ページ
		'query_string' => $_SERVER['QUERY_STRING'],               //GET文字列
		'referer'      => $_SERVER['HTTP_REFERER'],               //リファラー
		'time'         => date('Y-m-d H:i:s')
		);
		
		//挿入値準備
		$aVal   = array();
		$aVal[] = array('ip',            CDB::ConvertDBIn($aData['ip']));
		$aVal[] = array('domain',        CDB::ConvertDBIn($aData['domain']));
		$aVal[] = array('user_agent',    CDB::ConvertDBIn($aData['user_agent']));
		$aVal[] = array('self',          CDB::ConvertDBIn($aData['self'].(CString::IsNullString($aData['query_string'])? '': '?'.$aData['query_string'])));
		$aVal[] = array('referer',       CDB::ConvertDBIn($aData['referer']));
		$aVal[] = array('career',        CDB::ConvertDBIn(CModel::MPCareer()));
		$aVal[] = array('time',          CDB::ConvertDBIn($aData['time']));
		$aVal[] = array('unique_string', CDB::ConvertDBIn($pszUnique));

		//実行
		CDB::Insert('t_access', $aVal);
		
		return true;
	}

	//--単純総数
	//$aKey[] = array('[テーブルカラム名]', [挿入値]);　条件指定
	//$pszOption　他条件クエリ
	function GetSum($pszTable, $aKey=array(), $pszOption = '')
	{
		//検索条件
		$iKey = count($aKey);
		if(0 < $iKey)
		{
			for($i = 0, $pszWhere = 'WHERE '; $i < $iKey; $i++) $pszWhere .= $aKey[$i][0].' = '.$aKey[$i][1].(($i == ($iKey - 1))? ' ': ' AND ');
		}
		else
		{
			$pszWhere = '';
		}
		
		//クエリ
		$pszQuery  = 'SELECT * FROM '.$pszTable.' '.$pszWhere.' '.$pszOption;
		$resResult = mysql_query($pszQuery) or die('DB error function:'.__FUNCTION__.' - 1 - '.$pszTable);
		return mysql_num_rows($resResult);
	}
	
	//--単純インサート
	//$aVal[] = array('[テーブルカラム名]', [挿入値]);
	function Insert($pszTable, $aVal)
	{
		//パラメータ準備
		$iNum = count($aVal);
		for($i = 0, $pszSub = ''; $i < $iNum; $i++) {
			//$pszSub .= $aVal[$i][0].' = '.$aVal[$i][1].(($i == ($iNum - 1))? ' ': ', ');
			$pszSub .= $aVal[$i][0].' = '."'".$aVal[$i][1]."'".(($i == ($iNum - 1))? ' ': ', ');
		}
		//クエリ
		$pszQuery  = 'INSERT INTO '.$pszTable.' SET '.$pszSub;
		$resResult = mysql_query($pszQuery) or die('DB error function:'.__FUNCTION__.' - 1 - '.$pszTable);
        $intLastID = mysql_insert_id(); //AutoIncrementで登録されたIDを取得
        return $intLastID;
	}
	
    //インサートのSQL文を取得
    function GetInsertSQL($resConnect, $pszTable, $aVal)
    {
        //パラメータ準備
        $iNum = count($aVal);
        for($i = 0, $pszSub = ''; $i < $iNum; $i++) {
            $pszSub .= $aVal[$i][0].' = '."'".mysqli_escape_string($resConnect, $aVal[$i][1])."'".(($i == ($iNum - 1))? ' ': ', ');
        }
        //クエリ
        $pszQuery = 'INSERT INTO '.$pszTable.' SET '.$pszSub;
        return $pszQuery;
    }

	//--単純アップデート
	//$aVal[] = array('[テーブルカラム名]', [挿入値]);　更新内容
	//$aKey[] = array('[テーブルカラム名]', [挿入値]);　条件指定
	//$pszOption　他条件クエリ
	function Update($pszTable, $aVal, $aKey, $pszOption = '')
	{
		//パラメータ準備
		$iNum = count($aVal);
		for($i = 0, $pszSub   = ''; $i < $iNum; $i++) $pszSub   .= $aVal[$i][0].' = '."'".$aVal[$i][1]."'".(($i == ($iNum - 1))? ' ': ', ');
		$iKey = count($aKey);
		for($i = 0, $pszWhere = ''; $i < $iKey; $i++) $pszWhere .= $aKey[$i][0].' = '."'".$aKey[$i][1]."'".(($i == ($iKey - 1))? ' ': ' AND ');
		
		//クエリ
		$pszQuery  = 'UPDATE '.$pszTable.' SET '.$pszSub.' WHERE '.$pszWhere.' '.$pszOption;
		//echo $pszQuery;
		$resResult = mysql_query($pszQuery) or die('DB error function:'.__FUNCTION__.' - 1 - '.$pszTable);

		return true;
	}

    //--アップデートのSQL文を取得
    //$aVal[] = array('[テーブルカラム名]', [挿入値]);　更新内容
    //$aKey[] = array('[テーブルカラム名]', [挿入値]);　条件指定
    //$pszOption　他条件クエリ
    function GetUpdateSQL($pszTable, $aVal, $aKey, $pszOption = '')
    {
        //パラメータ準備
        $iNum = count($aVal);
        for($i = 0, $pszSub   = ''; $i < $iNum; $i++) $pszSub   .= $aVal[$i][0].' = '."'".$aVal[$i][1]."'".(($i == ($iNum - 1))? ' ': ', ');
        $iKey = count($aKey);
        for($i = 0, $pszWhere = ''; $i < $iKey; $i++) $pszWhere .= $aKey[$i][0].' = '."'".$aKey[$i][1]."'".(($i == ($iKey - 1))? ' ': ' AND ');
        
        //クエリ
        $pszQuery  = 'UPDATE '.$pszTable.' SET '.$pszSub.' WHERE '.$pszWhere.' '.$pszOption;
		
        return $pszQuery;
    }

    //--デリートのSQL文を取得
    //$aKey[] = array('[テーブルカラム名]', [挿入値]);　条件指定
    //$pszOption　他条件クエリ
    function GetDeleteSQL($pszTable, $aKey, $pszOption = '')
    {
        //パラメータ準備
        $iKey = count($aKey);
        for($i = 0, $pszWhere = ''; $i < $iKey; $i++) $pszWhere .= $aKey[$i][0].' = '."'".$aKey[$i][1]."'".(($i == ($iKey - 1))? ' ': ' AND ');
        
        //クエリ
        $pszQuery  = 'DELETE FROM '.$pszTable.' WHERE '.$pszWhere.' '.$pszOption;
		
        return $pszQuery;
    }
	
	//--DB削除：リストパターン　※主に管理画面でチェックボックスと組み合わせて使用する
	//$aList　リスト配列　　例)array(1, 2, 3, 4) ←削除のIDリストになっている
	function UpdateDelete($aList, $pszTable, $pszKey, $iMasterID = 0, $pszExtra = '')
	{
		//挿入値準備
		$aVal   = array();
		$aVal[] = array('delete_flag', 1);
		$aVal[] = array('update_date', CDB::ConvertDBIn(date('Y-m-d H:i:s')));
		if($iMasterID != 0) $aVal[] = array('master_id',   intval($iMasterID));

		//パラメータ準備
		$valTable      = addslashes($pszTable);
		$valKey        = addslashes($pszKey);
		$valList       = array_values($aList);
		$pszInList     = implode(',', $valList);
		$iNum          = count($aVal);
		for($i = 0, $pszSub = ''; $i < $iNum; $i++) $pszSub .= $aVal[$i][0].' = '.$aVal[$i][1].(($i == ($iNum - 1))? ' ': ', ');

		//クエリ
		$pszQuery  = 'UPDATE '.$valTable.' SET '.$pszSub.' WHERE '.$valKey.' IN ('.$pszInList.')'.(CString::IsNullString($pszExtra)? '': ' '.$pszExtra);
		$resResult = mysql_query($pszQuery) or die('DB error function:'.__FUNCTION__.' - 1 - '.$pszTable);

		return true;
	}
	
	//--単純セレクト　※単体キー検索のみ
	//$pszKey　　：テーブルカラム名　もしくは-＞テーブルカラム名　+　キー値（配列）
	//$valValue　：キー値
	function Select($pszTable, $pszKey, $valValue = NULL)
	{
		//20091112 変更：中村(拡張)
		//配列かどうかで処理を分ける
		if (is_array($pszKey)) {
			$iNum = count($pszKey);
			for($i = 0, $pszSub   = ''; $i < $iNum; $i++) $pszSub   .= $pszKey[$i][0]." = '".$pszKey[$i][1]."'".(($i == ($iNum - 1))? ' ': ' AND ');
			//クエリ
			$pszQuery  = 'SELECT * FROM '.$pszTable.' WHERE '.$pszSub.' LIMIT 1';
			//sqldebug
			//echo $pszQuery;
			$resResult = mysql_query($pszQuery) or die('DB error function:'.__FUNCTION__.' - 1 - '.$pszTable);
			$pszKey      = mysql_fetch_assoc($resResult);
	
			//抽出
			//基本情報
			$aInfo = $pszKey;
			//echo "<br>[".count($aVal)."]<br>";
			//var_dump($aVal);
			//加工 0より大きいか？　20091102変更
			//$aInfo['bool_exist'] = 0 < count($aVal)? 1:0;
		}else{
			//初期化
			$aInitialized = CDB::SelectInitialized($pszTable);

			//クエリ
			$pszQuery  = 'SELECT * FROM '.$pszTable.' WHERE '.$pszKey.' = '.$valValue.' LIMIT 1';
			$resResult = mysql_query($pszQuery) or die('DB error function:'.__FUNCTION__.' - 1 - '.$pszTable);
			$aVal      = mysql_fetch_assoc($resResult);
	
			//抽出
			if(!empty($aVal))
			{
				$aInfo = $aVal;
				$aInfo['bool_exist'] = true;
			}
			else
			{
				$aInfo = $aInitialized;
				$aInfo['bool_exist'] = false;			
			}
		}
		return $aInfo;
	}

	//--単純セレクト　※複数キー検索
	//$aKey[] = array('[テーブルカラム名]', [挿入値]);　条件指定
	//$pszKey　　：存在判定用キー値
	function SelectComplex($pszTable, $aKey, $pszKey, $pszOption = ' LIMIT 1')
	{
		//初期化
		$aInitialized = CDB::SelectInitialized($pszTable);

		//検索条件
		$iKey = count($aKey);
		for($i = 0, $pszWhere = ''; $i < $iKey; $i++) $pszWhere .= $aKey[$i][0].' = '.$aKey[$i][1].(($i == ($iKey - 1))? ' ': ' AND ');
		
		//クエリ
		$pszQuery  = 'SELECT * FROM '.$pszTable.' WHERE '.$pszWhere.' '.$pszOption;
		$resResult = mysql_query($pszQuery) or die('DB error function:'.__FUNCTION__.' - 1 - '.$pszTable);
		$aVal      = mysql_fetch_assoc($resResult);

		//抽出
		if(!empty($aVal))
		{
			$aInfo = $aVal;
			$aInfo['bool_exist'] = true;
		}
		else
		{
			$aInfo = $aInitialized;
			$aInfo['bool_exist'] = false;			
		}
/*		//抽出
		//基本情報
		$aInfo = $aVal;
		//加工
		$aInfo['bool_exist'] = 0 < $aVal[$pszKey];
*/
		return $aInfo;
	}
	
	//--セレクト　初期化用
	function SelectInitialized($pszTable)
	{
		//クエリ
		$pszQuery  = 'SHOW columns FROM '.$pszTable;
		$resResult = mysql_query($pszQuery) or die('DB error function:'.__FUNCTION__.' - 1 - '.$pszTable);
		//抽出
		for($i = 0, $aInfo = array(); $aVal = mysql_fetch_assoc($resResult); $i++) $aInfo[$aVal['Field']] = '';
		return $aInfo;
	}
}



//〓〓〓〓CLASS::時間〓〓〓〓//
class CTime
{
	function __construct()
	{
		return true;
	}
	function __destruct()
	{
		return true;
	}
	
	//--年、月、日、時間、分、秒をdate型にする
	function ChangeDate1($iYear, $iMonth, $iDay)
	{
		return sprintf('%04d-%02d-%02d', $iYear, $iMonth, $iDay);
	}
	function ChangeDate2($iYear, $iMonth, $iDay, $iHour = 0, $iMinute = 0, $iSecond = 0)
	{
		return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $iYear, $iMonth, $iDay, $iHour, $iMinute, $iSecond);
	}
	//--date型からY, m, dを抽出(datetime型も可)　※H,i,sは1970/1/1-2038/1/18のみ有効
	function ChangeDate3($pszDate, $pszKey)
	{
		switch($pszKey)
		{
			case 'Y': $iValue = substr($pszDate, 0, 4); break;
			case 'm': $iValue = substr($pszDate, 5, 2); break;
			case 'd': $iValue = substr($pszDate, 8, 2); break;
			case 'H': $iValue = date('H', strtotime($pszDate)); break;
			case 'i': $iValue = date('i', strtotime($pszDate)); break;
			case 's': $iValue = date('s', strtotime($pszDate)); break;
			default : $iValue = 0;
		}
		return $iValue;
	}
	//--date型からY年m月d日に変換
	function ChangeDate4($pszDate)
	{
		return ($pszDate == '0000-00-00')? '----': CTime::ChangeDate3($pszDate, 'Y').'年'.intval(CTime::ChangeDate3($pszDate, 'm')).'月'.intval(CTime::ChangeDate3($pszDate, 'd')).'日';
	}
	//--datetime型からY年m月d日 H:iに変換　　※H,i,sは1970/1/1-2038/1/18のみ有効
	function ChangeDate5($pszDatetime)
	{
		return ($pszDatetime == '0000-00-00 00:00:00')? '----': CTime::ChangeDate3($pszDatetime, 'Y').'年'.intval(CTime::ChangeDate3($pszDatetime, 'm')).'月'.intval(CTime::ChangeDate3($pszDatetime, 'd')).'日 '.intval(CTime::ChangeDate3($pszDatetime, 'H')).':'.CTime::ChangeDate3($pszDatetime, 'i');
	}
	//--date型からm/dに変換
	function ChangeDate6($pszDate)
	{
		return ($pszDate == '0000-00-00')? '00/00': intval(CTime::ChangeDate3($pszDate, 'm')).'/'.intval(CTime::ChangeDate3($pszDate, 'd'));
	}
	//--datetime型からdate型に変換
	function ChangeDate7($pszDatetime)
	{
		$iYear   = CTime::ChangeDate3($pszDatetime, 'Y');
		$iMonth  = CTime::ChangeDate3($pszDatetime, 'm');
		$iDay    = CTime::ChangeDate3($pszDatetime, 'd');
		
		return !checkdate($iMonth, $iDay, $iYear)? '0000-00-00': CTime::ChangeDate1($iYear, $iMonth, $iDay);
	}
	//--time型からH,i,sを抽出
	function ChangeDate8($pszTime, $pszKey)
	{
		switch($pszKey)
		{
			case 'H': $iValue = substr($pszTime, 0, 2); break;
			case 'i': $iValue = substr($pszTime, 3, 2); break;
			case 's': $iValue = substr($pszTime, 6, 2); break;
			default : $iValue = 0;
		}
		return $iValue;
	}
	//--日付調整(1970/1/1-2038/1/18のみ有効)
	function AdjustDate($iYear, $iMonth, $iDay, $iPlusY = 0, $iPlusM = 0, $iPlusD = 0)
	{
		$pszDate = date('Y-m-d'); //初期化
		if(checkdate($iMonth, $iDay, $iYear)) $pszDate = date('Y-m-d', mktime(0, 0, 0, $iMonth + $iPlusM, $iDay + $iPlusD, $iYear + $iPlusY));
		return $pszDate;
	}
	function AdjustDate2($pszDate, $iPlusY = 0, $iPlusM = 0, $iPlusD = 0)
	{
		$iYear   = CTime::ChangeDate3($pszDate, 'Y');
		$iMonth  = CTime::ChangeDate3($pszDate, 'm');
		$iDay    = CTime::ChangeDate3($pszDate, 'd');
		if(checkdate($iMonth, $iDay, $iYear)) $pszDate = date('Y-m-d', mktime(0, 0, 0, $iMonth + $iPlusM, $iDay + $iPlusD, $iYear + $iPlusY));
		return $pszDate;
	}
	//--時間調整(1970/1/1-2038/1/18のみ有効)
	function AdjustTime($pszDate, $iHour, $iMinutes, $iPlusH = 0, $iPlusI = 0)
	{
		$pszTime = date('Y-m-d H:i:s', strtotime($pszDate)); //初期化
		$iYear   = CTime::ChangeDate3($pszDate, 'Y');
		$iMonth  = CTime::ChangeDate3($pszDate, 'm');
		$iDay    = CTime::ChangeDate3($pszDate, 'd');
		if(checkdate($iMonth, $iDay, $iYear)) $pszTime = date('Y-m-d H:i:s', mktime($iHour + $iPlusH, $iMinutes + $iPlusI, 0, $iMonth, $iDay, $iYear));
		return $pszTime;
	}
	function AdjustTime2($pszTime, $iPlusH = 0, $iPlusI = 0)
	{
		$iHour    = substr($pszTime, 0, 2);
		$iMinutes = substr($pszTime, 3, 2);
		$pszTime  = date('H:i', mktime($iHour + $iPlusH, $iMinutes + $iPlusI, 0, date('m'), date('d'), date('Y')));
		return $pszTime;
	}
	
	//--日付情報
	function DateInfo($pszDate)
	{
		$iDateY  = CTime::ChangeDate3($pszDate, 'Y');
		$iDateM  = CTime::ChangeDate3($pszDate, 'm');
		$iDateD  = CTime::ChangeDate3($pszDate, 'd');
		if(!checkdate($iDateM, $iDateD, $iDateY))
		{
			$pszDate = date('Y-m-d');
			$iDateY  = CTime::ChangeDate3($pszDate, 'Y');
			$iDateM  = CTime::ChangeDate3($pszDate, 'm');
			$iDateD  = CTime::ChangeDate3($pszDate, 'd');
		}
		
		return array('date' => $pszDate, 'Y' => $iDateY, 'm' => $iDateM, 'd' => $iDateD,
		'former_date'  => CTime::AdjustDate($iDateY, $iDateM, $iDateD, 0, 0,  -1),
		'next_date'    => CTime::AdjustDate($iDateY, $iDateM, $iDateD, 0, 0,  1),
		'former_month' => CTime::AdjustDate($iDateY, $iDateM, $iDateD, 0, -1, 0),
		'next_month'   => CTime::AdjustDate($iDateY, $iDateM, $iDateD, 0, 1,  0));
	}
	
	//--曜日日本語文字列
	function StringWeekDay($pszDate)
	{
		switch(intval(date('w', strtotime($pszDate))))
		{
			case 0 : $pszWeekDay = '日'; break;
			case 1 : $pszWeekDay = '月'; break;
			case 2 : $pszWeekDay = '火'; break;
			case 3 : $pszWeekDay = '水'; break;
			case 4 : $pszWeekDay = '木'; break;
			case 5 : $pszWeekDay = '金'; break;
			case 6 : $pszWeekDay = '土'; break;
			default: $pszWeekDay = '不明';
		}
		return $pszWeekDay;
	}
	
	//引数の番号から曜日を取得する
	function GetWeekDay($intNumber)
	{
		switch($intNumber)
		{
			case 0 : $strRet = '日'; break;
			case 1 : $strRet = '月'; break;
			case 2 : $strRet = '火'; break;
			case 3 : $strRet = '水'; break;
			case 4 : $strRet = '木'; break;
			case 5 : $strRet = '金'; break;
			case 6 : $strRet = '土'; break;
			default: $strRet = '不明';
		}
		return $strRet;
	}
	
	//8ケタの日付から年月日＋曜日を取得する
	function GetFormatDateFrom8Number($strDate) {
        $strYear = substr($strDate, 0, 4);
        $strMonth = substr($strDate, 4, 2);
        $strDay = substr($strDate, 6, 2);
        $strRet = $strYear.'年'.$strMonth.'月'.$strDay.'日('.CTime::StringWeekDay($strYear.'-'.$strMonth.'-'.$strDay).')';
        return $strRet;
	}
	
	//8ケタの日付からYYYY/MM/DDを取得する
	function GetSlashDateFrom8Number($strDate) {
        $strYear = substr($strDate, 0, 4);
        $strMonth = substr($strDate, 4, 2);
        $strDay = substr($strDate, 6, 2);
        $strRet = $strYear.'/'.$strMonth.'/'.$strDay;
        return $strRet;
	}
	
    function GetGengo($intYearParam)
    {
        $intYear = intval($intYearParam);
        $strWork = "";
        $strGengo = "";
        $intYearResult = "";
        
        if (1869 <= $intYear && $intYear < 1912) {
            $strGengo = "明治";
            $intYearResult = $intYear - 1869 + 1;
            if ($intYear == 1869){
                $strWork = "元";
            }
        } else if (1912 <= $intYear && $intYear < 1926) {
            $strGengo = "大正";
            $intYearResult = $intYear - 1912 + 1;
            if ($intYear == 1912){
                $strWork = "元";
            }
        } else if (1926 <= $intYear && $intYear < 1989) {
            $strGengo = "昭和";
            $intYearResult = $intYear - 1926 + 1;
            if ($intYear == 1926){
                $strWork = "元";
            }
        } else if (1989 <= $intYear) {
            $strGengo = "平成";
            $intYearResult = $intYear - 1989 + 1;
            if ($intYear == 1989){
                $strWork = "元";
            }
        }
        
        if (!$strWork){
            $strWork = sprintf('%s%s', $strGengo , strval($intYearResult));
        }
        
        return array(
            'gengo' => $strGengo,
            'str'   => $strWork,
            'year'  => $intYearResult,
            );
    }
    
    function CheckLoginLimit($strLoginDateTime) {
        global $g_intLoginLimitHour, $g_intLogoutHour;
        if (strlen($strLoginDateTime) == 0) {
            return false;
        }
        
        $strLoginYMD = date('Ymd', strtotime($strLoginDateTime));
        $strNowYMD = date('Ymd');
        //前回ログインから日付が変わっている場合
        if ($strNowYMD != $strLoginYMD) {
            return false;
        }
        
        $intNowHour = date('H');
        $intLoginHour = date('H', strtotime($strLoginDateTime));
        
        //定時を過ぎている場合 AND 定時前にログインした場合
        if ($g_intLogoutHour <= $intNowHour && $intLoginHour < $g_intLogoutHour) {
            return false;
        }
        
        //$intLoginTimeStamp = strtotime($strLoginDateTime);
        //$intNowTimeStamp = time();
        //$intDifference = $intNowTimeStamp - $intLoginTimeStamp;
        //
        //$intDifferenceHour = $intDifferenceHour / (60 * 60);
        //if ($intDifferenceHour >= $g_intLoginLimitHour) {
        //    return false;
        //}
        
        //Login OK
        return true;
        //return false;
    }
}



//〓〓〓〓CLASS::文字列〓〓〓〓//
class CString
{
	function __construct()
	{
		return true;
	}
	function __destruct()
	{
		return true;
	}
	
	//--警告メッセージ
	function FontWarning($pszString, $bMP = false)
	{
		return '<p class="error-comment">'.$pszString.'</p>';
	}
	
	//--管理画面用警告メッセージ
	function FontWarningMng($pszString)
	{
		return '<p class="error-comment">'.$pszString.'</p>';
	}
	
	//--エラーメッセージ(1)：通常文字列評価
	//$pszString：入力文字列
	//$pszName：項目名
	//$iLimit：入力文字数上限値(半角byte値)
	//$bPermitNull：true-空文字を許す/false-空文字を許さない
	function ErrorMessage1($pszString, $pszName, $iLimit, $bPermitNull = true, $bMP = false)
	{
		$pszError = '';
		$iString  = strlen($pszString);
	
		//if(!$bPermitNull && ($iString == 0)) $pszError .= CString::FontWarning($pszName.'は必ず入力してください', $bMP)."\n";
		//if($iString > $iLimit)               $pszError .= CString::FontWarning($pszName.'は全角'.($iLimit/2).'文字以内にしてください', $bMP)."\n";
		if(!$bPermitNull && ($iString == 0)) $pszError .= '<br>'.$pszName.'は必ず入力してください';
		if($iString > $iLimit)               $pszError .= '<br>'.$pszName.'は全角'.($iLimit/2).'文字以内にしてください';
		return $pszError;
	}
	//--エラーメッセージ(2)：日付評価
	//$bPermitDefault：true-デフォルト日付'0000-00-00'を許す
	function ErrorMessage2($pszName, $iYear, $iMonth, $iDay, $bPermitDefault = true, $bMP = false)
	{
		$pszError       = '';
		if($bPermitDefault)
		{
			//'0000-00-00'は許す
			//if(!checkdate($iMonth, $iDay, $iYear)) $pszError .= CString::FontWarning($pszName.'の日付が不正です', $bMP)."\n";
			if(!checkdate($iMonth, $iDay, $iYear)) $pszError .= $pszName.'の日付が不正です';
		}
		else
		{
			//'0000-00-00'も許さない
			//if(!(CTime::ChangeDate1($iYear, $iMonth, $iDay) == '0000-00-00') && !checkdate($iMonth, $iDay, $iYear)) $pszError .= CString::FontWarning($pszName.'の日付が不正です', $bMP)."\n";
			if(!(CTime::ChangeDate1($iYear, $iMonth, $iDay) == '0000-00-00') && !checkdate($iMonth, $iDay, $iYear)) $pszError .= $pszName.'の日付が不正です';
		}
		
		return $pszError;
	}
	//--エラーメッセージ(3)：半角文字列評価
	function ErrorMessage3($pszString, $pszName, $iLimit, $bPermitNull = true, $bMP = false)
	{
		$pszError  = '';
		$iString   = strlen($pszString);
		$iMbString = mb_strlen($pszString, 'SJIS');
		
		if($iString != $iMbString)           $pszError .= CString::FontWarning($pszName.'は半角文字で入力してください', $bMP)."\n";
		if(!$bPermitNull && ($iString == 0)) $pszError .= CString::FontWarning($pszName.'は必ず入力してください', $bMP)."\n";
		if($iString > $iLimit)               $pszError .= CString::FontWarning($pszName.'は半角'.$iLimit.'文字以内にしてください', $bMP)."\n";
		return $pszError;
	}
    //エラーメッセージ(3)：半角文字列評価(UTF-8)
    function ErrorMessage3_UTF8($pszString, $pszName, $iLimit, $bPermitNull = true, $bMP = false)
    {
        $pszError  = '';
        $iString   = mb_strlen($pszString, 'UTF-8');   //全角・半角にかかわらず、１文字を１文字としてカウント
        $iMbString = mb_strwidth($pszString, 'UTF-8');

        if($iString != $iMbString)           $pszError .= CString::FontWarning($pszName.'は半角文字で入力してください', $bMP)."\n";
        if(!$bPermitNull && ($iString == 0)) $pszError .= CString::FontWarning($pszName.'は必ず入力してください', $bMP)."\n";
        if($iString > $iLimit)               $pszError .= CString::FontWarning($pszName.'は半角'.$iLimit.'文字以内にしてください', $bMP)."\n";
        return $pszError;
    }
	//--エラーメッセージ(4)：数字文字列評価
	function ErrorMessage4($pszString, $pszName, $iLimit, $bPermitNull = true, $bMP = false)
	{
		$pszError  = '';
		$iString   = strlen($pszString);
		
		if(!$bPermitNull && ($iString == 0))                            $pszError .= CString::FontWarning($pszName.'は必ず入力してください', $bMP)."\n";
		if(!is_numeric($pszString) && CString::IsNullString($pszError)) $pszError .= CString::FontWarning($pszName.'は数字で入力してください', $bMP)."\n";
		if($iString > $iLimit && CString::IsNullString($pszError))      $pszError .= CString::FontWarning($pszName.'は'.$iLimit.'文字以内にしてください', $bMP)."\n";
		return $pszError;
	}
    //エラーメッセージ(4)：数字文字列評価(UTF-8)
    function ErrorMessage4_UTF8($pszString, $pszName, $iLimit, $bPermitNull = true, $bMP = false)
    {
        $pszError  = '';
        $iString   = mb_strlen($pszString, 'UTF-8');   //全角・半角にかかわらず、１文字を１文字としてカウント

        if(!$bPermitNull && ($iString == 0))                            $pszError .= CString::FontWarning($pszName.'は必ず入力してください', $bMP)."\n";
        //if(!is_numeric($pszString) && CString::IsNullString($pszError)) $pszError .= CString::FontWarning($pszName.'は半角数字で入力してください', $bMP)."\n";
        
        if (!preg_match("/^[0-9]+$/", $pszString)) {
            $pszError .= CString::FontWarning($pszName.'は半角数字で入力してください', $bMP)."\n";
        }
        
        if($iString > $iLimit && CString::IsNullString($pszError))      $pszError .= CString::FontWarning($pszName.'は'.$iLimit.'文字以内にしてください', $bMP)."\n";
        return $pszError;
    }
	//--エラーメッセージ(5)：全角文字列評価
	function ErrorMessage5($pszString, $pszName, $iLimit, $bPermitNull = true, $bMP = false)
	{
		$pszError  = '';
		$iString   = strlen($pszString);
		$iMbString = mb_strlen($pszString, 'SJIS');
		
		if($iString != ($iMbString * 2))                                         $pszError .= CString::FontWarning($pszName.'は全角文字で入力してください', $bMP)."\n";
		if(!$bPermitNull && ($iString == 0) && CString::IsNullString($pszError)) $pszError .= CString::FontWarning($pszName.'は必ず入力してください', $bMP)."\n";
		if($iString > ($iLimit * 2) && CString::IsNullString($pszError))         $pszError .= CString::FontWarning($pszName.'は全角'.$iLimit.'文字以内にしてください', $bMP)."\n";
		return $pszError;
	}
    //エラーメッセージ(5)：全角文字列評価(UTF-8)
    function ErrorMessage5_UTF8($pszString, $pszName, $iLimit, $bPermitNull = true, $bMP = false)
    {
    	//改行コードは半角文字としてみなされるので削除
    	$pszString = str_replace(array("\r\n","\r","\n"), '', $pszString);
    	
        $pszError  = '';
        $iString   = mb_strlen($pszString, 'UTF-8');   //全角・半角にかかわらず、１文字を１文字としてカウント
        
        //未入力の場合
        if(!$bPermitNull && ($iString == 0) && CString::IsNullString($pszError)) {
            $pszError .= CString::FontWarning($pszName.'は必ず入力してください', $bMP);
            return $pszError;
        }
        
        for ($i = 0; $i < $iString; $i++) {
            $strGetString = mb_substr($pszString, $i, 1, 'UTF-8');
            $iMbLength = mb_strwidth($strGetString, 'UTF-8'); //全角は半角の２文字換算でカウント
            //全角文字でない場合
            if ($iMbLength != 2) {
                $pszError = CString::FontWarning($pszName.'は全角文字で入力してください', $bMP);
                return $pszError;
                break;
            }
        }
        
        if($iString > $iLimit && CString::IsNullString($pszError)) $pszError = CString::FontWarning($pszName.'は全角'.$iLimit.'文字以内にしてください', $bMP)."\n";
        return $pszError;
    }
    //エラーメッセージ(5)：全角文字列評価(UTF-8) (*)アスタリスクはOK
    function ErrorMessage5_Aster_UTF8($pszString, $pszName, $iLimit, $bPermitNull = true, $bMP = false)
    {
    	//改行コードは半角文字としてみなされるので削除
    	$pszString = str_replace(array("\r\n","\r","\n"), '', $pszString);
    	
        $pszError  = '';
        $iString   = mb_strlen($pszString, 'UTF-8');   //全角・半角にかかわらず、１文字を１文字としてカウント
        
        //未入力の場合
        if(!$bPermitNull && ($iString == 0) && CString::IsNullString($pszError)) {
            $pszError .= CString::FontWarning($pszName.'は必ず入力してください', $bMP);
            return $pszError;
        }
        
        $pszString_Work = str_replace('*', '', $pszString);
        $iString   = mb_strlen($pszString_Work, 'UTF-8');
        
        for ($i = 0; $i < $iString; $i++) {
            $strGetString = mb_substr($pszString_Work, $i, 1, 'UTF-8');
            $iMbLength = mb_strwidth($strGetString, 'UTF-8'); //全角は半角の２文字換算でカウント
            //全角文字でない場合
            if ($iMbLength != 2) {
                $pszError = CString::FontWarning($pszName.'は全角文字で入力してください', $bMP);
                return $pszError;
                break;
            }
        }
        
        if($iString > $iLimit && CString::IsNullString($pszError)) $pszError = CString::FontWarning($pszName.'は全角'.$iLimit.'文字以内にしてください', $bMP)."\n";
        return $pszError;
    }
	//--エラーメッセージ(6)：ラジオボタン評価(1) -- Yes/No
	function ErrorMessage6($pszValue, $pszName, $bMP)
	{
		$pszError  = '';
		if (strlen($pszValue) == 0)
		{
			$pszError = CString::FontWarning($pszName.'を選択して下さい。', $bMP);
		}
		else if (($pszValue != 'yes') && ($pszValue != 'no'))
		{
			$pszError = CString::FontWarning($pszName.'を選択して下さい。', $bMP);
		}
		return $pszError;
	}
	//--エラーメッセージ(7)：日付評価
	//$bPermitDefault：true-デフォルト日付'0000-00-00'を許す
	function ErrorMessage7($pszName, $iYear, $iMonth, $iDay, $iHour, $iMinute = 0, $iSecond = 0, $bPermitDefault = true, $bMP = false)
	{
		$pszError = CString::ErrorMessage2($pszName, $iYear, $iMonth, $iDay, $bPermitDefault, $bMP);
		
		//if(!(0 <= $iHour && $iHour < 24 && 0 <= $iMinute && $iMinute < 60 && 0 <= $iSecond && $iSecond < 60)) $pszError .= CString::FontWarning($pszName.'の時間が不正です', $bMP)."\n";
		if(!(0 <= $iHour && $iHour < 24 && 0 <= $iMinute && $iMinute < 60 && 0 <= $iSecond && $iSecond < 60)) $pszError .= $pszName.'の時間が不正です';
		return $pszError;
	}
	//--エラーメッセージ：郵便番号専用
	function ErrorMessageZip($pszString, $pszName, $iLimit, $bPermitNull = true, $bMP = false)
	{
		$pszError  = CString::ErrorMessage4($pszString, $pszName, $iLimit, $bPermitNull, $bMP);
		$iString   = strlen($pszString);
		
		if(!$bPermitNull && !($iString == $iLimit) && CString::IsNullString($pszError)) $pszError .= CString::FontWarning($pszName.'は数字'.$iLimit.'桁を入力してください', $bMP)."\n";
		return $pszError;
	}
	//--エラーメッセージ：半角FIX文字数専用
	function ErrorMessageFix($pszString, $pszName, $iLimit, $bPermitNull = true, $bMP = false)
	{
		$pszError  = CString::ErrorMessage3($pszString, $pszName, $iLimit, $bPermitNull, $bMP);
		$iString   = strlen($pszString);
		
		//if(!$bPermitNull && !($iString == $iLimit) && CString::IsNullString($pszError)) $pszError .= CString::FontWarning($pszName.'は半角'.$iLimit.'文字を入力してください', $bMP)."\n";
		if(!$bPermitNull && !($iString == $iLimit) && CString::IsNullString($pszError)) $pszError .= $pszName.'は半角'.$iLimit.'文字を入力してください';
		return $pszError;
	}
	//--エラーメッセージ：メールアドレス専用
	function ErrorMessageMailaddress($pszString, $pszName, $iLimit, $bPermitNull = true, $bMP = false)
	{
		$pszError  = CString::ErrorMessage3($pszString, $pszName, $iLimit, $bPermitNull, $bMP);
		
		if(!$bPermitNull && CString::IsNullString($pszError) && !CModel::IsMailAddress($pszString)) $pszError .= CString::FontWarning($pszName.'は[例)sample@sample.com]などの形式で入力してください', $bMP)."\n";
		return $pszError;
	}
	//--エラーメッセージ：全角カタカナチェック
	function ErrorMessageZenkakuKatakana_UTF8($pszString, $pszName, $iLimit, $bPermitNull = true, $bMP = false)
	{
		$pszError  = '';
        $iString   = mb_strlen($pszString, 'UTF-8');   //全角・半角にかかわらず、１文字を１文字としてカウント
		
		if (!$bPermitNull && ($iString == 0) && CString::IsNullString($pszError)) {
			$pszError .= CString::FontWarning($pszName.'は必ず入力してください', $bMP);
			return $pszError;
		}
		
    	//for ($i = 0; $i < $iString; $i++) {
    	//	$strGetString = mb_substr($pszString, $i, 1, 'UTF-8');
    	//	$iMbLength = mb_strwidth($strGetString, 'UTF-8'); //全角は半角の２文字換算でカウント
    	//	//全角文字でない場合
    	//	if ($iMbLength != 2) {
    	//		$pszError = CString::FontWarning($pszName.'は全角文字で入力してください', $bMP);
    	//		break;
    	//	}
    	//}
		
		if($iString > $iLimit && CString::IsNullString($pszError)) $pszError .= CString::FontWarning($pszName.'は全角'.$iLimit.'文字以内にしてください', $bMP);
		
		mb_regex_encoding('UTF-8');
		//カタカナ以外が含まれている場合
		if (!preg_match("/^[ァ-ヶー]+$/u", $pszString)) {
		    $pszError .= CString::FontWarning($pszName.'は全角カタカナで入力してください', $bMP);
		}
		return $pszError;
	}
	//--エラーメッセージ：全角カタカナチェック (*)アスタリスクはOK
	function ErrorMessageZenkakuKatakana_Aster_UTF8($pszString, $pszName, $iLimit, $bPermitNull = true, $bMP = false)
	{
		$pszError  = '';
        $iString   = mb_strlen($pszString, 'UTF-8');   //全角・半角にかかわらず、１文字を１文字としてカウント
		
		if (!$bPermitNull && ($iString == 0) && CString::IsNullString($pszError)) {
			$pszError .= CString::FontWarning($pszName.'は必ず入力してください', $bMP);
			return $pszError;
		}
		
    	//for ($i = 0; $i < $iString; $i++) {
    	//	$strGetString = mb_substr($pszString, $i, 1, 'UTF-8');
    	//	$iMbLength = mb_strwidth($strGetString, 'UTF-8'); //全角は半角の２文字換算でカウント
    	//	//全角文字でない場合
    	//	if ($iMbLength != 2) {
    	//		$pszError = CString::FontWarning($pszName.'は全角文字で入力してください', $bMP);
    	//		break;
    	//	}
    	//}
		
		if($iString > $iLimit && CString::IsNullString($pszError)) $pszError .= CString::FontWarning($pszName.'は全角'.$iLimit.'文字以内にしてください', $bMP);
		
        
        $pszString_Work = str_replace('*', '', $pszString);
        
		mb_regex_encoding('UTF-8');
		//カタカナ以外が含まれている場合
		if (strlen($pszString_Work) != 0 && !preg_match("/^[ァ-ヶー]+$/u", $pszString_Work)) {
		    $pszError .= CString::FontWarning($pszName.'は全角カタカナで入力してください', $bMP);
		}
		return $pszError;
	}
	//--電話番号入力チェック
	function ErrorMessageTelno($strTitle, $strTelno1, $strTelno2, $strTelno3, $bPermitNull = true) {
        $intTelnoTotalLength = 0;
        $intTelnoTotalLength += mb_strlen($strTelno1, 'UTF-8');
        $intTelnoTotalLength += mb_strlen($strTelno2, 'UTF-8');
        $intTelnoTotalLength += mb_strlen($strTelno3, 'UTF-8');
        
        //任意入力で電話番号の文字数が0の場合
        if ($bPermitNull && $intTelnoTotalLength == 0) {
            //OK
        } else {
            //電話番号が入力されていない場合
            if (!$bPermitNull && $intTelnoTotalLength == 0) {
                $pszError = CString::FontWarning($strTitle.'が入力されていません', true);
            //電話番号が入力されている場合
            } else {
                //TEL(左)
                $strReturn = CString::ErrorMessage4($strTelno1, $strTitle, 5, true, true);
                if (strlen($strReturn) != 0) {
                    $pszError = $strReturn;
                }
                //TEL(中)
                $strReturn = CString::ErrorMessage4($strTelno2, $strTitle, 5, true, true);
                if (strlen($strReturn) != 0) {
                    $pszError = $strReturn;
                }
                //TEL(右)
                $strReturn = CString::ErrorMessage4($strTelno3, $strTitle, 5, true, true);
                if (strlen($strReturn) != 0) {
                    $pszError = $strReturn;
                }
                //電話番号の桁数合計が9,10,11以外の場合
                if ($intTelnoTotalLength != 9 && $intTelnoTotalLength != 10 && $intTelnoTotalLength != 11) {
                    $pszError = CString::FontWarning($strTitle.'が正しく入力されていません', true);
                }
            }
        }
        return $pszError;
	}
	//--空文字判定
	function IsNullString($pszString)
	{
		return (strlen($pszString) == 0) || ($pszString == NULL) || ($pszString == '');
	}
	//--入力文字列：リクエスト処理
	function Request($pszString)
	{
		return htmlspecialchars(mb_convert_encoding($pszString, 'SJIS', 'auto'));
//		return htmlspecialchars(mb_convert_encoding(stripslashes($pszString), 'SJIS', 'auto'));
	}
	
	//--文字列：設定
	function StringConfig($iLevel)
	{
		switch($iLevel)
		{
			case 0:  $pszString = '設定しない'; break;
			case 1:  $pszString = '設定する';   break;
			default: $pszString = '設定しない';
		}
		return $pszString;
	}
	
	//--文字列：性別
	function StringGender($iInt)
	{
		$pszString = '';
		switch($iInt)
		{
			case 1 : $pszString = '男性'; break;
			case 2 : $pszString = '女性'; break;
			default: $pszString = '不明'; break;
		}
		return $pszString;
	}

	//--県名
	function StringPrefecture($iPrefecture)
	{
		switch($iPrefecture)
		{
			case 1:  $pszPrefacture = '北海道';   break;
			case 2:  $pszPrefacture = '青森県';   break;
			case 3:  $pszPrefacture = '岩手県';   break;
			case 4:  $pszPrefacture = '宮城県';   break;
			case 5:  $pszPrefacture = '秋田県';   break;
			case 6:  $pszPrefacture = '山形県';   break;
			case 7:  $pszPrefacture = '福島県';   break;
			case 8:  $pszPrefacture = '茨城県';   break;
			case 9:  $pszPrefacture = '栃木県';   break;
			case 10: $pszPrefacture = '群馬県';   break;
			case 11: $pszPrefacture = '埼玉県';   break;
			case 12: $pszPrefacture = '千葉県';   break;
			case 13: $pszPrefacture = '東京都';   break;
			case 14: $pszPrefacture = '神奈川県'; break;
			case 15: $pszPrefacture = '新潟県';   break;
			case 16: $pszPrefacture = '富山県';   break;
			case 17: $pszPrefacture = '石川県';   break;
			case 18: $pszPrefacture = '福井県';   break;
			case 19: $pszPrefacture = '山梨県';   break;
			case 20: $pszPrefacture = '長野県';   break;
			case 21: $pszPrefacture = '岐阜県';   break;
			case 22: $pszPrefacture = '静岡県';   break;
			case 23: $pszPrefacture = '愛知県';   break;
			case 24: $pszPrefacture = '三重県';   break;
			case 25: $pszPrefacture = '滋賀県';   break;
			case 26: $pszPrefacture = '京都府';   break;
			case 27: $pszPrefacture = '大阪府';   break;
			case 28: $pszPrefacture = '兵庫県';   break;
			case 29: $pszPrefacture = '奈良県';   break;
			case 30: $pszPrefacture = '和歌山県'; break;
			case 31: $pszPrefacture = '鳥取県';   break;
			case 32: $pszPrefacture = '島根県';   break;
			case 33: $pszPrefacture = '岡山県';   break;
			case 34: $pszPrefacture = '広島県';   break;
			case 35: $pszPrefacture = '山口県';   break;
			case 36: $pszPrefacture = '徳島県';   break;
			case 37: $pszPrefacture = '香川県';   break;
			case 38: $pszPrefacture = '愛媛県';   break;
			case 39: $pszPrefacture = '高知県';   break;
			case 40: $pszPrefacture = '福岡県';   break;
			case 41: $pszPrefacture = '佐賀県';   break;
			case 42: $pszPrefacture = '長崎県';   break;
			case 43: $pszPrefacture = '熊本県';   break;
			case 44: $pszPrefacture = '大分県';   break;
			case 45: $pszPrefacture = '宮崎県';   break;
			case 46: $pszPrefacture = '鹿児島県'; break;
			case 47: $pszPrefacture = '沖縄県';   break;
			case 48: $pszPrefacture = 'その他';   break;
			default: $pszPrefacture = '';
		}
		return $pszPrefacture;
	}
	
	//--県名
	function GetPrefectureIndex($strPrefecture)
	{
		switch($strPrefecture)
		{
			case '北海道'   : $intPrefectureIndex = 1;   break;
			case '青森県'   : $intPrefectureIndex = 2;   break;
			case '岩手県'   : $intPrefectureIndex = 3;   break;
			case '宮城県'   : $intPrefectureIndex = 4;   break;
			case '秋田県'   : $intPrefectureIndex = 5;   break;
			case '山形県'   : $intPrefectureIndex = 6;   break;
			case '福島県'   : $intPrefectureIndex = 7;   break;
			case '茨城県'   : $intPrefectureIndex = 8;   break;
			case '栃木県'   : $intPrefectureIndex = 9;   break;
			case '群馬県'   : $intPrefectureIndex = 10;  break;
			case '埼玉県'   : $intPrefectureIndex = 11;  break;
			case '千葉県'   : $intPrefectureIndex = 12;  break;
			case '東京都'   : $intPrefectureIndex = 13;  break;
			case '神奈川県' : $intPrefectureIndex = 14;  break;
			case '新潟県'   : $intPrefectureIndex = 15;  break;
			case '富山県'   : $intPrefectureIndex = 16;  break;
			case '石川県'   : $intPrefectureIndex = 17;  break;
			case '福井県'   : $intPrefectureIndex = 18;  break;
			case '山梨県'   : $intPrefectureIndex = 19;  break;
			case '長野県'   : $intPrefectureIndex = 20;  break;
			case '岐阜県'   : $intPrefectureIndex = 21;  break;
			case '静岡県'   : $intPrefectureIndex = 22;  break;
			case '愛知県'   : $intPrefectureIndex = 23;  break;
			case '三重県'   : $intPrefectureIndex = 24;  break;
			case '滋賀県'   : $intPrefectureIndex = 25;  break;
			case '京都府'   : $intPrefectureIndex = 26;  break;
			case '大阪府'   : $intPrefectureIndex = 27;  break;
			case '兵庫県'   : $intPrefectureIndex = 28;  break;
			case '奈良県'   : $intPrefectureIndex = 29;  break;
			case '和歌山県' : $intPrefectureIndex = 30;  break;
			case '鳥取県'   : $intPrefectureIndex = 31;  break;
			case '島根県'   : $intPrefectureIndex = 32;  break;
			case '岡山県'   : $intPrefectureIndex = 33;  break;
			case '広島県'   : $intPrefectureIndex = 34;  break;
			case '山口県'   : $intPrefectureIndex = 35;  break;
			case '徳島県'   : $intPrefectureIndex = 36;  break;
			case '香川県'   : $intPrefectureIndex = 37;  break;
			case '愛媛県'   : $intPrefectureIndex = 38;  break;
			case '高知県'   : $intPrefectureIndex = 39;  break;
			case '福岡県'   : $intPrefectureIndex = 40;  break;
			case '佐賀県'   : $intPrefectureIndex = 41;  break;
			case '長崎県'   : $intPrefectureIndex = 42;  break;
			case '熊本県'   : $intPrefectureIndex = 43;  break;
			case '大分県'   : $intPrefectureIndex = 44;  break;
			case '宮崎県'   : $intPrefectureIndex = 45;  break;
			case '鹿児島県' : $intPrefectureIndex = 46;  break;
			case '沖縄県'   : $intPrefectureIndex = 47;  break;
		}
		return $intPrefectureIndex;
	}
	
	//住所に含まれる都道府県名を削除
	function DeletePrefectureName($strAddress) {
	    $aryRet = array();
	    for ($i = 1; $i <= 46; $i++) {
	        $strPrefectureName = CString::StringPrefecture($i);
	        $intPos = strpos($strAddress, $strPrefectureName);
	        if ($intPos !== FALSE) {
	            //echo '['.$strAddress.']';
	            $aryRet[0] = CString::GetPrefectureIndex($strPrefectureName);
	            $aryRet[1] = str_replace($strPrefectureName, '', $strAddress);
	            //$strAddress = str_replace($strPrefectureName, '', $strAddress);
	            //echo '['.$strAddress.']';
	        }
	    }
	    return $aryRet;
	}
	
    //番地以外の住所を取得する
    function GetAddress($strAddress) {
        
        $intFirstP = 999;
        
        $intFindP = mb_strpos($strAddress, "1");
        if ($intFindP <> 0 && $intFindP < $intFirstP) {
            $intFirstP = $intFindP;
        }
        $intFindP = mb_strpos($strAddress, "2");
        if ($intFindP <> 0 && $intFindP < $intFirstP) {
            $intFirstP = $intFindP;
        }
        $intFindP = mb_strpos($strAddress, "3");
        if ($intFindP <> 0 && $intFindP < $intFirstP) {
            $intFirstP = $intFindP;
        }
        $intFindP = mb_strpos($strAddress, "4");
        if ($intFindP <> 0 && $intFindP < $intFirstP) {
            $intFirstP = $intFindP;
        }
        $intFindP = mb_strpos($strAddress, "5");
        if ($intFindP <> 0 && $intFindP < $intFirstP) {
            $intFirstP = $intFindP;
        }
        $intFindP = mb_strpos($strAddress, "6");
        if ($intFindP <> 0 && $intFindP < $intFirstP) {
            $intFirstP = $intFindP;
        }
        $intFindP = mb_strpos($strAddress, "7");
        if ($intFindP <> 0 && $intFindP < $intFirstP) {
            $intFirstP = $intFindP;
        }
        $intFindP = mb_strpos($strAddress, "8");
        if ($intFindP <> 0 && $intFindP < $intFirstP) {
            $intFirstP = $intFindP;
        }
        $intFindP = mb_strpos($strAddress, "9");
        if ($intFindP <> 0 && $intFindP < $intFirstP) {
            $intFirstP = $intFindP;
        }
        $intFindP = mb_strpos($strAddress, "一丁目");
        if ($intFindP <> 0 && $intFindP < $intFirstP) {
            $intFirstP = $intFindP;
        }
        $intFindP = mb_strpos($strAddress, "二丁目");
        if ($intFindP <> 0 && $intFindP < $intFirstP) {
            $intFirstP = $intFindP;
        }
        $intFindP = mb_strpos($strAddress, "三丁目");
        if ($intFindP <> 0 && $intFindP < $intFirstP) {
            $intFirstP = $intFindP;
        }
        $intFindP = mb_strpos($strAddress, "四丁目");
        if ($intFindP <> 0 && $intFindP < $intFirstP) {
            $intFirstP = $intFindP;
        }
        $intFindP = mb_strpos($strAddress, "五丁目");
        if ($intFindP <> 0 && $intFindP < $intFirstP) {
            $intFirstP = $intFindP;
        }
        $intFindP = mb_strpos($strAddress, "六丁目");
        if ($intFindP <> 0 && $intFindP < $intFirstP) {
            $intFirstP = $intFindP;
        }
        $intFindP = mb_strpos($strAddress, "七丁目");
        if ($intFindP <> 0 && $intFindP < $intFirstP) {
            $intFirstP = $intFindP;
        }
        $intFindP = mb_strpos($strAddress, "八丁目");
        if ($intFindP <> 0 && $intFindP < $intFirstP) {
            $intFirstP = $intFindP;
        }
        $intFindP = mb_strpos($strAddress, "九丁目");
        if ($intFindP <> 0 && $intFindP < $intFirstP) {
            $intFirstP = $intFindP;
        }
        if ($intFirstP <> 999) {
            $strReturn = mb_substr($strAddress, 0, $intFirstP);
        } else {
            $strReturn = $strAddress;
        }
        return $strReturn;
    }
	
	//■携帯絵文字変換
	function ReplaceMark($pszBody)
	{
		$iCount = substr_count($pszBody, '[m:');
		
		for($i = 0; $i < $iCount; $i++)
		{
			$iStartPosition = strpos($pszBody, '[m:');
			$pszMarkIndex   = substr($pszBody, $iStartPosition + 3, 3);
			
			$pszBody = substr_replace($pszBody, Mark($pszMarkIndex), $iStartPosition, 7);
		}
		return $pszBody;
	}
	
	//■SQLコマンド用の文字列に変換する関数
	function cnv_dbstr($strString) {
		//タグを無効にする
		$strString = htmlspecialchars($strString);
		
		//magic_quotes_gpcがOnの場合はエスケープを解除する
		if (get_magic_quotes_gpc()) {
			$strString = stripslashes($strString);
		}
		
		//SQLコマンド用の文字列にエスケープする
		$strString = mysql_real_escape_string($strString);
		return $strString;
	}
	
//    //メールの文章をキャリア毎にエンコードして返す関数
//    function cnv_mail_string($strString) {
//    	$strCareer = CModel::MPCareer();
//    	if ($strCareer == 'softbank') {
//    		$strReturn = mb_convert_encoding($strString, 'utf-8', 'SJIS');
//    	} else {
//    		$strReturn = $strString;
//    	}
//    	$strReturn = rawurlencode($strReturn);
//    }
	
	//GET、POSTの値に含まれている不正な文字を削除する関数
    function chkData($value) {
    	$value = trim($value);
        //$value = strip_tags($value);             //htmlタグの削除
        $value = mb_ereg_replace("'","",$value); //「'」シングルクオートの削除
        //$value = stripslashes($value);           //バックスラッシュを削除★
        
        //$value = htmlspecialchars($value);       //特殊文字の無効化
        $strEncode = mb_internal_encoding();
        if ($strEncode != 'ASCII') {
            $value = htmlspecialchars($value, ENT_QUOTES, $strEncode);       //特殊文字の無効化
        }
        
        return $value;
    }
	
	//GET、POSTの値に含まれている不正な文字を削除する関数(引数が配列用)
    function chkDataArray($aryValue) {
        if (!is_array($aryValue)) {
            return array();
        }
    	$strEncode = mb_internal_encoding();
    	for ($i = 0; $i < count($aryValue); $i++) {
	        //$aryValue[$i] = strip_tags($aryValue[$i]);             //htmlタグの削除
	        $aryValue[$i] = mb_ereg_replace("'","",$aryValue[$i]); //「'」シングルクオートの削除
	        //$aryValue[$i] = stripslashes($aryValue[$i]);           //バックスラッシュを削除
	        
	        //$aryValue[$i] = htmlspecialchars($aryValue[$i]);       //特殊文字の無効化
	        $aryValue[$i] = htmlspecialchars($aryValue[$i], ENT_QUOTES, $strEncode); //特殊文字の無効化
    	}
        return $aryValue;
    }
	
	//文字列に含まれる全角スペース、半角スペースを削除する
	function DeleteSpace($strValue) {
		//全角スペースを削除
		$strValue = str_replace('　', '', $strValue);
		//半角スペースを削除
		$strValue = str_replace(' ', '', $strValue);
		return $strValue;
	}
	
	//年齢
	function StringAgeCategory($iFlag)
	{
		$pszString = '';
		switch($iFlag)
		{
			case 1 : $pszString = '15～20'; break;
			case 2 : $pszString = '21～25'; break;
			case 3 : $pszString = '26～30'; break;
			case 4 : $pszString = '31～35'; break;
			case 5 : $pszString = '36～40'; break;
			case 6 : $pszString = '41～45'; break;
			case 7 : $pszString = '46～50'; break;
			case 8 : $pszString = '51～55'; break;
			case 9 : $pszString = '56～60'; break;
			case 10: $pszString = '61～65'; break;
			case 11: $pszString = '66～70'; break;
			case 12: $pszString = '71～'; break;
			default: $pszString = '不明';
		}
		return $pszString;
	}
	
	//パスワード生成
	function MakePassword($intLength) {
		return substr(str_shuffle('1234567890abcdefghijkmnprstuvwxyz'), 0, $intLength);
	}
	
	public function mb_wordwrap($str, $width=35, $break=PHP_EOL) {
        $c = mb_strlen($str);
        $arr = [];
        for ($i=0; $i<=$c; $i+=$width) {
            $arr[] = mb_substr($str, $i, $width);
        }
        return implode($break, $arr);
    }
}



//〓〓〓〓CLASS::その他〓〓〓〓//
class CModel
{
	function __construct()
	{
		return true;
	}
	function __destruct()
	{
		return true;
	}
	
	//--メールアドレス判定
	function IsMailAddress($pszMailAddress)
	{
		//return ereg('[0-9a-zA-Z_"\.\-]+@[0-9a-zA-Z_\.\-]+', $pszMailAddress);
		
		//2012.05.22 minakuchi PEARのメールアドレス入力チェック関数を引用
		return CModel::isValidInetAddress($pszMailAddress, true);
	}
	//2012.05.22 minakuchi PEARのメールアドレス入力チェック関数を引用
    function isValidInetAddress($data, $strict = false)
    {
        $regex = $strict ? '/^([.0-9a-z_-]+)@(([0-9a-z-]+\.)+[0-9a-z]{2,4})$/i' : '/^([*+!.&#$|\'\\%\/0-9a-z^_`{}=?~:-]+)@(([0-9a-z-]+\.)+[0-9a-z]{2,4})$/i';
        if (preg_match($regex, trim($data), $matches)) {
            return array($matches[1], $matches[2]);
        } else {
            return false;
        }
    }
	//--携帯メールアドレス判定
	function IsMPMailAddress($pszMailAddress)
	{
		return 
		CModel::IsMailAddress($pszMailAddress) && (
		CModel::IsDocomoMail($pszMailAddress)   || 
		CModel::IsAUMail($pszMailAddress)       || 
		CModel::IsWillcomMail($pszMailAddress)       || 
		CModel::IsSoftbankMail($pszMailAddress) ||
		CModel::IsEMobileMail($pszMailAddress)
		);
	}
	function IsDocomoMail($pszMailAddress)
	{
		return preg_match('/docomo\.ne\.jp/',   $pszMailAddress);
	}
	function IsAUMail($pszMailAddress)
	{
		return preg_match('/ezweb\.ne\.jp/',    $pszMailAddress);
	}
	function IsWillcomMail($pszMailAddress)
	{
		return preg_match('/willcom\.com/',    $pszMailAddress);
	}
	function IsSoftbankMail($pszMailAddress)
	{
		return 
		preg_match('/i\.softbank\.jp/',  $pszMailAddress) || 
		preg_match('/vodafone\.ne\.jp/', $pszMailAddress) || 
		preg_match('/softbank\.ne\.jp/', $pszMailAddress) || 
		preg_match('/disney\.ne\.jp/',   $pszMailAddress);
	}
	function IsEMobileMail($pszMailAddress)
	{
		return preg_match('/emnet\.ne\.jp/',    $pszMailAddress);
	}
	
	//--携帯判定
	function IsDocomo()
	{
		return preg_match('/DoCoMo/', $_SERVER['HTTP_USER_AGENT']);
	}
	function IsAU()
	{
		//return preg_match('/KDDI/', $_SERVER['HTTP_USER_AGENT']);
		return preg_match('/UP.Browser/', $_SERVER['HTTP_USER_AGENT']);
	}
	function IsSoftbank()
	{
		return preg_match('/Vodafone/', $_SERVER['HTTP_USER_AGENT']) || preg_match('/SoftBank/', $_SERVER['HTTP_USER_AGENT']) || preg_match('/J-PHONE/',  $_SERVER['HTTP_USER_AGENT']);
	}
	function IsEMobile()
	{
		return preg_match('/emobile/', $_SERVER['HTTP_USER_AGENT']);
	}
	function IsSmartPhone()
	{
		$strUserAgent = $_SERVER['HTTP_USER_AGENT'];
		if (
		preg_match('/iPhone/', $strUserAgent) || 
		preg_match('/iPod/', $strUserAgent) || 
		preg_match('/Android/', $strUserAgent) || 
		preg_match('/Windows Phone/', $strUserAgent) || 
		preg_match('/dream/', $strUserAgent) || 
		preg_match('/CUPCAKE/', $strUserAgent) || 
		preg_match('/blackberry/', $strUserAgent) || 
		preg_match('/webOS/', $strUserAgent) || 
		preg_match('/incognito/', $strUserAgent) || 
		preg_match('/webmate/', $strUserAgent)
		) {
			return true;
		} else {
			return false;
		}
	}
	function IsIPhone()
	{
		return preg_match('/iPhone/',  $_SERVER['HTTP_USER_AGENT']);
	}
	function IsAndroid()
	{
		return preg_match('/Android/',  $_SERVER['HTTP_USER_AGENT']);
	}
	function IsWindowsPhone()
	{
		return preg_match('/Windows Phone/', $_SERVER['HTTP_USER_AGENT']);
	}
	function IsMPAccess()
	{
		return CModel::IsDocomo() || CModel::IsAU() || CModel::IsSoftbank() || CModel::IsEMobile();
	}
	//※emobileなし
	function IsMPAccess2()
	{
		return CModel::IsDocomo() || CModel::IsAU() || CModel::IsSoftbank();
	}

	//--ページ設定
	//$iPage：現在ページ位置
	//$iSumItem：総表示数
	//$iBlock：1ページあたり表示数
	function PageInfo($iPage, $iSumItem, $iBlock = 50)
	{
		//総アイテム数
		$aInfo['sum_item']    = $iSumItem;
		//最小、最大ページ数
		$aInfo['page_min']    = 1;
		$aInfo['page_max']    = intval(ceil($iSumItem/$iBlock));
		//有効ページ
		$aInfo['page']        = ((0 < $iPage) && ($iPage <= $aInfo['page_max']))? $iPage: 1;
		//前後ページ
		$aInfo['former_page'] = ((1 < $aInfo['page']) && ($aInfo['page'] <= $aInfo['page_max']))? $aInfo['page'] - 1: 1;
		$aInfo['next_page']   = ((0 < $aInfo['page']) && ($aInfo['page'] < $aInfo['page_max']))?  $aInfo['page'] + 1: $aInfo['page_max'];
		
		//データ取得個数　※クエリで使用　LIMIT $aInfo['offset'] , $aInfo['num']
		$aInfo['offset']      = ($aInfo['page'] - 1) * $iBlock;
		$aInfo['num']         = ($aInfo['sum_item'] < ($aInfo['page'] * $iBlock))? ($aInfo['sum_item'] - (($aInfo['page'] - 1) * $iBlock)): ($aInfo['page'] * $iBlock);
		
		return $aInfo;
	}
	
	//--アクセスエラー
	function JumpToMPAccessError()
	{
		if(!CModel::IsMPAccess())
		{
			header('Location: access_error.php');
			return;
		}
	}
	
	//--携帯電話キャリア検出
	function MPCareer()
	{
		//※ソフトバンクのユーザーエージェントにもUP.Browserが含まれているものがあるため
		//AUよりもソフトバンクの判別処理を上に移動
		switch(true)
		{
			case CModel::IsDocomo($pszUserAgent):   $pszCareer = 'docomo';   break;
			case CModel::IsSoftbank($pszUserAgent): $pszCareer = 'softbank'; break;
			case CModel::IsAU($pszUserAgent):       $pszCareer = 'au';       break;
			case CModel::IsIPhone():                $pszCareer = 'iphone';   break;
			case CModel::IsAndroid():               $pszCareer = 'android';  break;
			case CModel::IsWindowsPhone():          $pszCareer = 'windowsphone';  break;
			case CModel::IsEMobile($pszUserAgent):  $pszCareer = 'emobile';  break; //windows phoneにIEMobileという文字列があるためwindows phoneより後にもってくる
			default:                                $pszCareer = 'pc';
		}
		return $pszCareer;
	}
	function MPCareerByMailaddress($pszMailaddress)
	{
		switch(true)
		{
			case CModel::IsDocomoMail($pszMailaddress):   $pszCareer = 'docomo';   break;
			case CModel::IsAUMail($pszMailaddress):       $pszCareer = 'au';       break;
			case CModel::IsSoftbankMail($pszMailaddress): $pszCareer = 'softbank'; break;
			case CModel::IsEMobileMail($pszMailAddress):  $pszCareer = 'emobile';  break;
			default:                                      $pszCareer = 'pc';
		}
		return $pszCareer;
	}
	//--端末ID取得　※製造番号バージョン　docomoではタグに「utn」要
	function MPSerialNo()
	{
		//※ソフトバンクのユーザーエージェントにもUP.Browserが含まれているものがあるため
		//AUよりもソフトバンクの判別処理を上に移動
		
		$pszUserAgent = $_SERVER['HTTP_USER_AGENT'];
		$pszSerialNo  = '';
	
		//DoCoMo端末の場合(mova,FOMA)
		if(CModel::IsDocomo())
		{
			$iPointS = strpos($pszUserAgent, 'ser');
			if($iPointS > 0)
			{
				$iPointS     = $iPointS + 3;
				$pszSerialNo = substr($pszUserAgent, $iPointS, 15);
			}
		}
	
		//J-PHONE/Vodafone/SoftBank端末の場合
		else if(CModel::IsSoftbank())
		{
			$iPointS = strpos($pszUserAgent, 'SN');
			if($iPointS > 0)
			{
				$iPointS = $iPointS + 2;
				$pszSerialNo = substr($pszUserAgent, $iPointS, 15);
			}
		}
	
		//au端末の場合
		else if(CModel::IsAU())
		{
			$pszEZPersonal = $_SERVER['HTTP_X_UP_SUBNO'];
			$pszSerialNo   = substr($pszEZPersonal, 0, 17);
		}
		
		//EMobile
		else
		{
			$pszEMPersonal = $_SERVER['HTTP_X_EM_UID'];
			$pszSerialNo   = substr($pszEMPersonal, 1, 17);
		}
		
		if(CString::IsNullString(trim($pszSerialNo)))
		{
			$pszSerialNo = '';
		}
		return $pszSerialNo;
	}
	
	//--端末ID取得 　※ユーザーIDバージョン　docomoではGETクエリに「guid=ON」要　またSSL配下では使用できないので注意
	function MPSerialNo2()
	{
		$pszSerialNo  = '';
	
		//DoCoMo端末の場合(mova,FOMA)
		if(CModel::IsDocomo())
		{
			$pszSerialNo = $_SERVER['HTTP_X_DCMGUID'];
		}
	
		//J-PHONE/Vodafone/SoftBank端末の場合
		else if(CModel::IsSoftbank())
		{
			$pszSerialNo = $_SERVER['HTTP_X_JPHONE_UID'];
		}
	
		//au端末の場合
		else if(CModel::IsAU())
		{
			$pszEZPersonal = $_SERVER['HTTP_X_UP_SUBNO'];
			$pszSerialNo   = substr($pszEZPersonal, 0, 17);
		}
		
		//EMobile
		else
		{
			$pszEMPersonal = $_SERVER['HTTP_X_EM_UID'];
			$pszSerialNo   = substr($pszEMPersonal, 1, 17);
		}

		if(CString::IsNullString(trim($pszSerialNo)))
		{
			$pszSerialNo = '';
		}
		return $pszSerialNo;
	}
}



//〓〓〓〓CLASS::メール〓〓〓〓//
class CMail
{
	function __construct()
	{
		return true;
	}
	function __destruct()
	{
		return true;
	}

	//--メール送信
	function SendMail($pszTo, $pszSubject, $pszBody)
	{
		global $g_Account, $g_DomainMail, $g_Account_bounce, $g_FromName;
		
        //言語設定、内部エンコーディングを指定する
        mb_language("japanese");
        mb_internal_encoding("UTF-8");
         
        $mail = new PHPMailer();
        $mail->CharSet = "UTF-8";
        $mail->Encoding = "base64";
        
        $mail->AddAddress($pszTo);
        $mail->From = $g_Account.'@'.$g_DomainMail;
        $mail->Sender = $g_Account.'@'.$g_DomainMail;
        $mail->FromName = mb_encode_mimeheader($g_FromName);
        $mail->Subject  = mb_encode_mimeheader($pszSubject);
        $mail->Body     = $pszBody;
        $mail->Send();
		
		//mb_language('Japanese');
		//
		//$pszSubject = mb_convert_encoding($pszSubject, 'SJIS', 'auto');
		//$pszBody    = str_replace("\r\n","\n",$pszBody);
		//$pszBody    = mb_convert_encoding($pszBody, 'SJIS', 'auto');
		//$strFromName = mb_encode_mimeheader($g_FromName, 'ISO-2022-JP');
		//
		//$strHeader  = "Content-Type: text/plain \r\n";
		//$strHeader .= "Return-Path: ".$g_Account.'@'.$g_DomainMail." \r\n";
		//$strHeader .= "From: ".$strFromName." \r\n";
		//$strHeader .= "Sender: ".$strFromName." \r\n";
		//$strHeader .= "Reply-To: ".$g_Account.'@'.$g_DomainMail." \r\n";
		//$strHeader .= "Organization: ".$strFromName." \r\n";
		//$strHeader .= "X-Sender: ".$g_Account.'@'.$g_DomainMail." \r\n";
		//$strHeader .= "X-Priority: 3 \r\n";
		//
		//mb_internal_encoding('SJIS');
		////mb_send_mail($pszTo, $pszSubject, $pszBody, 'From: '.$g_Account.'@'.$g_DomainMail, '-f '.$g_Account_bounce.'@'.$g_DomainMail);
		//mb_send_mail($pszTo, $pszSubject, $pszBody, $strHeader);
		
		/*
		mb_language("ja");
		mb_internal_encoding("SJIS");
	    
		//subjectエンコード
		$pszSubject = mb_encode_mimeheader($pszSubject, "SJIS-WIN");
		
		//本文エンコード
		$pszBody = mb_convert_encoding($pszBody, "SJIS-WIN", "SJIS");
		
	    mail($pszTo, $pszSubject, $pszBody, 'From: '.$g_Account.'@'.$g_DomainMail);
		*/
		return true;
	}
	
	//--文末署名
	function Signature()
	{
		return 
		'------------'."\n".
		'※このメールは自動送信システムで送信されています。'."\n".
		'このメールへの返信は出来ません。'."\n";
	}
}



//〓〓〓〓CLASS::ファイル〓〓〓〓//
class CFile
{
	function __construct()
	{
		return true;
	}
	function __destruct()
	{
		return true;
	}

	//--アップロード
	//$aFileData：$_FILES[(name属性)]
	//$pszTo：保存先ディレクトリ
	//$pszFileName：パス、拡張子を除いたファイル名
	//$pszFile：作成後のファイルフルパス
	function Upload($aFileData, $pszTo, $pszFileName, &$pszFile, $aPermitType = array('jpg'), $iMaxSize = 102400, $iMaxWidth = 240, $iMaxHeight = 240)
	{
		$bBool = false;
		if(!empty($aFileData) && is_uploaded_file($aFileData['tmp_name']))
		{
			$pszSuffix = CFile::Suffix($aFileData['type']);
			$bSize     = $aFileData['size'] <= $iMaxSize;
			$aIMGSize  = getimagesize($aFileData['tmp_name']);
			$bWidth    = $aIMGSize[0] <= $iMaxWidth;
			$bHeight   = $aIMGSize[1] <= $iMaxHeight;
			
			if(in_array($pszSuffix, $aPermitType) && $bSize && $bWidth && $bHeight)
			{
				//ファイル名取得
				$pszFile = $pszTo.$pszFileName.'.'.$pszSuffix;
				//削除
				CFile::DeleteFile($pszFile);
				//アップロード
				move_uploaded_file($aFileData['tmp_name'], $pszFile);
				$bBool = true;
			}
		}
		return $bBool;
	}
	
	//--コピー
	//$pszFile：ファイルフルパス
	//$pszTo：保存先ディレクトリ
	//$pszFileName：パス、拡張子を除いたファイル名
	//jpg,gif,pngのみ対応
	//縮小/拡大の比率　$iOld：$iNew
	//縮小下限
	function CopyResized($pszFile, $pszTo, $pszFileName, $iOld = 3, $iNew = 1, $iWidthUnder = 80)
	{
		if(file_exists($pszFile) && (intval($iOld) != 0) && (intval($iNew) != 0))
		{
			$aIMGSize  = getimagesize($pszFile);
			$pszSuffix = CFile::Suffix($aIMGSize['mime']);

			//ファイル名取得
			$pszNewFile = $pszTo.$pszFileName.'.'.$pszSuffix;
			copy($pszFile, $pszNewFile);
			chmod($pszNewFile, 0777);

			//計算
			$iWidth    = intval($aIMGSize[0]*($iNew/$iOld));
			$iHeight   = intval($aIMGSize[1]*($iNew/$iOld));
			
			//縮小横幅調整　横最小値制限で再計算
			if((intval($iOld) > intval($iNew)) && ($iWidth < $iWidthUnder))
			{
				$iWidth  = $iWidthUnder;
				$iHeight = intval($aIMGSize[1]*($iWidthUnder/$aIMGSize[0]));
				
				//サイズが同じ場合は終了
				if($iWidth == $aIMGSize[0])
				{
					return true;
				}
			}

			switch($pszSuffix)
			{
				case 'jpg':
					$resOld = imagecreatefromjpeg($pszNewFile);
					$resNew = imagecreate($iWidth, $iHeight);
					imagecopyresized($resNew, $resOld, 0, 0, 0, 0, $iWidth, $iHeight, $aIMGSize[0], $aIMGSize[1]);
					imagejpeg($resNew, $pszNewFile, 72);
					break;
				case 'gif':
					$resOld = imagecreatefromgif($pszNewFile);
					$resNew = imagecreate($iWidth, $iHeight);
					imagecopyresized($resNew, $resOld, 0, 0, 0, 0, $iWidth, $iHeight, $aIMGSize[0], $aIMGSize[1]);
					imagegif($resNew);
					break;
				case 'png':
					$resOld = imagecreatefrompng($pszNewFile);
					$resNew = imagecreate($iWidth, $iHeight);
					imagecopyresized($resNew, $resOld, 0, 0, 0, 0, $iWidth, $iHeight, $aIMGSize[0], $aIMGSize[1]);
					imagepng($resNew);
					break;
				default :
					return false;
			}
		}
	}
	
	//--削除
	function DeleteFile($pszFile)
	{
		if(file_exists($pszFile))
		{
			chmod($pszFile, 0777);
			unlink($pszFile);
		}
	}
	
	//--拡張子
	function Suffix($pszMIMEType)
	{
		$pszSuffix = '';
		if(preg_match('/image/', $pszMIMEType))
		{
			//※MIMEタイプがブラウザごとに異なるらしい…。なので↓判定を甘くしています
			switch(true)
			{
				case preg_match('/gif/',  $pszMIMEType) : $pszSuffix = 'gif'; break;
				case preg_match('/png/',  $pszMIMEType) : $pszSuffix = 'png'; break;
				case preg_match('/jpeg/', $pszMIMEType) : $pszSuffix = 'jpg'; break;
				case preg_match('/jpg/',  $pszMIMEType) : $pszSuffix = 'jpg'; break;
				default: $pszSuffix = '';
			}
		}
		return $pszSuffix;
	}
	
	//--アクセス履歴　※アクセス履歴を残したいページで読み込むこと
	function AccessStamp($strSessionID)
	{
		global $g_AccessPath;
		
		//初期化
		$aAccessBase = array();
		$pszVal      = '';
		
		//当日ログファイル
		$pszLogFile = $g_AccessPath.date('Y-m-d').'.txt';
		
		//アクセスデータを取得
		$pszSelf    = $_SERVER['PHP_SELF'].(CString::IsNullString($_SERVER['QUERY_STRING'])? '': '?'.$_SERVER['QUERY_STRING']);
		$pszReferer = isset($_SERVER['HTTP_REFERER'])? $_SERVER['HTTP_REFERER']: '';
		$aAccessBase = array(
		date('Y-m-d H:i:s'),                    //時間
		strval($_SERVER['REMOTE_ADDR']),        //IPアドレス
		gethostbyaddr($_SERVER['REMOTE_ADDR']), //ホスト
		CModel::MPCareer(),                     //キャリア
		$_SERVER['HTTP_USER_AGENT'],            //ユーザーエージェント
		$pszSelf,                               //閲覧ページ
		$pszReferer                             //リファラー
		);
		
		//文字列生成
		$aAccessOption = array();
		$aAccessOption[] = $strSessionID;
		$aAccess = array_values(array_merge($aAccessBase, $aAccessOption));
		$pszVal  = implode(',', $aAccess)."\n";
		
		if(file_exists($pszLogFile))
		{
				CFile::Write($pszLogFile, $pszVal);
		}
		else
		{
			if(touch($pszLogFile))
			{
				CFile::Write($pszLogFile, $pszVal);
			}
			else
			{
				echo '生成できず';
			}
		}
		unset($pszVal);
		return true;
	}
	
	//書込み
	function Write($pszFile, $pszString, $pszMode = 'ab+')
	{
		$resFile = fopen($pszFile, $pszMode);
		if(flock($resFile, LOCK_EX))
		{
			fwrite($resFile, $pszString);
			flock($resFile, LOCK_UN);
		}
		fclose($resFile);
		return true;
	}
}



//〓〓〓〓CLASS::ファイル〓〓〓〓//
class CHTMLItem
{
	function __construct()
	{
		return true;
	}
	function __destruct()
	{
		return true;
	}
	
	//--ページインデックス
	//$aPageInfo：CModel::PageInfoを使用すること
	function PageIndex($aPageInfo, $pszPage, $aName = array(), $aValue = array())
	{
		$pszContent = '';
		if($aPageInfo['page_max'] > 1)
		{
			$pszContent .= CHTMLItem::TagA('＜', $aData['self'], array_merge($aName, array('page')), array_merge($aValue, array($aPageInfo['former_page'])));
			$pszContent .= '&nbsp;[ ';
			
			for($i = 1; $i <= $aPageInfo['page_max']; $i++)
			{
				$pszContent .= ($i == $aPageInfo['page'])? $i: CHTMLItem::TagA($i, $aData['self'], array_merge($aName, array('page')), array_merge($aValue, array($i)));
				$pszContent .= '&nbsp;';
			}
			$pszContent .= ']&nbsp;';
			$pszContent .= CHTMLItem::TagA('＞', $aData['self'], array_merge($aName, array('page')), array_merge($aValue, array($aPageInfo['next_page'])));
		}
		
		return 
		($aPageInfo['sum_item'] > 0)?
		'<center>'."\n".
		$pszContent."<br />\n".
		$aPageInfo['page'].'/'.$aPageInfo['page_max']."\n".
		'</center>'."\n": '';
	}
	
	//--ボタン
	function Submit($pszName, $pszValue, $pszExtra = '')
	{
		return '<input type="submit" name="'.$pszName.'" value="'.$pszValue.'"'.(CString::IsNullString($pszExtra)? '': ' '.$pszExtra).' />';
	}
	//--画像
	function FileInput($pszName, $iSize = 0, $pszExtra = '')
	{
		return '<input type="file" name="'.$pszName.'" value=""'.((intval($iSize) == 0)? '': ' size="'.intval($iSize).'"').(CString::IsNullString($pszExtra)? '': ' '.$pszExtra).' />';
	}
	//--テキスト
	function Text($pszName, $pszValue, $iSize = 0, $iMaxlength = 0, $pszExtra = '')
	{
		return '<input type="text" name="'.$pszName.'" value="'.$pszValue.'"'.((intval($iSize) == 0)? '': ' size="'.intval($iSize).'"').((intval($iMaxlength) == 0)? '': ' maxlength="'.intval($iMaxlength).'"').(CString::IsNullString($pszExtra)? '': ' '.$pszExtra).' />';
	}
	//--パスワード
	function Password($pszName, $pszValue, $iSize = 0, $iMaxlength = 0, $pszExtra = '')
	{
		return '<input type="password" name="'.$pszName.'" value="'.$pszValue.'"'.((intval($iSize) == 0)? '': ' size="'.intval($iSize).'"').((intval($iMaxlength) == 0)? '': ' maxlength="'.intval($iMaxlength).'"').(CString::IsNullString($pszExtra)? '': ' '.$pszExtra).' />';
	}
	//--hidden
	function Hidden($pszName, $pszValue)
	{
		return '<input type="hidden" name="'.$pszName.'" value="'.$pszValue.'" />';
	}
	//--テキストエリア
	function TextArea($pszName, $pszValue, $iRows, $iCols, $pszExtra = ' wrap="soft"')
	{
		return '<textarea name="'.$pszName.'" rows="'.intval($iRows).'" cols="'.intval($iCols).'"'.(CString::IsNullString($pszExtra)? '': ' '.$pszExtra).'>'.$pszValue.'</textarea>';
	}
	//--チェックボックス：複数(配列取得)タイプ(parts)
	//$pszName：name属性
	//$aValue：value値　配列
	//$aChecked：選択済み　配列
	//$aValueName：表示文字　配列
	function CheckBox($pszName, $aValue, $aChecked = array(), $aValueName = array())
	{
		$iValue     = count($aValue);
		$iValueName = count($aValueName);
		$bText      = (0 < $iValueName) && ($iValueName == $iValue);

		$aBox   = array();
		for($i = 0; $i < $iValue; $i++) $aBox[$i] = '<input type="checkbox" name="'.$pszName.'[]" value="'.$aValue[$i].'" '.(in_array($aValue[$i], $aChecked)? 'checked': '').' />'.($bText? $aValueName[$i]: '')."\n";
		return $aBox;
	}
	//--チェックボックス2：単体タイプ(parts)
	//$pszName：name属性
	//$aValue：value値　配列
	//$aChecked：選択済み　配列
	//$aValueName：表示文字　配列
	function CheckBox2($pszName, $aValue, $aChecked = array(), $aValueName = array())
	{
		$iValue     = count($aValue);
		$iValueName = count($aValueName);
		$bText      = (0 < $iValueName) && ($iValueName == $iValue);

		$aBox   = array();
		for($i = 0; $i < $iValue; $i++) $aBox[$i] = '<input type="checkbox" name="'.$pszName.'" value="'.$aValue[$i].'" '.(in_array($aValue[$i], $aChecked)? 'checked': '').' />'.($bText? $aValueName[$i]: '')."\n";
		return $aBox;
	}
	//--ラジオボタン
	//$pszName：name属性
	//$aValue：value値　配列
	//$aChecked：選択済み　単体変数
	//$aValueName：表示文字　配列
	function RadioButton($pszName, $aValue, $aValueName, $pszChecked = NULL, $bBR = false)
	{
		$iValue     = count($aValue);
		$iValueName = count($aValueName);
	
		$pszBox = '';
		if(($iValue != 0) && ($iValueName != 0) && ($iValue == $iValueName))
		{
			for($i = 0; $i < $iValue; $i++) $pszBox .= '<input type="radio" name="'.$pszName.'" value = "'.$aValue[$i].'" '.(($pszChecked == $aValue[$i])? 'checked': '').' />'.$aValueName[$i].($bBR? "<br />\n": "\n");
		}
		return $pszBox;
	}
	//--ラジオボタン(ラベル付)
	function RadioButtonLabel($pszName, $aValue, $aValueName, $pszChecked = NULL, $bBR = false)
	{
		$iValue     = count($aValue);
		$iValueName = count($aValueName);
	
		$pszBox = '';
		if(($iValue != 0) && ($iValueName != 0) && ($iValue == $iValueName))
		{
			for ($i = 0; $i < $iValue; $i++) {
			    $pszBox .= '<input type="radio" name="'.$pszName.'" id="'.$pszName.'_'.($i+1).'" value = "'.$aValue[$i].'" '.(($pszChecked == $aValue[$i])? 'checked': '').' />';
			    $pszBox .= '<label for="'.$pszName.'_'.($i+1).'">'.$aValueName[$i].'</label>'.($bBR? "<br />\n": "\n");
			}
		}
		return $pszBox;
	}
	//--ラジオボタン(ラベル付)<li>囲み
	function RadioButtonLabel_li($pszName, $aValue, $aValueName, $pszChecked = NULL, $bBR = false)
	{
		$iValue     = count($aValue);
		$iValueName = count($aValueName);
	
		$pszBox = '';
		if(($iValue != 0) && ($iValueName != 0) && ($iValue == $iValueName))
		{
			for ($i = 0; $i < $iValue; $i++) {
			    $pszBox .= '<li><input type="radio" name="'.$pszName.'" id="'.$pszName.'_'.($i+1).'" value = "'.$aValue[$i].'" '.(($pszChecked == $aValue[$i])? 'checked': '').' />';
			    $pszBox .= '<label for="'.$pszName.'_'.($i+1).'" class="radio">'.$aValueName[$i].'</label></li>'.($bBR? "<br />\n": "\n");
			}
		}
		return $pszBox;
	}
	//--ラジオボタン：性別
	function RadioGender($pszName, $pszChecked = NULL, $bBR = false)
	{
		$aFlag    = range(0, 1);
		$iFlagNum = count($aFlag);
		for($i = 0; $i < $iFlagNum; $i++) $aFlagName[$i] = CString::StringGender($aFlag[$i]);
		
		$aValue     = $aFlag;
		$aValueName = $aFlagName;
		
		return CHTMLItem::RadioButton($pszName, $aValue, $aValueName, $pszChecked, false);
	}
	//--ラジオボタン：設定
	function RadioConfig($pszName, $pszChecked = NULL)
	{
		$aFlag    = range(0, 1);
		$iFlagNum = count($aFlag);
		for($i = 0; $i < $iFlagNum; $i++) $aFlagName[$i] = CString::StringConfig($aFlag[$i]);
		
		return CHTMLItem::RadioButton($pszName, $aFlag, $aFlagName, $pszChecked, false);
	}
	//--セレクトBOX
	//$pszName：name属性
	//$aValue：value値　配列
	//$pszSelected：選択済み　単体変数
	//$aValueName：表示文字　配列
	function SelectBox($pszName, $aValue, $aValueName, $pszSelected = NULL)
	{
		$iValue     = count($aValue);
		$iValueName = count($aValueName);
	
		$pszBox = '';
		$bolSelectedFlag = false;
		
		if (($iValue != 0) && ($iValueName != 0) && ($iValue == $iValueName)) {
			$pszBox = '<select name="'.$pszName.'">'."\n";
			for ($i = 0; $i < $iValue; $i++)
			{
				$pszBox .= '<option value = "'.$aValue[$i].'" ';
				
				if ($pszSelected == $aValue[$i] && !$bolSelectedFlag) {
					$pszBox .= 'selected';
					$bolSelectedFlag = true;
				}
				
				$pszBox .= '>'.$aValueName[$i];
				$pszBox .= '</option>'."\n";
			}
			$pszBox .= '</select>'."\n";
		}
		return $pszBox;
	}
	function SelectBoxMulti($pszName, $aValue, $aValueName, $arySelected = array())
	{
		$iValue     = count($aValue);
		$iValueName = count($aValueName);
	
		$pszBox = '';
		
		if(($iValue != 0) && ($iValueName != 0) && ($iValue == $iValueName))
		{
			$pszBox = '<select name="'.$pszName.'">'."\n";
			for ($i = 0; $i < $iValue; $i++) {
				$pszBox .= '<option value = "'.$aValue[$i].'" ';
				
				if (in_array($aValue[$i], $arySelected)) {
					$pszBox .= 'selected';
				} else {
					$pszBox .= '';
				}
				
				$pszBox .= '>';
				$pszBox .= $aValueName[$i].'</option>'."\n";
			}
			$pszBox .= '</select>'."\n";
		}
		return $pszBox;
	}
	//--誕生日：年
	function SelectBoxYear($pszName, $pszSelected = NULL)
	{
		$iYear             = date('Y', time());   //当年
		$aYear             = range(1980, $iYear); //1980年～当年
		$aDefaultValue     = array(0);
		$aDefaultValueName = array('----');
	
		$aValue     = array_merge($aDefaultValue,     $aYear);
		$aValueName = array_merge($aDefaultValueName, $aYear);
	
		return CHTMLItem::SelectBox($pszName, $aValue, $aValueName, $pszSelected);
	}
	//--登録年
	function SelectBoxYear2($pszName, $pszSelected = NULL)
	{
		$iYear             = date('Y', time());   //当年
		$aYear             = range($iYear - 1, ($iYear + 20)); //当年～20年先
		$aDefaultValue     = array(0);
		$aDefaultValueName = array('----');
	
		$aValue     = array_merge($aDefaultValue,     $aYear);
		$aValueName = array_merge($aDefaultValueName, $aYear);
	
		return CHTMLItem::SelectBox($pszName, $aValue, $aValueName, $pszSelected);
	}
	//--月
	function SelectBoxMonth($pszName, $pszSelected = NULL)
	{
		$aMonth            = range(1, 12);
		$aDefaultValue     = array(0);
		$aDefaultValueName = array('--');
	
		$aValue     = array_merge($aDefaultValue,     $aMonth);
		$aValueName = array_merge($aDefaultValueName, $aMonth);
	
		return CHTMLItem::SelectBox($pszName, $aValue, $aValueName, $pszSelected);
	}
	//--日
	function SelectBoxDay($pszName, $pszSelected = NULL)
	{
		$aDay              = range(1, 31);
		$aDefaultValue     = array(0);
		$aDefaultValueName = array('--');
	
		$aValue     = array_merge($aDefaultValue,     $aDay);
		$aValueName = array_merge($aDefaultValueName, $aDay);
	
		return CHTMLItem::SelectBox($pszName, $aValue, $aValueName, $pszSelected);
	}
	//--時間
	function SelectBoxHour($pszName, $pszSelected = NULL)
	{
		$aValue = range(0, 23);
	
		return CHTMLItem::SelectBox($pszName, $aValue, $aValue, $pszSelected);
	}
	//--分
	function SelectBoxMinute($pszName, $pszSelected = NULL)
	{
		$aValue = range(0, 59);
	
		return CHTMLItem::SelectBox($pszName, $aValue, $aValue, $pszSelected);
	}
	//--5分ごと
	function SelectBoxMinute5($pszName, $pszSelected = NULL)
	{
		$aValue = array(0,5,10,15,20,25,30,35,40,45,50,55);
	
		return CHTMLItem::SelectBox($pszName, $aValue, $aValue, $pszSelected);
	}
	//--秒
	function SelectBoxSecond($pszName, $pszSelected = NULL)
	{
		$aValue = range(0, 59);
	
		return CHTMLItem::SelectBox($pszName, $aValue, $aValue, $pszSelected);
	}
	//--県
	function SelectBoxPrefecture($pszName, $pszSelected = NULL)
	{
		$aPrefecture       = range(1, 47);
		for($i = 1; $i <= 47; $i++) $aPrefectureName[$i] = CString::StringPrefecture($i);
		$aDefaultValue     = array(0);
		//$aDefaultValueName = array('----');
		$aDefaultValueName = array('都道府県を選択してください');
	
		$aValue     = array_merge($aDefaultValue,     $aPrefecture);
		$aValueName = array_merge($aDefaultValueName, $aPrefectureName);
	
		return CHTMLItem::SelectBox($pszName, $aValue, $aValueName, $pszSelected);
	}
	//--県(関西メイン)
	function SelectBoxPrefectureKansaiMain($pszName, $pszSelected = NULL, $strDefaultString = '都道府県を選択してください')
	{
		$aDefaultValue     = array(0);
		$aDefaultValueName = array($strDefaultString);
		
		$aryArea = array(5,1,2,3,4,6,7,8);
		
		$strRet = '<select name="'.$pszName.'" label="'.$strLabel.'">'."\n";
		
		$strRet .= '<option value=""';
		if (intval($pszSelected) == 0) {
			$strRet .= ' selected ';
		}
		$strRet .= '>'.$strDefaultString.'</option>'."\n";
		
		foreach ($aryArea as $value) {
			switch ($value) {
			case 1 : 
			    $strLabel = '北海道・東北';
				$aPrefecture = range(1, 7);
			    break;
			case 2 : 
			    $strLabel = '関東';
				$aPrefecture = range(8, 14);
			    break;
			case 3 : 
			    $strLabel = '甲信越・北陸';
				$aPrefecture = range(15, 20);
			    break;
			case 4 : 
			    $strLabel = '東海';
				$aPrefecture = range(21, 24);
			    break;
			case 5 : 
			    $strLabel = '関西';
				$aPrefecture = array(27,26,28,29,30,25);
			    break;
			case 6 : 
			    $strLabel = '中国';
				$aPrefecture = range(31, 35);
			    break;
			case 7 : 
			    $strLabel = '四国';
				$aPrefecture = range(36, 39);
			    break;
			case 8 : 
			    $strLabel = '九州・沖縄';
				$aPrefecture = range(40, 47);
			    break;
			}
			$strRet .= '<optgroup label="'.$strLabel.'">'."\n";
			
			foreach ($aPrefecture as $intPrefectureIndex) {
				$strRet .= '<option value="'.$intPrefectureIndex.'"';
				if ($intPrefectureIndex == $pszSelected) {
					$strRet .= ' selected ';
				}
				$strRet .= '>';
				$strRet .= CString::StringPrefecture($intPrefectureIndex);
				$strRet .= '</option>'."\n";
			}
			$strRet .= '</optgroup>'."\n";
		}
		$strRet .= '</select>'."\n";
		return $strRet;
	}
	//--県(関西メイン2)
	function SelectBoxPrefectureKansaiMain2($pszName, $pszSelected = NULL)
	{
		$aDefaultValue     = array(0);
		$aDefaultValueName = array('都道府県を選択してください');
		
		$aryArea = array(1,2,3,4,5,6,7,8);
		
		$strRet = '<select name="'.$pszName.'" label="'.$strLabel.'">'."\n";
		
		foreach ($aryArea as $value) {
			switch ($value) {
			case 1 : 
			    $strLabel = '北海道・東北';
				$aPrefecture = range(1, 7);
			    break;
			case 2 : 
			    $strLabel = '関東';
				$aPrefecture = range(8, 14);
			    break;
			case 3 : 
			    $strLabel = '甲信越・北陸';
				$aPrefecture = range(15, 20);
			    break;
			case 4 : 
			    $strLabel = '東海';
				$aPrefecture = range(21, 24);
			    break;
			case 5 : 
			    $strLabel = '関西';
				$aPrefecture = array(27,26,28,29,30,25);
				
				$strRet .= '<option value=""';
				if (intval($pszSelected) == 0) {
					$strRet .= ' selected ';
				}
				$strRet .= '>都道府県を選択してください</option>'."\n";
				
			    break;
			case 6 : 
			    $strLabel = '中国';
				$aPrefecture = range(31, 35);
			    break;
			case 7 : 
			    $strLabel = '四国';
				$aPrefecture = range(36, 39);
			    break;
			case 8 : 
			    $strLabel = '九州・沖縄';
				$aPrefecture = range(40, 47);
			    break;
			}
			
			$strRet .= '<optgroup label="'.$strLabel.'">'."\n";
			
			foreach ($aPrefecture as $intPrefectureIndex) {
				$strRet .= '<option value="'.$intPrefectureIndex.'"';
				if ($intPrefectureIndex == $pszSelected) {
					$strRet .= ' selected ';
				}
				$strRet .= '>';
				$strRet .= CString::StringPrefecture($intPrefectureIndex);
				$strRet .= '</option>'."\n";
			}
			$strRet .= '</optgroup>'."\n";
		}
		$strRet .= '</select>'."\n";
		return $strRet;
	}
	//■年代カテゴリ
	function SelectBoxAgeCategory($pszName, $pszSelected = NULL)
	{
		$aValue            = range(1, 12);
		$aValueName        = CHTMLItem::ArrayAgeCategory();
		$aDefaultValue     = array(0);
		$aDefaultValueName = array('▼選択');

		$aValue     = array_merge($aDefaultValue,     $aValue);
		$aValueName = array_merge($aDefaultValueName, $aValueName);

		return CHTMLItem::SelectBox($pszName, $aValue, $aValueName, $pszSelected);
	}
	function ArrayAgeCategory()
	{
		for($i = 1, $aName = array(); $i < 13; $i++) $aName[$i] = CString::StringAgeCategory($i);
		return $aName;
	}

	//--空白
	function Space($iWidth = 0, $iHeight = 0)
	{
		global $g_ManagerIMGPath;
		$pszWidth  = ($iWidth == 0)?  '': 'width="'.$iWidth.'"';
		$pszHeight = ($iHeight == 0)? '': 'height="'.$iHeight.'"';
	
		return '<img src="'.$g_ManagerIMGPath.'spacer.gif" alt="" '.$pszWidth.' '.$pszHeight.'>';
	}
	
	//--タグ：A
	//$pszString ：リンク表示文字列
	//$pszPage   ：(パス＋)ファイル名
	//$aName     ：GETクエリ　name
	//$aValue    ：GETクエリ　value
	function TagA($pszString, $pszPage, $aName = array(), $aValue = array(), $pszOption = '')
	{
		return '<a href="'.CHTMLItem::TagURL($pszPage, $aName, $aValue).'"'.(CString::IsNullString($pszOption)? '': ' '.$pszOption).'>'.$pszString.'</a>';
	}
	function TagURL($pszPage, $aName = array(), $aValue = array())
	{
		$iName    = count($aName);
		$iValue   = count($aValue);
		$pszQuery = '';
		if(($iValue != 0) && ($iName != 0) && ($iValue == $iName))
		{
			for($i = 0; $i < $iValue; $i++) $pszQuery .= (($i == 0)? '?': '&').$aName[$i].'='.$aValue[$i];
		}
		return $pszPage.$pszQuery;
	}
	
	//--パンくず文字列
	function TopicList($aTopicList)
	{
		$pszString = '';
		$iNum      = count($aTopicList);
		for($i = 0; $i < $iNum; $i++) $pszString .= '&gt;&nbsp;'.(($aTopicList[$i]['link'] == '')? $aTopicList[$i]['title']: '<a href="'.$aTopicList[$i]['link'].'">'.$aTopicList[$i]['title'].'</a>').'&nbsp;';
		return $pszString;
	}
}

class CViewPC
{
	function __construct()
	{
		return true;
	}
	function __destruct()
	{
		return true;
	}
	
	//◆ヘッダ
	function Header($strPageTitle = "")
	{
        header('Content-type: text/html; charset=UTF-8');
        return 
        '<!DOCTYPE HTML>'."\n".
        '<html lang="ja">'."\n".
        '<head>'."\n".
        '<!-- Global site tag (gtag.js) - Google Analytics -->'."\n".
        '<script src="../js/analytics.js"></script>'."\n".
        '<meta charset="UTF-8">'."\n".
        '<title>'.$strPageTitle.' | テレボートウェルカムキャンペーン</title>'."\n".
        '<meta name="keywords" content="">'."\n".
        '<meta name="description" content="">'."\n".
        '<meta name="robots" content="noindex, nofollow">'."\n".
        '<link rel="stylesheet" href="css/html5reset.css?ver20190624">'."\n".
        '<link rel="stylesheet" href="css/common.css?ver20190624">'."\n".
        '<link rel="stylesheet" media="print" href="css/print.css?ver20190624">'."\n".
        '<link rel="stylesheet" href="webfont/awesome/css/font-awesome.min.css?ver20190624">'."\n".
        '<meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1" />'."\n".
        '<meta name="format-detection" content="telephone=no">'."\n".
        '<script src="js/jquery-1.12.0.min.js"></script>'."\n".
        '<script src="js/script.js"></script>'."\n".
        '</head>'."\n".
        '<body>'."\n".
        '<div id="wrap">'."\n";
	}
	
	function Header2020Next($strPageTitle = "")
	{
        header('Content-type: text/html; charset=UTF-8');
        return 
        '<!DOCTYPE HTML>'."\n".
        '<html lang="ja">'."\n".
        '<head>'."\n".
        '<!-- Global site tag (gtag.js) - Google Analytics -->'."\n".
        '<script src="../js/analytics.js"></script>'."\n".
        '<meta charset="UTF-8">'."\n".
        '<title>'.$strPageTitle.' | テレボートウェルカムキャンペーン</title>'."\n".
        '<meta name="keywords" content="">'."\n".
        '<meta name="description" content="">'."\n".
        '<meta name="robots" content="noindex, nofollow">'."\n".
        '<link rel="stylesheet" href="css/html5reset.css?ver20190624">'."\n".
        '<link rel="stylesheet" href="css/magnific-popup.css">'."\n".
        '<link rel="stylesheet" href="css/common.css?ver20190624">'."\n".
        '<link rel="stylesheet" media="print" href="css/print.css?ver20190624">'."\n".
        '<link rel="stylesheet" href="webfont/awesome/css/font-awesome.min.css?ver20190624">'."\n".
        '<meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1" />'."\n".
        '<meta name="format-detection" content="telephone=no">'."\n".
        '<script src="js/jquery-1.12.0.min.js"></script>'."\n".
        '<script src="js/script.js"></script>'."\n".
        '<script src="js/jquery.magnific-popup.min.js"></script>'."\n".
        '</head>'."\n".
        '<body>'."\n".
        '<div id="wrap">'."\n";
	}
	
	//◆ヘッダTOP
	function HeaderTop($strContent = "TELEBOAT WELCOME CAMPAIGN 2019年4月にテレボート会員になった方限定！")
	{
        header('Content-type: text/html; charset=UTF-8');
        return 
        '<html lang="ja">'."\n".
        '<head>'."\n".
        '<!-- Global site tag (gtag.js) - Google Analytics -->'."\n".
        '<script src="js/analytics.js"></script>'."\n".
        '<meta charset="UTF-8">'."\n".
        '<meta property="og:title" content="'.$strContent.'">'."\n".
        '<meta property="og:type" content="website">'."\n".
        '<meta property="og:url" content="">'."\n".
        '<meta property="og:site_name" content="'.$strContent.'">'."\n".
        '<meta property="og:description" content="'.$strContent.'">'."\n".
        '<meta property="og:image" content="snsthamnail.jpg">'."\n".
        '<meta name="keywords" content="" />'."\n".
        '<meta name="description" content="" />'."\n".
        '<meta name="robots" content="noindex, nofollow">'."\n".
        '<meta content="86400" http-equiv="Expires" >'."\n";
	}
	
	//◆ヘッダTOP
	function HeaderTop2020($strContent = "")
	{
        header('Content-type: text/html; charset=UTF-8');
        return 
        '<!DOCTYPE HTML>'."\n".
        '<html lang="ja">'."\n".
        '<head>'."\n".
        '<!-- Global site tag (gtag.js) - Google Analytics -->'."\n".
        '<script src="js/analytics.js"></script>'."\n".
        '<meta charset="UTF-8">'."\n".
        '<meta property="og:title" content="'.$strContent.'">'."\n".
        '<meta property="og:type" content="website">'."\n".
        '<meta property="og:url" content="">'."\n".
        '<meta property="og:site_name" content="'.$strContent.'">'."\n".
        '<meta property="og:description" content="'.$strContent.'">'."\n".
        '<meta name="keywords" content="" />'."\n".
        '<meta name="description" content="" />'."\n".
        '<meta name="robots" content="noindex, nofollow">'."\n";
	}
	
	//◆ヘッダTOP スマートフォン
	function HeaderTopSp($strContent = "TELEBOAT WELCOME CAMPAIGN 2019年4月にテレボート会員になった方限定！")
	{
        header('Content-type: text/html; charset=UTF-8');
        return 
        '<!DOCTYPE HTML>'."\n".
        '<html lang="ja">'."\n".
        '<head>'."\n".
        '<!-- Global site tag (gtag.js) - Google Analytics -->'."\n".
        '<script src="../js/analytics.js"></script>'."\n".
        '<meta charset="UTF-8">'."\n".
        '<meta property="og:title" content="'.$strContent.'">'."\n".
        '<meta property="og:type" content="website">'."\n".
        '<meta property="og:url" content="">'."\n".
        '<meta property="og:site_name" content="'.$strContent.'">'."\n".
        '<meta property="og:description" content="'.$strContent.'">'."\n".
        '<meta property="og:image" content="snsthamnail.jpg">'."\n".
        '<meta name="keywords" content="" />'."\n".
        '<meta name="description" content="" />'."\n".
        '<meta name="robots" content="noindex, nofollow">'."\n".
        '<meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1, maximum-scale=1">'."\n".
        '<meta name="format-detection" content="telephone=no">'."\n";
        //'<title>'.$strPageTitle.'</title>'."\n".
        //'<link rel="stylesheet" href="css/style.css?ver20190624">'."\n".
        //'<link rel="stylesheet" href="css/common.css?ver20190624">'."\n".
        //'<link rel="stylesheet" href="css/acordion.css?ver20190624">'."\n".
        //'<link href="css/drawer.min.css?ver20190624" rel="stylesheet">'."\n".
        //'<script src="js/jquery-2.1.1.js"></script>'."\n".
        //'</head>'."\n";
	}
	
	//◆ヘッダTOP スマートフォン
	function HeaderTopSp2020($strContent = "")
	{
        header('Content-type: text/html; charset=UTF-8');
        return 
        '<!DOCTYPE HTML>'."\n".
        '<html lang="ja">'."\n".
        '<head>'."\n".
        '<!-- Global site tag (gtag.js) - Google Analytics -->'."\n".
        '<script src="../js/analytics.js"></script>'."\n".
        '<meta charset="UTF-8">'."\n".
        '<meta property="og:title" content="'.$strContent.'">'."\n".
        '<meta property="og:type" content="website">'."\n".
        '<meta property="og:url" content="">'."\n".
        '<meta property="og:site_name" content="'.$strContent.'">'."\n".
        '<meta property="og:description" content="'.$strContent.'">'."\n".
        '<meta name="keywords" content="" />'."\n".
        '<meta name="description" content="" />'."\n".
        '<meta name="robots" content="noindex, nofollow">'."\n".
        '<meta name="viewport" content="width=320, user-scalable=no, initial-scale=1, maximum-scale=1">'."\n".
        '<meta name="format-detection" content="telephone=no">'."\n".
        '<meta name="theme-color" content="#59b7ff">'."\n".
        '<meta name="apple-mobile-web-app-status-bar-style" content="#59b7ff">'."\n";
	}
	
	//◆フッター
	function Footer($intYear = 2019)
	{
        return 
        '<footer>'."\n".
        '<p class="copyright">Copyright '.$intYear.' TELEBOAT All Rights Reserved.</p>'."\n".
        '</footer>'."\n".
        ''."\n".
        '<div class="totop"><a href="#top"><i class="fa fa-angle-up"></i></a></div>'."\n".
        ''."\n".
        ''."\n".
        '</div><!--/#wrap-->'."\n".
        ''."\n".
        ''."\n".
        ''."\n".
        '</body>'."\n".
        '</html>'."\n";
	}
	
	//◆フッターTOP
	function FooterTop()
	{
        return 
        '</body>'."\n".
        '</html>'."\n";
	}
	
	//◆フッターTOP スマートフォン
	function FooterTopSp()
	{
        return 
        '</body>'."\n".
        '</html>'."\n";
	}
	//◆上へ
	function LinkToTop()
	{
		return '<div style="text-align:right; font-size:xx-small;" align="right"><a href="#TOP">▲上へ</a></div>'."\n";
	}
	
	
	//◆homeへ
	function LinkToHome()
	{
		return 
		CViewMP::Line()."\n".
		CViewMP::Space()."\n".
		'<span style="font-size:xx-small;"><a href="index.php" accesskey="0">'.Mark($pszType='134', $pszColor = '').'●●TOPへ</a></span>'."\n";
	}
	
		
	//◆タイトル
	function Title($pszTitle, $pszColorBG='#FFFFFF', $pszColorLine='#e9546a')
	{
		return CViewMP::Line($pszColorLine)."\n".
		'<div style="font-size:small; background:'.$pszColorBG.'; color:'.$pszColorLine.';">'.$pszTitle.'</div>'."\n".
		CViewMP::Line($pszColorLine)."\n";
	}

	//◆共通：ライン
	function Line($pszColor = '#a31f24', $iWidth = 1, $iHeight = 1)
	{
		return '<div style="background-color:'.$pszColor.'; font-size:1px;">'.CViewMP::SpaceImg($iWidth, $iHeight).'<br /></div>';
	}
	
	//◆空白
	function Space($iWidth = 1, $iHeight = 1)
	{
		return '<div>'.CViewMP::SpaceImg($iWidth, $iHeight).'</div>';
	}
	function SpaceImg($iWidth = 1, $iHeight = 1)
	{
		return '<img src="../../img/spacer.gif" width="'.$iWidth.'" height="'.$iHeight.'" />';
	}
	
	//◆画像変換
	//$pszFileName：拡張子なしのファイル名　　[$pszFileName].png
	//docomo：gif,その他：png
	function MPImg($pszFileName)
	{
		return $pszFileName.'.'.(CModel::IsDocomo()? 'gif': 'png');
	}
}

//管理者ページ用
class CViewManagerTop
{
    function __construct()
    {
        return true;
    }
    function __destruct()
    {
        return true;
    }
    
    //◆ヘッダ
    function Header($pszPageTitle, $strMenu, $intFixedMidashi = 0)
    {
        $aryActive = array();
        $aryActive[$intMenuID] = ' class="active"';
        
        $strString = 
        '<!DOCTYPE HTML>'."\n".
        '<html lang="ja">'."\n".
        '<head>'."\n".
        '<meta charset="UTF-8">'."\n".
        '<meta name="viewport" content="width=device-width, initial-scale=1">'."\n".
        '<title>'.$pszPageTitle.' | 管理画面</title>'."\n".
        '<meta name="robots" content="noindex,nofollow">'."\n".
        '<meta name="keywords" content="">'."\n".
        '<meta name="description" content="">'."\n".
        '<meta name="copyright" content="Copyright 2019 TELEBOAT ALL Right Reserved.">'."\n".
        '<link rel="stylesheet" href="css/html5reset.css?ver20190624">'."\n".
        '<link rel="stylesheet" href="css/common.css?ver20190624">'."\n".
        '<link rel="stylesheet" href="webfont/awesome/css/font-awesome.min.css">'."\n".
        '<link rel="stylesheet" href="css/jquery.mCustomScrollbar.css">'."\n".
        '</head>'."\n";
        
        if ($intFixedMidashi == 1) {
            $strString .= '<body onLoad="FixedMidashi.create();">'."\n";
        } else {
            $strString .= '<body>'."\n";
        }
        
        $strString .= 
        '<div id="wrap">'."\n".
        ''."\n".
        '<header>'."\n".
        '<div class="hd"><h1><a href="">テレボートキャンペーン管理システム</a></h1>'."\n".
        '<p class="copy">'.$_SESSION['twm']['name'].'としてログイン中</p>'."\n".
        '<p class="logout"><a href="logout.php">ログアウト</a></p></div>'."\n".
        '<nav class="global">'."\n".
        '<ul>'."\n".
        $strMenu.
        '</ul>'."\n".
        '</nav>'."\n".
        '<script src="js/jquery-1.12.0.min.js"></script>'."\n".
        '<script src="js/script.js"></script>'."\n".
        '<script src="js/jquery.mCustomScrollbar.concat.min.js"></script>'."\n".
        //'<script src="js/jquery.autoKana.js" language="javascript" type="text/javascript"></script>'."\n".
        '</header>'."\n";
        
        return $strString;
    }
    
    //◆フッター
    function Footer($bolFooterVisibleFlag = true)
    {
        if ($bolFooterVisibleFlag) {
            $strFooter = 
            '<footer>'."\n".
            '<p>Copyright TELEBOAT All Rights Reserved.</p>'."\n".
            '</footer>'."\n";
        }
        
        $strFooter .= 
        ''."\n".
        '</div><!--/wrapper-->'."\n".
        ''."\n".
        '<script>'."\n".
        '    (function($){'."\n".
        '        $(window).on("load",function(){'."\n".
        '            '."\n".
        '            $("#side").mCustomScrollbar({'."\n".
        '                theme:"minimal"'."\n".
        '            });'."\n".
        '            '."\n".
        '        });'."\n".
        '    })(jQuery);'."\n".
        '</script>'."\n".
        '</body>'."\n".
        '</html>'."\n";
        
        return $strFooter;
    }
}
?>