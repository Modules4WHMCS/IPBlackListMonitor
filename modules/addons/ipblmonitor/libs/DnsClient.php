<?php
/**
 * Created by IntelliJ IDEA.
 * User: roman
 * Date: 11/27/17
 * Time: 9:05 PM
 */



class DNSAnswer implements \Countable, \Iterator
{
    /**
     * @var DNSResult[]
     */
    private $results = array();

    /**
     * @param DNSResult $result
     */
    public function addResult(DNSResult $result)
    {
        $this->results[] = $result;
    }

    /**
     * Countable
     *
     * @return int
     */
    public function count()
    {
        return count($this->results);
    }

    /**
     * Iterator
     *
     * @return DNSResult
     */
    public function current()
    {
        return current($this->results);
    }

    /**
     * Iterator
     */
    public function next()
    {
        return next($this->results);
    }

    /**
     * Iterator
     *
     * @return int
     */
    public function key()
    {
        return key($this->results);
    }

    /**
     * Iterator
     *
     * @return bool
     */
    public function valid()
    {
        return key($this->results) !== null;
    }

    /**
     * Iterator
     */
    public function rewind()
    {
        return reset($this->results);
    }

}

class DNSAsyncQuery extends DNSQuery
{

    private static $DNSAsyncQueryObjets;
    public $socket;
    public $question;
    public $timestamp;
    public $dns_answers;


    public function __construct($server, $port = 53, $timeout = 60, $udp = true, $debug = false, $binarydebug = false)
    {
        Logger::getLogger('ipmonitor')->debug(__METHOD__);
        parent::__construct($server, $port, $timeout, $udp, $debug, $binarydebug);
        Logger::getLogger('ipmonitor')->debug("RETURN");
    }


    public function getQueryObjetsCount()
    {
        return count(DNSAsyncQuery::$DNSAsyncQueryObjets);
    }

    public function getAnswers()
    {
        $readyObjects = array();
        $readArr=NULL;
        $writeArr=NULL;
        $exceptArr = NULL;

        foreach (DNSAsyncQuery::$DNSAsyncQueryObjets as $DNSAsyncQueryObj){
            $readArr[] = $DNSAsyncQueryObj->socket;
            $exceptArr[] = $DNSAsyncQueryObj->socket;
        }
        if(stream_select($readArr,$writeArr,$exceptArr,0,0)){
            foreach ($readArr as $socket){
                $DNSAsyncQueryObj = &DNSAsyncQuery::$DNSAsyncQueryObjets[(integer)$socket];
                $socket = $DNSAsyncQueryObj->socket;
                if($DNSAsyncQueryObj->udp) {
                    $answer = fread($socket, 4096);
                    if (!$answer && !$DNSAsyncQueryObj->rawbuffer) {
                        $DNSAsyncQueryObj->setError('Failed to read data buffer::' . socket_last_error($socket));
                        fclose($socket);
                        unset(DNSAsyncQuery::$DNSAsyncQueryObjets[$socket]);
                        $readyObjects[] = $DNSAsyncQueryObj;
                        continue;
                    }
                    $DNSAsyncQueryObj->rawbuffer .= $answer;
                }
                else{
                    if(!$DNSAsyncQueryObj->tcpStreamLength) {
                        $DNSAsyncQueryObj->tcpStreamLength = fread($socket, 2);
                        if(!$DNSAsyncQueryObj->tcpStreamLength) {
                            $DNSAsyncQueryObj->setError('Failed to read size from TCP socket');
                            unset(DNSAsyncQuery::$DNSAsyncQueryObjets[$socket]);
                            fclose($socket);
                            continue;
                        }
                        $tmplen = unpack('nlength', $DNSAsyncQueryObj->tcpStreamLength);
                        $DNSAsyncQueryObj->tcpStreamLength = $tmplen['length'];
                    }

                    $answer = fread($socket, $DNSAsyncQueryObj->tcpStreamLength);
                    if (!$answer && !$DNSAsyncQueryObj->rawbuffer) {
                        $DNSAsyncQueryObj->setError('Failed to read data buffer::' . socket_last_error($socket));
                        unset(DNSAsyncQuery::$DNSAsyncQueryObjets[$socket]);
                        $readyObjects[] = $DNSAsyncQueryObj;
                        fclose($socket);
                        continue;
                    }

                    $DNSAsyncQueryObj->rawbuffer .= $answer;
                    if(strlen($DNSAsyncQueryObj->rawbuffer) < $DNSAsyncQueryObj->tcpStreamLength){
                        $DNSAsyncQueryObj->timestamp = time();
                        continue;
                    }
                }

                if($this->parseDnsAnswer($DNSAsyncQueryObj) || $DNSAsyncQueryObj->hasError()){
                    unset(DNSAsyncQuery::$DNSAsyncQueryObjets[$socket]);
                    $readyObjects[] = $DNSAsyncQueryObj;
                    fclose($socket);
                }
                $DNSAsyncQueryObj->timestamp = time();
            }

            foreach ($exceptArr as $socket){
                echo "SOCKET ERROR\n";
                echo socket_last_error($socket)."\n";
                $DNSAsyncQueryObj = DNSAsyncQuery::$DNSAsyncQueryObjets[$socket];
                $DNSAsyncQueryObj->setError(socket_last_error($socket));
                unset(DNSAsyncQuery::$DNSAsyncQueryObjets[$socket]);
                $readyObjects[] = $DNSAsyncQueryObj;
                fclose($socket);
            }
        }

        foreach (DNSAsyncQuery::$DNSAsyncQueryObjets as $DNSAsyncQueryObj){
            if(time()-$DNSAsyncQueryObj->timestamp > $DNSAsyncQueryObj->timeout){
                $DNSAsyncQueryObj->setError("ERROR TIMEOUT");
                unset(DNSAsyncQuery::$DNSAsyncQueryObjets[$DNSAsyncQueryObj->socket]);
                $readyObjects[] = $DNSAsyncQueryObj;
                fclose($socket);
            }
        }

        return $readyObjects;
    }

    public function close()
    {
        unset(DNSAsyncQuery::$DNSAsyncQueryObjets[$this->socket]);
        fclose($this->socket);
    }

    public function query($question, $type = 'A')
    {
        Logger::getLogger('ipmonitor')->debug(__METHOD__);
        $this->clearError();
        $this->question = $question;
        $typeid = $this->types->getByName($type);

        if ($typeid === false) {
            $this->setError('Invalid Query Type ' . $type);
            return false;
        }

        if ($this->udp) {
            $host = 'udp://' . $this->server;
        } else {
            $host = $this->server;
        }

        Logger::getLogger('ipmonitor')->debug($host);
        $errno = 0;
        $errstr = '';

        if (!$this->socket = fsockopen($host, $this->port, $errno, $errstr, $this->timeout)) {
            $this->setError('Failed to Open Socket');
            Logger::getLogger('ipmonitor')->debug("Failed to Open Socket: ".$errno.'/'.$errstr);
            Logger::getLogger('ipmonitor')->debug("RETURN");
            return false;
        }

        $this->timestamp = time();
        stream_set_blocking($this->socket, 0 );

        if (preg_match('/[a-z|A-Z]/', $question) == 0 && $question != '.') {
            $labels = array_reverse(explode('.', $question));
            $labels[] = 'IN-ADDR';
            $labels[] = 'ARPA';
        } else {
            if ($question == '.') {
                $labels = array('');
            } else { // hostname
                $labels = explode('.', $question);
            }
        }

        $question_binary = '';

        foreach ($labels as $label) {
            if ($label != '') {
                $size = strlen($label);
                $question_binary .= pack('C', $size);
                $question_binary .= $label;
            } else {

            }
        }

        $question_binary .= pack('C', 0);

        $this->debug('Question: ' . $question . ' (type=' . $type . '/' . $typeid . ')');

        $id = rand(1, 255) | (rand(0, 255) << 8);    // generate the ID

        // Set standard codes and flags
        $flags = 0x0100 & 0x0300; // recursion & queryspecmask
        $opcode = 0x0000; // opcode

        // Build the header
        $header = '';
        $header .= pack('n', $id);
        $header .= pack('n', $opcode | $flags);
        $header .= pack('nnnn', 1, 0, 0, 0);
        $header .= $question_binary;
        $header .= pack('n', $typeid);
        $header .= pack('n', 0x0001); // internet class
        $headersize = strlen($header);
        $headersizebin = pack('n', $headersize);

        $this->debug('Header Length: ' . $headersize . ' Bytes');
        $this->debugBinary($header);

        if (($this->udp) && ($headersize >= 512)) {
            $this->setError('Question too big for UDP (' . $headersize . ' bytes)');
            fclose($this->socket);
            return false;
        }

        if ($this->udp) { // UDP method
            if (!fwrite($this->socket, $header, $headersize)) {
                $this->setError('Failed to write question to socket');
                Logger::getLogger('ipmonitor')->debug("Failed to write question to socket");
                Logger::getLogger('ipmonitor')->debug("RETURN");
                fclose($this->socket);
                return false;
            }

            DNSAsyncQuery::$DNSAsyncQueryObjets[(integer)$this->socket] = $this;
            Logger::getLogger('ipmonitor')->debug("RETURN");
            return true;

        } else { // TCP
            // write the socket
            if (!fwrite($this->socket, $headersizebin)) {
                $this->setError('Failed to write question length to TCP socket');
                fclose($this->socket);
                return false;
            }

            if (!fwrite($this->socket, $header, $headersize)) {
                $this->setError('Failed to write question to TCP socket');
                fclose($this->socket);
                return false;
            }
            //
            DNSAsyncQuery::$DNSAsyncQueryObjets[$this->socket] = $this;
            return true;
        }
    }


    private function parseDnsAnswer(&$DNSAsyncQueryObj)
    {
        $buffersize = strlen($DNSAsyncQueryObj->rawbuffer);

        $this->debug('Read Buffer Size ' . $buffersize);

        if ($buffersize < 12) {
            $DNSAsyncQueryObj->setError('Return Buffer too Small');
            return false;
        }

        $DNSAsyncQueryObj->rawheader = substr($DNSAsyncQueryObj->rawbuffer, 0, 12); // first 12 bytes is the header
        $DNSAsyncQueryObj->rawresponse = substr($DNSAsyncQueryObj->rawbuffer, 12); // after that the response

        $DNSAsyncQueryObj->responsecounter = 12; // start parsing response counter from 12 - no longer using response so can do pointers

        $this->debugBinary($DNSAsyncQueryObj->rawbuffer);

        $DNSAsyncQueryObj->header = unpack('nid/nspec/nqdcount/nancount/nnscount/narcount', $DNSAsyncQueryObj->rawheader);

        $id = $DNSAsyncQueryObj->header['id'];

        $rcode = $DNSAsyncQueryObj->header['spec'] & 15;
        $z = ($DNSAsyncQueryObj->header['spec'] >> 4) & 7;
        $ra = ($DNSAsyncQueryObj->header['spec'] >> 7) & 1;
        $rd = ($DNSAsyncQueryObj->header['spec'] >> 8) & 1;
        $tc = ($DNSAsyncQueryObj->header['spec'] >> 9) & 1;
        $aa = ($DNSAsyncQueryObj->header['spec'] >> 10) & 1;
        $opcode = ($DNSAsyncQueryObj->header['spec'] >> 11) & 15;
        $type = ($DNSAsyncQueryObj->header['spec'] >> 15) & 1;

        $this->debug("ID=$id, Type=$type, OPCODE=$opcode, AA=$aa, TC=$tc, RD=$rd, RA=$ra, RCODE=$rcode");

        if ($tc === 1 && $DNSAsyncQueryObj->udp) { // Truncation detected
            $DNSAsyncQueryObj->setError('Response too big for UDP, retry with TCP');
            return false;
        }

        $answers = $DNSAsyncQueryObj->header['ancount'];

        $this->debug('Query Returned ' . $answers . ' Answers');

        $DNSAsyncQueryObj->dns_answers = new DNSAnswer();

        // Deal with the header question data
        if ($DNSAsyncQueryObj->header['qdcount'] > 0) {
            $this->debug('Found ' . $DNSAsyncQueryObj->header['qdcount'] . ' Questions');

            for ($a = 0; $a < $DNSAsyncQueryObj->header['qdcount']; $a++) {
                $c = 1;

                while ($c != 0) {
                    $c = hexdec(bin2hex($DNSAsyncQueryObj->readResponse(1)));
                }

                $DNSAsyncQueryObj->readResponse(4);
            }
        }

        // New Functional Method
        for ($a = 0; $a < $DNSAsyncQueryObj->header['ancount']; $a++) {
            $record = $DNSAsyncQueryObj->readRecord();

            $DNSAsyncQueryObj->dns_answers->addResult(
                new DNSResult(
                    $record['header']['type'], $record['typeid'], $record['header']['class'], $record['header']['ttl'],
                    $record['data'], $record['domain'], $record['string'], $record['extras']
                )
            );
        }

        $DNSAsyncQueryObj->lastnameservers = new DNSAnswer();

        for ($a = 0; $a < $DNSAsyncQueryObj->header['nscount']; $a++) {
            $record = $DNSAsyncQueryObj->readRecord();

            $DNSAsyncQueryObj->lastnameservers->addResult(
                new DNSResult(
                    $record['header']['type'], $record['typeid'], $record['header']['class'], $record['header']['ttl'],
                    $record['data'], $record['domain'], $record['string'], $record['extras']
                )
            );
        }

        $DNSAsyncQueryObj->lastadditional = new DNSAnswer();

        for ($a = 0; $a < $DNSAsyncQueryObj->header['arcount']; $a++) {
            $record = $DNSAsyncQueryObj->readRecord();

            $DNSAsyncQueryObj->lastadditional->addResult(
                new DNSResult(
                    $record['header']['type'], $record['typeid'], $record['header']['class'], $record['header']['ttl'],
                    $record['data'], $record['domain'], $record['string'], $record['extras']
                )
            );
        }

        return true;
    }
}

class DNSQuery
{
    /**
     * @var string
     */
    public $server = '';

    /**
     * @var int
     */
    public $port;

    /**
     * @var int
     */
    public $timeout; // default set in constructor

    /**
     * @var bool
     */
    public $udp;

    /**
     * @var bool
     */
    public $debug;

    /**
     * @var bool
     */
    public $binarydebug = false;

    /**
     * @var DNSTypes
     */
    public $types;

    /**
     * @var string
     */
    public $rawbuffer = "";

    /**
     * @var string
     */
    public $rawheader = '';

    /**
     * @var string
     */
    public $rawresponse = '';

    /**
     * @var array
     */
    public $header;

    /**
     * @var int
     */
    public $responsecounter = 0;

    /**
     * @var DNSAnswer
     */
    public $lastnameservers;

    /**
     * @var DNSAnswer
     */
    public $lastadditional;

    /**
     * @var bool
     */
    public $error = false;

    /**
     * @var string
     */
    public $lasterror = '';

    /**
     * @param string $server
     * @param int $port
     * @param int $timeout
     * @param bool $udp
     * @param bool $debug
     * @param bool $binarydebug
     */
    public function __construct($server, $port = 53, $timeout = 60, $udp = true, $debug = false, $binarydebug = false)
    {
        $this->server = $server;
        $this->port = $port;
        $this->timeout = $timeout;
        $this->udp = $udp;
        $this->debug = $debug;
        $this->binarydebug = $binarydebug;

        $this->types = new DNSTypes();

        $this->debug('DNSQuery Class Initialised');
    }

    /**
     * @param int $count
     * @param string $offset
     * @return string
     */
    public function readResponse($count = 1, $offset = '')
    {
        if ($offset == '') {
            // no offset so use and increment the ongoing counter
            $return = substr($this->rawbuffer, $this->responsecounter, $count);
            $this->responsecounter += $count;
        } else {
            $return = substr($this->rawbuffer, $offset, $count);
        }

        return $return;
    }

    /**
     * @param int $offset
     * @param int $counter
     * @return array
     */
    public function readDomainLabels($offset, &$counter = 0)
    {
        $labels = array();
        $startoffset = $offset;
        $return = false;

        while (!$return) {
            $label_len = ord($this->readResponse(1, $offset++));

            if ($label_len <= 0) {
                $return = true;
                // end of data
            } else {
                if ($label_len < 64) { // uncompressed data
                    $labels[] = $this->readResponse($label_len, $offset);
                    $offset += $label_len;
                } else { // label_len >= 64 -- pointer
                    $nextitem = $this->readResponse(1, $offset++);
                    $pointer_offset = (($label_len & 0x3f) << 8) + ord($nextitem);

                    // Branch Back Upon Ourselves...
                    $this->debug('Label Offset: ' . $pointer_offset);

                    $pointer_labels = $this->readDomainLabels($pointer_offset);

                    foreach ($pointer_labels as $ptr_label) {
                        $labels[] = $ptr_label;
                    }

                    $return = true;
                }
            }
        }

        $counter = $offset - $startoffset;

        return $labels;
    }

    /**
     * @return string
     */
    public function readDomainLabel()
    {
        $count = 0;
        $labels = $this->readDomainLabels($this->responsecounter, $count);
        $domain = implode('.', $labels);

        $this->responsecounter += $count;

        $this->debug('Label ' . $domain . ' len ' . $count);

        return $domain;
    }

    /**
     * @param string $text
     */
    public function debug($text)
    {
        if ($this->debug) {
            echo $text . "\n";
        }
    }

    /**
     * @param string $data
     */
    public function debugBinary($data)
    {
        if (!$this->binarydebug) {
            return;
        }

        for ($a = 0; $a < strlen($data); $a++) {
            $hex = bin2hex($data[$a]);
            $dec = hexdec($hex);

            echo $a;
            echo "\t";
            printf('%d', $data[$a]);
            echo "\t";
            echo '0x' . $hex;
            echo "\t";
            echo $dec;
            echo "\t";

            if (($dec > 30) && ($dec < 150)) {
                echo $data[$a];
            }

            echo "\n";
        }
    }

    /**
     * @param string $text
     */
    public function setError($text)
    {
        $this->error = true;
        $this->lasterror = $text;

        $this->debug('Error: ' . $text);
    }

    protected function clearError()
    {
        $this->error = false;
        $this->lasterror = '';
    }

    /**
     * @return array
     */
    public function readRecord()
    {
        // First the pesky domain names - maybe not so pesky though I suppose

        $domain = $this->readDomainLabel();

        $ans_header_bin = $this->readResponse(10); // 10 byte header
        $ans_header = unpack('ntype/nclass/Nttl/nlength', $ans_header_bin);

        $this->debug(
            'Record Type ' . $ans_header['type'] . ' Class ' . $ans_header['class'] .
            ' TTL ' . $ans_header['ttl'] . ' Length ' . $ans_header['length']
        );

        $typeid = $this->types->getById($ans_header['type']);
        $extras = array();
        $data = '';
        $string = '';

        switch ($typeid) {
            case 'A':
                $ipbin = $this->readResponse(4);
                $ip = inet_ntop($ipbin);
                $data = $ip;
                $extras['ipbin'] = $ipbin;
                $string = $domain . ' has IPv4 address ' . $ip;
                break;

            case 'AAAA':
                $ipbin = $this->readResponse(16);
                $ip = inet_ntop($ipbin);
                $data = $ip;
                $extras['ipbin'] = $ipbin;
                $string = $domain . ' has IPv6 address ' . $ip;
                break;

            case 'CNAME':
                $data = $this->readDomainLabel();
                $string = $domain . ' alias of ' . $data;
                break;

            case 'DNAME':
                $data = $this->readDomainLabel();
                $string = $domain . ' alias of ' . $data;
                break;

            case 'DNSKEY':
            case 'KEY':
                $stuff = $this->readResponse(4);

                // key type test 21/02/2014 DC
                $test = unpack('nflags/cprotocol/calgo', $stuff);
                $extras['flags'] = $test['flags'];
                $extras['protocol'] = $test['protocol'];
                $extras['algorithm'] = $test['algo'];

                $data = base64_encode($this->readResponse($ans_header['length'] - 4));
                $string = $domain . ' KEY ' . $data;
                break;

            case "NSEC":
                $data=$this->ReadDomainLabel();
                $string=$domain." points to ".$data;
                break;

            case 'MX':
                $prefs = $this->readResponse(2);
                $prefs = unpack('nlevel', $prefs);
                $extras['level'] = $prefs['level'];
                $data = $this->readDomainLabel();
                $string = $domain . ' mailserver ' . $data . ' (pri=' . $extras['level'] . ')';
                break;

            case 'NS':
                $nameserver = $this->readDomainLabel();
                $data = $nameserver;
                $string = $domain . ' nameserver ' . $nameserver;
                break;

            case 'PTR':
                $data = $this->readDomainLabel();
                $string = $domain . ' points to ' . $data;
                break;

            case 'SOA':
                // Label First
                $data = $this->readDomainLabel();
                $responsible = $this->readDomainLabel();

                $buffer = $this->readResponse(20);
                $extras = unpack('Nserial/Nrefresh/Nretry/Nexpiry/Nminttl', $buffer); // butfix to NNNNN from nnNNN for 1.01
                $dot = strpos($responsible, '.');
                if($dot !== false){
                    $responsible[$dot] = '@';
                }
                $extras['responsible'] = $responsible;
                $string = $domain . ' SOA ' . $data . ' Serial ' . $extras['serial'];
                break;

            case 'SRV':
                $prefs = $this->readResponse(6);
                $prefs = unpack('npriority/nweight/nport', $prefs);
                $extras['priority'] = $prefs['priority'];
                $extras['weight'] = $prefs['weight'];
                $extras['port'] = $prefs['port'];
                $data = $this->readDomainLabel();
                $string = $domain . ' SRV ' . $data . ':' . $extras['port'] . ' (pri=' . $extras['priority'] . ', weight=' . $extras['weight'] . ')';
                break;

            case 'TXT':
            case 'SPF':
                $data = '';

                for ($string_count = 0; strlen($data) + (1 + $string_count) < $ans_header['length']; $string_count++) {
                    $string_length = ord($this->readResponse(1));
                    $data .= $this->readResponse($string_length);
                }

                $string = $domain . ' TEXT "' . $data . '" (in ' . $string_count . ' strings)';
                break;

            case "NAPTR":
                $buffer = $this->ReadResponse(4);
                $extras = unpack("norder/npreference",$buffer);
                $addonitial = $this->ReadDomainLabel();
                $data = $this->ReadDomainLabel();
                $extras['service']=$addonitial;
                $string = $domain." NAPTR ".$data;
                break;
        }

        return array(
            'header' => $ans_header,
            'typeid' => $typeid,
            'data' => $data,
            'domain' => $domain,
            'string' => $string,
            'extras' => $extras
        );
    }

    /**
     * @param string $question
     * @param string $type
     * @return DNSAnswer|false
     */
    public function query($question, $type = 'A')
    {
        $this->clearError();

        $typeid = $this->types->getByName($type);

        if ($typeid === false) {
            $this->setError('Invalid Query Type ' . $type);
            return false;
        }

        if ($this->udp) {
            $host = 'udp://' . $this->server;
        } else {
            $host = $this->server;
        }

        $errno = 0;
        $errstr = '';

        if (!$socket = fsockopen($host, $this->port, $errno, $errstr, $this->timeout)) {
            $this->setError('Failed to Open Socket');
            return false;
        }

        // handles timeout on stream read set using timeout as well
        stream_set_timeout($socket, $this->timeout);

        // Split Into Labels
        if (preg_match('/[a-z|A-Z]/', $question) == 0 && $question != '.') { // IP Address
            // reverse ARPA format
            $labels = array_reverse(explode('.', $question));
            $labels[] = 'IN-ADDR';
            $labels[] = 'ARPA';
        } else {
            if ($question == '.') {
                $labels = array('');
            } else { // hostname
                $labels = explode('.', $question);
            }
        }

        $question_binary = '';

        foreach ($labels as $label) {
            if ($label != '') {
                $size = strlen($label);
                $question_binary .= pack('C', $size); // size byte first
                $question_binary .= $label; // then the label
            } else {

            }
        }

        $question_binary .= pack('C', 0); // end it off

        $this->debug('Question: ' . $question . ' (type=' . $type . '/' . $typeid . ')');

        $id = rand(1, 255) | (rand(0, 255) << 8);    // generate the ID

        // Set standard codes and flags
        $flags = 0x0100 & 0x0300; // recursion & queryspecmask
        $opcode = 0x0000; // opcode

        // Build the header
        $header = '';
        $header .= pack('n', $id);
        $header .= pack('n', $opcode | $flags);
        $header .= pack('nnnn', 1, 0, 0, 0);
        $header .= $question_binary;
        $header .= pack('n', $typeid);
        $header .= pack('n', 0x0001); // internet class
        $headersize = strlen($header);
        $headersizebin = pack('n', $headersize);

        $this->debug('Header Length: ' . $headersize . ' Bytes');
        $this->debugBinary($header);

        if (($this->udp) && ($headersize >= 512)) {
            $this->setError('Question too big for UDP (' . $headersize . ' bytes)');
            fclose($socket);
            return false;
        }

        if ($this->udp) { // UDP method
            if (!fwrite($socket, $header, $headersize)) {
                $this->setError('Failed to write question to socket');
                fclose($socket);
                return false;
            }

            if (!$this->rawbuffer = fread($socket, 4096)) { // read until the end with UDP
                $this->setError('Failed to read data buffer');
                fclose($socket);
                return false;
            }
        } else { // TCP
            // write the socket
            if (!fwrite($socket, $headersizebin)) {
                $this->setError('Failed to write question length to TCP socket');
                fclose($socket);
                return false;
            }

            if (!fwrite($socket, $header, $headersize)) {
                $this->setError('Failed to write question to TCP socket');
                fclose($socket);
                return false;
            }

            if (!$returnsize = fread($socket, 2)) {
                $this->setError('Failed to read size from TCP socket');
                fclose($socket);
                return false;
            }

            $tmplen = unpack('nlength', $returnsize);
            $datasize = $tmplen['length'];

            $this->debug('TCP Stream Length Limit ' . $datasize);

            if (!$this->rawbuffer = fread($socket, $datasize)) {
                $this->setError('Failed to read data buffer');
                fclose($socket);
                return false;
            }
        }

        fclose($socket);

        $buffersize = strlen($this->rawbuffer);

        $this->debug('Read Buffer Size ' . $buffersize);

        if ($buffersize < 12) {
            $this->setError('Return Buffer too Small');
            return false;
        }

        $this->rawheader = substr($this->rawbuffer, 0, 12); // first 12 bytes is the header
        $this->rawresponse = substr($this->rawbuffer, 12); // after that the response

        $this->responsecounter = 12; // start parsing response counter from 12 - no longer using response so can do pointers

        $this->debugBinary($this->rawbuffer);

        $this->header = unpack('nid/nspec/nqdcount/nancount/nnscount/narcount', $this->rawheader);

        $id = $this->header['id'];

        $rcode = $this->header['spec'] & 15;
        $z = ($this->header['spec'] >> 4) & 7;
        $ra = ($this->header['spec'] >> 7) & 1;
        $rd = ($this->header['spec'] >> 8) & 1;
        $tc = ($this->header['spec'] >> 9) & 1;
        $aa = ($this->header['spec'] >> 10) & 1;
        $opcode = ($this->header['spec'] >> 11) & 15;
        $type = ($this->header['spec'] >> 15) & 1;

        $this->debug("ID=$id, Type=$type, OPCODE=$opcode, AA=$aa, TC=$tc, RD=$rd, RA=$ra, RCODE=$rcode");

        if ($tc == 1 && $this->udp) { // Truncation detected
            $this->setError('Response too big for UDP, retry with TCP');
            return false;
        }

        $answers = $this->header['ancount'];

        $this->debug('Query Returned ' . $answers . ' Answers');

        $dns_answer = new DNSAnswer();

        // Deal with the header question data
        if ($this->header['qdcount'] > 0) {
            $this->debug('Found ' . $this->header['qdcount'] . ' Questions');

            for ($a = 0; $a < $this->header['qdcount']; $a++) {
                $c = 1;

                while ($c != 0) {
                    $c = hexdec(bin2hex($this->readResponse(1)));
                }

                $this->readResponse(4);
            }
        }

        // New Functional Method
        for ($a = 0; $a < $this->header['ancount']; $a++) {
            $record = $this->readRecord();

            $dns_answer->addResult(
                new DNSResult(
                    $record['header']['type'], $record['typeid'], $record['header']['class'], $record['header']['ttl'],
                    $record['data'], $record['domain'], $record['string'], $record['extras']
                )
            );
        }

        $this->lastnameservers = new DNSAnswer();

        for ($a = 0; $a < $this->header['nscount']; $a++) {
            $record = $this->readRecord();

            $this->lastnameservers->addResult(
                new DNSResult(
                    $record['header']['type'], $record['typeid'], $record['header']['class'], $record['header']['ttl'],
                    $record['data'], $record['domain'], $record['string'], $record['extras']
                )
            );
        }

        $this->lastadditional = new DNSAnswer();

        for ($a = 0; $a < $this->header['arcount']; $a++) {
            $record = $this->readRecord();

            $this->lastadditional->addResult(
                new DNSResult(
                    $record['header']['type'], $record['typeid'], $record['header']['class'], $record['header']['ttl'],
                    $record['data'], $record['domain'], $record['string'], $record['extras']
                )
            );
        }

        return $dns_answer;
    }

    /**
     * @param string $hostname
     * @param int $depth
     *
     * @return string
     */
    public function smartALookup($hostname, $depth = 0)
    {
        $this->debug('SmartALookup for ' . $hostname . ' depth ' . $depth);

        // avoid recursive lookups
        if ($depth > 5) {
            return '';
        }

        // The SmartALookup function will resolve CNAMES using the additional properties if possible
        $answer = $this->query($hostname, 'A');

        // failed totally
        if ($answer === false) {
            return '';
        }

        // no records at all returned
        if (count($answer) === 0) {
            return '';
        }

        foreach ($answer as $record) {
            // found it
            if ($record->getTypeid() == 'A') {
                $best_answer = $record;
                break;
            }

            // alias
            if ($record->getTypeid() == 'CNAME') {
                $best_answer = $record;
                // and keep going
            }
        }

        if (!isset($best_answer)) {
            return '';
        }

        if ($best_answer->getTypeid() == 'A') {
            return $best_answer->getData();
        } // got an IP ok

        if ($best_answer->getTypeid() != 'CNAME') {
            return '';
        } // shouldn't ever happen

        $newtarget = $best_answer->getData(); // this is what we now need to resolve

        // First is it in the additional section
        foreach ($this->lastadditional as $result) {
            if (($result->getDomain() == $hostname) && ($result->getTypeid() == 'A')) {
                return $result->getData();
            }
        }

        // Not in the results

        return $this->smartALookup($newtarget, $depth + 1);
    }

    /**
     * @return DNSAnswer
     */
    public function getLastnameservers()
    {
        return $this->lastnameservers;
    }

    /**
     * @return DNSAnswer
     */
    public function getLastadditional()
    {
        return $this->lastadditional;
    }

    /**
     * @return boolean
     */
    public function hasError()
    {
        return $this->error;
    }

    /**
     * @return string
     */
    public function getLasterror()
    {
        return $this->lasterror;
    }
}

class DNSResult
{
    /**
     * @var string
     */
    private $type;

    /**
     * @var int
     */
    private $typeid;

    /**
     * @var string
     */
    private $class;

    /**
     * @var int
     */
    private $ttl;

    /**
     * @var string
     */
    private $data;

    /**
     * @var string
     */
    private $domain;

    /**
     * @var string
     */
    private $string;

    /**
     * @var array string
     */
    private $extras = array();

    /**
     * @param string $type
     * @param int $typeid
     * @param string $class
     * @param int $ttl
     * @param string $data
     * @param string $domain
     * @param string $string
     * @param array $extras
     */
    public function __construct($type, $typeid, $class, $ttl, $data, $domain, $string, array $extras)
    {
        $this->type = $type;
        $this->typeid = $typeid;
        $this->class = $class;
        $this->ttl = $ttl;
        $this->data = $data;
        $this->domain = $domain;
        $this->string = $string;
        $this->extras = $extras;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return int
     */
    public function getTypeid()
    {
        return $this->typeid;
    }

    /**
     * @return string
     */
    public function getClass()
    {
        return $this->class;
    }

    /**
     * @return int
     */
    public function getTtl()
    {
        return $this->ttl;
    }

    /**
     * @return string
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @return string
     */
    public function getDomain()
    {
        return $this->domain;
    }

    /**
     * @return string
     */
    public function getString()
    {
        return $this->string;
    }

    /**
     * @return array
     */
    public function getExtras()
    {
        return $this->extras;
    }
}

class DNSTypes
{
    private $types = array(
        1 => 'A', // RFC 1035 (Address Record)
        2 => 'NS', // RFC 1035 (Name Server Record)
        5 => 'CNAME', // RFC 1035 (Canonical Name Record (Alias))
        6 => 'SOA', // RFC 1035 (Start of Authority Record)
        12 => 'PTR', // RFC 1035 (Pointer Record)
        15 => 'MX', // RFC 1035 (Mail eXchanger Record)
        16 => 'TXT', // RFC 1035 (Text Record)
        17 => 'RP', // RFC 1183 (Responsible Person)
        18 => 'AFSDB', // RFC 1183 (AFS Database Record)
        24 => 'SIG', // RFC 2535
        25 => 'KEY', // RFC 2535 & RFC 2930
        28 => 'AAAA', // RFC 3596 (IPv6 Address)
        29 => 'LOC', // RFC 1876 (Geographic Location)
        33 => 'SRV', // RFC 2782 (Service Locator)
        35 => 'NAPTR', // RFC 3403 (Naming Authority Pointer)
        36 => 'KX', // RFC 2230 (Key eXchanger)
        37 => 'CERT', // RFC 4398 (Certificate Record, PGP etc)
        39 => 'DNAME', // RFC 2672 (Delegation Name Record, wildcard alias)
        42 => 'APL', // RFC 3123 (Address Prefix List (Experimental)
        43 => 'DS', // RFC 4034 (Delegation Signer (DNSSEC)
        44 => 'SSHFP', // RFC 4255 (SSH Public Key Fingerprint)
        45 => 'IPSECKEY', // RFC 4025 (IPSEC Key)
        46 => 'RRSIG', // RFC 4034 (DNSSEC Signature)
        47 => 'NSEC', // RFC 4034 (Next-secure Record (DNSSEC))
        48 => 'DNSKEY', // RFC 4034 (DNS Key Record (DNSSEC))
        49 => 'DHCID', // RFC 4701 (DHCP Identifier)
        50 => 'NSEC3', // RFC 5155 (NSEC Record v3 (DNSSEC Extension))
        51 => 'NSEC3PARAM', // RFC 5155 (NSEC3 Parameters (DNSSEC Extension))
        55 => 'HIP', // RFC 5205 (Host Identity Protocol)
        99 => 'SPF', // RFC 4408 (Sender Policy Framework)
        249 => 'TKEY', // RFC 2930 (Secret Key)
        250 => 'TSIG', // RFC 2845 (Transaction Signature)
        251 => 'IXFR', // RFC 1995 (Incremental Zone Transfer)
        252 => 'AXFR', // RFC 1035 (Authoritative Zone Transfer)
        255 => 'ANY', // RFC 1035 AKA "*" (Pseudo Record)
        32768 => 'TA', // (DNSSEC Trusted Authorities)
        32769 => 'DLV', // RFC 4431 (DNSSEC Lookaside Validation)
    );

    /**
     * @param string $name
     * @return int
     */
    public function getByName($name)
    {
        if (false !== $index = array_search($name, $this->types, true)) {
            return $index;
        }

        return 0;
    }

    /**
     * @param int $id
     * @return string
     */
    public function getById($id)
    {
        if (isset($this->types[$id])) {
            return $this->types[$id];
        }

        return '';
    }

    /**
     * @return array
     */
    public function getAllTypeNamesSorted()
    {
        $types = array_values($this->types);
        sort($types);

        return $types;
    }
}


