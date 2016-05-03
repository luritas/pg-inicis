<?php
/**
 * Created by PhpStorm.
 * User: programmer
 * Date: 2016-05-03
 * Time: 오후 3:56
 */
namespace Visualplus\PgInicis\Libs;

require ('INICls.php');

/* ----------------------------------------------------- */
/* LOG Class                                           */
/* ----------------------------------------------------- */

class INILog
{

    var $handle;
    var $type;
    var $log;
    var $debug_mode;
    var $array_key;
    var $debug_msg;
    var $starttime;
    var $homedir;
    var $mid;
    var $mkey;
    var $mergelog;

    function INILog($request)
    {
        $this->debug_msg = array("", "CRITICAL", "ERROR", "NOTICE", "4", "INFO", "6", "DEBUG", "8");
        $this->debug_mode = $request["debug"];
        $this->type = $request["type"];
        $this->log = $request["log"];
        $this->homedir = $request["inipayhome"];
        $this->mid = $request["mid"];
        $this->starttime = GetMicroTime();
        $this->mergelog = $request["mergelog"];
    }

    function StartLog()
    {
        if ($this->log == "false")
            return true;

        if ($this->type == "chkfake")
            $type = "securepay";
        else
            $type = $this->type;
        if ($this->mergelog == "1")
            $logfile = $this->homedir . "/log/" . PROGRAM . "_" . $type . "_mergelog_" . date("ymd") . ".log";
        else
            $logfile = $this->homedir . "/log/" . PROGRAM . "_" . $type . "_" . $this->mid . "_" . date("ymd") . ".log";
        $this->handle = fopen($logfile, "a+");
        if (!$this->handle)
            return false;
        $this->WriteLog(INFO, "START " . PROGRAM . " " . $this->type . " (V" . VERSION . "-" . BUILDDATE . ")(OS:" . php_uname('s') . php_uname('r') . ",PHP:" . phpversion() . ")");
        return true;
    }

    function WriteLog($debug, $data)
    {
        if ($this->log == "false" || !$this->handle)
            return;

        if (!$this->debug_mode && $debug >= DEBUG)
            return;

        $pfx = $this->debug_msg[$debug] . "\t[" . SetTimestamp() . "] <" . getmypid() . "> ";
        if (is_array($data)) {
            foreach ($data as $key => $val) {
                fwrite($this->handle, $pfx . $key . ":" . $val . "\r\n");
            }
        } else {
            fwrite($this->handle, $pfx . $data . "\r\n");
        }
        fflush($this->handle);
    }

    function CloseLog($msg)
    {
        if ($this->log == "false")
            return;

        $laptime = GetMicroTime() - $this->starttime;
        $this->WriteLog(INFO, "END " . $this->type . " " . $msg . " Laptime:[" . round($laptime, 3) . "sec]");
        $this->WriteLog(INFO, "===============================================================");
        fclose($this->handle);
    }

}