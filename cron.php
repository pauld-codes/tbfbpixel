<?php
ini_set('max_execution_time', 300);

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

include(dirname(__FILE__).'/../../config/config.inc.php');

//$remoteFile = 'http://www.google-analytics.com/ga.js';
$remoteFile = 'https://connect.facebook.net/en_US/fbevents.js';

// renamed original js, to bypass a.d.s. blocking
$localfile = dirname(__FILE__) . '/views/js/tbf.js';

if(true)
{
	clearstatcache();
	
	if(!file_exists($localfile))
	{
		$fcreate = fopen($localfile, 'w');
		fclose($fcreate);
	}

	if(is_writable($localfile))
	{
		$response = Tools::file_get_contents($remoteFile);
		
		if ($response !== false)
		{
			// Remove the headers
			$pos = strpos($response, "\r\n\r\n");
			$response = substr($response, $pos + 4);
			// remove comments
			$response = trim(preg_replace('!/\*.*?\*/!s', '', $response));
			//$response = preg_replace('/<!--(.*)-->/Uis', '', $response);
			
			if($handle = fopen($localfile, 'w'))
			{
				try
				{
					fwrite($handle, $response);
					fclose($handle);
				}
				catch (Exception $e)
				{
					die('error:saving');
				}
				
				//die('ok');
			
			}
			else
				die('error:opening');
		}
		else
			die('error:connection');
	}
	else
		die('error:unwritable');
}

if (_PS_MODE_DEV_)
{
	echo "\n\nDEBUG:\n" . 'total time: ' . number_format((microtime(true) - $start_time), 2) .
	"s \n" . 'memory: ' . number_format(memory_get_peak_usage(true) / 1048576, 2)  . 'MB';
}