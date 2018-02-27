<?php


class PManager
{

	public static function is_process_running($pid)
	{
		$pid = (int)$pid;
		if($pid === 0) return false;
		if(file_exists('/proc/'.$pid)){
			return true;
		}else{
			return false;
		}
	}

	public static function run_in_background($command, $priority = 0,$log="/dev/null")
	{
		if($priority !==0){
			$pid = shell_exec("nice -n $priority $command >> $log 2>&1 & echo $!");
		}else{
			$pid = shell_exec("$command >> $log 2>&1 & echo $!");
		}
		return($pid);
	}



}