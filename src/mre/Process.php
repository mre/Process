<?php

namespace mre;

/**
 * Process class
 *
 * @author Christian Lück <christian@lueck.tv>
 * @author Matthias Endler <matthias-endler@gmx.net>
 * @license http://www.opensource.org/licenses/mit-license MIT License
 * @package Process
 * @version v0.0.2
 * @link https://github.com/mre/Process
 */
class Process
{
    /**
     * process resource
     *
     * @var resource
     */
    protected $fp;

    /**
     * process input/output stream resources
     *
     * @var array[resource]
     */
    protected $pipes = array();

    /**
     * exit code
     *
     * @var int|NULL
     */
    protected $exitcode = NULL;

    /**
     * instanciate new process
     *
     * @param string $cmd command to execute
     * @param NULL|string $dir directory to execute command in
     * @param NULL|array $env environment to use (NULL=use current)
     * @throws ProcessException if process could not be started
     * @uses proc_open()
     */
    public function __construct($cmd, $dir = NULL, $env = NULL)
    {
        static $pipes = array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => STDERR
        );

        $this->fp = proc_open($cmd, $pipes, $this->pipes, $dir, $env);
        if ($this->fp === false)
        {
            throw new ProcessException('Unable to start process');
        }
    }

    /**
     * destruct and end process
     *
     * @uses Process::kill()
     * @uses Process::close()
     */
    public function __destruct()
    {
        $this->kill(true)->close(true);
    }

    /**
     * get process status
     *
     * @param string|NULL $flag
     * @return mixed
     * @throws ProcessException on error
     * @uses proc_get_status()
     */
    public function getStatus($flag = NULL)
    {
        $status = proc_get_status($this->fp);
        if ($status === false)
        {
            throw new ProcessException('Unable to get process status');
        }
        if ($status['exitcode'] == -1 && $this->exitcode !== NULL)
        {              // exit code unknown, eg not first call after being exited
            $status['exitcode'] = $this->exitcode;                              // restore previous code
        } else if ($status['exitcode'] != -1 && $status['running'] === false)
        {    // exit code present
            $this->exitcode = $status['exitcode'];                              // save for future reference
        }

        if ($flag !== NULL)
        {
            if (!isset($status[$flag]))
            {
                throw new ProcessException('Invalid status flag given');
            }
            return $status[$flag];
        }
        return $status;
    }

    /**
     * get process exit code
     * @throws ProcessException
     * @throws \Exception
     * @uses Process::getStatus()
     * @return int exit code
     */
    public function getExitcode()
    {
        $code = $this->exitcode;
        if ($code === NULL)
        {
            $code = $this->getStatus('exitcode');
            if ($code === NULL)
            {
                throw new ProcessException('Exit code not available');
            }
        }
        return $code;
    }

    /**
     * get process ID
     *
     * Warning: PHP returns the PID of the shell (sh) that runs the actual
     * command.
     *
     * @return int
     * @uses Process::getStatus()
     * @link http://www.php.net/manual/en/function.proc-get-status.php#93382 PHP manual for more information on the PID returned
     */
    public function getPid()
    {
        return $this->getStatus('pid');
    }

    /**
     * checks whether the process is still running
     *
     * @return boolean
     * @uses Process::getStatus()
     */
    public function isRunning()
    {
        return $this->getStatus('running');
    }

    /**
     * get the command string that was passed to the process
     *
     * @return string
     * @uses Process::getStatus()
     */
    public function getCommand()
    {
        return $this->getStatus('command');
    }

    /**
     * close process handle and input/output pipes
     *
     * Warning: proc_close() waits for the process to terminate, so consider
     * calling kill(true) before trying to close
     *
     * @param boolean $force whether to ignore any issues and complete operation (should not be used unless your have a very good reason, e.g. destructing)
     * @return Process $this (chainable)
     * @throws ProcessException on error
     * @see  Process::kill()
     * @uses fclose() to close every input/ouput stream
     * @uses proc_close() to close process handle
     */
    public function close($force = false)
    {
        //var_dump($this->pipes);
        foreach ($this->pipes as $n => $pipe)
        {
            if ($force)
            {
                @fclose($pipe);
            } else if (fclose($pipe) === false)
            {
                throw new ProcessException('Unable to close process pipe ' . $n);
            }
        }
        $this->pipes = array();
        $code = proc_close($this->fp);
        if ($code === false)
        {                                                    // invalid result like when handle is invalid (already closed?)
            throw new ProcessException('Unable to close process handle');
        }
        if ($code !== -1)
        {                                                       // ignore invalid exit code (likely process already dead)
            $this->exitcode = $code;
        }
        return $this;
    }

    /**
     * kill process (with given signal)
     *
     * please be aware that this command does not necessarily terminate the process,
     * as some signals can be blocked (most notably the default signal).
     *
     * calling 'kill(true)' is an alias to SIGKILL. use it to make sure the process
     * will actually be terminated
     *
     * @param NULL|int|boolean $signal signal to send (true=SIGKILL,default:NULL=SIGTERM)
     * @return Process $this (chainable)
     * @uses ProcessHelper::getSignal() to resolve signal names to signal numbers
     * @uses proc_terminate()
     */
    public function kill($signal = NULL)
    {
        if ($signal === true)
        {
            $signal = ProcessHelper::getSignal('SIGKILL');
        }
        if ($signal === NULL || $signal === false)
        {
            proc_terminate($this->fp);
        } else
        {
            proc_terminate($this->fp, $signal);
        }
        return $this;
    }

    /**
     * read up to $len bytes from process output
     *
     * @param int $len
     * @return string
     * @throws ProcessException on error
     * @uses fread()
     */
    public function receive($len)
    {
        $ret = fread($this->pipes[1], $len);
        if ($ret === false)
        {
            throw new ProcessException('Unable to read from process output stream');
        }
        return $ret;
    }

    /**
     * write buffer to process input
     *
     * @param string $buffer
     * @return int number of bytes actually written
     * @throws ProcessException on error
     * @uses fwrite()
     */
    public function send($buffer)
    {
        $ret = fwrite($this->pipes[0], $buffer);
        if ($ret === false)
        {
            throw new ProcessException('Unable to send to process input stream');
        }
        return $ret;
    }

    /**
     * get stream to read from
     *
     * @return resource
     */
    public function getStreamRead()
    {
        return $this->pipes[1];
    }

    /**
     * get stream to write to
     *
     * @return resource
     */
    public function getStreamWrite()
    {
        return $this->pipes[0];
    }

    public function setStreamBlocking($mode, $pipe = NULL)
    {
        if ($pipe === NULL)
        {
            foreach ($this->pipes as $pipe)
            {
                stream_set_blocking($pipe, $mode);
            }
            return true;
        } else if (!isset($this->pipes[$pipe]))
        {
            throw new ProcessException('Invalid pipe');
        }
        return stream_set_blocking($this->pipes[$pipe], $mode);
    }
}
