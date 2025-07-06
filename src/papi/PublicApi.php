<?php

namespace Shipard\papi;


/**
 * class PublicApi
 */
class PublicApi
{
	public function __construct(public \Shipard\WebApplication $app)
	{
	}

	protected function iotApi()
	{
    $iotApi = new \Shipard\papi\IoTApi($this->app);
		return $iotApi->run();
	}

	protected function esignsApi()
	{
		return TRUE;
	}

	public function run()
	{
		if ($this->app->requestPath(1) === 'iot-api')
		{
			return $this->iotApi();
		}

		if ($this->app->requestPath(1) === 'esigns-api')
		{
			return $this->esignsApi();
		}

		return $this->app->error(404, 'Not found - invalid API id');
	}
}
