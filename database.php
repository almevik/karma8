<?php
require_once('vendor/autoload.php');

include_once 'config.php';

/**
 * @return mysqli
 */
function initDB(): mysqli
{
    // Подключаемся к базе
    $mysqli = mysqli_connect($_SERVER['DB_HOST'], $_SERVER['DB_USER'], $_SERVER['DB_PASS'], $_SERVER['DB_NAME']);

    if (!$mysqli) {
        die('Ошибка соединения: (' . mysqli_connect_errno() . ')' . mysqli_connect_error());
    }

    return $mysqli;
}
