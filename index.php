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

if (file_exists(__DIR__."/db.sqlite"))
	copy(__DIR__."/db.sqlite", __DIR__."/backup/".date("Y-m-d_H-i-s-").substr(microtime(TRUE)-time(), 2, 4).".sqlite");

dibi::connect(array(
	'driver' => "sqlite3",
	'database' => __DIR__."/db.sqlite",
	'formatDateTime' => "'Y-m-d H:i:s'",
	'formatDate' => "'Y-m-d'",
	'lazy' => TRUE,
	'profiler' => TRUE
));

dibi::loadFile(__DIR__."/db.structure.sql");

if (!defined('STDIN'))
{
	echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd"><html><head>'
		.'<meta http-equiv="Content-Type" content="text/html; charset=utf-8"><title>Nette Jabber Log Parser</title></head><body><code><pre>';
}

Parser::$originalDataDir = __DIR__."/data";
Parser::$debug = TRUE;

if (($date = Nette\Environment::getHttpRequest()->getQuery('date', NULL)) || ($date = isset($argv[1])  ? $argv[1] : NULL))
	Parser::parseDate($date, TRUE);
else
	Parser::parse(10);
echo "\n\n";
echo "Total Time: ".round(Nette\Debug::timer()*1000, 2)."ms\n";
echo "Memory: ".round(memory_get_usage()/1024, 2)."KB (Real Memory: ".round(memory_get_usage(TRUE)/1024, 2)."KB)\n";
echo "SQL Queries: ".dibi::$numOfQueries." SQL Time: ".round(dibi::$totalTime*1000, 2)."ms";