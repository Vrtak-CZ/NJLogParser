<?php
namespace NJLogParser;

require_once __DIR__ . "/libs/nette.min.php";
require_once __DIR__ . "/libs/Curl.php";
require_once __DIR__ . "/libs/CurlResponse.php";
require_once __DIR__ . "/libs/Parser.php";

use Nette\Diagnostics\Debugger;

set_time_limit(0);
Debugger::enable(Debugger::DEVELOPMENT, __DIR__);
Debugger::timer();

/************************************ SQLite *************************************/
/*if (file_exists(__DIR__."/db.sqlite")) {
	copy(__DIR__."/db.sqlite", __DIR__."/backup/".date("Y-m-d_H-i-s-").substr(microtime(TRUE)-time(), 2, 4).".sqlite");
}
$connection = new \Nette\Database\Connection('sqlite:' . __DIR__ . '/db.sqlite');
\Nette\Database\Helpers::loadFromFile($connection, __DIR__ . '/db.structure.sqlite.sql');
/************************************ MySQL *************************************/
$connection = new \Nette\Database\Connection('mysql:host=localhost;dbname=njlogparser', 'nette', 'nette');
\Nette\Database\Helpers::loadFromFile($connection, __DIR__ . '/db.structure.mysql.sql');

if (!defined('STDIN'))
{
	echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd"><html><head>'
		.'<meta http-equiv="Content-Type" content="text/html; charset=utf-8"><title>Nette Jabber Log Parser</title></head><body><code><pre>';
}

$parser = new Parser($connection);

$parser->originalDataDir = __DIR__."/data";
//$parser->debug = TRUE;

$factory = new \Nette\Http\RequestFactory;
$factory->setEncoding('UTF-8');
$req = $factory->createHttpRequest();

if (($date = $req->getQuery('date', NULL)) || ($date = isset($argv[1])  ? $argv[1] : NULL))
	$parser->parseDate($date, TRUE);
else
	$parser->parse(10);
echo "\n\n";

$sec = $time = Debugger::timer();
$hours = (int)($sec/3600);
$sec -= $hours*3600;
$mins = (int)($sec/60);
$sec -= $mins*60;
$sec = (int)$sec;
echo "Total Time: ".round($time*1000, 2)."ms ".str_pad($hours, 2, 0, STR_PAD_LEFT).":".str_pad($mins, 2, 0, STR_PAD_LEFT)
	.":".str_pad($sec, 2, 0, STR_PAD_LEFT).".".round(($time-$sec)*1000)."\n";
echo "Memory: ".round(memory_get_usage()/1024, 2)."KB (Real Memory: ".round(memory_get_usage(TRUE)/1024, 2)."KB)\n";