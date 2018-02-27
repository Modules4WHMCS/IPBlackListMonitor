<?php

class IpBlLock
{
    private $lockfile;
    private $lockfp=null;
    
    function __construct($lockname)
    {
        $this->lockfile = sys_get_temp_dir().'/'.$lockname.'.lc';
    }
    
    public function __destruct()
    {
    	if($this->lockfp){
        	flock($this->lockfp, LOCK_UN);
        	fclose($this->lockfp);
        	$this->lockfp=null;
    	}
    }
    
    public function lock($timeout=null,$block=false)
    {
        $this->lockfp = fopen($this->lockfile, "w+");
        $result = false;
        
        while(true){
            if(flock($this->lockfp, LOCK_EX,$block)){
                $result = true;
                break;
            }
            if(!$timeout) break;
            usleep(100);
            $timeout -= 100;
        } 
        
        return $result;
    }
    
    public function free()
    {
    	$this->__destruct();
    }
            
}


