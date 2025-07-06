<?php

namespace Shipard\papi;


/**
 * class IoTApi
 */
class IoTApi
{
  var $deviceUID = '';
  var $deviceMAC = '';
  var $deviceRecData = NULL;
  var $deviceCfg = NULL;

  var $operation = '';
	var $result = [];

	public function __construct(public \Shipard\WebApplication $app)
	{
	}

	public function init()
	{
	}

  protected function detectParams()
  {
    // -- deviceId
    $devId = $this->app->requestPath(2);
    if (substr($devId, 0, 4) === 'mac:')
    {
      $this->deviceMAC = str_replace('-', ':', substr($devId, 4));
      if ($this->deviceMAC === '')
      {
        $this->result['msg'] = 'Missing device MAC';
        return FALSE;
      }
      $baseFileName = strtolower(str_replace(':', '-', $this->deviceMAC));
      $fullFileName = '/var/www/iot-boxes/cfg/'.$baseFileName.'.json';
      if (is_readable($fullFileName))
      {
        $deviceCfgStr = file_get_contents($fullFileName);
        if ($deviceCfgStr === FALSE)
        {
          $this->result['msg'] = 'Cannot read device configuration file';
          $this->app->sendJson($this->result, 404);
          return TRUE;
        }
        $deviceCfg = json_decode($deviceCfgStr, TRUE);
        if ($deviceCfg === NULL)
        {
          $this->result['msg'] = 'Invalid device configuration file'; // JSON decode error
          return;
        }

        $this->deviceCfg = $deviceCfg;
      }
    }
    else
    {
      $this->deviceUID = $devId;
      if ($this->deviceUID === '')
      {
        $this->result['msg'] = 'Missing device UID';
        return FALSE;
      }
      // TODO: load via device uid
      $this->result['msg'] = 'Device UID is not supported yet';
    }

    // -- operation
    $this->operation = $this->app->requestPath(3);

    return TRUE;
  }

  protected function doOperation()
  {
    switch($this->operation)
    {
      case 'getDeviceCfg':
        $this->opGetDeviceCfg();
        break;
      case 'setDeviceInfo':
        $this->opSetDeviceInfo();
        break;
      default:
        $this->result['msg'] = 'Unknown operation `'.$this->operation.'`';
        return;
    }
  }

  protected function opGetDeviceCfg()
  {
    if ($this->deviceCfg)
    {
      $this->app->sendJson(json_encode($this->deviceCfg, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
      return TRUE;
    }

    return $this->app->error(404, 'Device with MAC `'.$this->deviceMAC.'` not found');
  }

  protected function opSetDeviceInfo()
  {
		$headers = $this->app->getAllHeaders();
    $topic = $headers['x-iot-topic'] ?? '';

    $postDataStr = $this->app->postData();
    if ($postDataStr[0] === '{')
    {
      // JSON data
      $postData = json_decode($postDataStr, TRUE);
      if (!$postData)
      {
        $this->result['error'] = 'Invalid JSON POST data';
        return;
      }

      if (isset($postData['pwr-batt-voltage']) || isset($postData['items']['verFW']))
      { // device info
        $postData['devNdx'] = $this->deviceCfg['ndx'];
        $postData['infoType'] = 'shn-ib-info';
        $postData['datetime'] = date('Y-m-d H:i:s');

        $fileName = '/var/lib/shipard-node/upload/lan/iot-box-'.$postData['devNdx'].'-'.time().'_'.mt_rand().'.json';
        file_put_contents($fileName, json_encode($postData, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));

        $this->app->sendText('OK');
        return TRUE;
      }
    }
    else
    { // text data
      $postData = $postDataStr;
      if (str_starts_with($topic, 'shp/sensors/'))
      { // sensor value
        /*
        $sensorData = [
          'serverId' => $this->deviceRecData['nodeServer'],
          'sensorsData' => [
            [
              'topic' => $topic,
              'value' => $postDataStr,
              'time' => microtime(),
            ],
          ]
        ];
        */
        // TODO: save to file
        $this->app->sendText('OK');
        return TRUE;
      }
    }
  }

	public function run ()
	{
    if (!$this->detectParams())
    {
      $this->result['error'] = 'Invalid parameters';
      $this->app->sendJson($this->result, 404);
      return FALSE;
    }

    $this->doOperation();

    return TRUE;
	}
}
