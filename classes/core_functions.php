<?php

function trim2($x)
{
    $x = preg_replace('/^\s+/', '', $x);
    $x = preg_replace('/\s+$/', '', $x);
    $x = preg_replace('/\s+/', ' ', $x);
    return $x;
}

function cget($var, $to = 86400)
{
    DB::xCheck("cache");
    return DB::xGet("cache", $var, $to);
}

function cput($var, $val)
{
    DB::xCheck("cache");
    return DB::xPut("cache", $var, $val);
}

function pg($pvar = null)
{
    global $HTTP_POST_VARS, $HTTP_GET_VARS, $_POST, $_GET;

    if ($rq = $_SERVER["REDIRECT_QUERY_STRING"])
    {
        parse_str($rq, $HTTP_GET_VARS);
    }
    $pp = array();
    foreach (array($HTTP_POST_VARS, $HTTP_GET_VARS, $_POST, $_GET) as $arr)
    {
        foreach ($arr as $var => $val)
            $pp[$var] = $val;
    }
    return ($pvar ? ($pp[$pvar] ?? false) : $pp);
}

function tpg($var)
{
    return trim2(pg($var));
}

function f2($x)
{
    return sprintf("%.2f", $x);
}

function nsplit2($str)
{
    $xx = nsplit($str);
    $aa = array();
    for ($n = 0; $n < count($xx); $n += 2)
        $aa[$xx[$n]] = $xx[$n + 1];
    return ($aa);
}

function nsplit($str)
{
    if (!preg_match("/\S/", $str))
        return (array());
    $str = preg_replace("/^\s+/", "", $str);
    $str = preg_replace("/\s+$/", "", $str);
    return (preg_split("/[ \t\r]*\n[ \t\r]*/", $str));
}

function ssplit($str)
{
    if (!preg_match("/\S/", $str))
        return (array());
    $str = preg_replace("/^\s+/", "", $str);
    $str = preg_replace("/\s+$/", "", $str);
    return (preg_split("/\s+/", $str));
}

