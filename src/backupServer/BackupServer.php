<?php


namespace Shipard\backupServer;



class BackupServer extends \Shipard\host\Core
{
	var $backupCfg = NULL;
	var $dateStr = '';
	var $server = '';

	public function init()
	{
		$this->backupCfg = $this->app->loadCfgFile('/etc/shipard-node/backupServer.json');

		$dateNow = new \DateTime();
		$this->dateStr = $dateNow->format('Y-m-d');
	}

	protected function downloadDataSources()
	{
		foreach ($this->backupCfg['dsServers'] as $srv)
		{
			if ($this->server && $this->server !== $srv['hostName'])
				continue;

			$this->downloadDataSourcesServer($srv);
		}
	}

	protected function downloadDataSourcesServer($srv)
	{
		$remoteUser = $this->hostRemoteUser($srv);
		$port = $this->hostPort($srv);
		$hostName = $this->hostName($srv);
		$hostBackupDir = $this->hostBackupDir($srv);

		// -- prepare server dir
		$serverDestDir = $this->backupCfg['destFolder'].'dsServers/'.$hostName.'/'.$this->dateStr;
		if (!is_dir($serverDestDir))
			mkdir ($serverDestDir, 0750, TRUE);

		// -- load backup info
		$cmd = "scp -P $port {$remoteUser}@$hostName:$hostBackupDir/{$this->dateStr}/backupInfo.json $serverDestDir/";
		echo $cmd . "\n";
		passthru($cmd);
		$backupInfoFileName = $serverDestDir.'/backupInfo.json';
		$backupInfo = json_decode(file_get_contents($backupInfoFileName), TRUE);

		// -- host files
		if (isset($backupInfo['hostFiles']))
		{
			foreach ($backupInfo['hostFiles'] as $hf)
			{
				$cmd = "scp -P $port {$remoteUser}@$hostName:{$hf['fileName']} $serverDestDir/";
				echo $cmd . "\n";
				passthru($cmd);
			}
		}

		// -- databases
		foreach ($backupInfo['dataSources'] as $backup)
		{
			$dsDestDir = $this->backupCfg['destFolder'].'dataSources/'.$backup['dsid'];
			if (!is_dir($dsDestDir))
				mkdir ($dsDestDir, 0750, TRUE);

			$cmd = "scp -P $port {$remoteUser}@$hostName:{$backup['bkpFileName']} {$dsDestDir}/";
			echo $cmd . "\n";
			passthru($cmd);
		}

		// -- attachments
		foreach ($backupInfo['dataSources'] as $backup)
		{
			if (!$backup['syncAttachments'])
				continue;

			$syncDestDir = $this->backupCfg['destFolder'].'dataSources/'.$backup['dsid'].'/sync';
			if (!is_dir($syncDestDir))
				mkdir ($syncDestDir, 0750, TRUE);

			$cmd = "rsync -ak -e \"ssh -p $port\" {$remoteUser}@$hostName:{$backup['serverPath']}/att $syncDestDir";
			echo $cmd . "\n";
			passthru($cmd);
		}
	}

	public function downloadNodeServers()
	{
		if (!isset($this->backupCfg['nodeServers']))
			return;

		foreach ($this->backupCfg['nodeServers'] as $srv)
		{
			if ($this->server && $this->server !== $srv['hostName'])
				continue;

			echo $srv['hostName']."\n";
			$this->downloadNodeServer($srv);
		}
	}

	protected function downloadNodeServer($srv)
	{
		$remoteUser = $this->hostRemoteUser($srv);
		$port = $this->hostPort($srv);
		$hostName = $this->hostName($srv);
		$hostBackupDir = '/var/lib/shipard-node/backups/';
		$localDestDir = $this->backupCfg['destFolder'].'nodeServers/'.$hostName.'/'.$this->dateStr;

		if (!is_dir($localDestDir))
			mkdir ($localDestDir, 0750, TRUE);

		$cmd = "scp -r -P $port {$remoteUser}@$hostName:$hostBackupDir/{$this->dateStr}/* $localDestDir";
		echo $cmd . "\n";
		passthru($cmd);

		// -- remove old backups
		$oldDate = new \DateTime();
		$oldDate->modify('-7 days');
		$oldDateStr = $oldDate->format('Y-m-d');
		$oldLocalDestDir = $this->backupCfg['destFolder'].'nodeServers/'.$hostName.'/'.$oldDateStr;

		$oldDay = intval($oldDate->format('d'));
		if (is_dir($oldLocalDestDir))
		{
			$oldDay = intval($oldDate->format('d'));
			if ($oldDay === 1)
			{ // archive
				$archiveDestDir = $this->backupCfg['destFolder'].'nodeServers/'.$hostName.'/'.'archive/'.$oldDate->format('Y');
				if (!is_dir($archiveDestDir))
					mkdir ($archiveDestDir, 0750, TRUE);
				$cmd = "mv $oldLocalDestDir $archiveDestDir/";
				//echo $cmd . "\n";
				passthru($cmd);
				return;
			}
			else
			{ // remove
				exec ('rm -rf '.$oldLocalDestDir);
			}
		}
	}

	protected function hostRemoteUser($h)
	{
		if (isset($h['remoteUser']))
			return $h['remoteUser'];
		if (isset($this->backupCfg['defaults']['remoteUser']))
			return $this->backupCfg['defaults']['remoteUser'];

		return '';
	}

	protected function hostName($h)
	{
		if (isset($h['hostName']))
			return $h['hostName'];

		return '';
	}

	protected function hostBackupDir($h)
	{
		if (isset($h['backupDir']))
			return $h['backupDir'];

		return '/var/lib/shipard/backups';
	}

	protected function hostPort($h)
	{
		if (isset($h['port']))
			return $h['port'];
		if (isset($this->backupCfg['defaults']['port']))
			return $this->backupCfg['defaults']['port'];

		return 22;
	}

	public function downloadAll()
	{
		$this->downloadDataSources();
		$this->downloadNodeServers();
	}

	public function updateDirStruct($dryRun = 1)
	{
		//$dateActive = new \DateTime('40 days ago');
		//$dateActiveStr = $dateActive->format('Y-m-d');
		$maxLastFilesCnt = 40;
		$lastFilesCnt = 0;

		$dir = '';

		$years = glob($dir . '????', GLOB_ONLYDIR);
		rsort($years);
		forEach ($years as $yearDir)
		{
			if ($yearDir == 'sync' || intval($yearDir) != $yearDir)
				continue;

			$allYearOK = 1;
			$months = glob($yearDir.'/??', GLOB_ONLYDIR);
			rsort($months);
			forEach ($months as $monthDir)
			{
				$allMonthOK = 1;
				$firstInMonth = 1;
				$days = glob($monthDir.'/??', GLOB_ONLYDIR);
				rsort($days);
				forEach ($days as $dayDir)
				{
					echo $dayDir;
					$files = glob ($dayDir.'/*.tgz');

					if (count($files) === 0)
					{
						echo " - no files!\n";
						$allMonthOK = 0;
						continue;
					}
					elseif (count($files) !== 1)
					{
						echo " - too many files!\n";
						$allMonthOK = 0;
						continue;
					}

					$dateStr = str_replace('/', '-', $dayDir);
					$date = new \DateTime($dateStr);
					$archiveDay = intval($date->format('d'));

					$cmd = '';
					if ($lastFilesCnt < $maxLastFilesCnt)
					{
						$cmd = "mv $dayDir/* . && rmdir $dayDir";
						echo ': '.$cmd;
						$lastFilesCnt++;
					}
					elseif ($firstInMonth || $archiveDay === 15)
					{
						$archiveDestDir = $dir.'archive/'.$date->format('Y');
						if (!is_dir($archiveDestDir))
							mkdir ($archiveDestDir, 0750, TRUE);

						$cmd = "mv $dayDir/* $archiveDestDir/ && rmdir $dayDir";
						echo ': '.$cmd;
						$firstInMonth = 0;
					}
					else
					{
						$cmd = "rm -rf $dayDir";
						echo ': '.$cmd;
					}

					if ($cmd !== '' && !$dryRun)
						passthru($cmd);

					echo "\n";
				}
				if ($allMonthOK)
				{
					// -- remove month dir
					$cmd = "rmdir $monthDir";
					echo '--- '.$cmd . "\n";
					if (!$dryRun)
						passthru($cmd);
				}
				else
				{
					echo "--- month NOT OK: $monthDir\n";
					$allYearOK = 0;
				}
			}
			if ($allYearOK)
			{
				// -- remove year dir
				$cmd = "rmdir $yearDir";
				echo '=== '.$cmd . "\n";
				if (!$dryRun)
					passthru($cmd);
			}
		}

		if ($dryRun)
			echo "### DRY RUN - no changes made. use --run to execute ###\n";
	}
}
