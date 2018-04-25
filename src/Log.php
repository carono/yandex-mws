<?php

namespace carono\yandex;

class Log
{
    public $log_file = 'yandex.log';

    public function info($str)
    {
        if (is_array($str) || is_object($str)) {
            $str = print_r($str, true);
        }
        $str .= "\n";
        file_put_contents($this->log_file, '[' . date("Y-m-d H:i:s") . '] ' . $str, FILE_APPEND);
    }
}