<?php
namespace NJLogParser;

require_once __DIR__ . "/libs/loader.php";

use Nette;
use dibi;

set_time_limit(0);
Nette\Debug::enable(Nette\Debug::DEVELOPMENT);
Nette\Debug::timer();
Nette\Environment::setVariable("tempDir", __DIR__ . "/temp");
$loader = new Nette\Loaders\RobotLoader();
$loader->addDirectory(__DIR__."/libs");
$loader->register();

/************************************ SQLite *************************************/
/*if (file_exists(__DIR__."/db.sqlite"))
	copy(__DIR__."/db.sqlite", __DIR__."/backup/".date("Y-m-d_H-i-s-").substr(microtime(TRUE)-time(), 2, 4).".sqlite");
dibi::connect(array(
	'driver' => "sqlite3",
	'database' => __DIR__."/db.sqlite",
	'formatDateTime' => "'Y-m-d H:i:s'",
	'formatDate' => "'Y-m-d'",
	'lazy' => TRUE,
	'profiler' => TRUE
));
dibi::loadFile(__DIR__."/db.structure.sqlite.sql");*/
/************************************ MySQL *************************************/
dibi::connect(array(
	'driver' => "mysql",
	'host' => "localhost",
	'database' => "nettejabber",
	'username' => "nettejabber",
	'password' => "nettejabber",
	'lazy' => TRUE,
	'profiler' => TRUE
));
dibi::loadFile(__DIR__."/db.structure.mysql.sql");

if (!defined('STDIN'))
{
	echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd"><html><head>'
		.'<meta http-equiv="Content-Type" content="text/html; charset=utf-8"><title>Nette Jabber Log Parser</title></head><body><code><pre>';
}

Parser::$originalDataDir = __DIR__."/data";
//Parser::$debug = TRUE;

if (($date = Nette\Environment::getHttpRequest()->getQuery('date', NULL)) || ($date = isset($argv[1])  ? $argv[1] : NULL))
	Parser::parseDate($date, TRUE);
else
	Parser::parse(10);
echo "\n\n";

$sec = $time = Nette\Debug::timer();
$hours = (int)($sec/3600);
$sec -= $hours*3600;
$mins = (int)($sec/60);
$sec -= $mins*60;
$sec = (int)$sec;
echo "Total Time: ".round($time*1000, 2)."ms ".str_pad($hours, 2, 0, STR_PAD_LEFT).":".str_pad($mins, 2, 0, STR_PAD_LEFT)
	.":".str_pad($sec, 2, 0, STR_PAD_LEFT).".".round(($time-$sec)*1000)."\n";
echo "Memory: ".round(memory_get_usage()/1024, 2)."KB (Real Memory: ".round(memory_get_usage(TRUE)/1024, 2)."KB)\n";
echo "SQL Queries: ".dibi::$numOfQueries." SQL Time: ".round(dibi::$totalTime*1000, 2)."ms\n\n\n\r";