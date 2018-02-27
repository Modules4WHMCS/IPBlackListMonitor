<?php

class ipblSmarty extends Smarty
{
    private $deftplpath;
    private $deftpl;
	
    /**
     * 
     * @param unknown $tplpath
     */
    function __construct($tplpath = null)
    {
    	parent::__construct();
        $this->deftpl = $tplpath;
        if(!$tplpath){
            $this->deftplpath = 'file:'.__DIR__;
            $this->deftpl = $this->deftplpath.'/../ui.tpl';
        }
 
        $this->compile_dir = $GLOBALS[templates_compiledir];
        $this->compile_check = true;
    }
	
    /**
     * 
     */
    function ipblfetch(){
        return $this->fetch($this->deftpl);
    }
	
    /**
     * 
     */
    function ipbldisplay(){
        return $this->display($this->deftpl);
    }

}

