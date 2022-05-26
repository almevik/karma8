<?php
require_once('vendor/autoload.php');

const EMERGENCY = 'emergecy';
const ALERT = 'alert';
const CRITICAL = 'critical';
const ERROR = 'error';
const WARNING = 'warning';
const NOTICE = 'notice';
const INFO = 'info';
const DEBUG = 'debug';

function getLogLevelMap(): array
{
    return [
        EMERGENCY => 1,
        ALERT     => 2,
        CRITICAL  => 3,
        ERROR     => 4,
        WARNING   => 5,
        NOTICE    => 6,
        INFO      => 7,
        DEBUG     => 8,
    ];
}

/**
 * @param $level
 * @param $message
 * @return void
 */
function logger($level, $message)
{
    if (getLogLevelMap()[$level] <= getLogLevelMap()[$_SERVER['LOG_LEVEL'] ?? INFO]) {
        if (empty($_SERVER['LOG_FILE'])) {
            print_r(date("Y-m-d H:i:s") . ' [' . $level . '] ' . $message . "\n");
        } else {
            file_put_contents($_SERVER['LOG_FILE'], date("Y-m-d H:i:s") . ' [' . $level . '] ' . $message . "\n", FILE_APPEND);
        }
    }
}
