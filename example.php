<?php

require_once __DIR__.DIRECTORY_SEPARATOR."vendor".DIRECTORY_SEPARATOR."autoload.php";

use dinist\php_excel_image_crawler\ImageCrawler;

$imageCrawl = new ImageCrawler();
$imageCrawl->run();