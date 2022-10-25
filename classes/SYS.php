<?php

class SYS
{
    static $verbose = false;
    static $intlog = 0;
    static $errlog = 0;
    static $NID = 0;

    function remote_addr()
    {
        foreach (array("HTTP_X_FORWARDED_FOR", "HTTP_CF_CONNECTING_IP", "REMOTE_ADDR") as $var)
        {
            $addr = ($_SERVER[$var] ?? false);
            if (!empty($addr) && filter_var($addr, FILTER_VALIDATE_IP))
                return $addr;
        }
        return "127.0.0.0";
    }


    function urlcheck()
    {
        $host = $_SERVER["HTTP_HOST"];
        if (strstr(ENV::get('WEB'), "https") && !$_SERVER["HTTPS"])
        {
            $path = $_SERVER["REQUEST_URI"];
            $path = preg_replace('@^/+@', '', $path);

            JS::go("https://$host/$path");
        }
    }

    /** Проверка, что мы на тестовом сервере, в частности чтобы не слать e-mail клиентам
     * или использовать другие proxy
     */
    public static function test_server()
    {
        return (is_dir("/www/jukebox.au.nu"));
    }

    public static function verbose($set = null)
    {
        if ($set !== null)
            self::$verbose = $set;
        return self::$verbose;
    }

    // Setting log debug level
    public static function intlog($value = -1)
    {
        if ($value !== -1)
            static::$intlog = $value;
        return (static::$intlog);
    }

    public static function log($msg)
    {
        global $argv;

        $path = $_SERVER["REQUEST_URI"];
        if (empty($path))
            $path = preg_replace("@^.*/@", "", $argv[0]);
        $time = time();

        if (!($dir = ENV::get('LOG_DIR')))
            $dir = ROOT_DIR . '/log';
        if (!is_dir($dir))
            mkdir($dir, 0777);
        if (!is_dir($dir))
            die("NO ACCESS TO $dir\n");
        $logfn = sprintf("%s/%s", $dir, date("Ymd", $time));

        $x = gettimeofday();
        $msecs = sprintf(".%03d", $x["usec"] / 1000);
        $ts = date("d/m/Y H:i:s") . $msecs;
        $old = file_exists($logfn);

        $fd = fopen($logfn, "a");
        if (is_array($msg))
        {
            $amsg = preg_replace("/\n/", "<br>", print_r($msg, 1));
        } else
            $amsg = $msg;
        $smsg = preg_replace("/\s+/", " ", print_r($msg, 1));

        $pid = getmypid();
        $msg = preg_replace("@\s+$@", "", $msg);
        $tstr = "$ts\t$tag\t$pid\t$smsg\n";
        fwrite($fd, "$tstr");
        fclose($fd);
        if (!$old)
            chmod($logfn, 0666);
    }

    /** Временное отключение предупреждений если есть подозрение, что их не избежать :) */
    public static function errOff()
    {
        static::$errlog = error_reporting();
        error_reporting(0);
    }

    public static function errOn()
    {
        error_reporting(static::$errlog);
    }

    // Напечатать массив с нормальной читаемостью для web страницы
    public static function dump(...$vars)
    {
//        foreach (func_get_args() as $var)
        foreach ($vars as $var)
        {
            if (is_array($var))
            {
                if (count($var))
                {
                    echo "<pre>";
                    print_r($var);
                    echo "</pre>";
                    return;
                }
                $var = "Empty Array";
            }
            echo "<li><code>$var</code><br>";
        }
    }

    public static function myUrl()
    {
        $path = $_SERVER["REQUEST_URI"];
        if (!$path)
            $path = $_SERVER["REDIRECT_URL"];
        if (!$path)
            $path = $_SERVER["REDIRECT_REDIRECT_SCRIPT_URL"];
        return $path;
    }

    public static function myPath($depth = 1)
    {
        $path = self::myUrl();
        $path = preg_replace("@^/+@", "", $path);
        $path = preg_replace("@/+$@", "", $path);
        $path = preg_replace("/\.[^\.]*$/", "", $path);
        if (self::test_server())
            $depth++;

        while ($depth > 1)
        {
            $depth--;
            $path = preg_replace("@^[^/]+/*@", "", $path);
        }
        return ($path ? $path : "index");
    }

    public static function nid($md5 = false)
    {
        $x = sprintf("%06x%06x%04x", getmypid(), time(), ++self::$NID);
        if($md5)
            $x = md5($x);
        return $x;
    }

    public static function check_email($str)
    {
        return (preg_match("/^[A-Za-z0-9_\-\.]+@[A-z0-9_\-\.]+\.[A-z0-9_\-]+$/", $str));
    }

    public static function check_login($str)
    {
        return (preg_match("/^[A-Za-z0-9_\-\.]+$/", $str));
    }

    public static function checkImage404()
    {
        list($ext) = array_reverse(explode('.', SYS::myUrl()));
        if (stristr(" jpg jpeg gif png ", " $ext "))
        {
            header("Location: http://de.au.nu/images/spacer.gif");
            exit;
        }
        if (stristr(" ico icon ", " $ext "))
        {
            header("Location: http://de.au.nu/images/favicon.ico");
            exit;
        }

    }

    public static function jsx($arr = false)
    {
        JS::alert(print_r(($arr ? $arr : pg()), 1));
    }

    public static function xml2array($string, $fix_attrs = false)
    {
//        $xml = simplexml_load_string($string);
        $xml = simplexml_load_string($string, null, LIBXML_NOCDATA);
//        print_r($xml);
        $data = self::xml2array2($xml, $fix_attrs);
        return $data;
    }

    public static function xml2array2($obj, $fix_attrs = false)
    {
        $x = var_export($obj, 1);
        if (strstr($x, 'SimpleXMLElement'))
        {
            $out = array();
            foreach ((array)$obj as $index => $node)
            {
                $out[$index] = self::xml2array2($node, $fix_attrs);
            }
        } else {
            $out = $obj;
    }
        if ($fix_attrs && is_array($out))
        {
            foreach ($out['@attributes'] as $var => $val)
                $out[$var] = $val;
            unset($out['@attributes']);
        }
        return $out;
    }
}




