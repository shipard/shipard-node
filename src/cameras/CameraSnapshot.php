<?php


namespace Shipard\cameras;


/**
 * class CameraSnapshot
 */
class CameraSnapshot
{
	/** @var  \Shipard\WebApplication */
	var $app;

  var $cameraNdx = '';
  var $cameraCfg = NULL;
  var $baseFileName = '';

	public function __construct ($app)
	{
		$this->app = $app;
	}

  protected function snapshotUrl()
  {
    // -- from camera:  http://USER:PASSWORD@10.11.12.111/ISAPI/Streaming/channels/101/picture
    // -- from go2rtc:  http://127.0.0.1:1984/api/frame.jpeg?src=camId
    // -- from frigate: http://127.0.0.1:5000/api/cameraId/latest.jpg
    if ($this->cameraCfg === NULL)
      return '';

    $url = 'http://';
    $url .= $this->cameraCfg['cfg']['camLogin'] . ':' . $this->cameraCfg['cfg']['camPasswd'] . '@';
    $url .= $this->cameraCfg['ip'] . '/ISAPI/Streaming/channels/101/picture';

    return $url;
  }

  public function init()
  {
    $cameraNdx = intval($this->app->requestPath(2));
		$this->baseFileName = $this->app->requestPath(3);

    $this->cameraNdx = $cameraNdx;
    $this->cameraCfg = $this->app->nodeCfg['cfg']['cameras'][$cameraNdx] ?? NULL;
  }

  public function download()
  {
    $dstFileName = 'imgcache/'.$this->baseFileName;

    if ($this->baseFileName !== '' && is_readable($dstFileName))
    {
      $imgPath = $this->app->urlRoot.'/'.$dstFileName;
      header('Content-type: ' . 'image/jpeg');
      header('X-Accel-Redirect: ' . $imgPath);
      return NULL;
    }

    $url = $this->snapshotUrl();

    copy($url, $dstFileName);

    $imgPath = $this->app->urlRoot.'/'.$dstFileName;
    header('Content-type: ' . 'image/jpeg');
    header('X-Accel-Redirect: ' . $imgPath);

    return NULL;
  }
}
