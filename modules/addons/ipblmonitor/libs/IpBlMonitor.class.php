<?php

ini_set('memory_limit', '256M');

include_once __DIR__.'/3rdlib/PHPMailer/src/PHPMailer.php';
include_once __DIR__.'/3rdlib/PHPMailer/src/Exception.php';
include_once __DIR__.'/3rdlib/PHPMailer/src/OAuth.php';
include_once __DIR__.'/3rdlib/PHPMailer/src/POP3.php';
include_once __DIR__.'/3rdlib/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer AS PHPMailer;
use IpBlMonitor\IP AS IPUtil;

class IpBlMonitor
{
	public $db_host,$db_username,$db_password,$db_name,$db_link;
	public $blackListedIPs;
    public $whiteListedIPs;
    private $checkMonitorDnsQuery;
	
	public function __construct()
	{
        $this->reconnectToDB();
	}
	
	public function __destruct()
	{
		if($this->db_link){
			mysqli_close($this->db_link);
		}
	}


	private function reconnectToDB()
    {
        if($this->db_link){
            mysqli_close($this->db_link);
        }
        if(DEBUG_LOCAL){
            include '/home/roman/www/whmcs6/configuration.php';
        }
        else{
            include __DIR__ . '/../../../../configuration.php';
        }

        $this->db_host = $db_host;
        $this->db_username = $db_username;
        $this->db_password = $db_password;
        $this->db_name = $db_name;
        $this->db_link=mysqli_connect($this->db_host,$this->db_username,$this->db_password,$this->db_name);
    }

    public function activate()
    {
        $installDataFile = __DIR__.'/../install/mod_ipblmonitor_db.sql';
        $tablesSql = file_get_contents($installDataFile);

        $result=mysqli_multi_query($this->db_link, $tablesSql);
        sleep(1);
        $this->reconnectToDB();
        mysqli_multi_query($this->db_link, file_get_contents(__DIR__.'/../install/mod_ipblmonitor_rbl.sql'));
        $this->reconnectToDB();
        $this->mysqlQuery("INSERT INTO mod_ipblmonitor_rblchecker (id,ip,status) 
                                      VALUES('".MONITOR_CHECK_JID."','0.0.0.0','finished')");
        $this->mysqlQuery('INSERT INTO mod_ipblmonitor (id,checkfrequency) VALUES(0,24)');
    }

    public function deactivate()
    {
        if (!DEBUG_LOCAL) {
            $this->mysqlQuery('DROP TABLE mod_ipblmonitor');
            $this->mysqlQuery('DROP TABLE mod_ipblmonitor_black_ips');
            $this->mysqlQuery('DROP TABLE mod_ipblmonitor_ips');
            $this->mysqlQuery('DROP TABLE mod_ipblmonitor_rbl');
            $this->mysqlQuery('DROP TABLE mod_ipblmonitor_rblchecker');
            $this->mysqlQuery('DROP TABLE mod_ipblmonitor_groups');
        }
    }

    private function findRblDns($query)
    {
        $nsServers = null;
        if($query->dns_answers->current()) {
            foreach($query->dns_answers as $dnsAnswer) {
                if ($dnsAnswer->getTypeid() === "NS") {
                    $nsServers[] = $dnsAnswer->getData();
                }
            }

            Logger::getLogger('findRblDns')->debug($query);
            return $nsServers;
        }

        $nsServer = $query->getLastnameservers()->current();
        if($nsServer && $nsServer->getTypeid() === "SOA") {
            $nsName = $nsServer->getDomain();
            $newQuery = new DNSAsyncQuery('8.8.8.8');
            $newQuery->dnsZone = $query->dnsZone;
            $newQuery->query($nsName, "NS");
            return -1;
        }

        return null;
    }

    public function checkAllRblServers()
    {
        $rootDnsIP = "8.8.8.8";

        $result = $this->mysqlQuery('SELECT dnszone FROM mod_ipblmonitor_rbl');
        while ($row = mysqli_fetch_assoc($result)) {
            if (!$row['dnszone']) continue;
            $query = new DNSAsyncQuery($rootDnsIP);
            $query->query($row['dnszone'], "NS");
            $query->dnsZone = $row['dnszone'];
        }

        while(true) {
            if (!$query) {
                return -1;
            }

            $currentQuerysCount = $query->getQueryObjetsCount();
            if ($currentQuerysCount === 0) {
                break;
            }

            $dnsQueryArr = $query->getAnswers();
            foreach ($dnsQueryArr as $DNSAsyncQuery) {
                if (!$DNSAsyncQuery->error) {
                    $DnsServersArr = $this->findRblDns($DNSAsyncQuery);
                    if (is_array($DnsServersArr)) {
                        $DnsServerIpArr=array();
                        foreach ($DnsServersArr as $hostname){
                            $hostip=gethostbyname($hostname);
                            /*$dnsIp4Query = new DNSQuery('8.8.8.8');
                            $ip4Answer=$dnsIp4Query->query($hostname,'A');

                            $dnsIp4Query = new DNSQuery('8.8.8.8');
                            $ip4Answer=$dnsIp4Query->query($hostname,'A');*/

                            if($hostip === $hostname) {
                                continue;
                            }
                            $DnsServerIpArr[]=$hostip;
                        }
                        if(count($DnsServerIpArr)>0) {
                            $this->mysqlQuery("UPDATE mod_ipblmonitor_rbl SET status='good',
                                  lastcheck=NOW(),zone_ns_servers=%s WHERE dnszone=%s",
                                serialize($DnsServerIpArr), $DNSAsyncQuery->dnsZone);
                            continue;
                        }
                    }
                }
                    $this->mysqlQuery("UPDATE mod_ipblmonitor_rbl SET status='bad', lastcheck=NOW() WHERE dnszone=%s",
                        $DNSAsyncQuery->dnsZone);

            }
            usleep(500);
        }
    }

    public function checkMonitoredIP($ip,$gid,$blockid,$jid=MONITOR_CHECK_JID,$isDomain=false)
    {
        Logger::getLogger('ipmonitor')->debug(__METHOD__);
        Logger::getLogger('ipmonitor')->debug($ip.'/'.$gid.'/'.$blockid.'/'.$jid.'/'.$isDomain);
        if($ip) {
            $dnsZonesArr = null;
            if (!$isDomain) {
                $reverse_ip = implode(".", array_reverse(explode(".", $ip)));
                $rDns = dns_get_record($reverse_ip . '.in-addr.arpa');
                $hostName = gethostbyaddr($ip);
                $rDns = $rDns[0]['target'];
                $dnsZonesArr = $this->ipv4DnsZones;
            } else {
                $reverse_ip = $ip;
                $dnsZonesArr = $this->domainDnsZones;
            }

            Logger::getLogger('ipmonitor')->debug("TEST1");
            foreach ($dnsZonesArr as $rbl_row) {
                Logger::getLogger('ipmonitor')->debug($rbl_row);
                $query = new DNSAsyncQuery($rbl_row['zone_ns_servers'][0]);
                $query->dnsZone = $rbl_row['dnszone'];
                $query->qType = 'A';
                $query->queryIP = $ip;
                $query->reverse_ip = $reverse_ip;
                $query->hostName = $hostName;
                $query->rDns = $rDns;
                $query->gid = $gid;
                $query->jid = $jid;
                $query->bid = $blockid;
                $query->dns_servers = $rbl_row['zone_ns_servers'];
                $query->dns_servers_index = 1;
                $query->dns_servers_index_max = count($rbl_row['zone_ns_servers']);
                $query->query($reverse_ip . '.' . $query->dnsZone, "A");
                if ($this->checkMonitorDnsQuery === null) {
                    $this->checkMonitorDnsQuery = $query;
                }
            }
        }

        if(!$this->checkMonitorDnsQuery){
            return;
        }

        $dnsQueryArr = $this->checkMonitorDnsQuery->getAnswers();
        Logger::getLogger('ipmonitor')->debug("dnsQueryArr=".count($dnsQueryArr));

        foreach ($dnsQueryArr as $DNSAsyncQuery) {
            if (!$DNSAsyncQuery->error) {
                if ($DNSAsyncQuery->qType === 'A') {
                    $dnsAnswer = $DNSAsyncQuery->dns_answers->current();
                    if (!$dnsAnswer) {
                        $this->mysqlQuery($this->blackip_del_query, $DNSAsyncQuery->queryIP, $DNSAsyncQuery->dnsZone);
                        continue;
                    }

                    $newQuery = new DNSAsyncQuery($DNSAsyncQuery->dns_servers[$DNSAsyncQuery->dns_servers_index]);
                    $newQuery->dnsZone = $DNSAsyncQuery->dnsZone;
                    $newQuery->qType = 'TXT';
                    $newQuery->gid = $DNSAsyncQuery->gid;
                    $newQuery->jid = $DNSAsyncQuery->jid;
                    $newQuery->bid = $DNSAsyncQuery->bid;
                    $newQuery->hostName = $DNSAsyncQuery->hostName;
                    $newQuery->rDns = $DNSAsyncQuery->rDns;
                    $newQuery->queryIP = $DNSAsyncQuery->queryIP;
                    $newQuery->dns_servers = $DNSAsyncQuery->dns_servers;
                    $newQuery->dns_servers_index = $DNSAsyncQuery->dns_servers_index;
                    $newQuery->dns_servers_index_max = $DNSAsyncQuery->dns_servers_index_max;
                    $newQuery->parent = $DNSAsyncQuery;
                    $newQuery->reverse_ip = $DNSAsyncQuery->reverse_ip;
                    $newQuery->query($newQuery->reverse_ip . '.' . $newQuery->dnsZone, "TXT");
                } else {
                    $this->updateMonitoredBlackListDB($DNSAsyncQuery);
                }
            }
            else{
                if ($DNSAsyncQuery->dns_servers_index >= $DNSAsyncQuery->dns_servers_index_max) {
                    $this->updateMonitoredBlackListDB($DNSAsyncQuery);
                    continue;
                }
                $newQuery = new DNSAsyncQuery($DNSAsyncQuery->dns_servers[$DNSAsyncQuery->dns_servers_index]);
                $newQuery->dnsZone = $DNSAsyncQuery->dnsZone;
                $newQuery->qType = $DNSAsyncQuery->qType;
                $newQuery->queryIP = $DNSAsyncQuery->queryIP;
                $newQuery->gid = $DNSAsyncQuery->gid;
                $newQuery->jid = $DNSAsyncQuery->jid;
                $newQuery->bid = $DNSAsyncQuery->bid;
                $newQuery->hostName = $DNSAsyncQuery->hostName;
                $newQuery->rDns = $DNSAsyncQuery->rDns;
                $newQuery->dns_servers = $DNSAsyncQuery->dns_servers;
                $newQuery->dns_servers_index = $DNSAsyncQuery->dns_servers_index + 1;
                $newQuery->dns_servers_index_max = $DNSAsyncQuery->dns_servers_index_max;
                $newQuery->parent = $DNSAsyncQuery->parent;
                $newQuery->reverse_ip = $DNSAsyncQuery->reverse_ip;
                $newQuery->query($newQuery->reverse_ip . '.' . $newQuery->dnsZone, $DNSAsyncQuery->qType);
            }

        }

        Logger::getLogger('ipmonitor')->debug("RETURN");

    }

    private $blackip_del_query = 'DELETE FROM mod_ipblmonitor_black_ips WHERE ip=%s AND rbl_server=%s';

    private function updateMonitoredBlackListDB($DNSAsyncQuery)
    {
        Logger::getLogger('ipmonitor')->debug("updateMonitoredBlackListDB");
        if(!$DNSAsyncQuery){
            Logger::getLogger('ipmonitor')->debug("RETURN");
            return false;
        }

        if($DNSAsyncQuery->parent->dns_answers){
            $a_result = $DNSAsyncQuery->parent->dns_answers->current()->getData();
        }
        if($DNSAsyncQuery->dns_answers){
            $dnsAnswer = $DNSAsyncQuery->dns_answers->current();
        }
        $txt_result = "";
        if ($dnsAnswer) {
            $txt_result = $dnsAnswer->getData();
        }
        if ($txt_result) {
            $txt_result = preg_replace('/(http[s]?:\/\/[^\s]*)/',
                '<a style="color:blue;" target="_blank" href="$1">$1</a>', $txt_result);
        }
        if (!$a_result) {
            $this->mysqlQuery($this->blackip_del_query, $DNSAsyncQuery->queryIP, $DNSAsyncQuery->dnsZone);
        }
        else{
            $result=$this->mysqlQuery('SELECT ip FROM mod_ipblmonitor_black_ips 
                                                  WHERE ip=%s AND rbl_server=%s AND jid="'.MONITOR_CHECK_JID.'"',
                                                $DNSAsyncQuery->queryIP,
                                                $DNSAsyncQuery->dnsZone
                                                );
            if(mysqli_num_rows($result) > 0) {
                $this->mysqlQuery('UPDATE mod_ipblmonitor_black_ips SET
                                              host_name=%s,
                                              rdns=%s,
                                              rbl_response_a=%s,
                                              rbl_response_txt=%s
                                              WHERE ip=%s AND rbl_server=%s AND jid="'.MONITOR_CHECK_JID.'"',
                    $DNSAsyncQuery->hostName,
                    $DNSAsyncQuery->rDns,
                    $a_result,
                    $txt_result,
                    $DNSAsyncQuery->queryIP,
                    $DNSAsyncQuery->dnsZone
                );
            }
            else{
                $this->mysqlQuery('INSERT INTO mod_ipblmonitor_black_ips 
                                          (ip,rbl_server,host_name,rdns,rbl_response_a,
                                          rbl_response_txt,gid,bid,jid) 
                                          VALUES(%s,%s,%s,%s,%s,%s,%s,%s,%s)',
                    $DNSAsyncQuery->queryIP,
                    $DNSAsyncQuery->dnsZone,
                    $DNSAsyncQuery->hostName,
                    $DNSAsyncQuery->rDns,
                    $a_result, $txt_result,
                    $DNSAsyncQuery->gid,
                    $DNSAsyncQuery->bid,
                    $DNSAsyncQuery->jid
                    );
            }
        }

        Logger::getLogger('ipmonitor')->debug("RETURN");
        return true;
    }


    private $ipv4DnsZones;
    private $ipv6DnsZones;
    private $domainDnsZones;

    public function checkMonitoredIps($ForceReCheckMonitorIps=false)
    {
        Logger::getLogger('ipmonitor')->debug(__METHOD__);
        $result = $this->mysqlQuery('SELECT * FROM mod_ipblmonitor WHERE id=0');
        $settings=mysqli_fetch_assoc($result);
        $checkfrequency=$settings['checkfrequency']*60*60;
        $result = $this->mysqlQuery('SELECT UNIX_TIMESTAMP(lastscan) AS lastscan FROM mod_ipblmonitor_ips 
                                                                  WHERE lastscan');
        $row=mysqli_fetch_assoc($result);
        $lastscan=$row['lastscan'];
        if($ForceReCheckMonitorIps===false && time()-$lastscan < $checkfrequency){
            return;
        }

        $lock = new IpBlLock('ipblmonitor-cron');
        if($lock->lock()){
            $this->mysqlQuery('UPDATE mod_ipblmonitor_rblchecker SET status="runned" WHERE id=%s',
                                                    MONITOR_CHECK_JID);
            if(!$ForceReCheckMonitorIps) {
                $this->checkAllRblServers();
                sleep(5);
            }

            $result = $this->mysqlQuery("SELECT dnszone,zone_ns_servers 
                      FROM mod_ipblmonitor_rbl WHERE status='good' AND ipv6='yes'");
            while($row=mysqli_fetch_assoc($result)){
                $row['zone_ns_servers']=unserialize($row['zone_ns_servers']);
                $this->ipv6DnsZones[]=$row;}
            $result = $this->mysqlQuery("SELECT dnszone,zone_ns_servers 
                      FROM mod_ipblmonitor_rbl WHERE status='good' AND ipv4='yes'");
            while($row=mysqli_fetch_assoc($result)){
                $row['zone_ns_servers']=unserialize($row['zone_ns_servers']);
                $this->ipv4DnsZones[]=$row;}
            $result = $this->mysqlQuery("SELECT dnszone,zone_ns_servers 
                      FROM mod_ipblmonitor_rbl WHERE status='good' AND domain='yes'");
            while($row=mysqli_fetch_assoc($result)){
                $row['zone_ns_servers']=unserialize($row['zone_ns_servers']);
                $this->domainDnsZones[]=$row;}

            $ipresult=$this->mysqlQuery('SELECT * FROM mod_ipblmonitor_ips');
            Logger::getLogger('ipmonitor')->debug("ipv4DnsZones");
            Logger::getLogger('ipmonitor')->debug($this->ipv4DnsZones);

            while($iprow=mysqli_fetch_assoc($ipresult)){
                $ipstart = $iprow['ip_start'];
                Logger::getLogger('ipmonitor')->debug("ipstart=".$ipstart);

                $ipend = $iprow['ip_end'];
                $netmask = $iprow['netmask'];
                if(isIp($ipstart)) {
                    if ($ipend) {
                        for($ip = IPUtil\IP::create($ipstart); $ip->humanReadable() !== $ipend; $ip = $ip->plus(1)){
                            $this->checkMonitoredIP($ip->humanReadable(),$iprow['gid'],$iprow['id']);
                        }
                    } else if ($netmask) {
                        $ipblock = IPUtil\IPBlock::create($ipstart, $netmask);
                        for ($i = 0; $i < $ipblock->count(); $i++, $ipblock->next()) {
                            $this->checkMonitoredIP($ipblock->current()->humanReadable(),$iprow['gid'],$iprow['id']);
                        }
                    } else {
                        $this->checkMonitoredIP($ipstart, $iprow['gid'],$iprow['id']);
                    }
                }
                else{
                    $this->checkMonitoredIP($ipstart, $iprow['gid'],$iprow['id'], MONITOR_CHECK_JID,true);
                }

                Logger::getLogger('ipmonitor')->debug("usleep");
                usleep(500);
            }

            ////////////////////////////////////////////////////////////////////////////////////////////////////////
            // Wait all response from rbl servers
            while($this->checkMonitorDnsQuery->getQueryObjetsCount()){
                Logger::getLogger('ipmonitor')->debug('getQueryObjetsCount='.$this->checkMonitorDnsQuery->getQueryObjetsCount());
                $this->checkMonitoredIP(0, 0,0);
                usleep(500);
            }


            $this->mysqlQuery('UPDATE mod_ipblmonitor_ips SET lastscan=NOW()');
            $this->mysqlQuery('UPDATE mod_ipblmonitor_rblchecker SET status="finished" WHERE id=%s',
                                            MONITOR_CHECK_JID);

            if($settings['notifyemails'] && $settings['enable_notifyemails']){
                $statArr=$this->ajaxDashStats();
                $summaryText = "IP BlackList Monitor\n";
                $summaryText .=  $statArr['ipinfo']['title']."\n".
                                'BlackListed: '.$statArr['ipinfo']['data']['BlackListed']."\n".
                                'Clean: '.$statArr['ipinfo']['data']['Clean']."\n".
                                'Last check: '.date("Y-m-d H:i:s")."\n\n";


                $reportEmails=explode("\n",$settings['notifyemails']);

                $mail = new PHPMailer\PHPMailer();
                $mail->From = 'ipblmonitor@'.gethostname();
                $mail->FromName = 'IP Black List Monitor';

                if($settings['smtp_host']){
                    $mail->isSMTP();
                    $mail->Host = $settings['smtp_host'];
                    $mail->Port = $settings['smtp_port'];
                    if($settings['smtp_user'] != '')
                    {
                        $mail->SMTPAuth  = true;
                        $mail->Username  = $settings['smtp_user'];
                        $mail->Password  = $settings['smtp_password'];
                    }
                }
                foreach ($reportEmails as $email) {
                    if(trim($email)!='') {
                        $mail->addAddress($email);
                    }
                }
                $mail->Subject = 'IPBLMonitor Check Report';
                $mail->isHTML(false);
                $mail->Body = $summaryText;
                $mail->send();

                $command = 'LogActivity';
                $postData = array(
                    'description' => $summaryText,
                );
                $adminUsername = $settings['adminusername']; // Optional for WHMCS 7.2 and later

                $results = localAPI($command, $postData, $adminUsername);
            }

            $lock->free();
        }
        else{
            Logger::getLogger('')->error("ERROR lock file");
        }
    }


    public function rblCheckerCheckIP($ip,$jid,$isDomain=false)
    {
        if (!$isDomain) {
            $reverse_ip = implode(".", array_reverse(explode(".", $ip)));
            $rDns = dns_get_record($reverse_ip . '.in-addr.arpa');
            $hostName = gethostbyaddr($ip);
            $rDns = $rDns[0]['target'];
            $andQuery = "ipv4='yes'";
        } else {
            $andQuery = "domain='yes'";
            $reverse_ip = $ip;
        }

        $dnsblresult = $this->mysqlQuery("SELECT dnszone,zone_ns_servers
                      FROM mod_ipblmonitor_rbl WHERE status='good' AND " . $andQuery);
        while ($rbl_row = mysqli_fetch_assoc($dnsblresult)) {
            $ns_servers = unserialize($rbl_row['zone_ns_servers']);
            $query = new DNSAsyncQuery($ns_servers[0]);
            $query->dnsZone = $rbl_row['dnszone'];
            $query->qType = 'A';
            $query->dns_servers = $ns_servers;
            $query->dns_servers_index = 1;
            $query->dns_servers_index_max = count($ns_servers);
            $query->query($reverse_ip . '.' . $query->dnsZone, "A");
        }

        while (true) {
            if ($query) {
                $currentQuerysCount = $query->getQueryObjetsCount();
                if ($currentQuerysCount == 0) {
                    break;
                }
            }
            $dnsQueryArr = $query->getAnswers();
            foreach ($dnsQueryArr as $DNSAsyncQuery) {
                if (!$DNSAsyncQuery->error) {
                    if ($DNSAsyncQuery->qType === 'A') {
                        $dnsAnswer = $DNSAsyncQuery->dns_answers->current();
                        if (!$dnsAnswer) {
                            //del mysql record
                            continue;
                        }
                        if ($DNSAsyncQuery->dns_servers_index >= $DNSAsyncQuery->dns_servers_index_max) {
                            continue;
                        }
                        $newQuery = new DNSAsyncQuery($DNSAsyncQuery->dns_servers[$DNSAsyncQuery->dns_servers_index]);
                        $newQuery->dnsZone = $DNSAsyncQuery->dnsZone;
                        $newQuery->qType = 'TXT';
                        $newQuery->dns_servers = $DNSAsyncQuery->dns_servers;
                        $newQuery->dns_servers_index = $DNSAsyncQuery->dns_servers_index + 1;
                        $newQuery->dns_servers_index_max = $DNSAsyncQuery->dns_servers_index_max;
                        $newQuery->parent = $DNSAsyncQuery;
                        $newQuery->query($reverse_ip . '.' . $newQuery->dnsZone, "TXT");
                    } else {
                        $a_result = $DNSAsyncQuery->parent->dns_answers->current()->getData();
                        $dnsAnswer = $DNSAsyncQuery->dns_answers->current();
                        $txt_result = "";
                        if ($dnsAnswer) {
                            $txt_result = $dnsAnswer->getData();
                        }
                        if ($txt_result) {
                            $txt_result = preg_replace('/(http[s]?:\/\/[^\s]*)/',
                                '<a style="color:blue;" target="_blank" href="$1">$1</a>', $txt_result);
                        }

                        $this->mysqlQuery('INSERT INTO mod_ipblmonitor_black_ips
                                          (ip,rbl_server,host_name,rdns,rbl_response_a,rbl_response_txt,jid) 
                                  VALUES(%s,%s,%s,%s,%s,%s,%s)',
                            $ip, $DNSAsyncQuery->dnsZone, $hostName, $rDns, $a_result, $txt_result, $jid);

                    }
                }
            }
            usleep(500);
        }
    }

    public function ajaxGetDnsblServers()
    {
        $query='SELECT dnszone,status,lastcheck,enabled,description,url,ipv4,ipv6,domain,name 
                  FROM mod_ipblmonitor_rbl';
        $queryCount= 'SELECT COUNT(dnszone) FROM mod_ipblmonitor_rbl';

        return $this->gridSelectQuery($queryCount,$query);
    }


    public function ajaxEditDnsblServer()
    {
        switch($_REQUEST['oper']){
            case "add":{
                $this->mysqlQuery("INSERT INTO mod_ipblmonitor_rbl (dnszone,status,description,url,ipv4,ipv6,domain,name) 
						                  VALUES(%s,'good',%s,%s)",
                                    $_REQUEST['dnszone'],$_REQUEST['description'],$_REQUEST['url'],
                                    $_REQUEST['ipv4'],$_REQUEST['ipv6'],$_REQUEST['domain'],$_REQUEST['name']);
                break;
            }
            case "del":{
                $this->mysqlQuery('DELETE FROM mod_ipblmonitor_rbl WHERE dnszone=%s',
                    $_REQUEST['dnszone']);
                break;
            }
            case 'edit':{
                if($_REQUEST['enabled']){
                    $subQuery[] = 'enabled='.$this->quote_smart($_REQUEST['enabled']);
                }
                if($_REQUEST['name']){
                    $subQuery[] = 'name='.$this->quote_smart($_REQUEST['name']);
                }
                if($_REQUEST['description']){
                    $subQuery[] = 'description='.$this->quote_smart($_REQUEST['description']);
                }
                if($_REQUEST['url']){
                    $subQuery[] = 'url='.$this->quote_smart($_REQUEST['url']);
                }
                if($_REQUEST['ipv4']){
                    $subQuery[] = 'ipv4='.$this->quote_smart($_REQUEST['ipv4']);
                }
                if($_REQUEST['ipv6']){
                    $subQuery[] = 'ipv6='.$this->quote_smart($_REQUEST['ipv6']);
                }
                if($_REQUEST['domain']){
                    $subQuery[] = 'domain='.$this->quote_smart($_REQUEST['domain']);
                }
                if($_REQUEST['name']){
                    $subQuery[] = 'name='.$this->quote_smart($_REQUEST['name']);
                }
                if(count($subQuery)>0) {
                    $subQuery = implode(',', $subQuery);
                    $this->mysqlQuery('UPDATE mod_ipblmonitor_rbl SET ' . $subQuery . ' WHERE dnszone=%s',
                        $_REQUEST['dnszone']);
                }
                break;
            }
        }
    }

    public function ajaxGetSettings()
    {
        $result=$this->mysqlQuery('SELECT * FROM mod_ipblmonitor WHERE id=0');
        $row=mysqli_fetch_assoc($result);
        $row['croncmd'] = '0 */1 * * * php '.__DIR__.'/CronJobs.php';

        return $row;
    }

    public function ajaxIpblMonitor()
    {

        $retArr['license_msg'] = $errMsg;
        $result=$this->mysqlQuery('SELECT status FROM mod_ipblmonitor_rblchecker WHERE id=%s',MONITOR_CHECK_JID);
        $retArr['recheckscan']=mysqli_fetch_assoc($result)['status'];

        return $retArr;
    }



    public function ajaxSaveSettings()
    {
        $subQuery[] = 'notifyemails='.$this->quote_smart($_REQUEST['notifyemails']);
        $subQuery[] = 'checkfrequency='.$this->quote_smart($_REQUEST['checkfrequency']);

        $subQuery[] = 'smtp_host='.$this->quote_smart($_REQUEST['smtp_host']);
        $subQuery[] = 'smtp_port='.($_REQUEST['smtp_port']==''?'NULL':$this->quote_smart($_REQUEST['smtp_port']));
        $subQuery[] = 'smtp_user='.$this->quote_smart($_REQUEST['smtp_user']);
        $subQuery[] = 'smtp_password='.$this->quote_smart($_REQUEST['smtp_password']);
        $subQuery[] = 'adminusername='.$this->quote_smart($_REQUEST['adminusername']);
        $subQuery[] = 'enable_notifyemails='.$this->quote_smart($_REQUEST['enable_notifyemails']);

        if(count($subQuery)>0) {
            $subQuery = implode(',', $subQuery);
            $this->mysqlQuery('UPDATE mod_ipblmonitor SET ' . $subQuery . ' WHERE id=0');
        }
    }

    public function saveSettings($setArr)
    {
        foreach ($setArr as $key=>$val){
            $subQuery[] = $key.'='.$this->quote_smart($val);
        }

        if(count($subQuery)>0) {
            $subQuery = implode(',', $subQuery);
            $this->mysqlQuery('UPDATE mod_ipblmonitor SET ' . $subQuery . ' WHERE id=0');
        }
    }

    public function ajaxSetIpGroups()
    {
        switch ($_REQUEST['oper']){
            case "add":{
                $this->mysqlQuery('INSERT INTO mod_ipblmonitor_groups (name) VALUES(%s)',
                                            $_REQUEST['name']);
                break;
            }
            case "edit":{
                $result=$this->mysqlQuery('UPDATE mod_ipblmonitor_groups SET name=%s WHERE id=%s',
                                            $_REQUEST['name'],$_REQUEST['id']);
                break;
            }
            case "del":{
                $this->mysqlQuery('DELETE FROM mod_ipblmonitor_groups WHERE id=%s',
                                            $_REQUEST['id']);
                break;
            }
        }
    }

    public function ajaxGetIpGroups()
    {
        $gridArr = $this->gridSelectQuery('SELECT COUNT(id) FROM (SELECT g.id,g.name,ips.id AS ipsid,ips.ip_end,ips.ip_start,ips.lastscan,ips.netmask,tmp3.blacklisted
FROM mod_ipblmonitor_groups AS g
  LEFT JOIN  mod_ipblmonitor_ips AS ips ON g.id=ips.gid
  LEFT JOIN (SELECT COUNT(tmp2.id) AS blacklisted,tmp2.bid
             FROM
               (SELECT tmp.bid,tmp.id,tmp.ip_end,tmp.ip_start,tmp.lastscan,tmp.netmask
                FROM
                  (SELECT bips.bid,bips.ip,mips.id,mips.ip_end,mips.ip_start,
                     mips.lastscan,mips.netmask FROM mod_ipblmonitor_black_ips
                    AS bips
                    LEFT JOIN mod_ipblmonitor_ips AS mips ON mips.gid=bips.gid
                                                             AND mips.id=bips.bid
                   WHERE bips.gid
                   GROUP BY bips.ip
                  ) AS tmp
               )AS tmp2 GROUP BY tmp2.bid
            )AS tmp3 ON tmp3.bid=ips.id
WHERE g.id) AS tmp4',
                'SELECT g.id,g.name,ips.id AS ipsid,ips.ip_end,ips.ip_start,ips.lastscan,ips.netmask,tmp3.blacklisted
FROM mod_ipblmonitor_groups AS g
  LEFT JOIN  mod_ipblmonitor_ips AS ips ON g.id=ips.gid
  LEFT JOIN (SELECT COUNT(tmp2.id) AS blacklisted,tmp2.bid
             FROM
               (SELECT tmp.bid,tmp.id,tmp.ip_end,tmp.ip_start,tmp.lastscan,tmp.netmask
                FROM
                  (SELECT bips.bid,bips.ip,mips.id,mips.ip_end,mips.ip_start,
                     mips.lastscan,mips.netmask FROM mod_ipblmonitor_black_ips
                    AS bips
                    LEFT JOIN mod_ipblmonitor_ips AS mips ON mips.gid=bips.gid
                                                             AND mips.id=bips.bid
                   WHERE bips.gid
                   GROUP BY bips.ip
                  ) AS tmp
               )AS tmp2 GROUP BY tmp2.bid
            )AS tmp3 ON tmp3.bid=ips.id
WHERE g.id %s');

        $groupsRows=array();
        foreach ($gridArr['rows'] as $row) {
            $groupsRows[$row['name']]['id'] = $row['id'];
            $groupsRows[$row['name']]['blacklisted'] += $row['blacklisted'];
            if($row['netmask'] && isIp($row['ip_start'])){
                $ipblock = IPUtil\IPBlock::create($row['ip_start'],$row['netmask']);
                $groupsRows[$row['name']]['totalip'] += $ipblock->count();
            }
            elseif (!$row['netmask'] && isIp($row['ip_start']) && isIp($row['ip_end'])) {
                $ip_start = IPUtil\IP::create($row['ip_start']);
                $ip_end = IPUtil\IP::create($row['ip_end']);
                $tmp = $ip_end->minus($ip_start->numeric());
                $groupsRows[$row['name']]['totalip'] += $tmp->numeric()+1;
            }
            elseif($row['ip_start'] && !isIp($row['ip_start'])) {
                $query = new DNSQuery("8.8.8.8");
                $result2 = $query->query($row['ip_start'], "A");

                if($result2->count() > 0) {
                    $groupsRows[$row['name']]['totalip'] += $result2->count();
                }
                else{
                    $groupsRows[$row['name']]['totalip']++;
                }
            }
            elseif($row['ip_start']){
                $groupsRows[$row['name']]['totalip']++;
            }
        }

        $groupsRowsArr = array();
        foreach ($groupsRows as $groupName=>$row){
            $groupsRowsArr[] = array('name'=>$groupName,'id'=>$row['id'],'totalip'=>$row['totalip'],'blacklisted'=>$row['blacklisted']);

        }
        $gridArr['rows'] = $groupsRowsArr;
        return $gridArr;
    }

    public function ajaxGetMonitoredIps()
    {
        $gridArr = $this->gridSelectQuery('SELECT COUNT(id) FROM mod_ipblmonitor_ips 
                                                                  WHERE gid='.$this->quote_smart($_REQUEST['gid']),
            'SELECT ips.id,ips.ip_end,ips.ip_start,ips.lastscan,ips.netmask,tmp3.blacklisted
                              FROM mod_ipblmonitor_ips AS ips
                              LEFT JOIN (SELECT COUNT(tmp2.id) AS blacklisted,tmp2.bid 
                                            FROM
				                            (SELECT tmp.bid,tmp.id,tmp.ip_end,tmp.ip_start,tmp.lastscan,tmp.netmask 
                                    		      FROM 
                                         		  (SELECT bips.bid,bips.ip,mips.id,mips.ip_end,mips.ip_start,
                                         		          mips.lastscan,mips.netmask FROM mod_ipblmonitor_black_ips
                                         		           AS bips                        
                        						        LEFT JOIN mod_ipblmonitor_ips AS mips ON mips.gid=bips.gid  
                        						        AND mips.id=bips.bid                    
                        							    WHERE bips.gid='.$this->quote_smart($_REQUEST['gid']).'
                        							     GROUP BY bips.ip
                        						  ) AS tmp
                                            )AS tmp2 GROUP BY tmp2.bid
                              )AS tmp3 ON tmp3.bid=ips.id
                        WHERE ips.gid='.$this->quote_smart($_REQUEST['gid']).' %s');

        foreach ($gridArr['rows'] as &$row) {
            if($row['netmask'] && isIp($row['ip_start'])){
                $ipblock = IPUtil\IPBlock::create($row['ip_start'],$row['netmask']);
                $row['totalip'] = $ipblock->count();
            }
            elseif (!$row['netmask'] && isIp($row['ip_start']) && isIp($row['ip_end'])) {
                $ip_start = IPUtil\IP::create($row['ip_start']);
                $ip_end = IPUtil\IP::create($row['ip_end']);
                $tmp = $ip_end->minus($ip_start->numeric());
                $row['totalip'] = $tmp->numeric()+1;
            }
            elseif(!isIp($row['ip_start'])) {
                $query = new DNSQuery("8.8.8.8");
                $result2 = $query->query($row['ip_start'], "A");

                if($result2->count() > 0) {
                    $row['totalip'] += $result2->count();
                }
            }
            else{
                $row['totalip'] = 1;
            }
        }

        return $gridArr;
    }


    public function ajaxDashStats()
    {
        $totGroups = $this->ajaxGetIpGroups();

        $result = $this->mysqlQuery('SELECT COUNT(status) AS total ,
                (SELECT COUNT(status) AS count FROM mod_ipblmonitor_rbl WHERE status="good") AS good,
                (SELECT COUNT(status) AS count FROM mod_ipblmonitor_rbl WHERE status="bad") AS bad
                FROM mod_ipblmonitor_rbl WHERE 1');
        $row = mysqli_fetch_assoc($result);
        $retData['rbltotal']['title'] = 'Total RBL Servers '.$row['total'];
        $retData['rbltotal']['data']['1'] = "2";
        $retData['rbltotal']['data']['good'] = (int)$row['good'];
        $retData['rbltotal']['data']['bad'] = (int)$row['bad'];


        $result = $this->mysqlQuery('SELECT bips.rbl_server,rbl.count FROM mod_ipblmonitor_black_ips bips
                          LEFT JOIN (SELECT COUNT(id) AS count,rbl_server FROM mod_ipblmonitor_black_ips
                          WHERE jid="'.MONITOR_CHECK_JID.'" GROUP BY rbl_server) AS rbl ON rbl.rbl_server=bips.rbl_server
                          GROUP BY bips.rbl_server');
        $retData['toprbl']['title'] = 'Top Detected RBL Servers ';
        $retData['toprbl']['data']['1'] = "2";
        while($row = mysqli_fetch_assoc($result)){
            $retData['toprbl']['data'][$row['rbl_server']] = (int)$row['count'];
        }


        $result=$this->mysqlQuery('SELECT COUNT(id) AS blackIPs FROM 
                                                (SELECT id FROM mod_ipblmonitor_black_ips WHERE jid="'.MONITOR_CHECK_JID.'" 
                                                    GROUP BY ip) AS tmp');
        $row = mysqli_fetch_assoc($result);

        $retData['ipinfo']['data']['1'] = "2";
        $retData['ipinfo']['data']['BlackListed'] = (int)$row['blackIPs'];

        $totalips=0;
        foreach ($totGroups['rows'] as $arr) {
                $totalips += (int)$arr['totalip'];
        }

        $retData['ipinfo']['title'] = 'Monitored Objects\'s '.$totalips;
        $retData['ipinfo']['data']['Clean'] = $totalips>0?(int)$totalips-$row['blackIPs']:0;
        return $retData;
    }

    public function ajaxSetMonitoredIps()
    {
        switch ($_REQUEST['oper']) {
            case "add": {
                $this->mysqlQuery('INSERT INTO mod_ipblmonitor_ips (ip_start, ip_end, netmask,gid) 
                                            VALUES (%s,%s,%s,%s)',
                                                $_REQUEST['ip_start'],
                                                $_REQUEST['ip_end'],
                                                $_REQUEST['netmask'],
                                                $_REQUEST['gid']);
                break;
            }
            case "edit":{
                $subQuery[] = 'ip_start='.$this->quote_smart($_REQUEST['ip_start']);
                $subQuery[] = 'ip_end='.$this->quote_smart($_REQUEST['ip_end']);
                $subQuery[] = 'netmask='.$this->quote_smart($_REQUEST['netmask']);

                $subQuery = implode(',', $subQuery);
                $this->mysqlQuery('UPDATE mod_ipblmonitor_ips SET ' . $subQuery . ' WHERE id=%s',
                                            $_REQUEST['id']);
                break;
            }
            case "del":{
                $this->mysqlQuery('DELETE FROM mod_ipblmonitor_ips WHERE id=%s',$_REQUEST['id']);
                break;
            }
        }
    }


    public function ajaxGetBlackListedGroups()
    {
        $gridArr=$this->ajaxGetIpGroups();
        $blacklistedRows=array();
        for($i=0,$j=count($gridArr['rows']);$i<$j;$i++){
            if($gridArr['rows'][$i]['blacklisted']){
                $blacklistedRows[]=$gridArr['rows'][$i];
            }
        }
        $gridArr['records'] = count($blacklistedRows);
        $gridArr['rows'] = $blacklistedRows;
        return $gridArr;
    }

    public function ajaxGetBlackListedIps()
    {
        $gridArr = $this->gridSelectQuery('SELECT COUNT(x.ip) FROM
                      (SELECT ip FROM mod_ipblmonitor_black_ips WHERE gid='.$this->quote_smart($_REQUEST['gid']).' 
                                GROUP BY ip) AS x',
                 'SELECT id,ip,host_name,rdns,lastcheck FROM mod_ipblmonitor_black_ips
                                WHERE gid='.$this->quote_smart($_REQUEST['gid']).' %s GROUP BY ip');
        return $gridArr;
    }

    public function ajaxGetBlackListedRbl()
    {
        if(!isset($_REQUEST['jid'])){
            $_REQUEST['jid'] = MONITOR_CHECK_JID;
        }

        $gridArr = $this->gridSelectQuery('SELECT COUNT(id) FROM mod_ipblmonitor_black_ips
                                                                  WHERE ip='.$this->quote_smart($_REQUEST['ip']).
                                                                    ' AND jid='.$this->quote_smart($_REQUEST['jid']),
            'SELECT rbl_server,rbl_response_a,rbl_response_txt FROM mod_ipblmonitor_black_ips
            WHERE ip=' .$this->quote_smart($_REQUEST['ip']).
            ' AND jid='.$this->quote_smart($_REQUEST['jid']).
            ' %s'
        );

        foreach ($gridArr['rows'] as &$row) {
            $result = $this->mysqlQuery('SELECT url FROM mod_ipblmonitor_rbl WHERE dnszone=%s', $row['rbl_server']);
            $rblUrl = mysqli_fetch_assoc($result);
            if($rblUrl['url']){
                $row['rbl_server'] = '<a href="'.$rblUrl['url'].'" target="blank">'.$row['rbl_server'].'</a>';
            }
        }
        return $gridArr;
    }

    private function guidv4()
    {
        $data = openssl_random_pseudo_bytes(16);
        assert(strlen($data) == 16);

        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public function ajaxRblCheckGetResult()
    {
        if($_REQUEST['jid']) {
            $result=$this->mysqlQuery('SELECT status FROM mod_ipblmonitor_rblchecker WHERE id=%s',
                                            $_REQUEST['jid']);
            $retData['status']=mysqli_fetch_assoc($result)['status'];
            $result=$this->mysqlQuery('SELECT COUNT(ip) AS blacklisted,ip,host_name,rdns,jid 
                                              FROM mod_ipblmonitor_black_ips WHERE jid=%s GROUP BY ip',
                                                $_REQUEST['jid']);
            while($row=mysqli_fetch_assoc($result)){
                $retData['rows'][] = $row;
            }

            $result=$this->mysqlQuery("SELECT COUNT(dnszone) AS totrbls 
                      FROM mod_ipblmonitor_rbl WHERE status='good'");
            $retData['totrbls']=mysqli_fetch_assoc($result)['totrbls'];
            $retData['totrbls']=$retData['totrbls']===null?0:$retData['totrbls'];

            $result=$this->mysqlQuery("SELECT DISTINCT rbl_server 
                      FROM mod_ipblmonitor_black_ips WHERE jid=%s",$_REQUEST['jid']);
            $retData['blackrbls']=mysqli_num_rows($result);
            $retData['blackrbls']=$retData['blackrbls']===null?0:$retData['blackrbls'];

        }

        return $retData;
    }

    public function ajaxRblCheckGetResultRbls()
    {
        if($_REQUEST['jid'] && $_REQUEST['ip']) {
            $result=$this->mysqlQuery('SELECT ips.rbl_server,ips.rbl_response_a,ips.rbl_response_txt,rbls.url
                                                    FROM mod_ipblmonitor_black_ips AS ips
                                                    LEFT JOIN mod_ipblmonitor_rbl AS rbls ON rbls.name=ips.rbl_server 
                                                    WHERE jid=%s AND ip=%s',
                                                $_REQUEST['jid'],
                                                $_REQUEST['ip']);
            while($row=mysqli_fetch_assoc($result)){
                if($row['url']){
                    $row['rbl_server'] = '<a class="textLink" href="'.$row['url'].'" target="blank">'.$row['rbl_server'].'</a>';
                }
                $retData['rows'][] = $row;
            }
        }

        return $retData;
    }


    public function ajaxForceReCheckMonitorIps()
    {
        Logger::getLogger('ipmonitor')->debug('ajaxForceReCheckMonitorIps');
        $cmd = 'php ' . __DIR__ . '/CronJobs.php --ForceReCheckMonitorIps 1';
        Logger::getLogger('ipmonitor')->debug($cmd);
        $pid = PManager::run_in_background($cmd);
    }

    public function ajaxRblCheck()
    {
        if($_REQUEST['blcheckip']) {
            $uuid = $this->guidv4();
            $cmd = 'php ' . __DIR__ . '/CronJobs.php --ip ' . escapeshellarg($_REQUEST['blcheckip']).
                                ' --jid '.escapeshellarg($uuid);
            $pid = PManager::run_in_background($cmd);
            return array('jid'=>$uuid);
        }
        elseif($_REQUEST['jid']) {
            $retArr = array();
            $result=$this->mysqlQuery('SELECT * FROM mod_ipblmonitor_black_ips WHERE jid=%s',$_REQUEST['jid']);
            while ($row=mysqli_fetch_assoc($result)){
                $retArr[] = $row;
            }
            $result=$this->mysqlQuery('SELECT * FROM mod_ipblmonitor_rblchecker WHERE id=%s',$_REQUEST['jid']);
            $row=mysqli_fetch_assoc($result);
            $retArr['status'] = $row['status'];
            return $retArr;
        }
    }

    public function mysqlQuery($query)
    {
        $argcount = func_num_args();
        Logger::getLogger("debug")->debug('$query = '.$query );
        Logger::getLogger("debug")->debug('$argcount = '.$argcount );

        if($argcount > 1){
            $args = func_get_args();
            Logger::getLogger("debug")->debug(print_r($args,true));
            unset($args[0]);
            for ($i = 1; $i <= $argcount - 1; $i++) {
                $args[$i] = $args[$i] === 'NULL'?'NULL':$this->quote_smart($args[$i]);
            }
            $query = vsprintf($query,$args);
        }
        Logger::getLogger("debug")->debug($args);
        Logger::getLogger("debug")->debug($query);
        $result=mysqli_query($this->db_link,$query);
        Logger::getLogger("debug")->debug($result);
        $err=mysqli_errno($this->db_link);
        Logger::getLogger("debug")->debug('mysqli_error='.
            mysqli_error($this->db_link)."\n".'mysqli_errno='.
            mysqli_errno($this->db_link));


        if($err === 2006 || $err === 2013){
            //RECONNECT TO THE MYSQL DB
            $this->db_link=mysqli_connect($this->db_host,$this->db_username,$this->db_password,$this->db_name);
            return $this->mysqlQuery($query);
        }

        return $result;
    }


    private function quote_smart($value)
    {
        // Stripslashes
        if (get_magic_quotes_gpc()){
            $value = stripslashes($value);
        }
        // Quote if not a number or a numeric string
        if (!is_numeric($value)){
            $value = "'" . mysqli_real_escape_string($this->db_link,$value) . "'";
        }
        return $value;
    }


    /**
     *
     * @param unknown $countQuery
     * @param unknown $selectQuery
     * @param string $rowcallback
     * @param string $subtables
     * @return multitype:Ambigous <unknown, number> number unknown
     */
    private function gridSelectQuery($countQuery,$selectQuery,$rowcallback=NULL,$subtables=null)
    {
        $responce = array();
        $page = $_GET['page']; // get the requested page
        $limit = $_GET['rows']; // get how many rows we want to have into the grid
        $sidx = $_GET['sidx']; // get index row - i.e. user click to sort
        $sord = $_GET['sord']; // get the direction
        if(!$sidx) $sidx =1;

        //echo $countQuery;
        Logger::getLogger("debug")->debug($countQuery);
        $result2= $this->mysqlQuery($countQuery);
        $count = mysqli_fetch_row($result2);
        $count = $count[0];
        if( $count >0 ){
            $total_pages = ceil($count/$limit);
        }
        else{
            $total_pages = 0;
        }

        if ($page > $total_pages) {
            $page = $total_pages;
        }
        $start = $limit*$page - $limit;

        if($start < 0){$start = 0;}

        if($_REQUEST['_search']){
            $filters = json_decode(html_entity_decode($_REQUEST['filters']));
            foreach($filters->rules as $rule){
                if($rule->field == 'ip' || $rule->field == 'network' || $rule->field == 'ns1' || $rule->field == 'ns2' || $rule->field == 'gateway'){
                    $ipsOctets = explode('.', $rule->data);
                    foreach($ipsOctets as $octet) {
                        if($octet) {
                            $octetscount++;
                            $searchhex .= sprintf('%02x',$octet);
                        }
                    }
                    $whereQuery .= ' AND ';
                    $whereQuery .= 'left('.$rule->field.','.$octetscount.")=UNHEX('$searchhex')";
                }
                else {
                    $whereQuery .= ' AND '.$rule->field.' LIKE '.quote_smart($rule->data.'%').' ';
                }
            }
        }

        $selectQuery = sprintf($selectQuery,$whereQuery);
        if ($sidx != '' && $sord != '') {
            $selectQuery .= " ORDER BY $sidx $sord";
        }
        if ($limit) {
            $selectQuery .= " LIMIT $start , $limit";
        }

        //echo $selectQuery;
        Logger::getLogger("debug")->debug($selectQuery);
        $result = $this->mysqlQuery($selectQuery);
        while (($row = mysqli_fetch_assoc($result))){
            if ($rowcallback) {
                $row = call_user_func($rowcallback, $row);

            }
            $responce['rows'][]=$row;
        }

        $responce['page'] = $page;
        $responce['total'] = (string)$total_pages;
        $responce['records'] = $count;

        return $responce;
    }


    /**
     *
     * @param unknown $str
     * @return string
     */
    private function ajaxError($str)
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
    private function ajaxSuccess($response=null,$msg=null,$msgType=null)
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
    private function ajaxFinish($json)
    {
        if ($json != -1) {
            echo ajaxSuccess($json);
        }
        header("Connection: close", true);
        ob_end_flush();
        flush();
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        die();
    }





}



