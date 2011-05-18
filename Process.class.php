<?php

/**
 * process class
 * 
 * @author mE
 */
class Process implements Interface_Stream_Duplex{
    /**
     * newline string (CRLF)
     * 
     * @var string
     */
    const NL = "\r\n";
    
    protected $fp;
    protected $pipes;
    protected $active;
    protected $exitcode;

    protected $cmd;
    protected $dir;
    protected $user;
    protected $env;

    // Internal functions

    public function __construct($cmd,$dir=NULL,$user=NULL,$env=NULL){
        $this->fp       = false;
        $this->pipes    = false;
        $this->active   = false;
        $this->exitcode = 'n/a';

        $this->cmd      = $cmd;
        $this->dir      = $dir;
        $this->user     = $user;
        $this->env      = $env;
    }

    public function __destruct(){
        if($this->active){
            $this->stop();
        }
    }
    
    public function reinitialize($cmd,$dir=NULL,$user=NULL,$env=NULL){
        if($cmd !== $this->cmd || $dir !== $this->dir || $user !== $this->user || $env !== $this->env){ // something changed
            $this->cmd  = $cmd;                                                 // apply new values
            $this->dir  = $dir;
            $this->user = $user;
            $this->env  = $env;
            
            if($this->active){
                $this->stop();
                $this->start();
            }
            
            return true;
        }
        return false;
    }

    protected function _proc_open($cmd){
        static $pipes = array(
            0 => array('pipe','r'),
            1 => array('pipe','w'),
            2 => STDERR
        );

        return proc_open($cmd,$pipes,$this->pipes,$this->dir,$this->env);
    }

    public function _get_status($flag = NULL){
        $status = proc_get_status($this->fp);
        if($status['exitcode'] == -1){                                          // exit code unknown, eg not first call after being exited
            $status['exitcode'] = $this->exitcode;                              // restore previous code
        }else{                                                                  // exit code present
            $this->exitcode = $status['exitcode'];                              // save for future reference
        }

        if($flag !== NULL){
            return $status[$flag];
        }
        return $status;
    }

    public function getChildren($recurse=true){
        if(!$this->active){
            throw new Exception('Inactive process');
        }

        $check = array($this->_get_status('pid'));                              // PIDs that are to be checked
        $found = array();                                                       // found pids

        while($check){
            $pid = array_shift($check);                                         // select next PID
            if($ret = self::exec('ps --ppid '.$pid.' -o pid=',2)){              // if this PID has children
                foreach($ret as $pid){                                          // iterate through all children
                    $pid = (int)trim($pid);
                    $found[] = $pid;                                            // append them to found PIDs
                    if($recurse){                                               // recurse if neccessary
                        $check[] = $pid;
                    }
                }
            }
        }
        return $found;
    }

    // User functions

    public function start($blocking=false){
        if($this->active){
            throw new Process_Exception('Process already started');
        }

        $cmd = $this->cmd;
        if($this->user !== NULL){
            if(PHP_OS == 'Linux'){
                $cmd = 'sudo -n -u '.$this->user.' '.$cmd;
            }else{
                //throw new Exception('Not yet implemented');
            }
        }

        $this->fp = $this->_proc_open($cmd);

        if(!is_resource($this->fp)){
            throw new Exception('Error executing ['.$cmd.']');
        }

        if(!$blocking){
            foreach($this->pipes as $pipe){
                stream_set_blocking($pipe,0);
            }
        }

        $this->active = true;
    }

    public function stop($signal=NULL){
        if(!$this->active){
            throw new Process_Exception('Process not active');
        }
        if($signal === NULL){
            proc_terminate($this->fp);
        }else{
            proc_terminate($this->fp,$signal);
        }
        foreach($this->pipes as $pipe){
            @fclose($pipe);
        }
        @proc_close($this->fp);

        $this->active = false;
    }

    public function isRunning(){
        if($this->active){
            if($this->_get_status('running')){
                return true;
            }
        }
        return false;
    }
    
    public function isActive(){
        return $this->active;
    }

    public function putLine($line){
        return $this->fputs($line.self::NL);
    }

    public function readLine($pipe=1){
        if(!$this->active){
            throw new Process_Exception('Cant read from inactive process');
        }
        $ret = fgets($this->pipes[$pipe]);
        if($ret === false){
            throw new Exception('Unable to read from given process stream');
        }
        return $ret;
    }

    // Service functions

    public function renice($level){
        if(PHP_OS != 'Linux'){
            throw new Process_Exception('Not yet implemented');
        }
        if(!$this->active){
            throw new Process_Exception('Cant renice inactive process');
        }
        $pid   = (int)$this->_get_status('pid');
        $ret   = self::exec_root('renice '.(($level > 0)?'+':'').$level.' '.$pid,1);
        return ($ret == 0) ? true : false;
    }

    public function kill($signal=NULL){
        if(!$this->active){
            throw new Process_Exception('Cant kill inactive process');
        }
        if(PHP_OS != 'Linux'){
            return proc_terminate($signal);
        }
        $pid   = (int)$this->_get_status('pid');
        $ret   = self::exec_root('kill '.(($signal !== NULL)?('-'.$signal):'').' '.$pid,1);
        return ($ret == 0) ? true : false;
    }

    // Advanced functions

    public function fread($len){
        if(!$this->active){
            throw new Process_Exception('Cant read from inactive process');
        }
        $ret = fread($this->pipes[1],$len);
        if($ret === false){
            throw new Exception('Unable to read from process stream');
        }
        return $ret;
    }

    public function fputs($buffer){
        if(!$this->active){
            throw new Process_Exception('Cant put to inactive process');
        }
        $ret = fputs($this->pipes[0],$buffer);
        if($ret === false){
            throw new Exception('Unable to send to process stream');
        }
        return $ret;
    }

    public function ready($pipe=1,$read=true){
        $set = array($this->pipes[$pipe]);
        $n   = NULL;
        if($read){
            return stream_select($set,$n,$n,0);
        }else{
            return stream_select($n,$set,$n,0);
        }
    }
    
    /**
     * get stream to read from
     * 
     * @return resource
     */
    public function getStreamReceive(){
        return $this->pipes[1];
    }
    
    /**
     * get stream to write to
     * 
     * @return resource
     */
    public function getStreamSend(){
        return $this->pipes[0];
    }
    
    /**
     * receive data from process stream
     * 
     * @return string
     */
    public function streamReceive(){
        return $this->fread(4096);
    }
    
    /**
     * send given data to process stream
     * 
     * @param string $data
     * @return int number of bytes sent
     */
    public function streamSend($data=''){
        return $this->fputs($data);
    }
    
    public function hasStreamOutgoing(){
        return false;
    }
    
    /**
     * execute command
     *
     * @param string $cmd
     * @param int    $mode 0=last line, 1=return code, 2=output array
     * @return mixed
     */
    public static function exec($cmd,$mode=0){
        $code  = 0;
        $lines = array();
        $line  = exec($cmd,$lines,$code);
        
        if($mode === 0){
            return $line;
        }else if($mode === 1){
            return $code;
        }else{
            return $lines;
        }
    }
    
    /**
     * execute given command as root
     *
     * @param string $cmd
     * @param int    $mode
     * @return mixed
     * @uses Process::exec()
     */
    public static function execRoot($cmd,$mode=0){
        if(!function_exists('posix_getuid') || posix_getuid() !== 0){
            $cmd = 'sudo '.$cmd;
        }
        return self::exec($cmd,$mode);
    }
}

/**
 * lightweight process exception
 * 
 * @author mE
 */
class Process_Exception extends Exception{ }
