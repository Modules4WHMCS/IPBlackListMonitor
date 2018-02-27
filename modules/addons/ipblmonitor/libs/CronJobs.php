<?php


set_time_limit(0);
if(php_sapi_name()!=='cli') exit();

set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__);

$ROOTDIR=__DIR__."/../../../../";
include_once $ROOTDIR."init.php";
include_once $ROOTDIR.'includes/functions.php';

require_once __DIR__ . '/../common.php';

Logger::getLogger("debug")->debug('CronJobs.php runned');



try{
    //////////////////////////////////////////////////////////////////////////////////////////
    // Manual Checker Scan process
    $options = getopt(null,array("ip:","jid:","ForceReCheckMonitorIps:"));

    /////////////////////////////////////////////////////////////////////////////////////////
    // RBL Checker
    if(isset($options['ip'])) {
        $blcheck = new IpBlMonitor();

        $options['ip'] = trim($options['ip']);
        $blcheck->mysqlQuery('INSERT INTO mod_ipblmonitor_rblchecker (id,ip) VALUES(%s,%s)',
            $options['jid'],
            $options['ip']);
        if (isIp($options['ip'])) {
            $blcheck->rblCheckerCheckIP($options['ip'], $options['jid']);

        } else {
            $query = new DNSQuery("8.8.8.8");
            $result2 = $query->query($options['ip'], "A");
            $dnsRes = $result2->current();
            while ($dnsRes) {
                $blcheck->rblCheckerCheckIP($dnsRes->getData(), $options['jid']);
                $dnsRes = $result2->next();
            }

            $blcheck->rblCheckerCheckIP($options['ip'], $options['jid'], true);

        }
        $blcheck->mysqlQuery('UPDATE mod_ipblmonitor_rblchecker SET status="finished" WHERE id=%s',
            $options['jid']);

        exit();
    }


    /////////////////////////////////////////////////////////////////////////////////////////
    // Cron run
    Logger::getLogger('ipmonitor')->debug('CronJobs.php started');
    Logger::getLogger('ipmonitor')->debug($options);
    $ipBlMonitor = new IpBlMonitor();
    $ipBlMonitor->checkMonitoredIps($options['ForceReCheckMonitorIps']);


    // DELETE MANUAL CHECK JOBS
    $ipBlMonitor->mysqlQuery('DELETE FROM mod_ipblmonitor_rblchecker 
                                      WHERE UNIX_TIMESTAMP(NOW())-UNIX_TIMESTAMP(time) > 24*60*60 AND id!="'.MONITOR_CHECK_JID.'"');


}
catch(Exception $e){
    Logger::getLogger('')->error("Exception: ".$e);
}






