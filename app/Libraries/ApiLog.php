<?php

namespace App\Libraries;

class ApiLog
{
    public function __construct()
    {
    }
    /**
     * 로그를 write 폴더에 기록한다.
     *
     * @param mixed $service
     * @param mixed $part
     * @param mixed $in_param
     * @param mixed $out_param
     * @return void
     */
    public static function write(string $service, string $action, $in_param, $out_param)
    {
        if (!$service || !$action) return false;

        $log_dir = ROOTPATH . "/writable/logs/{$service}/{$action}";
        $log_file = "{$log_dir}/{$action}_" . date("Ymd") . ".log";
        @mkdir($log_dir, 0777, true);
        @chmod($log_file, 0777);

        $msg = '============ LOG START ' . date("Y-m-d H:i:s") . " ==============\n";
        $msg .= "[INPUT PARAM]\n";
        if (is_array($in_param)) {
            $msg .= print_r($in_param, true);
        } else if (is_object($in_param)) {
            $msg .= print_r($in_param, true);
        } else {
            $msg .= "{$in_param}\n";
        }

        $msg .= "[OUTPUT PARAM]\n";
        if (is_array($out_param)) {
            $msg .= print_r($out_param, true);
        } else if (is_object($out_param)) {
            $msg .= print_r($out_param, true);
        } else {
            $msg .= "{$out_param}\n";
        }

        file_put_contents($log_file, $msg, FILE_APPEND);
    }
}
