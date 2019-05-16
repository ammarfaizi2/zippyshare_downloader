<?php

namespace ZippyShare;

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
	 * @var resource
	 */
	private static $logHandle = -1;

	/**
	 * @param string $url
	 */
	public function __construct(string $url)
	{
		$this->url = $url;
		$this->cookieFile = defined("ZIPPYSHARE_COOKIEFILE_DIR") ? ZIPPYSHARE_COOKIEFILE_DIR."/".sha1($this->url) : __DIR__.sha1($this->url);

		if (!is_resource(self::$logHandle)) {
			if (defined("ZIPPYSHARE_LOG_HANDLE") && is_resource("ZIPPYSHARE_LOG_HANDLE")) {
				self::$logHandle = ZIPPYSHARE_LOG_HANDLE;
			} else {
				self::$logHandle = STDOUT;
			}
		}
	}

	/**
	 * @return bool
	 */
	public function exec(): bool
	{
		if ($this->visitPage()) {

		}
	}

	/**
	 * @return bool
	 */
	private function visitPage(): bool
	{

	}

	/**
	 * @return void
	 */
	public function outFile(string $outFile): void
	{
		$this->outFile = $file;
	}


	/**
	 * @param string $url
	 * @param array  $opt
	 * @return array
	 */
	private function curl(string $url, array $opt = []): array
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

		foreach ($opt as $k => $v) {
			$optf[$k] = $v;
		}

		curl_setopt_array($ch, $optf);

		self::log("Fetching %s...", $url);

		$out = curl_exec($ch);
		$err = curl_error($ch);
		$ern = curl_errno($ch);
		$info = curl_getinfo($ch);
		curl_close($ch);

		if ($err) {
			self::log("There was an error occured when accessing %s: (%d) %s.", $url, $ern, $err);
			if (!$retried) {
				self::log("Retrying to fetch %s...", $url);
				$retried = true;
				goto start_curl;
			} else {
				self::log("Aborting fetch %s...", $url);
			}
		} else {
			self::log("Fetch completed with http_code: %d", $info["http_code"]);
		}

		return [
			"out" => $out,
			"error" => $err,
			"errno" => $ern,
			"info" => $info
		];
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
