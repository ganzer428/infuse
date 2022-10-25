<?php

define('ROOT_DIR', __DIR__);

error_reporting(5);

include_once("classes/ENV.php");
include_once("classes/DB.php");
include_once("classes/SYS.php");

include_once("classes/core_functions.php");

if(empty(ENV::get("WEB")))
    die("Wrong environment");

DB::tableCheck("stat", "
id              char(63) NOT NULL,
ip_address      char(63) NOT NULL,
user_agent      longtext NOT NULL,
page_url        longtext NOT NULL,
view_date       int NOT NULL,
views_count     int NOT NULL,

page_md5        char(63) NOT NULL,  # If we need to index by page url

INDEX (ip_address),
INDEX (page_md5),
INDEX (views_count),
PRIMARY KEY (id)");

