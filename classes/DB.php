<?php

class DB
{
    static $connected = false;
    /** @var PDO */
    static $connection;
    static $inTransaction = false;
    static $errorMsg = false;
    static $lastId = false;
    static $verbose = false;
    static $cache = array();

    /** Пишем или нет выполняемые запросы в logfile */
    public static function verbose($verbose = null)
    {
        if ($verbose !== null)
            self::$verbose = $verbose;
        return self::$verbose;
    }

    public static function init()
    {
        if (self::$connected)
            return;

        $max = 10;
        /** Пытаемся переподключитья если не вышло */
        for ($n = 0; $n < $max; usleep(500), $n++)
        {
            try
            {
                if (!($dsn = ENV::get('DB_DSN')))
                {
                    if (!($host = ENV::get('DB_HOST')))
                        $host = 'localhost';
                    $name = ENV::get('DB_NAME');
                    $dsn = "mysql:dbname=$name;host=$host";
                }
                if ((self::$connection = $x = new PDO($dsn, ENV::get('DB_USER'), ENV::get('DB_PASS'))) !== false)
                {
                    self::$connected = true;
                    self::$connection->exec("set names utf8");
                    self::$connection->exec("SET sql_mode = 'TRADITIONAL'");
                    self::$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                }
                return;
            } catch (PDOException $e)
            {
                self::$connection = false;
                SYS::log("Error opening DB: " . $e->getMessage());
            }
        }
        API::response(100, "DB connection error");
        SYS::log("Can't connect to DB after $max tries");
        exit;
    }

    public static function sql($query, $bind = false, $type = 0, $nolock = false)
    {
        if (!($query && strlen($query)))
        {
            SYS::log('Empty MySQL query');
            exit;
        }
        if ($bind !== false && $bind !== null && !is_array($bind))
            $bind = array($bind);

        self::init();
        if (!self::$inTransaction && $nolock)
        {
            self::$connection->exec("SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED ;");
        }

//        SYS::log($query);
        $stmt = self::$connection->prepare($query);
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        try
        {
            $result = $stmt->execute(is_array($bind) ? $bind : null);
        } catch (Error $e)
        {
            $err_str = 'MySQL exception: ' . $e->getMessage() . "; SQL: " . $stmt->queryString;
            SYS::log($err_str);
            self::$errorMsg = $e->getMessage();
            self::$errorMsg = $err_str;
            throw $e;
        };
        self::$errorMsg = false;

        if (!self::$inTransaction && $nolock)
        {
            self::$connection->exec("COMMIT ;");
        }
        self::$lastId = self::$connection->lastInsertId();
        switch ($type)
        {
            case 1:
                $result = $stmt->fetch();
                break;
            case 2:
                $result = $stmt->fetchAll();
                break;
            case 3:
                $result = $stmt->fetchColumn();
                break;
            case 4:
                $result = self::$lastId;
                break;
            case 5:
                /** Возвращаем просто значения без связки key -> val,
                 * если в запросе одно поле, то это 0 -> val1, 1 -> val2, ...
                 * если несколько 0 -> [ val1_1, val1_2, .. ] 1 -> [ val2_1, val2_2, ...] ...
                 */
                $result = array();
                foreach ($stmt->fetchAll() as $arr)
                {
                    $vals = array_values($arr);
                    $result[] = (count($vals) > 1 ? $vals : $vals[0]);
                }
                break;
        }

        if (stripos($query, 'DELETE') === 0 || stripos($query, 'UPDATE') === 0)
        {
            return $stmt->rowCount();
        }
        return $result;
    }

    public function lastInsertId()
    {
        return (self::$lastId);
    }

    public function lastError()
    {
        return (self::$errorMsg);
    }

    /** Подготовка строчки для использования в ручном запросе */
    public static function quote($value)
    {
        self::init();
        return (self::$connection->quote($value));
    }

    /** Находим имя первичного ключа чтобы пролучать/отправлять не задумываясь о нём */
    public static function primaryKey($table = false)
    {
        if (!$table)
            return (false);
        $cachevar = "KEY:$table";
        if (isset(self::$cache[$cachevar]))
            return (self::$cache[$cachevar]);
        $query = "SHOW KEYS FROM `$table` WHERE Key_name = 'PRIMARY'";
        $result = self::sql($query, 0, 2);
        if (isset($result[0]["Column_name"]))
            $key = $result[0]["Column_name"];
        else
            $key = false;
        return (self::$cache[$cachevar] = $key);
    }

    /** Свойства полей таблицы */
    public static function keyProperties($table)
    {
        if (!$table)
            return (false);
        $cachevar = "KEYPROPERTIES:$table:";
        if ($arr = (self::$cache[$cachevar] ?? false))
        {
            return ($arr);
        }
        $arr = array();
        $result = self::sql("DESCRIBE `$table`", 0, 2);
        foreach ($result as $parr)
            $arr[$parr["Field"]] = $parr;
        return (self::$cache[$cachevar] = $arr);
    }

    /** Получение свойства поля */
    public static function keyProperty($table, $id, $property)
    {
        if (!$table || !$id)
            return (false);
        $arr = self::keyProperties($table);
        return ($arr[$id][$property] ?? false);
    }

    /** Список полей */
    public static function keyList($table)
    {
        if (!$table)
            return (false);
        $arr = self::keyProperties($table);
        return (is_array($arr) ? array_keys($arr) : false);
    }

    /** Не помешает знать при добавлении новых записей */
    public static function keyIsAutoinc($table, $id)
    {
        return (self::keyProperty($table, $id, "Extra") === "auto_increment" ? 1 : 0);
    }

    public static function isColumnName($x)
    {
        return preg_match('/^[a-z][a-z\d_]*$/i', $x);
    }

    public static function isColumnNames($x)
    {
        $x = strtolower($x);
        foreach (ssplit($x) as $word)
        {
            if (!self::isColumnName($word))
                return (false);
            if ($word == 'as')
                return (false);
        }
        return true;
    }

    public static function columnsList($table = false, $fields = false, $raw = false)
    {
        if ($fields === false)
            $fields = self::keyList($table);
        if (!is_array($fields))
        {
            /** $fields - список полей, разделённый запятыми или пробелами */
            /** Сначала проверяем на запятые, если есть хоть одна - на пробелы не обращаем внимание */
            $arr = preg_split("/\s*,\s*/", $fields);
            if (count($arr) > 1)
                $fields = $arr;
            else
            {
                /** Если есть специальные символы, например '*' или'COUNT(x) as num' - это одно поле, иначе - список типа 'field1 field2 ...' */
                if (self::isColumnNames($fields))
                    $fields = preg_split("/\s+/", $fields);
                else
                    $fields = array($fields);
            }
            /** Избавляемся от дублей если есть */
            $fieldArr = array();
            foreach ($fields as $field)
            {
                if (strlen($field))
                    $fieldArr["$field"] = 1;
            }
            if (!count($fieldArr))
                return (false);

            if (!$raw)
                return array_keys($fieldArr);
            $fields = array();
            foreach ($fieldArr as $var => $val)
                $fields[] = str_replace('`', '', $var);
            return $fields;
        }

        foreach ($fields as $num => $field)
        {
            /** Специальные запросы типа count() пропускаем */
            /** Остальное закавычиваем как положено */
            if (self::isColumnName($field))
                $fields[$num] = "`$field`";
        }
        return ($fields);
    }

    /** Получаем значения полей по условию
     * $fields = false - все поля
     * $fields : array - поля из списка
     * $fields : строка - имена полей из строи разделенные пробелмаи или запятымии
     */
    public static function getColumnsWhere($table, $fields = false, $where = false, $bind = false)
    {
        if (!$where)
            $where = 1;
        if(!is_array($bind))
            $bind = ($bind === false ? array() : array($bind));
        $fields = self::columnsList($table, $fields);
        if ($fields === false)
            return (array());

        $fields = implode(", ", $fields);
        if (self::isColumnName($table))
            $table = "`$table`";
        $sql = "SELECT $fields from $table WHERE $where";
        $result = self::sql($sql, $bind, 2);
        return ($result);
    }

    /** То же самое, но для одного поля, возвращает просто массив из одиночных значений одного поля */
    public static function getColumnWhere($table, $field = false, $where = false, $bind = [])
    {
        if (!$where)
            $where = 1;
        $result = self::getColumnsWhere($table, $field, $where, $bind);
        $values = array();
        foreach ($result as $arr)
            list($values[]) = array_values($arr);
        return ($values);
    }

    public static function getCountWhere($table, $where = false, $bind = [])
    {
        if (!$where)
            $where = 1;
        $sql = "SELECT count(1) from $table WHERE $where";
        return self::sql($sql, $bind, 3);
    }

    public static function keyCond($table, $key = false)
    {
        $conds = array();
        if (!is_array($key))
        {
            $idvar = self::primaryKey($table);
            if (!$idvar)
                return (false);
            $key = array($idvar => $key);
        }
        foreach ($key as $var => $val)
        {
            $qval = self::quote($val);
            $conds[] = "`$var` = $qval";
        }
        return '(' . implode(' AND ', $conds) . ')';
    }

    /** Значения полей одной записи по primary key в виде массива: column => value
     * $key - primary id или массив ключевых полей
     */
    public static function getColumns($table, $fields, $key = false)
    {
        $fields = self::columnsList($table, $fields);
        if ($fields === false)
            return (array());
        if (!$key)
            return (array());
        $cond = self::keyCond($table, $key);
        if (!$cond)
            return (array());
        $result = self::getColumnsWhere($table, $fields, $cond);
        if (is_array($result) && count($result))
            return ($result[0]);

        /** Now fill with false so extract() works */
        $emptyArr = array();
        foreach ($fields as $field)
        {
            if (strlen($field))
                $emptyArr[trim($field, '` ')] = false;
        }
        return ($emptyArr);
    }

    /** Одно поле одной записи - скаляр */
    public static function getColumn($table, $field, $key)
    {
        $result = self::getColumns($table, $field, $key);
        return ($result ? $result[$field] : false);
    }

    /** Генерируем уникальный ID для таблиц с текстовым ключом */
    public static function newId()
    {
        return sprintf("%06x%04x%04x", time(), getmypid(), rand(0, 65500));
    }

    /** Изменение/добавление записи по уникальному ключу $key
     * $fields - ассоциативный массив поле -> значение
     * $key - primary key ID или массив column => value ...
     * если $key не указан - запись добавляется
     * Возвращает ID новой/обновлённой записи
     */
    public static function setColumns($table, $fields = false, $key = false)
    {
        $idvar = self::primaryKey($table);
        $key_cond = self::keyCond($table, $key);
        $key_type = self::keyProperty($table, $idvar, "Type");
        $ivars = $ivals = $sets = '';
        if (!$fields)
            $fields = array();

        if (is_array($key))
        {
            /** Для ситуации когда присваиваем column1 = value1 по ключу column2 = value2, а записи с таким ключом еще нет */
            foreach ($key as $var => $val)
            {
                if (!isset($fields[$var]))
                    $fields[$var] = $val;
            }
        } else
        {
            if (isset($fields[$idvar]))
                unset($fields[$idvar]);
        }
        $auto_inc = self::keyIsAutoinc($table, $idvar);

        /** Подставляем значения для полей, у которых нет default */
        $ifields = $fields;
        foreach (self::keyProperties($table) as $var => $arr)
        {
            if (isset($ifields[$var]) || $var == $idvar)
            {
                /** На случай если для datetime передан timestamp */
                if (strstr($arr["Type"], "datetime") && is_int($ifields[$var]))
                    $ifields[$var] = date('Y-m-d H:i:s', $ifields[$var]);
                continue;
            }
            if (strlen($arr["Default"]))
                continue;
            if (strstr($arr["Type"], "int") || strstr($arr["Type"], "float") || strstr($arr["Type"], "double"))
                $ifields[$var] = 0;
            else if (strstr($arr["Type"], "datetime"))
                $ifields[$var] = date('Y-m-d H:i:s');
            else
                $ifields[$var] = "";
        }

        $sets = $allsets = '';
        foreach ($ifields as $var => $val)
        {
            if ($ivars)
                $ivars .= ', ';
            $ivars .= "`$var`";
            $val = self::quote($val);
            if ($ivals)
                $ivals .= ', ';
            $ivals .= "$val";

            if ($allsets)
                $allsets .= ', ';
            $allsets .= "`$var`= $val";
            if (!isset($fields[$var])) continue;
            if ($sets)
                $sets .= ', ';
            $sets .= "`$var`= $val";
        }

        $id = false;
        $do_insert = false;
        /** Если записи с таким ключом нет, обновлять нечего */
        if ($key && self::getColumn($table, $idvar, $key))
        {
            /** Запись уже есть - обновляем */
            if (!$sets)
                return (false);
            $sql = "UPDATE `$table` SET $sets WHERE $key_cond";
        } else
        {
            $do_insert = true;
            /** Добавляем новую */
            if ($auto_inc)
            {
                /** autoinc - id не нужен, вставляем данные или просто пустую запись с новым ID */
                if ($ivals)
                    $sql = "INSERT INTO `$table` ($ivars) VALUES ($ivals)";
                else
                    $sql = "INSERT INTO `$table` (`$idvar`) VALUES(NULL)";


            } else
            {
                if ($key && !is_array($key))
                {
                    $id = $key;
                } else
                {
                    /** Нет id и не auto_inc,
                     * если ключ числовой - берём следующий после максимального,
                     * если текстовый- генерируем случайный
                     */
                    if (stristr($key_type, "char"))
                        $id = self::newId();
                    else
                    {
                        list($arr) = self::getColumnsWhere($table, $idvar, "1 ORDER BY $idvar DESC LIMIT 0,1");
                        $id = intval($arr[$idvar] ?? time()) + 1;
                    }
                }
                /** Вставляем запись с этим ID */
                if ($allsets)
                {
                    if ($ivals)
                        $sql = "INSERT INTO `$table` (`$idvar`, $ivars) VALUES ('$id', $ivals)  ON DUPLICATE KEY UPDATE $allsets";
                    else
                        $sql = "INSERT INTO `$table` (`$idvar`) VALUES ('$id')  ON DUPLICATE KEY UPDATE $allsets";
                } else
                    $sql = "INSERT INTO `$table` (`$idvar`) VALUES ('$id')";
            }
        }

        if ($id === false)
            $id = $key;
        $last_id = self::sql($sql, false, 4);
        if ($auto_inc && $do_insert && $last_id !== false)
            $id = $last_id;
        return ($id);
    }

    /** Операции с полем extra_data или heap, в котором в виде JSON можем хранить что угодно */
    public static function extraColumn($table)
    {
        foreach (array("extra_data", "heap") as $var)
        {
            if (self::keyProperty($table, $var, "Type") !== false)
                return ($var);
        }
        return false;
    }

    public static function getExtra($table, $id = false)
    {
        if (!$id)
            return false;
        $var = self::extraColumn($table);
        return ($var ? json_decode(self::getColumn($table, $var, $id)) : false);
    }

    public static function setExtra($table, $id = false, $data = false)
    {
        if (!$id || !$data)
            return false;
        $var = self::extraColumn($table);
        return ($var ? self::setColumns($table, array($var => json_encode($data)), $id) : false);
    }

    public static function getExtraVal($table, $id = false, $var = false)
    {
        if (!$id || !$var)
            return false;
        $data = self::getExtra($table, $id);
        return ($data[$var] ?? false);
    }

    public static function setExtraVal($table, $id = false, $var = false, $val = false)
    {
        if (!$id || !$var)
            return false;
        $data = self::getExtra($table, $id);
        $data[$var] = $val;
        return self::setExtra($table, $id, $data);
    }

    /** Удаляем записи */
    public static function delRecordsWhere($table, $where = false)
    {
        if (!$where)
            return (false);
        $sql = "DELETE FROM `$table` WHERE  $where";
        return self::sql($sql, false, 4);
    }

    /** Удаляем запись по primary key id */
    public static function del($table, $id = false)
    {
        if (!$id)
            return (false);
        $idvar = self::primaryKey($table);
        return self::delRecordsWhere($table, "`$idvar` = '$id'");
    }

    public static function count($table, $where = 1, $bind = false)
    {
	return self::getCountWhere($table, $where, $bind);
    }

    public static function get($table, $key, $field)
    {
        return self::getColumn($table, $field, $key);
    }

    public static function gets($table, $key, $fields)
    {
        return array_values(self::getColumns($table, $fields, $key));
    }

    public static function getss($table, $key, $fields)
    {
        $ff = self::columnsList($table, $fields);
        $arr = self::getColumns($table, $fields, $key);
        foreach ($ff as $field)
        {
            if (!isset($arr[$field]))
                $arr[$field] = false;
        }
        return $arr;
    }

    public static function getw($table, $field, $where = 1, $bind = false)
    {
        return array_values(self::getColumnWhere($table, $field, $where, $bind));
    }

    public static function getc($table, $where = 1, $bind = false)
    {
        return self::getCountWhere($table, $where, $bind);
    }

    public static function getsw($table, $fields, $where = 1, $bind = false)
    {
        $result = array();
        foreach (self::getColumnsWhere($table, $fields, $where, $bind) as $arr)
        {
            $result[] = array_values($arr);
        }
        return $result;
    }

    public static function put($table, $key = false, ...$fields)
    {
        if (count($fields) == 1)
            /** $fields is one array param */
            $data = $fields[0];
        else
        {
            /** $fields are few parameters $var1, $val1, .... */
            $data = array();
            for ($n = 0; $n < count($fields); $n += 2)
                $data[$fields[$n]] = $fields[$n + 1];
        }
        self::setColumns($table, $data, $key);
    }

    public static function tableExists($table)
    {
        if (!isset(self::$cache["TABLES"]))
        {
            foreach (self::tables() as $name)
                self::$cache["TABLES"][$name] = true;
        }
        return (self::$cache["TABLES"][$table] ?? false);
    }

    public static function tables()
    {
        $arr = array();
        $xx = self::sql("SHOW TABLES", 0, 2);
        foreach ($xx as $zz)
        {
            foreach ($zz as $var => $val)
                $arr[] = $val;
        }
        return $arr;
    }

    public static function tableDrop($table)
    {
        if (self::tableExists($table))
            self::sql("DROP TABLE $table");
        self::$cache["TABLES"][$table] = false;
    }


    public static function tableCheck($table, $spec)
    {
        if (!self::tableExists($table))
	    self::sql("create table `$table`
(
$spec
)");
        self::$cache["TABLES"][$table] = true;
    }

    public static function tableCheck2($table, $spec)
    {
        self::tableDrop($table);
        self::tableCheck($table, $spec);
    }

    public static function dump($table)
    {
        SYS::dump(self::getColumnsWhere($table, "*"));
    }

    public static function xCheck($table)
    {
        DB::tableCheck($table, "
x_id varchar(63) NOT NULL,
x_ndx varchar(255),
x_data longtext,
x_time int,
INDEX (x_ndx),
INDEX (x_time),
PRIMARY KEY (x_id)
");
    }

    public static function xGet($table, $id, $timeout = 86400)
    {
        $t0 = time() - $timeout;

        $id = urlencode($id);
        list($data, $t) = self::gets($table, $id, "x_data x_time");
//        if (intval($t) < $t0)             return false;
        return json_decode($data, 1);
    }

    public static function xPut($table, $id, $arr, $ndx = '')
    {
        $data = json_encode($arr);
        $id = urlencode($id);
        self::put($table, $id, array("x_data" => $data, "x_time" => time(), "x_ndx" => $ndx));
    }

    public static function xDel($table, $id)
    {
        $id = urlencode($id);
        self::del($table, $id);
    }

    public static function xGetVar($table, $id, $var, $timeout = 86400)
    {
        $arr = self::xGet($table, $id, $timeout);
        return ($arr[$var] ?? false);
    }

    public static function xSetVar($table, $id, $var, $val)
    {
        $arr = self::xGet($table, $id);
        $arr[$var] = $val;
        self::xPut($table, $id, $arr);
    }

    public static function xDelVar($table, $id, $var, $val)
    {
        $arr = self::xGet($table, $id);
        unset($arr[$var]);
        self::xPut($table, $id, $arr);
    }

    public static function xFlush($table, $timeout = 86400)
    {
        $t0 = time() - $timeout;
        self::sql("DELETE FROM $table WHERE x_time < $t0");
    }

    public static function kvars($tbl, $id, $str, $prefix = '')
    {
        $ss = $vv = array();
        foreach (ssplit($str) as $var)
        {
            $nvar = $prefix . preg_replace("/^x_/", '', $var);
            if (strtolower($prefix) !== $prefix)
                $nvar = strtoupper($nvar);
            $ss[] = "`$var` as `$nvar`";
            $vv[$nvar] = 1;
        }
        $sstr = implode(',', $ss);
        $arr = DB::getColumns($tbl, $sstr, $id);
        foreach ($vv as $var)
        {
            if (!isset($arr[$var]))
                $arr[$var] = null;
        }
        return $arr;
    }

    public static function exists($table, $id)
    {
        return self::get($table, $id, self::primaryKey($table));
    }

}



