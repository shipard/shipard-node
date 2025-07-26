<?php


namespace Shipard\host;


/**
 * Class CertsDownloader
 */
class CertsDownloader extends \Shipard\host\Core
{
	var $apiBaseUrl = 'https://hq.shipard.app/';
	var $certsConfig = NULL;
	var $certsConfigFileName = '/etc/shipard-node/certs.json';
	var $certsBasePath = '/etc/ssl/shipard-certs/';
	var $downloadedCerts = [];
	var $cmdsAfterInstall = [];


	/*
	 * Downloads certificates from the server and installs them
	 * example configuration in `/etc/shipard-node/certs.json`:
	 * {
	 *   "certificates": {
	 *     "test.example.com": {
	 *       "api-id": "api-cert-id",
	 * 			 "api-key": "api-key-jdskjghksajhgakjshgk",
	 * 		 	 "reload-services": ["nginx"],
	 * 			 "restart-services": ["incus"],
	 * 			}
	 * 	 }
	 * }
	 */

	function init()
	{
		if (!is_dir($this->certsBasePath))
		{
			mkdir($this->certsBasePath, 0755);
		}

		$this->certsConfig = $this->app->loadCfgFile($this->certsConfigFileName);
		if (!$this->certsConfig)
		{
			return $this->app->err("Cannot load certs configuration from file: ".$this->certsConfigFileName);
		}

		return TRUE;
	}

	function downloadCerts()
	{
		foreach ($this->certsConfig['certificates'] as $certId => $cert)
		{
			$url = $this->apiBaseUrl.'/papi/certs/';
			$url .= $cert['api-id'].'/'.$cert['api-key'];

			if ($this->app->debug)
				echo ("* DOWNLOAD `$certId` from `$url`\n");

			$cert = $this->app->apiCall($url);
			if (!$cert)
			{
				$this->app->err("* ERROR: Cannot download certificate with ID: $certId");
				continue;
			}

			if (!isset($cert['object']['cert']))
			{
				$this->app->err("* ERROR: Invalid certificate with ID: $certId");
				continue;
			}

			$this->downloadedCerts[$certId] = $cert['object']['cert'];
		}

		return TRUE;
	}

	function installCertificates()
	{
		if (!count($this->downloadedCerts))
			return;

		foreach ($this->downloadedCerts as $certId => $cert)
		{
			$isNew = 0;

			$certPath = $this->certsBasePath.$certId.'/';
			if (!is_dir($certPath))
			{
				mkdir($certPath, 0755);
			}

			file_put_contents($certPath.'crt.info', json_encode($cert, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));

			foreach ($cert['files'] as $fileNameOrig => $fileContent)
			{
				if ($fileNameOrig === 'cert.pem')
					continue;

				$fileName = $fileNameOrig;
				if ($fileName === 'chain.pem')
					$fileName = 'fullchain.pem';

				if (is_readable($certPath.$fileName))
					$oldFileCheckSum = sha1_file($certPath.$fileName);
				else
					$oldFileCheckSum = '';
				$newFileCheckSum = sha1($fileContent);

				if ($oldFileCheckSum !== $newFileCheckSum)
					$isNew = 1;

				file_put_contents($certPath.$fileName, $fileContent);
			}

			if ($isNew)
			{
				$certDownloadCfg = $this->certsConfig['certificates'][$certId] ?? [];
				if (isset($certDownloadCfg['reload-services']))
				{
					foreach ($certDownloadCfg['reload-services'] as $service)
					{
						$cmd = 'systemctl reload '.$service;
						if (in_array($cmd, $this->cmdsAfterInstall))
							continue;
						$this->cmdsAfterInstall[] = $cmd;
					}
				}
				if (isset($certDownloadCfg['restart-services']))
				{
					foreach ($certDownloadCfg['restart-services'] as $service)
					{
						$cmd = 'systemctl restart '.$service;
						if (in_array($cmd, $this->cmdsAfterInstall))
							continue;
						$this->cmdsAfterInstall[] = $cmd;
					}
				}
			}

			if ($this->app->debug && $isNew)
				echo ("* INSTALLED `$certId` to `$certPath`\n");
		}
	}

	protected function doCmdsAfterInstall()
	{
		if (!count($this->cmdsAfterInstall))
			return;

		foreach ($this->cmdsAfterInstall as $cmd)
		{
			if ($this->app->debug)
				echo ("* RUN CMD: $cmd\n");

			exec($cmd, $output, $returnVar);
			if ($returnVar !== 0)
			{
				$this->app->err("* ERROR: Command failed: $cmd");
			}
		}

		return TRUE;
	}

	public function run()
	{
		$this->init();
		$this->downloadCerts();
		$this->installCertificates();
		$this->doCmdsAfterInstall();
	}
}
