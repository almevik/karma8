<?php

require_once('vendor/autoload.php');

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$dotenv->required(['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS']);

const EMERGENCY = 'emergecy';
const ALERT = 'alert';
const CRITICAL = 'critical';
const ERROR = 'error';
const WARNING = 'warning';
const NOTICE = 'notice';
const INFO = 'info';
const DEBUG = 'debug';

const NEED_USERS = 1000000;
const MAX_INSERT_CNT = 10000;

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

/**
 * @param mysqli $mysqli
 * @return string|null
 * @throws Exception
 */
function getEmail(mysqli $mysqli): ?string
{
    $email = null;

    // Получение почты для проверки делаем в транзакции, чтобы не было конфликтов в нескольких процессах
    mysqli_begin_transaction($mysqli);

    try {
        $selectEmail = "SELECT email FROM emails WHERE checked = 0 AND valid = 0 LIMIT 1";

        if (!$result = mysqli_query($mysqli, $selectEmail)) {
            throw new Exception("Failed to make query ($selectEmail) " . mysqli_error($mysqli));
        }

        if ($data = mysqli_fetch_assoc($result)) {
            $email = $data['email'];
            mysqli_query($mysqli, "UPDATE emails SET checked = 2 WHERE email = '$email'");
        }

        mysqli_commit($mysqli);
    } catch (Exception $e) {
        logger('error', $e->getMessage());
        mysqli_rollback($mysqli);
        throw $e;
    }

    return $email;
}

/**
 * @param mysqli $mysqli
 * @param string $email
 * @param int $valid
 * @return void
 * @throws Exception
 */
function completeCheck(mysqli $mysqli, string $email, int $valid)
{
    mysqli_begin_transaction($mysqli);

    try {
        mysqli_query($mysqli, "UPDATE emails SET checked = 1, valid = $valid WHERE email = '$email'");
        logger('info', "Почта $email проверена");

        // Если почта валидна, обновляем флаг в пользователях
        if ($valid) {
            logger('info', "Почта $email валидна");
            mysqli_query($mysqli, "UPDATE users SET confirmed = 1 WHERE email = '$email'");
        }

        mysqli_commit($mysqli);
    } catch (Exception $e) {
        logger('error', $e->getMessage());
        mysqli_rollback($mysqli);
        throw $e;
    }
}

/**
 * @param mysqli $mysqli
 * @param string $email
 * @return int
 */
function cancelCheck(mysqli $mysqli, string $email): int
{
    mysqli_query($mysqli, "UPDATE emails SET checked = 0 WHERE email = '$email'");
    return mysqli_affected_rows($mysqli);
}

function check_email(string $email): int
{
    // Нет смысла проверять почту, если она не почта
    if (strpos($email, "@") === false) {
        logger('info', "Почта $email некорректна");
        return 0;
    }

    // Рандомно выполняется 1-60 сек
    sleep(rand(1, 60));
    return rand(0, 1);
}

$mysqli = initDB();

// TODO Добавить обработку сигналов остановки процесса
// Если остановить выполнение вручную, задание останется залоченным и не выполнится
// Тут можно использовать rabbitMQ
while (true) {
    // TODO Тут можно воткнуть что-то вроде try{}catch{} и обработать ошибку потери подключения к БД
    // if (mysqli_connect_error()) {$mysqli = initDB();}
    if ($email = getEmail($mysqli)) {
        try {
            completeCheck($mysqli, $email, check_email($email));
        } catch (Exception $e) {
            logger('error', $e->getMessage());
            cancelCheck($mysqli, $email);
        }
    } else {
        sleep(1);
    }
}
