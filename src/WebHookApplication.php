<?php

namespace Shipard;


class WebHookApplication extends \Shipard\Application
{
	var $dsRoot;
	var $urlRoot;
	var $requestPath;
	var $command;

	var $cmd = '';
	var $authToken = '';

	var $nodeTokens = NULL;

	protected function parseUrl ()
	{
		// -- parse url for routing
		$url = $_SERVER ["REQUEST_URI"];
		$p = strpos($url, '?');
		if($p !== false)
			$url = substr($url, 0, $p);

		$url = str_replace("//","/",$url);

		$this->dsRoot = strstr($_SERVER['SCRIPT_NAME'], '/index.php', true);
		$this->urlRoot = $this->dsRoot;

		$requestURI = explode ('/', $url);
		$scriptName = explode ('/', $_SERVER['SCRIPT_NAME']);
		$this->requestPath = array_values (array_diff_assoc ($requestURI, $scriptName));
	}

	public function requestPath ($idx = -1)
	{
		if ($idx === -1)
			return '/' . implode ('/', $this->requestPath);
		if (isset ($this->requestPath [$idx]))
			return $this->requestPath [$idx];
		return '';
	}

	public function init ()
	{
		$this->parseUrl();
		$this->command = $this->requestPath[0];

		$this->authToken = $this->requestPath[0] ?? '';
		$this->cmd = $this->requestPath[1] ?? '';

		$this->nodeTokens = $this->loadCfgFile('/etc/shipard-node/node-tokens.json');
		if (!$this->nodeTokens)
			$this->nodeTokens = [];
	}

	public function error ($status, $msg)
	{
		//header ('X-Frame-Options: SAMEORIGIN');
		header("HTTP/1.1 " . $status.' '.$msg);
		echo ("ERROR ".$status.': '.$msg);

		return FALSE;
	}

	public function postData ()
	{
		$data = '';
		$handle = fopen('php://input','r');
		while (1)
		{
			$buffer = fgets($handle, 4096);
			if (strlen($buffer) === 0)
				break;
			$data .= $buffer;
		}
		fclose ($handle);
		return $data;
	}

	public function sendJson ($data, int $status = 200)
	{
		//header ('X-Frame-Options: SAMEORIGIN');
		header ("Content-type: " . 'application/json');
		header ("HTTP/1.1 $status OK");

		$callback = '';
		if (isset($_GET['callback']))
			$callback = htmlspecialchars ($_GET['callback']);

		if ($callback !== '')
			echo ("$callback(");

		echo $data;

		if ($callback !== '')
			echo(')');
	}

	public function sendHtml ($text)
	{
		//header ('X-Frame-Options: SAMEORIGIN');
		header ("Content-type: " . 'text/html');
		header ("HTTP/1.1 200 OK");

		echo $text;
	}

	public function sendText ($text)
	{
		header ("Content-type: " . 'text/plain');
		header ("HTTP/1.1 200 OK");

		echo $text;
	}

	public function getAllHeaders()
	{
		$headers = [];
		foreach ($_SERVER as $name => $value)
		{
			if (substr($name, 0, 5) == 'HTTP_')
				$headers[str_replace(' ', '-', strtolower(str_replace('_', ' ', substr($name, 5))))] = $value;
		}
		return $headers;
	}

	protected function pdTempFileName()
	{
		$fn = '/var/lib/shipard-node/tmp/'.'___wh-' . time() . '-' . mt_rand(100000, 999999) . '.txt';

		return $fn;
	}

	public function hikvLpr ()
	{
		$this->saveToTemp();

		return TRUE;
	}

	public function saveToTemp()
	{
		$postDataStr = $this->postData();
		$fn = $this->pdTempFileName();
		file_put_contents($fn, $postDataStr);
	}

	public function run ()
	{
		$this->init();

		if (!$this->command || $this->command === '')
		{
			$this->saveToTemp();
			return $this->error(404, 'WH1: Not found!!!');
		}


		switch ($this->command)
		{
			case 'hikv-lpr':	return $this->hikvLpr();
		}

		$this->saveToTemp();
		$this->error(404, 'WH2: Not found!');
	}
}
