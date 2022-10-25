<?php

include_once("core.php");

foreach(DB::getColumnsWhere("stat", "*") as $arr)
    SYS::dump($arr);