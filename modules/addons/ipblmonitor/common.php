<?php


define('DEBUG_LOCAL',false);
define('MONITOR_CHECK_JID','0-0-0-0');


mt_srand();

define('IPBLMONITOR_MODULE_NAME','IP Black List Monitor');
define('IPBLMONITOR_MODULE_VERSION','1.0');
define('IPBLMONITOR_MODULE_VENDOR', 'roman-int3 [https://github.com/roman-int3]');
define('IPBLMONITOR_MODULE_DESCRIPTION','IP Black List Monitor for WHMCS');


set_error_handler('IPBlMonitor_exception_error_handler');
register_shutdown_function('IPBlMonitor_fatal_handler');
spl_autoload_register(array('IPBlMonitorAutoloader', 'autoload'));

Logger::configure(__DIR__.'/log4php_config.xml');


include_once __DIR__.'/libs/IP.php';
include_once __DIR__.'/libs/DnsClient.php';




/**
 * exception_error_handler
 */
function IPBlMonitor_exception_error_handler($errno, $errstr, $errfile, $errline )
{
    $errMsg = $errno.' '. $errstr.' '. $errfile.' '. $errline;
    Logger::getLogger("")->error($errMsg);
}


class IPBlMonitorAutoloader
{
	/**
	 * Loads a class.
	 * @param string $className The name of the class to load.
	*/
	public static function autoload($className) 
	{
		if(!self::autoSearchClass($className,__DIR__.'/libs')){
		}
	}
	
	private static function autoSearchClass($className,$searchdir)
	{
		$dir=dir($searchdir);
		while($resName=$dir->read()){
			if($resName != '.' && $resName != '..' && is_dir($searchdir.'/'.$resName) === true) {
				if(self::autoSearchClass($className,$searchdir.'/'.$resName)){
					return true;
				}
			}
			else if(strcmp($resName,$className.'.php') === 0 ||
                    strcmp($resName,$className.'.class.php') === 0 ||
					strcmp($resName,$className.'.interface.php')===0){
				include_once $searchdir.'/'.$resName;
				return true;
			}
		}
		return false;
	}
}

function IPBlMonitor_fatal_handler()
{
    $errfile = "unknown file";
    $errstr  = "shutdown";
    $errno   = E_CORE_ERROR;
    $errline = 0;

    $error = error_get_last();

    if( $error !== NULL) {
        $errno   = $error["type"];
        $errfile = $error["file"];
        $errline = $error["line"];
        $errstr  = $error["message"];
    }
    Logger::getLogger("")->error(IPBlMonitor_format_error( $errno, $errstr, $errfile, $errline ));
}

/**
 * 
 * @param unknown $errno
 * @param unknown $errstr
 * @param unknown $errfile
 * @param unknown $errline
 * @return string
 */
function IPBlMonitor_format_error( $errno, $errstr, $errfile, $errline )
{
    $trace = print_r( debug_backtrace( false ), true );
    $content = "Error: $errstr\r\n";
    $content .= "Errno: $errno\r\n";
    $content .= "File: $errfile\r\n";
    $content .= "Line: $errline\r\n";
    $content .= "Trace:\r\n$trace\r\n\r\n";

    return $content;
}


function isIp($str)
{
    if(isIpV4($str)){
        return true;
    }
    return isIpV6($str);
}

function isIpV4($str)
{
    $ip4RegEx = '/(^((([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5]))$)/';
    return preg_match($ip4RegEx,$str);
}

// IPv6 RegEx
//(^((([0-9A-Fa-f]{1,4}:){7}([0-9A-Fa-f]{1,4}|:))|(([0-9A-Fa-f]{1,4}:){6}(:[0-9A-Fa-f]{1,4}|((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3})|:))|(([0-9A-Fa-f]{1,4}:){5}(((:[0-9A-Fa-f]{1,4}){1,2})|:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3})|:))|(([0-9A-Fa-f]{1,4}:){4}(((:[0-9A-Fa-f]{1,4}){1,3})|((:[0-9A-Fa-f]{1,4})?:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){3}(((:[0-9A-Fa-f]{1,4}){1,4})|((:[0-9A-Fa-f]{1,4}){0,2}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){2}(((:[0-9A-Fa-f]{1,4}){1,5})|((:[0-9A-Fa-f]{1,4}){0,3}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){1}(((:[0-9A-Fa-f]{1,4}){1,6})|((:[0-9A-Fa-f]{1,4}){0,4}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(:(((:[0-9A-Fa-f]{1,4}){1,7})|((:[0-9A-Fa-f]{1,4}){0,5}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:)))(%.+)?$)
function isIpV6($str)
{
    $ip6RegEx = '/(^((([0-9A-Fa-f]{1,4}:){7}([0-9A-Fa-f]{1,4}|:))|(([0-9A-Fa-f]{1,4}:){6}(:[0-9A-Fa-f]{1,4}|((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3})|:))|(([0-9A-Fa-f]{1,4}:){5}(((:[0-9A-Fa-f]{1,4}){1,2})|:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3})|:))|(([0-9A-Fa-f]{1,4}:){4}(((:[0-9A-Fa-f]{1,4}){1,3})|((:[0-9A-Fa-f]{1,4})?:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){3}(((:[0-9A-Fa-f]{1,4}){1,4})|((:[0-9A-Fa-f]{1,4}){0,2}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){2}(((:[0-9A-Fa-f]{1,4}){1,5})|((:[0-9A-Fa-f]{1,4}){0,3}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){1}(((:[0-9A-Fa-f]{1,4}){1,6})|((:[0-9A-Fa-f]{1,4}){0,4}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(:(((:[0-9A-Fa-f]{1,4}){1,7})|((:[0-9A-Fa-f]{1,4}){0,5}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:)))(%.+)?$)/';
    return preg_match($ip6RegEx,$str);
}

// Domain name RegEx
// (^((([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5]))$)
function isDomainName($str)
{
    $domainRegEx = '/(^((([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5]))$)/';
    return preg_match($domainRegEx,$str);
}





/**
 *
 * @param unknown $str
 * @return string
 */
function IpBlMonitor_ajaxError($str)
{
    return json_encode(array('status'=>'error','msg'=>$str));
}

/**
 *
 * @param string $response
 * @param string $msg
 * @param string $msgType
 * @return string
 */
function IpBlMonitor_ajaxSuccess($response=null,$msg=null,$msgType=null)
{
    $retArr = array('status'=>'ok');
    if ($response) {
        $retArr['response'] = $response;
    }
    if ($msg) {
        $retArr['msg'] = $msg;
    }
    return json_encode($retArr);
}

/**
 *
 * @param unknown $json
 */
function IpBlMonitor_ajaxFinish($json)
{
    if ($json != -1) {
        echo IpBlMonitor_ajaxSuccess($json);
    }
    header("Connection: close", true);
    ob_end_flush();
    flush();
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    die();
}

