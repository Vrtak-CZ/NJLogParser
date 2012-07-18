<?php
/**
 * Nette Jabber Log Parser
 *
 * @copyright  Copyright (c) 2012 Patrik Votoček
 * @license    http://nellacms.com/license  New BSD License
 * @link       http://addons.nettephp.com/cs/active-mapper
 * @category   NJLogParser
 * @package    NJLogParser
 */

namespace NJLogParser;

use DateTime, DateInterval;

/**
 * Parser
 *
 * @author     Patrik Votoček
 * @copyright  Copyright (c) 2012 Patrik Votoček
 * @package    NJLogParser
 */
class Parser
{
	const VERSION = "1.3";

	/** @var \Nette\Database\Connection */
	private $connection;

	/** @var string */
	public $originalDataDir;
	/** @var string */
	public $startDate = "2009-05-12";
	/** @var bool */
	public $debug = FALSE;
	/** @var int */
	private $totalDays = NULL;
	/** @var int */
	private $parsedDays = 0;
	/** @var int */
	private $progressTime = NULL;

	/**
	 * @param \Nette\Database\Connection
	 */
	public function __construct(\Nette\Database\Connection $connection)
	{
		$this->connection = $connection;
	}


	/**
	 * Parse
	 *
	 * @param int $dayLimit parse day limit
	 */
	public function parse($dayLimit = NULL)
	{
		$date = new DateTime($this->startDate);
		if (empty($dayLimit))
			$this->totalDays = (int)((time()-$date->getTimestamp())/86400)+1;
		else
			$this->totalDays = $dayLimit;

		$myDate = new DateTime();
		$parsetime = $myDate->sub(new DateInterval("P2D"))->getTimestamp();
		unset($myDate);

		$this->parsedDays = 1;
		do {
			if (($dayLimit !== NULL && $this->totalDays < $this->parsedDays) || $date->getTimestamp() > time()) {
				break;
			}
			if (!defined('STDIN') && !$this->debug) { //Next code is realy FUCKING hack
				echo $date->format("Y-m-d") . "<br>\n";
				@ob_end_flush();
			    @ob_flush();
			    @flush();
			    @ob_start();
		  	}

			$this->connection->beginTransaction();
			$selection = $this->connection->table('parsed');
			if ($selection->where("date = ?", $date->format("Y-m-d"))->count('*') < 1 || $date->getTimestamp() > $parsetime) {
				$this->parseDate($date->format("Y-m-d"));
				$selection->insert(array('date' => $date->format("Y-m-d")));
				$this->parsedDays++;
			} elseif ($dayLimit === NULL) {
				$this->parsedDays++;
			}

			$this->connection->commit();
		} while ($date->add(new DateInterval('P1D'))->getTimestamp() < time());
	}

	/**
	 * Parse file
	 *
	 * @param string $path
	 * @return array
	 */
	public function parseDate($date, $reload = FALSE)
	{
		$this->log("parse-start", $date);
		$data = $this->loadData($date, $reload);
		if (!empty($data)) {
			$this->parseData($data, $date);
		}
		$this->log("parse-end", $date);
	}

	/**
	 * Load data
	 *
	 * @param string $date
	 * @param bool $reload
	 * @return string
	 */
	private function loadData($date, $reload = FALSE)
	{
		$this->log("load-data-start", $date);
		$parseDate = new DateTime($date);
		$myDate = new DateTime();
		$myDate->sub(new DateInterval("P2D"));
		$data = NULL;
		if ($reload || !file_exists($this->originalDataDir."/".$date.".html") || $parseDate->getTimestamp() > $myDate->getTimestamp()) {
			$curl = new \Curl();
			$curl->setUserAgent("Mozilla/5.0 (compatible; NetteJabberLogParser/".static::VERSION."; +http://nettejabber.jdem.cz)");
			$curl->setHeader("HTTP_ACCEPT", "text/xml,application/xml,application/xhtml+xml,text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5");
			$curl->setHeader("HTTP_ACCEPT_LANGUAGE", "cs-cz,cs,en-us;q=0.7,en;q=0.3");
			$curl->setHeader("HTTP_ACCEPT_ENCODING", "gzip,deflate");
			$curl->setHeader("HTTP_ACCEPT_CHARSET", "windows-1250,utf-8;q=0.7,*;q=0.7");
			$curl->setHeader("HTTP_KEEP_ALIVE", 300);
			$curl->setHeader("HTTP_CONNECTION", "keep-alive");
			$response = $curl->get("http://nezmar.jabbim.cz/logs/nette@conf.netlab.cz/".str_replace("-", "/", $date).".html");
			if ($response->getHeader("Status-Code") == 200) {
				file_put_contents($this->originalDataDir."/".$date.".html", $response->getBody());
				$data = $response->getBody();
			}
		}
		elseif (file_exists($this->originalDataDir."/".$date.".html")) {
			$data = file_get_contents($this->originalDataDir."/".$date.".html");
		}

		$this->log("load-data-end", $date);
		return $data;
	}

	/**
	 * Parse data
	 *
	 * @param string $data
	 * @return array
	 */
	private function parseData($data, $date)
	{
		$this->log("parse-data-start", $date);
		$data = substr($data, strpos($data, '<a id="'));
		$data = substr($data, 0, strpos($data, '<div class="legend">') - 1);
		$data = explode("<br/>\n<a id", $data);

		$i = 1;
		foreach ($data as $line) {
			if (preg_match("/.+name=\"([0-9:]+)\.([0-9]+)\".+\<font class=\"mj\"\>(.+) joins the room.+/i", $line, $matches) === 1) {
				$this->add($date." ".$matches[1], $matches[2], "join", $matches[3]);
			} elseif (preg_match("/.+name=\"([0-9:]+)\.([0-9]+)\".+\<font class=\"ml\"\>(.+) leaves the room.+/i", $line, $matches) === 1) {
				$this->add($date." ".$matches[1], $matches[2], "leave", $matches[3]);
			} elseif (preg_match("/.+name=\"([0-9:]+)\.([0-9]+)\".+\<font class=\"mn\"\>&lt;(.+)&gt;\<\/font\> (.*)/i", $line, $matches) === 1) {
				$this->add($date." ".$matches[1], $matches[2], "message", $matches[3], $matches[4]);
			} elseif (preg_match("/.+name=\"([0-9:]+)\.([0-9]+)\".+\<font class=\"msc\"\>(.+) has set the subject to: (.*)\<\/font\>/i", $line, $matches) === 1) {
				$this->add($date." ".$matches[1], $matches[2], "subject", $matches[3], $matches[4]);
			} elseif (preg_match("/.+name=\"([0-9:]+)\.([0-9]+)\".+\<font class=\"mnc\"\>(.+) is now known as (.*)\<\/font\>/i", $line, $matches) === 1) {
				$this->add($date." ".$matches[1], $matches[2], "rename", $matches[3], $matches[4]);
			} elseif (preg_match("/.+name=\"([0-9:]+)\.([0-9]+)\".+\<font class=\"mk\"\>(.+) has been kicked: (.*)\<\/font\>/i", $line, $matches) === 1) {
				$this->add($date." ".$matches[1], $matches[2], "kick", $matches[3], $matches[4]);
			} elseif (preg_match("/.+name=\"([0-9:]+)\.([0-9]+)\".+\<font class=\"mne\"\>([^ ]+) (.*)\<\/font\>/i", $line, $matches) === 1) {
				$this->add($date." ".$matches[1], $matches[2], "status", $matches[3], $matches[4]);
			} else {
				$this->log('unknow', $line);
			}

			if (defined('STDIN') && !$this->debug) {
				$this->progressBar($i, count($data));
			}
			$i++;
		}
		$this->log("parse-data-end", $date);
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
	private function add($datetime, $ms, $type, $name, $data = NULL)
	{
		$selection = $this->connection->table('data');
		if ($selection->where("datetime = ? AND ms = ?", $datetime, $ms)->count('*') > 0) {
			$selection->update(array(
				'type' => $type,
				'name' => $name,
				'data' => $data
			))->where("datetime = ? AND ms = ?", $datetime, $ms);
			$this->log("update", $datetime . "#" . $type);
		} else {
			$selection->insert(array(
				'datetime' => $datetime,
				'ms' => $ms,
				'type' => $type,
				'name' => $name,
				'data' => $data)
			);
			$this->log("add", $datetime . "#" . $type);
		}
	}

	/**
	 * Log
	 *
	 * @param string $type
	 * @param string $message
	 */
	private function log($type, $message = NULL)
	{
		$selection = $this->connection->table('logs');
		$selection->insert(array(
			'datetime' => date("Y-m-d H:i:s"),
			'type' => $type,
			'message' => $message,
		));
		if ($this->debug) {
			echo date("Y-m-d H:i:s.") . substr((microtime(TRUE)-time())."", 2, 4) . " @ " . $type . " # " . $message . "\n";
			if (!defined('STDIN')) { //Next code is realy FUCKING hack
				@ob_end_flush();
				@ob_flush();
				@flush();
				@ob_start();
			}
		}
	}


	/**
	 * show a status bar in the console
	 *
	 * <code>
	 * for($x=1;$x<=100;$x++){
	 *
	 *     show_status($x, 100);
	 *
	 *     usleep(100000);
	 *
	 * }
	 * </code>
	 *
	 * @author dealnews.com, Inc.
	 * @copyright Copyright (c) 2010, dealnews.com, Inc.
	 * @param int $doneLines how many items are completed
	 * @param int $totalLines how many items are to be done total
	 * @param int $size optional size of the status bar
	 * @return void
	 */
	private function progressBar($doneLines, $totalLines, $size=10)
	{
		if($this->parsedDays > $this->totalDays) {
			return;
		}
		if(empty($this->progressTime)) {
			$this->progressTime=microtime(TRUE);
		}

		if ($doneLines < $totalLines) {
			$perc=(double)(($this->parsedDays-1+((double)($doneLines/$totalLines)))/$this->totalDays);
		} else {
			$perc=(double)($this->parsedDays/$this->totalDays);
		}

		$bar=floor($perc*$size);

		$status_bar="\r[";
		$status_bar.=str_repeat("=", $bar);
		if($bar<$size) {
			$status_bar.=">";
			$status_bar.=str_repeat(" ", $size-$bar);
		} else {
			$status_bar.="=";
		}

		$disp=number_format($perc*100, 0);

		$status_bar.="]".str_pad($disp, 3, ' ', STR_PAD_LEFT)."%  ".str_pad($this->parsedDays, strlen($this->totalDays.""), ' ', STR_PAD_LEFT)."/".$this->totalDays;

		/***************************** lines ******************************/
		$perc=(double)($doneLines/$totalLines);

		$bar=floor($perc*$size);

		$status_bar.=" [";
		$status_bar.=str_repeat("=", $bar);
		if($bar<$size) {
			$status_bar.=">";
			$status_bar.=str_repeat(" ", $size-$bar);
		} else {
			$status_bar.="=";
		}

		$disp=number_format($perc*100, 0);

		$status_bar.="]".str_pad($disp, 3, ' ', STR_PAD_LEFT)."% ".str_pad($doneLines, 5, 0, STR_PAD_LEFT)."/".str_pad($totalLines, 5, 0, STR_PAD_LEFT);
		/*******************************************************************/

		$rate = (microtime(TRUE)-$this->progressTime) / ($this->parsedDays + $perc);
		$left = $this->totalDays - ($this->parsedDays - 1 + $perc);
		$eta = $rate * $left;

		$elapsed = (int)(time() - $this->progressTime);

		$status_bar.= " R:";
		$status_bar.= ($eta > 60) ? (round($eta/60, 2)."m") : (round($eta, 2)."s");
		$status_bar.= " E:";
		$status_bar.= ($elapsed > 60) ? ((int)($elapsed/60)."m") : $elapsed."s";

		echo "$status_bar  ";

		@flush();

		// when done, send a newline
		if($this->parsedDays == $this->totalDays && $doneLines == $totalLines) {
			echo "\n";
		}
	}
}