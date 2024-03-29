#!/usr/bin/env php
<?php

require_once __DIR__.'/../src/node.php';


/**
 * Class WatchApp
 */
class WatchApp extends \Shipard\Application
{
	var $cameraId;
	var $cameraDir;
	var $cameraCfg = NULL;
	var $evd = 0;

	var $sizeFilesCounter = 0;
	var $sizeFilesLen = 0;

	public function watch()
	{
		$fd = inotify_init();
		$watch_descriptor = inotify_add_watch($fd, $this->cameraDir, IN_CLOSE_WRITE);

		$redis = new Redis ();
		$redis->connect('127.0.0.1');

		$keyImage = 'e10-monc-camLastImg-'.$this->cameraId;
		$keyTime = 'e10-monc-camLastImgTime-'.$this->cameraId;

		while (1)
		{
			$events = inotify_read($fd);

			foreach ($events as $event)
			{
				$imgFileName = $event['name'];

				// -- vehicle-detect
				if ($this->evd)
				{
					$isVD = 0;
					//if (substr($imgFileName, -21) === 'VEHICLE_DETECTION.jpg')
					//	$isVD = 1;
					$vdid = $this->cameraId.'_';
					if (str_starts_with($imgFileName, $vdid))
						$isVD = 1;

					if ($isVD)
					{
						$srcFileName = $this->cameraDir . '/' . $imgFileName;
						$dstFileName = $this->vehicleDetectDir . $imgFileName;
						rename($srcFileName, $dstFileName);

						continue;
					}
				}

				$redis->set($keyImage, $imgFileName);
				$redis->set($keyTime, time());

				$srcFileName = $this->cameraDir . '/' . $imgFileName;
				$this->sizeFilesLen += filesize($srcFileName);
				$this->sizeFilesCounter++;

				if ($this->sizeFilesCounter > 1800)
				{
					$avgFileSize = intval($this->sizeFilesLen / $this->sizeFilesCounter);
					file_put_contents('/var/lib/shipard-node/tmp/cam_pict_avg_size_'.$this->cameraId, strval($avgFileSize));

					$netDataStrValue = 'cameras.avgImgSize.'.$this->cameraId.': '.$avgFileSize."|g|#units=bytes,family=cameras.avgImgSizes\n";
					$sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
					socket_sendto($sock, $netDataStrValue, strlen($netDataStrValue), 0, '127.0.0.1', 8125);
					socket_close($sock);

					$this->sizeFilesLen = 0;
					$this->sizeFilesCounter = 0;
				}
			}
		}

		inotify_rm_watch($fd, $watch_descriptor);
	}

	public function run ()
	{
		$this->cameraId = $this->command();
		if ($this->cameraId === '')
			return $this->err('ERROR: Missing cameraId param.');

		if (!isset($this->nodeCfg['cfg']['cameras'][$this->cameraId]))
			return $this->err('ERROR: Invalid cameraId param.');

		$this->cameraCfg = $this->nodeCfg['cfg']['cameras'][$this->cameraId];

		$this->cameraDir = $this->picturesDir;
		if ($this->cameraCfg['cfg']['picturesFolder'])
			$this->cameraDir .= $this->cameraCfg['cfg']['picturesFolder'];
		else
			$this->cameraDir .= $this->cameraId;

		if (isset($this->cameraCfg['cfg']['enableVehicleDetect']) && $this->cameraCfg['cfg']['enableVehicleDetect'])
			$this->evd = 1;

		if (!is_dir($this->cameraDir))
			return $this->err("ERROR: Camera directory '{$this->cameraDir}' not found.");

		$this->watch();
	}
}

$myApp = new WatchApp ($argv);
$myApp->run ();

