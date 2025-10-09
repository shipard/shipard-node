<?php


namespace Shipard\host;


/**
 * Class WpxManager
 */
class WpxManager extends \Shipard\host\Core
{
	var $wpxConfigDownload = NULL;
	var $wpxConfigDownloadFileName = '/etc/shipard-node/wpxDownload.json';
	var $certsBasePath = '/etc/ssl/shipard-certs/';

	function init()
	{
		if (!is_dir($this->certsBasePath))
		{
			mkdir($this->certsBasePath, 0755);
		}

		$this->wpxConfigDownload = $this->app->loadCfgFile($this->wpxConfigDownloadFileName);
		if (!$this->wpxConfigDownload)
		{
			return $this->app->err("Cannot load wpx configuration from file: ".$this->wpxConfigDownloadFileName);
		}

		$url = $this->wpxConfigDownload['url'] ?? '';
		if ($url === '')
		{
			return $this->app->err("Wpx url is missing: ".$this->wpxConfigDownloadFileName);
		}

		return TRUE;
	}

	function downloadConfig()
	{
		$url = $this->wpxConfigDownload['url'];
		if ($this->app->debug)
			echo ("* DOWNLOAD config from `$url`\n");

		$cfgData = file_get_contents($url);
		if (!$cfgData)
		{
			return $this->app->err("* ERROR: Cannot download wpx configuration from: $url");
		}

		$cfg = json_decode($cfgData, TRUE);
		if (!$cfg)
		{
			return $this->app->err("* ERROR: Cannot parse wpx configuration from: $url");
		}

		if (!$cfg['success'] || ($cfg['object']['error'] ?? 1) || !isset($cfg['object']['webProxyConfig']))
		{
			return $this->app->err("* ERROR: wpx configuration error #".intval($cfg['object']['error'] ?? 1).": ".($cfg['message'] ?? 'unknown error'));
		}

		file_put_contents('/etc/shipard-node/wpx.json', json_encode($cfg['object']['webProxyConfig'], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
	}

	public function applyConfig()
	{
		$wpxCfg = $this->app->loadCfgFile('/etc/shipard-node/wpx.json');
		if (!$wpxCfg)
		{
			return $this->app->err("Cannot load wpx configuration from file: /etc/shipard-node/wpx.json");
		}

		$reloadNginx = 0;

		// -- install certificates
		if (isset($wpxCfg['certs']) && is_array($wpxCfg['certs']))
		{
			foreach ($wpxCfg['certs'] as $certId => $certCfg)
			{
				if ($this->app->debug)
					echo ("* crt `$certId`: ");

				if ($this->installCert($certCfg))
					$reloadNginx = 1;

				if ($this->app->debug)
					echo ("\n");
			}
		}

		// -- install cfg files
		if (isset($wpxCfg['cfgFiles']) && is_array($wpxCfg['cfgFiles']))
		{
			foreach ($wpxCfg['cfgFiles'] as $fileName => $fileContent)
			{
				if ($this->app->debug)
					echo ("* cfg `$fileName`: ");

				if ($this->installCfgFile($fileName, $fileContent))
					$reloadNginx = 1;

				if ($this->app->debug)
					echo ("\n");
			}
		}

		if ($reloadNginx)
		{
			$returnVar = 0;
			if ($this->app->debug)
				echo ("* RELOAD NGINX\n");
			passthru('systemctl reload nginx', $returnVar);
		}
	}

	protected function installCert($cert)
	{
		$fnFullChain = $this->certsBasePath.$cert['cid'].'/fullchain.pem';
		$oldFullChainCheckSum = '';
		if (is_readable($fnFullChain))
			$oldFullChainCheckSum = sha1_file($fnFullChain);
		$newFullChainCheckSum = sha1($cert['fullchain'] ?? '');

		$fnPrivKey = $this->certsBasePath.$cert['cid'].'/privkey.pem';
		$oldPrivKeyCheckSum = '';
		if (is_readable($fnPrivKey))
			$oldPrivKeyCheckSum = sha1_file($fnPrivKey);
		$newPrivKeyCheckSum = sha1($cert['privkey'] ?? '');

		if (($oldFullChainCheckSum !== $newFullChainCheckSum) || ($oldPrivKeyCheckSum !== $newPrivKeyCheckSum))
		{
			if (!is_dir($this->certsBasePath.$cert['cid'].'/'))
				mkdir($this->certsBasePath.$cert['cid'].'/', 0755, TRUE);

			file_put_contents($fnFullChain, $cert['fullchain'] ?? '');
			file_put_contents($fnPrivKey, $cert['privkey'] ?? '');

			if ($this->app->debug)
				echo (" INSTALLED to `$this->certsBasePath$cert[cid]/`");

			return 1;
		}
		if ($this->app->debug)
			echo (" unchanged");

		return 0;
	}

	protected function installCfgFile($fileName, $fileContent)
	{
		if ($fileName === '/etc/hosts')
			return $this->installCfgFileEtcHosts($fileName, $fileContent);

		if (is_readable($fileName))
		{
			$oldFileContent = file_get_contents($fileName);
			if ($oldFileContent === $fileContent)
			{
				if ($this->app->debug)
					echo ("unchanged");
				return 0;
			}
		}

		file_put_contents($fileName, $fileContent);
		if ($this->app->debug)
			echo (" INSTALLED");

		return 1;
	}

	protected function installCfgFileEtcHosts($fileName, $fileContent)
	{
		echo ("unchanged");
		return 0;
	}
}
