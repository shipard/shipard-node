<?php

namespace Shipard\control;


/**
 * Class Control
 */
class Control
{
	var $app;

	var $requestData = NULL;
	var $responseData = ['success' => 0];

	public function __construct($app)
	{
		$this->app = $app;
	}

	public function setRequest($requestData)
	{
		$this->requestData = $requestData;
	}

	function doThingAction()
	{
		if (!isset($this->requestData['thing']) || $this->requestData['thing'] == '')
			return;
		if (!isset($this->requestData['thingAction']) || $this->requestData['thingAction'] == '')
			return;
		if (!isset($this->requestData['iotControl']) || $this->requestData['iotControl'] == '')
			return;

		if (!isset($this->app->mqttEngineCfg['things'][$this->requestData['thing']]))
			return;

		$thingCfg = $this->app->mqttEngineCfg['things'][$this->requestData['thing']];

		$cmd = '/usr/lib/shipard-node/tools/shn-iot-thing.php action';
		$cmd .= ' --type='.$thingCfg['coreType'];
		$cmd .= ' --iot-control="'.$this->requestData['iotControl'].'"';
		$cmd .= ' --thing="'.$this->requestData['thing'].'"';
		$cmd .= ' --thing-action="'.$this->requestData['thingAction'].'"';

		passthru($cmd);

		$this->responseData['success'] = 1;
	}

	function sendMqttMessage($topic, $payload)
	{
		$mqttHost = '127.0.0.1';
		$cmd = 'mosquitto_pub -h '.$mqttHost.' -t "'.$topic.'" -m \''.$payload.'\'';
		//echo $cmd."\n";

		passthru($cmd);
	}

	function doMqttPublish()
	{
		if (!isset($this->requestData['mqttTopic']) || $this->requestData['mqttTopic'] == '')
			return;
		if (!isset($this->requestData['mqttPayload']) || $this->requestData['mqttPayload'] == '')
			return;
		
		$this->sendMqttMessage($this->requestData['mqttTopic'], $this->requestData['mqttPayload']);
	}

	public function run()
	{
		if ($this->requestData['actionType'] === 'thing-action')
			$this->doThingAction();
		elseif ($this->requestData['actionType'] === 'mqtt-publish')
			$this->doMqttPublish();
	}
}
