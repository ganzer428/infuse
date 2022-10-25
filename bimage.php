<?php

include_once("core.php");


header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("HTTP/1.1 200 OK");
header("Status: 200 OK");


$agent = $_SERVER['HTTP_USER_AGENT'];
$url = $_SERVER['HTTP_REFERER'];
$ip =  "127.0.0.0";
foreach (array("HTTP_X_FORWARDED_FOR", "HTTP_CF_CONNECTING_IP", "REMOTE_ADDR") as $var)
{
    $x = ($_SERVER[$var] ?? false);
    if (!empty($x) && filter_var($x, FILTER_VALIDATE_IP))
        $ip = $x;
}

$id = md5("$ip\t$agent\t$url");
$ukey = md5($url);
// Use classes/DB.php
DB::put("stat", $id, "ip_address", $ip, "user_agent", $agent, "page_url", $url, "page_md5", $ukey, "view_date", time());
DB::sql("UPDATE stat SET views_count = views_count + 1 WHERE id = ?", $id);
if(0)
{
    // For test
    header('Content-type: text/plain');
    echo <<<EOF
$ip
$url
$agent
EOF;
    exit;
}
else
{
    header('Content-type: image/png');
    echo file_get_contents("images/banner.png");
}
