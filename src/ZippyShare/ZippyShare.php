<?php

namespace ZippyShare;

use Error;
use stdClass;
use Exception;

/**
 * @author Ammar Faizi <ammarfaizi2@gmail.com> https://www.facebook.com/ammarfaizi2
 * @license MIT
 * @version 0.0.1
 */
final class ZippyShare
{
	/**
	 * @var string
	 */
	private $url;

	/**
	 * @var string
	 */
	private $cookieFile;

	/**
	 * @var string
	 */
	private $outFile;

	/**
	 * @var string
	 */
	private $fileUrl;

	/**
	 * @var string
	 */
	private $proxy = -1;

	/**
	 * @var int
	 */
	private $proxyType = CURLPROXY_SOCKS5;

	/**
	 * @var resource
	 */
	private static $logHandle = -1;

	/**
	 * @param string $url
	 */
	public function __construct(string $url)
	{
		$this->url = $url;
		$this->cookieFile = defined("ZIPPYSHARE_COOKIEFILE_DIR") ? ZIPPYSHARE_COOKIEFILE_DIR."/".sha1($this->url).".cookie" : __DIR__."/".sha1($this->url).".cookie";

		if (!is_resource(self::$logHandle)) {
			if (defined("ZIPPYSHARE_LOG_HANDLE") && is_resource("ZIPPYSHARE_LOG_HANDLE")) {
				self::$logHandle = ZIPPYSHARE_LOG_HANDLE;
			} else {
				self::$logHandle = STDOUT;
			}
		}
	}

	/**
	 * Destructor.
	 */
	public function __destruct()
	{
		file_exists($this->cookieFile) and unlink($this->cookieFile);
	}

	/**
	 * @return bool
	 */
	public function exec(): bool
	{
		try {
			if ($this->visitPage()) {
				$this->downloadFile();
				return true;
			}
		} catch (Exception $e) {
			self::log("An error occured: %s", $e->getMessage());
		}

		return false;
	}

	/**
	 * @return strng
	 */
	public function getFileUrl(): string
	{
		return $this->fileUrl;
	}

	/**
	 * @param string $proxy
	 * @param int    $proxyType
	 * @return void
	 */
	public function setProxy(string $proxy, int $proxyType = CURLPROXY_SOCKS5): void
	{
		$this->proxy = $proxy;
		$this->proxyType = $proxyType;
	}

	/**
	 * @return void
	 */
	public function outFile(string $outFile): void
	{
		$this->outFile = $outFile;
	}

	/**
	 * @return void
	 */
	private function downloadFile(): void
	{
		self::log("Downloading file...");

		$handle = fopen($this->outFile, "w");

		$o = $this->curl($this->fileUrl,
			[
				CURLOPT_RETURNTRANSFER => false,
				CURLOPT_WRITEFUNCTION => function ($ch, $str) use ($handle) {
					return fwrite($handle, $str);
				}
			]
		);

		if ($o->error) {
			throw new Exception("Download error: ({$o->errno}) {$o->error}");
		}

		fflush($handle);
		fclose($handle);
	}

	/**
	 * @throws \Exception
	 * @return bool
	 */
	private function visitPage(): bool
	{
		$o = $this->curl($this->url);
		// print $o->out;
		// file_put_contents("test.tmp", $o->out);
		// $o = new stdClass;
		// $o->out = file_get_contents("test.tmp");

		if (preg_match(
			"/(?:<script type=\"text\/javascript\">[\n\s]+?var a = )(\d+)(?:;[\n\s]+?var b = )(\d+)(?:;.+?dlbutton'\).href = \")(.+?)(?:\"\+\((.+?)\)\+\")(.+?)(?:\")/si",
			$o->out,
			$m
		)) {
			$a = (int)$m[1];
			$b = (int)$m[2];

			$m[4] = str_replace(["a", "b"], ["\$a", "\$b"], $m[4]);

			// var_dump($a, $b, $m[4]);

			$a = (int)floor(((int)$m[1]) / 3);

			try {
				eval("\$m[4] = {$m[4]};");
			} catch (Error $e) {
				throw new Exception("Invalid evaluation");
			}

			$serverNum = "";
			if (preg_match("/www(\d+)/", $this->url, $mm)) {
				$serverNum = $mm[1];
			}

			$this->fileUrl = "https://www{$serverNum}.zippyshare.com/".ltrim($m[3], "/").$m[4].$m[5];

			return true;
		}

		throw new Exception("Couldn't find the file link");
	}


	/**
	 * @param string $url
	 * @param array  $opt
	 * @return stdClass
	 */
	private function curl(string $url, array $opt = []): stdClass
	{
		self::log("Preparing curl to %s...", $url);

		$retried = false;

start_curl:
		
		$ch = curl_init($url);

		$optf = [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_USERAGENT => "Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:65.0) Gecko/20100101 Firefox/65.0",
			CURLOPT_COOKIEJAR => $this->cookieFile,
			CURLOPT_COOKIEFILE => $this->cookieFile
		];

		if ($this->proxy !== -1) {
			$optf[CURLOPT_PROXY] = $this->proxy;
			$optf[CURLOPT_PROXYTYPE] = $this->proxyType;
		}

		foreach ($opt as $k => $v) {
			$optf[$k] = $v;
		}

		curl_setopt_array($ch, $optf);

		self::log("Fetching %s...", $url);

		$o = new stdClass;

		$o->out = curl_exec($ch);
		$o->error = curl_error($ch);
		$o->errno = curl_errno($ch);
		$o->info = curl_getinfo($ch);
		curl_close($ch);

		if ($o->error) {
			self::log("There was an error occured when accessing %s: (%d) %s.", $url, $o->errno, $o->error);
			if (!$retried) {
				self::log("Retrying to fetch %s...", $url);
				$retried = true;
				goto start_curl;
			} else {
				self::log("Aborting fetch %s...", $url);
			}
		} else {
			self::log("Fetch completed with http_code: %d", $o->info["http_code"]);
		}

		return $o;
	}

	/**
	 * @param string $format
	 * @param mixed  ...$args
	 * @return void
	 */
	private static function log(string $format, ...$args): void
	{
		if (self::$logHandle === -1) {
			return;
		}

		fprintf(
			self::$logHandle,
			"[%s]: %s\n",
			date("Y-m-d H:i:s"),
			vsprintf($format, $args)
		);
	}
}
