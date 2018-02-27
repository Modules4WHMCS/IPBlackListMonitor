<?php


set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__);
require_once __DIR__.'/common.php';



add_hook("AdminAreaHeadOutput",1,"ipblmonitor_AdminAreaHeadOutput");
/**
 * Хук функция для добавления кастомных html хедеров на страницы админ интерфейса
 * @return String html код для добавления в head страницы
 */
function ipblmonitor_AdminAreaHeadOutput()
{
    $smarty = new ipblSmarty();
    $smarty->assign('adminhead',true);
    $retData = $smarty->ipblfetch();
    return $retData;
}


/**
 * Функция получения информации о конфигурации модуля для WHMCS
 * @return multitype:string
 */
function ipblmonitor_config()
{
    $configarray = array(
        "name" => IPBLMONITOR_MODULE_NAME,
        "description" => IPBLMONITOR_MODULE_DESCRIPTION,
        "version" => IPBLMONITOR_MODULE_VERSION,
        "author" => IPBLMONITOR_MODULE_VENDOR,
        "language" => "english"//,

    );
    return $configarray;
}

/**
 * функция активации модуля WHMCS
 * @return multitype:string NULL |multitype:string
 */
function ipblmonitor_activate()
{
    try{
        $ipbl = new IpBlMonitor();
        $ipbl->activate();
    }
    catch (Exception $e){
        Logger::getLogger('')->error("Exception",$e);
        return array('status'=>'error','description'=> $e->getMessage());
    }

    return array('status'=>'success','description'=>'Installation is successful.');
}

/**
 * Деактивация модуля WHMCS
 * @return multitype:string NULL |multitype:string
 */
function ipblmonitor_deactivate()
{
    try{
        $ipbl = new IpBlMonitor();
        $ipbl->deactivate();
    }
    catch (Exception $e){
        Logger::getLogger('')->error("Exception",$e);
        return array('status'=>'error','description'=> $e->getMessage());
    }

    return array('status'=>'success','description'=>'Uninstallation is successful.');
}

/**
 * Получение данных от модуля для отображения функционала WHMCS модуля
 * @param Array $vars
 */
function ipblmonitor_output($vars)
{
    global $aInt,$whmcs;

    Logger::getLogger("debug")->debug($_REQUEST);
    $ipbl = new IpBlMonitor();


    ///////////////////////////////////////////////////////////////////////
    // AJAX request processing
    if($_REQUEST['f']){
        try{
            $aInt->adminTemplate = '';
            $json = null;

            if(!function_exists('ModuleBuildParams')){
                require_once __DIR__.'/../../../includes/modulefunctions.php';
            }

            $callFunc = 'ajax'.ucfirst($_REQUEST['f']);

            if(method_exists($ipbl,$callFunc)) {
                if ($isLicenseValid !== true) {
                    if ($callFunc === "ajaxSaveSettings" ||
                        $callFunc === "ajaxGetSettings" ||
                        $callFunc === "ajaxIpblMonitor") {
                        $json = $ipbl->$callFunc();
                    }
                }
                else{
                    $json = $ipbl->$callFunc();
                }
            }
        }
        catch(Exception $e){
            Logger::getLogger(__FUNCTION__)->error("Exception",$e);
            echo IpBlMonitor_ajaxError($e->getMessage());
            header("Connection: close", true);

            ob_end_flush();
            flush();
            if(function_exists('fastcgi_finish_request')) fastcgi_finish_request();

            exit();
        }

        if($json !== -1){
            Logger::getLogger(__FUNCTION__)->debug($json);
            $response = IpBlMonitor_ajaxSuccess($json);
        }

        ob_end_clean();
        header("Connection: close");
        ignore_user_abort();
        ob_start();
        echo $response;
        $size = ob_get_length();
        header("Content-Length: $size");
        http_response_code(200);
        ob_end_flush();
        flush();

        exit();
    }

    try{
        $smarty = new ipblSmarty();
        $smarty->assign('adminbody',true);
        $smarty->ipbldisplay();
    }
    catch (Exception $e){
        Logger::getLogger(__FUNCTION__)->error("Exception",$e);
    }

}

function ipblmonitor_mycustomfunction($vars) {

}






