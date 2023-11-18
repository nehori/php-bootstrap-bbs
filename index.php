<?php
/**
 * Copyright (c) 2023 Kazutaka Yasuda
 * Released under the MIT license
 * http://opensource.org/licenses/mit-license.php
 */
// ユーザ変更箇所 ---------------------------------------

$logfile       = "./data/bbs.dat"; // ログファイル名
$max_num       = 5;        // 一ページあたりの親記事数
$max_log       = 500;      // ログに保存する件数
$com_max       = 600;      // 一度に投稿できる最大文字数
$pass          = "1234";   // パスワード

// テンプレートファイルを指定する
$main_mdl      = "./mdl/main.mdl";
$error_mdl     = "./mdl/error.mdl";
$list_mdl      = "./mdl/list.mdl";
$respro_mdl    = "./mdl/respro.mdl";
$list_sub_mdl  = "./mdl/list_sub.mdl";

// 投稿記事内容にふさわしくない語句の禁止
$words = array('url=http','乱交','セックス','SEX','↓↓','濡れやすい','性欲');
// 投稿記事内容にふさわしくないIPアドレスの禁止
$ip_address = array('');

// -----------------------------------------------------

//ini_set('memory_limit', '512M');
require("./decode.inc"); // 定義ファイル

// テンプレートファイルを読み込む
$main_line     = read_template($main_mdl);
$list_line     = read_template($list_mdl);
$list_sub_line = read_template($list_sub_mdl);
if (phpversion() >= "4.1.0") {
	extract($_POST);
	extract($_GET);
	extract($_SERVER);
}

// URL? 以降の因数定義
if (!isset($action)) {
	$action = "";
}

switch($action) {
	case 'regist': regist($_POST["res"]); break; // データ入力処理
	case 'respro': respro($_GET["number"]); exit;  // 投稿ページ処理
	case 'delete': delete($_POST["pwd"], $_POST["no"]); break; // データ削除処理
}
main($_GET["page"]); // メイン関数
exit;

// データ入力処理
function regist($res) {
	global $max_log, $logfile, $com_max, $REQUEST_METHOD;

	// POST以外のリクエストを禁止
	if($REQUEST_METHOD != "POST"){ error("不正な投稿をしないで下さい"); }
	if (!$_POST["name"]){ error("名前が入力されていません"); }
	if (!$_POST["message"]){ error("内容が入力されていません"); }
	if (mb_strlen($_POST["message"], "UTF-8") > $com_max) { error("投稿記事の文字数が多すぎます。"); }
#	if (!matchesIn($_POST["referer"], "uguisu.skr.jp")) {  error("リファラが正しくありません。直アクセスは禁止です。");  }
	if (!checkIp($_SERVER["REMOTE_ADDR"])){ error("書き込み権限がありません。"); }
	if (!isvalidstr($_POST["message"])){ error("内容が不適切と判断されました。"); }
	if (!isvalidstr($_POST["subject"])){ error("内容が不適切と判断されました。"); }
	// URL を自動記載している
	if ($_POST["url"]){ error("内容が不適切と判断されました。"); }

	// タグ禁止処理
	$name    = convstr($_POST["name"]);
	$email   = convstr($_POST["email"]);
	$subject = convstr($_POST["subject"]);
	$message = convstr($_POST["message"]);
	$url     = convstr($_POST["url"]);
	// 時間取得
	$week    = array("日", "月", "火", "水", "木", "金", "土");
	$time    = time()+9*60*60;
	$date    = gmdate("Y年m月d日", $time);
	$date   .= "(" . $week[gmdate("w", $time)] . ")";
	$date   .= gmdate("H時i分s秒",$time);

	$data = NULL;
	if (file_exists($logfile)) {
		 $data = file($logfile); // データの読み込み
	}
	if (sizeof($data) < 1) {	 // データにNoを付けて配列に格納
		$no = 1; $nname = ""; $nmessage = ""; // 初期化
	} else {
		list($nno, $nres, $nname, $nmail, $nsubject, $ndate, $nmessage, $ncolor) = explode(",", $data[1]);
//		list($nno) = explode(",", $data[0]);
		$no = $nno + 1;
	}
	if ($name != $nname || $message != $nmessage) { // 2重投稿判定
		// 子記事
		if ($res !== 0) {
//			$subject .= "Re: ";
		}
		// 連結
		$host = $_SERVER["REMOTE_HOST"] . "(" .$_SERVER["REMOTE_ADDR"] .")";
		$new_data = implode(",", array($no, $res, $name, $mail, $subject, $date, $message, $color, $url, $host, $pwd));
		//'w+' - 読みこみ・書きこみ用にオープンします。
		$fp = fopen($logfile, "w+");
		flock($fp, LOCK_EX);
		fputs($fp, "$no\n");
		fputs($fp, "$new_data\n");

		$max = sizeof($data);
		if ($max_log <= sizeof($data)) { // 最大記録数の調整
			$max = $max_log - 1;
		}
		// 返信記事の場合、親記事を上位に持ってくる (06/07/2014)
		for ($i = 1; $i < sizeof($data); $i++) {	// 残りのデータの書きこみ
			list($nno, $nres, $ntemp) = explode(",", $data[$i]);
			if ($res == $nno) {
				fputs($fp, $data[$i]);
				unset($data[$i]);
				$max --;
				break;
			}
		}
		for ($i = 1; $i < $max; $i++) {	// 残りのデータの書きこみ
			fputs($fp, $data[$i]);
		}
		flock($fp, LOCK_UN);
		fclose($fp);
	}
}

function checkIp($remoteIp) {
	global $ip_address;
	foreach ($ip_address as $keyword) {
		if (mb_strpos($remoteIp, $keyword, 0, "UTF-8") !== false) {
			return false;
		}
	}
	return true;
}

// 禁止ワードチェック
function isvalidstr($str) {
	global $words;
	foreach ($words as $keyword) {
		if (mb_strpos($str, $keyword, 0, "UTF-8") !== false) {
			return false;
		}
	}
	return true;
}

// データ削除処理
function delete($pwd, $no) {
	global $pass, $logfile;
	if ($pwd != $pass) {
		error("パスワードが違います !");
	}

	if (file_exists($logfile) && ($no != "")) {
		$data = file($logfile);
		$fp = fopen($logfile, "w");
		flock($fp, LOCK_EX);
		fputs($fp, $data[0]);
		for($i = 1; $i < count($data); $i ++){
			list($nno, $nres, $nname, $nmail, $nsubject, $ndate, $nmessage, $ncolor) = explode(",", $data[$i]);
			if ($nno != $no) {
				fputs($fp, $data[$i]);
			}
		}
		flock($fp, LOCK_UN);
		fclose($fp);
	}
}

// エラー作業
function error($msg) {
	global $error_mdl;
	
	$error_line    = read_template($error_mdl);
	$temp = str_replace("<!--MESSAGE-->" ,$msg ,$error_line);
	print $temp;	// 画面に表示する
	exit;
}

// 返信ページ
function respro($pno) {
	global $respro_mdl, $max_log, $PHP_SELF;

	$respro_line     = read_template($respro_mdl);
	$list = output($pno, 0, $max_log, $parnum);

	$respro_line = str_replace("<!--NO-->"      ,$pno     ,$respro_line);
	$respro_line = str_replace("<!--SUBJECT-->" ,$psubject,$respro_line);
	$respro_line = str_replace("<!--LIST-->"    ,$list    ,$respro_line);
	$respro_line = str_replace("<!--REFERER-->",  $_SERVER["HTTP_REFERER"],  $respro_line);
	$respro_line = str_replace("<!--PHP_SELF-->",$PHP_SELF,$respro_line);
	print $respro_line;	// 画面に表示する
	exit;
}

// メイン関数
function main($page) {
	global $max_num, $max_log, $PHP_SELF, $main_line, $list_line, $list_sub_line;

	if (!isset($next)) {
		$next = 0; // 変数がセットされているか
	}
	$start = $next * $max_num; 
	if ($page != "") {
		$start = $page;
	}

	$end = $start + $max_num;
	$list = output(0, $start, $end, $parnum);

	$main_line = str_replace("<!--LIST-->"    ,$list    ,$main_line);
	$temp = "";
	for ($i = 0; $i < ($parnum/$max_num); $i++) {
		$temp .= "<li class='active'><a href='". $PHP_SELF ."?page=" .$i * $max_num ."'>" .($i + 1) ."</a></li>";
	}
	$main_line = str_replace("<!--PAGE-->",     $temp,     $main_line);
	$main_line = str_replace("<!--REFERER-->",  $_SERVER["HTTP_REFERER"],  $main_line);
	$main_line = str_replace("<!--PHP_SELF-->", $PHP_SELF  ,$main_line);
	print "$main_line";	// 画面に表示する
	exit;
}

// 出力データ関数
function output($ppno, $start, $pend, &$parnum) {
	global $main_line, $list_line, $list_sub_line, $logfile;

	$data = NULL;
	if (file_exists($logfile)) {
		$data = file($logfile); // データの読み込み
	}
	$count = count($data);       // ファイルのデータ数
	$end = $pend;
	if ($end > $count) {
		$end = $count;
	}

	// 親記事の数を数える
	$parnum = 0;
	for ($i = 0; $i < $count; $i ++){ // 降順
		list($no, $res, $name) = explode(",", $data[$i]);
		if ($res == 0) {
			$parnum ++;
		}
	}
	// テンプレートにデータを埋め込む
	$list = "";
	$i = 0;
	$pno = $ppno; // 返信処理
	array_shift($data);
	foreach ($data as $item) {
		list($no, $res, $name, $mail, $subject, $date, $message, $color, $url, $host, $pwd) = explode(",", $item);
		if ($mail != "") { $name = "<a href=\"mailto:$mail\">$name</a>"; }
		if ($ppno == 0) {
			$pno = $no; // 返信処理
		}
		if ($res == 0 && $no == $pno) { // 親記事
			$i ++;

			if ($i <= $start) { continue; }
			if ($i > $end) { break; }
			$temp = $list_line;
			$temp = str_replace("<!--NO-->"      ,$no      ,$temp);
			$temp = str_replace("<!--NAME-->"    ,$name    ,$temp);
			$temp = str_replace("<!--MAIL-->"    ,$email   ,$temp);
			$temp = str_replace("<!--MESSAGE-->" ,autoLinker($message) ,$temp);
			$temp = str_replace("<!--SUBJECT-->" ,$subject ,$temp);
			$temp = str_replace("<!--DATE-->"    ,$date    ,$temp);
			if ($url != "") {
				$url = "<a href='$url'>$url</a>";
			}
			$temp = str_replace("<!--URL-->"     ,$url     ,$temp);
	 		$list = $list . $temp;
 			$list_sub = "";
			for ($j = $count - 1; $j >= 0; $j --){ // 降順
				list($rno, $rres, $rname, $rmail, $rsubject, $rdate, $rmessage, $rcolor, $rurl, $rhost, $rpwd) = explode(",", $data[$j]);
				if ($mail != "") { $rname = "<a href=\"mailto:$rmail\">$rname</a>"; }
				if ($rres == $pno) { // 子記事
			 		$temp = $list_sub_line;
					$temp = str_replace("<!--NO-->"      ,$rno      ,$temp);
					$temp = str_replace("<!--NAME-->"    ,$rname    ,$temp);
					$temp = str_replace("<!--MAIL-->"    ,$remail   ,$temp);
					$temp = str_replace("<!--MESSAGE-->" ,autoLinker($rmessage) ,$temp);
//					$temp = str_replace("<!--SUBJECT-->" ,$rsubject ,$temp);
					$temp = str_replace("<!--SUBJECT-->" ,"Re: " .$subject ,$temp);
					$temp = str_replace("<!--DATE-->"    ,$rdate    ,$temp);
					if ($rurl != "") {
						$url = "<a href='$rurl'>$rurl</a>";
					}
					$temp = str_replace("<!--URL-->"     ,$rurl     ,$temp);
	 				$list_sub = $list_sub . $temp;
				}
		 	}
			if ($ppno == 0) {
				$list = str_replace("<!--RESPRO-->" ,
						"<p>この記事に[<a href='?action=respro&amp;number=" .$no ."'>返信</a>]</p>",
						$list);
			}
			$list = str_replace("<!--LIST_SUB-->" ,$list_sub    ,$list);
	 	} 
	}
	return $list;
}

?>
