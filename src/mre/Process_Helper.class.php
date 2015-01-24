<?php

namespace mre;

/**
 * static process helper functions
 *
 * @author Christian Lück <christian@lueck.tv>
 * @copyright Copyright (c) 2011, Christian Lück
 * @license http://www.opensource.org/licenses/mit-license MIT License
 * @package Process
 * @version v0.0.1
 * @link https://github.com/clue/Process
 */
class Process_Helper
{
    /**
     * ignore exit code (i.e. do not throw exception when code is not 0)
     *
     * @var int
     */
    const EXEC_CODE_IGNORE = 1;

    /**
     * return last line
     *
     * @var int
     */
    const EXEC_LAST = 2;

    /**
     * return array of all output lines
     *
     * @var int
     */
    const EXEC_LINES = 4;

    /**
     * return exit code (includes 1 to accept all exit codes and do not throw exceptions)
     *
     * @var int
     * @see Process_Helper::EXEC_CODE_IGNORE
     */
    const EXEC_CODE = 9;

    ////////////////////////////////////////////////////////////////////////////

    /**
     * get array of child PIDs for given parent PID
     *
     * @param int|Process|array $pid
     * @param boolean $recurse
     * @return array[int]
     */
    public static function getChildPids($pid, $recurse = true)
    {
        $check = self::getPids($pid); // PIDs that are to be checked
        $found = array(); // found pids

        while ($check)
        {
            $pid = array_shift($check);  // select next PID
            if ($ret = self::exec('ps --ppid ' . $pid . ' -o pid=', self::EXEC_LINES))
            { // if this PID has children
                foreach ($ret as $pid)
                { // iterate through all children
                    $pid = (int)trim($pid);
                    if (!in_array($pid, $found, true))
                    {
                        $found[] = $pid; // append them to found PIDs
                        if ($recurse)
                        { // recurse if neccessary
                            $check[] = $pid;
                        }
                    }
                }
            }
        }
        return $found;
    }

    /**
     * execute command
     *
     * @param string $cmd
     * @param int $mode see EXEC_* constants
     * @return mixed
     * @throws Process_Exception on error
     */
    public static function exec($cmd, $mode = self::EXEC_LAST)
    {
        $code = 0;
        $lines = array();
        $line = exec($cmd, $lines, $code);

        if ($code !== 0 && !($mode & self::EXEC_CODE_IGNORE))
        {
            throw new Process_Exception('Process returned with exit code ' . $code);
        }

        if ($mode & self::EXEC_LINES)
        {
            return $lines;
        } else if ($mode & self::EXEC_CODE)
        {
            return $code;
        } else
        {
            return $line;
        }
    }

    /**
     * execute given command as root
     *
     * @param string $cmd
     * @param int $mode
     * @return mixed
     * @throws Process_Exception on error
     * @uses posix_getuid()
     * @uses Process_Helper::exec()
     */
    public static function execRoot($cmd, $mode = self::EXEC_LAST)
    {
        if (!function_exists('posix_getuid') || posix_getuid() !== 0)
        {
            $cmd = 'sudo -n -u root -- ' . $cmd;
        }
        return self::exec($cmd, $mode);
    }

    /**
     * renice given process(es)
     *
     * @param int|Process|array $pid processes to renice
     * @param int $level
     * @throws Process_Exception on error
     * @uses Process_Helper::getPids()
     * @uses Process_Helper::execRoot()
     */
    public static function renice($pid, $level)
    {
        //if(PHP_OS != 'Linux'){
        //    throw new Process_Exception('Not yet implemented');
        //}
        if ($level > 0)
        {
            $level = '+' . $level;
        }
        self::execRoot('renice ' . $level . ' ' . implode(' ', self::getPids($pid)));
    }

    /**
     * kill given process(es)
     *
     * @param int|Process|array $pid processes to kill
     * @param NULL|int $signal signal to use
     * @throws Process_Exception on error
     * @uses Process_Helper::getPids() to expand PIDs
     * @uses Process_Helper::execRoot()
     */
    public static function kill($pid, $signal = NULL)
    {
        self::execRoot('kill ' . (($signal !== NULL) ? ('-' . $signal) : '') . ' ' . implode(' ', self::getPids($pid)));
    }

    /**
     * recursively kill given process(es) and all sub-processes
     *
     * @param int|Process|array $pid
     * @param NULL|int $signal
     * @throws Process_Exception on error
     * @uses Process_Helper::getPids()
     * @uses Process_Helper::getChildPids()
     * @uses Process_Helper::kill()
     */
    public static function killRecursive($pid, $signal = NULL)
    {
        $kill = self::getPids($pid);
        self::kill(array_merge($kill, self::getChildPids($kill)), $signal);
    }

    /**
     * get PIDs for given process instance(s) or PID(s)
     *
     * @param int|Process|array $pid single process or array of processes
     * @return array[int]
     */
    public static function getPids($pid)
    {
        if (is_int($pid))
        {
            return array($pid);
        } else if ($pid instanceof Process)
        {
            return array($pid->getPid());
        } else
        {
            $pids = array();
            foreach ($pid as $p)
            {
                if ($p instanceof Process)
                {
                    $p = $p->getPid();
                }
                $pids[] = $p;
            }
            return array_unique($pids);
        }
    }

    /**
     * get signal number of given signal name
     *
     * uses signal constants when available (PCNTL extension must be installed)
     * or calls the 'kill' program to figure out signal numbers
     *
     * @param string|int $signal
     * @return int
     * @throws Process_Exception if the given signal can not be found
     * @uses Process_Helper::exec()
     * @link http://www.php.net/manual/en/pcntl.constants.php
     */
    public static function getSignal($signal)
    {
        if (is_int($signal))
        {
            return $signal;
        }
        if (substr($signal, 0, 3) === 'SIG')
        {
            if (defined($signal))
            {
                return constant($signal);
            }
        } else
        {
            if (defined('SIG' . $signal))
            {
                return constant('SIG' . $signal);
            }
        }
        if ($signal === 'SIGKILL')
        {
            return 9;
        }
        $ret = (int)trim(self::exec('kill -l ' . escapeshellarg($signal)));
        if ($ret === 0)
        {
            throw new Process_Exception('Invalid signal name');
        }
        return $ret;
    }
}
