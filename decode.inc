<?php

/**
 * タグ無効化の処理
 * @param string $str
 * @return string $str
 */
function convstr($str){
	$str = htmlspecialchars($str);	// タグを取り除く
	$str = stripslashes($str);		// 不要な「\」を取り除く
	$str = ereg_replace(",", "&#44;", $str); // 文字列内のコンマを変換する
	$str = ereg_replace("\r|\n|\r\n", "<br />", $str); // 文字列内の改行を<br />タグに変換する
	return $str;
}

/**
 * URLの自動リンク
 * @param string $str
 * @return string $str
 */
function autoLinker($str)
{
	$pat_sub = preg_quote('-._~%:/?#[]@!$&\'()*+,;=', '/'); // 正規表現向けのエスケープ処理
	$pat  = '/((http|https):\/\/[0-9a-z' . $pat_sub . ']+)/i'; // 正規表現パターン
	$rep  = '<a href="\\1">\\1</a>'; // \\1が正規表現にマッチした文字列に置き換わります
	$str = preg_replace ($pat, $rep, $str); // 実処理
	return $str;
}

/**
 * テンプレート読み込み
 * @param string $template
 * @return string $template_line
 */
function read_template($template) {
	$template_line = "";
	$fp = fopen("$template","r");
	while(!feof($fp)) {
		$line = fgets($fp, 4096);
		$template_line = $template_line . $line;
	}
	fclose($fp);
	return($template_line);
}

 
/**
 * 部分一致
 * $haystackの中に$needleが含まれているか判定します。
 * @param string $haystack
 * @param string $needle
 * @return boolean
 */
function matchesIn($haystack, $needle) {
    return strpos($haystack, $needle) !== FALSE;
}

?>
