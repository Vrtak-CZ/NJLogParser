<?php
/**
 * Nette Jabber Log Parser
 *
 * @copyright  Copyright (c) 2010 Patrik Votoček
 * @license    http://nellacms.com/license  New BSD License
 * @link       http://addons.nettephp.com/cs/active-mapper
 * @category   NJLogParser
 * @package    NJLogParser
 */

namespace NJLogParser;

use dibi;
use DateTime;
use DateInterval;

/**
 * Parser
 *
 * @author     Patrik Votoček
 * @copyright  Copyright (c) 2010 Patrik VotoÄŤek
 * @package    NJLogParser
 */
class Parser
{
	const VERSION = "1.0";

	/** @var string */
	public static $originalDataDir;
	/** @var string */
	public static $startDate = "2009-05-12";
	/** @var bool */
	public static $debug = FALSE;


	/**
	 * Parse
	 *
	 * @param int $dayLimit parse day limit
	 */
	public static function parse($dayLimit = NULL)
	{
		$date = new DateTime(self::$startDate);
		$myDate = new DateTime();
		$myDate->sub(new DateInterval("P2D"));
		$i = 1;
		do {
			if ($dayLimit !== NULL && $dayLimit < $i)
				break;

			dibi::begin();
			if (dibi::select('id')->from('parsed')->where("[date] = %d", $date->format("Y-m-d"))->fetchSingle() === FALSE || $date->getTimestamp() > $myDate->getTimestamp())
			{
				self::parseDate($date->format("Y-m-d"));
				dibi::insert('parsed', array('date' => $date->format("Y-m-d")))->execute();
				$i++;
			}
			dibi::commit();
		} while ($date->add(new \DateInterval('P1D'))->getTimestamp() < time());
	}

	/**
	 * Parse file
	 *
	 * @param string $path
	 * @return array
	 */
	public static function parseDate($date, $reload = FALSE)
	{
		self::log("parse-start", $date);
		$data = self::loadData($date, $reload);
		if (!empty($data))
			self::parseData ($data, $date);
		self::log("parse-end", $date);
	}

	/**
	 * Load data
	 *
	 * @param string $date
	 * @param bool $reload
	 * @return string
	 */
	private static function loadData($date, $reload = FALSE)
	{
		self::log("load-data-start", $date);
		$parseDate = new DateTime($date);
		$myDate = new DateTime();
		$myDate->sub(new DateInterval("P2D"));
		$data = NULL;
		if ($reload || !file_exists(self::$originalDataDir."/".$date.".html") || $parseDate->getTimestamp() > $myDate->getTimestamp())
		{
			$curl = new \Curl();
			$curl->setUserAgent("Mozilla/5.0 (compatible; NetteJabberLogParser/".self::VERSION."; +http://nettejabber.jdem.cz)");
			$curl->setHeader("HTTP_ACCEPT", "text/xml,application/xml,application/xhtml+xml,text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5");
			$curl->setHeader("HTTP_ACCEPT_LANGUAGE", "cs-cz,cs,en-us;q=0.7,en;q=0.3");
			$curl->setHeader("HTTP_ACCEPT_ENCODING", "gzip,deflate");
			$curl->setHeader("HTTP_ACCEPT_CHARSET", "windows-1250,utf-8;q=0.7,*;q=0.7");
			$curl->setHeader("HTTP_KEEP_ALIVE", 300);
			$curl->setHeader("HTTP_CONNECTION", "keep-alive");
			$response = $curl->get("http://nezmar.jabbim.cz/logs/nette@conf.netlab.cz/".str_replace("-", "/", $date).".html");
			if ($response->getHeader("Status-Code") == 200)
			{
				file_put_contents(self::$originalDataDir."/".$date.".html", $response->getBody());
				$data = $response->getBody();
			}
		}
		elseif (file_exists(self::$originalDataDir."/".$date.".html"))
				$data = file_get_contents(self::$originalDataDir."/".$date.".html");

		self::log("load-data-end", $date);
		return $data;
	}

	/**
	 * Parse data
	 *
	 * @param string $data
	 * @return array
	 */
	private static function parseData($data, $date)
	{
		self::log("parse-data-start", $date);
		$data = substr($data, strpos($data, '<a id="'));
		$data = substr($data, 0, strpos($data, '<div class="legend">') - 1);
		$data = explode("<br/>\n<a id", $data);

		foreach ($data as $line)
		{
			if (preg_match("/.+name=\"([0-9:]+)\.([0-9]+)\".+\<font class=\"mj\"\>(.+) joins the room.+/i", $line, $matches) === 1)
				self::add($date." ".$matches[1], $matches[2], "join", $matches[3]);
			elseif (preg_match("/.+name=\"([0-9:]+)\.([0-9]+)\".+\<font class=\"ml\"\>(.+) leaves the room.+/i", $line, $matches) === 1)
				self::add($date." ".$matches[1], $matches[2], "leave", $matches[3]);
			elseif (preg_match("/.+name=\"([0-9:]+)\.([0-9]+)\".+\<font class=\"mn\"\>&lt;(.+)&gt;\<\/font\> (.*)/i", $line, $matches) === 1)
				self::add($date." ".$matches[1], $matches[2], "message", $matches[3], $matches[4]);
			elseif (preg_match("/.+name=\"([0-9:]+)\.([0-9]+)\".+\<font class=\"msc\"\>(.+) has set the subject to: (.*)\<\/font\>/i", $line, $matches) === 1)
				self::add($date." ".$matches[1], $matches[2], "subject", $matches[3], $matches[4]);
			elseif (preg_match("/.+name=\"([0-9:]+)\.([0-9]+)\".+\<font class=\"mnc\"\>(.+) is now known as (.*)\<\/font\>/i", $line, $matches) === 1)
			self::add($date." ".$matches[1], $matches[2], "rename", $matches[3], $matches[4]);
			elseif (preg_match("/.+name=\"([0-9:]+)\.([0-9]+)\".+\<font class=\"mne\"\>([^ ]+) (.*)\<\/font\>/i", $line, $matches) === 1)
				self::add($date." ".$matches[1], $matches[2], "status", $matches[3], $matches[4]);
			else
				self::log('unknow', $line);
		}
		self::log("parse-data-end", $date);
	}

	/**
	 * Add data
	 *
	 * @param string $datetime
	 * @param int $ms
	 * @param string $type
	 * @param string $name
	 * @param string $data
	 */
	private static function add($datetime, $ms, $type, $name, $data = NULL)
	{
		if (dibi::select('id')->from('data')->where("[datetime] = %t AND [ms] = %i", $datetime, $ms)->fetchSingle() !== FALSE)
		{
			dibi::update('data', array('type' => $type, 'name' => $name, 'data' => $data))->where("[datetime] = %d AND [ms] = %i", $datetime, $ms)->execute();
			self::log("update", $datetime . "#" . $type);
		}
		else
		{
			dibi::insert('data', array('datetime' => $datetime, 'ms' => $ms, 'type' => $type, 'name' => $name, 'data' => $data))->execute();
			self::log("add", $datetime . "#" . $type);
		}
	}

	/**
	 * Log
	 *
	 * @param string $type
	 * @param string $message
	 */
	private static function log($type, $message = NULL)
	{
		dibi::insert("logs", array('datetime' => date("Y-d-m H:i:s"), 'type' => $type, 'message' => $message))->execute();
		if (self::$debug)
		{
			echo date("Y-d-m H:i:s.") . substr((microtime(TRUE)-time())."", 2, 4) . " @ " . $type . " # " . $message . "\n";
			if (!defined('STDIN')) //Next code is realy FUCKING hack
			{
				@ob_end_flush(); 
		    @ob_flush(); 
		    @flush(); 
		    @ob_start();
		  }
		}
	}
}