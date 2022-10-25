<?php

class ENV
{
    static $data = false;
    static $block = false;

    public static function set($var, $val, $add = false)
    {
        if ($var == (self::$data['block'] ?? 'NOPE'))
        {
            self::$block = $val;
            return;
        }

        if (self::$data['block'] ?? false)
        {
            if ($add && isset(self::$data["blocks"][self::$block][$var]))
                self::$data["blocks"][self::$block][$var] .= "\n";
            else
                self::$data["blocks"][self::$block][$var] = "";
            self::$data["blocks"][self::$block][$var] .= $val;
        } else
        {
            if ($add && isset(self::$data[$var]))
                self::$data[$var] .= "\n";
            else
                self::$data[$var] = "";
            self::$data[$var] .= $val;
        }
    }

    public static function init()
    {
        self::$block = false;

        if (is_array(self::$data))
            return;

        self::$data = array('HOME' => ROOT_DIR);
        if (SYS::test_server())
            $efn = '/.env.test';
        else
            $efn = '/.env';

        $fn = ROOT_DIR . "/$efn";
        $envFile = file_get_contents($fn);
        $eofkey = false;
        foreach (explode("\n", $envFile) as $row)
        {
            $row = trim(preg_replace('/#.*/', '', $row));
            if (preg_match('/^EOF\;*$/', $row))
            {
                $eofkey = false;
                continue;
            }

            if ($eofkey)
            {
                self::set($eofkey, $row, true);
                continue;
            }

            if(!preg_match("/\S/", $row))
                continue;
            $eqPos = strpos($row, '=');
            if ($eqPos === false)
            {
                $arr = ssplit($row);
                list($key) = array_splice($arr, 0, 1);
                $row = implode(' ', $arr);
            } else
            {
                $key = substr($row, 0, $eqPos);
                $row = substr($row, $eqPos + 1);
            }
            $key = trim2($key);
            $row = trim2($row);
            if (preg_match('/^\<*EOF$/', $row))
            {
                $eofkey = $key;
                continue;
            }
            self::set($key, $row);
        }

        foreach (self::$data as $key => $row)
        {
            if(is_array($row))
                continue;
            foreach (self::$data as $xvar => $xval)
            {

                $row = str_replace('${' . $xvar . '}', $xval, $row);
                $row = str_replace('{{' . $xvar . '}}', $xval, $row);
                $row = str_replace('$' . $xvar, $xval, $row);
            }
            $row = preg_replace('/\{\{[A-Za-z\d_]*\}\}/', '', $row);
            $row = preg_replace('/\$\{[A-Za-z\d_]*\}/', '', $row);
            $row = preg_replace('/\$[A-Za-z\d_]*/', '', $row);
            self::$data[$key] = trim($row);
        }
    }

    public static function get($var)
    {
        self::init();
        return self::$data[$var] ?? null;
    }

    public static function render($str)
    {
        self::init();

        return RENDER::process($str, self::$data);
    }

}
