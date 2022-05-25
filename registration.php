<?php

require_once('vendor/autoload.php');

// Тут конечно ООП, но думаю не критично)
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
 * @param mysqli $mysqli
 * @return int
 */
function getUsersCnt(mysqli $mysqli): int
{
    $select = "SELECT COUNT(id) AS cnt FROM users";

    if (!$result = mysqli_query($mysqli, $select)) {
        // Тут можно в сентри отправить или че-то еще сделать, пока просто завершение с ошибкой
        logger('error', "Failed to make query ($select) " . mysqli_error($mysqli));
        exit(1);
    }

    return (int)mysqli_fetch_assoc($result)['cnt'];
}

// Подключаемся к базе
$mysqli = mysqli_connect($_SERVER['DB_HOST'], $_SERVER['DB_USER'], $_SERVER['DB_PASS'], $_SERVER['DB_NAME']);

if (!$mysqli) {
    die('Ошибка соединения: (' . mysqli_connect_errno() . ')' . mysqli_connect_error());
}

$usersCnt = getUsersCnt($mysqli);
$needUsers = NEED_USERS - $usersCnt;

$usersForInsert = [];
$emailsForInsert = [];

if ($needUsers > 0) {
    $newUsers = [];
    $newEmails = [];

    for ($i = 1; $i <= $needUsers; $i++) {
        if ($i % MAX_INSERT_CNT == 0) {
            $usersForInsert[] = $newUsers;
            $newUsers = [];
            $emailsForInsert[] = $newEmails;
            $newEmails = [];
        }

        $newUserId = $usersCnt + $i;
        // Рандомное время окончания подписки или вообще отсутствие для 10% пользователей
        $validTs = (int)(rand(0, 10) == 1) ? null : rand(time() - 5 * 24 * 3600, time() + 10 * 24 * 3600);
        $valid = (int)(rand(0, 10) == 1);
        $checked = $valid ?? (int)(rand(0, 10) == 1);

        // Для 1% пользователей указываем некорректную почту
        if ((int)(rand(0, 100) == 1)) {
            $newUsers[] = "(\"user$newUserId\", \"user{$newUserId}mail.ru\", $validTs, 0)";
            $newEmails[] = "(\"user{$newUserId}mail.ru\", 0, 0)";
        } else {
            $newUsers[] = "(\"user$newUserId\", \"user$newUserId@mail.ru\", $validTs, $valid)";
            $newEmails[] = "(\"user$newUserId@mail.ru\", $checked, $valid)";
        }
    }

    $usersForInsert[] = $newUsers;
    $emailsForInsert[] = $newEmails;
}

$sqlsUsers = [];
$sqlsEmails = [];

// Генерим тексты запросов для инсерта MAX_INSERT_CNT записей
foreach ($usersForInsert as $users) {
    $sqlsUsers[] = "INSERT INTO users (`name`, email, validts, confirmed) VALUES " . implode(',', $users);
}

foreach ($emailsForInsert as $emails) {
    $sqlsEmails[] = "INSERT INTO emails (email, checked, valid) VALUES" . implode(',', $emails);
}

foreach ($sqlsUsers as $key => $sqlUsers) {
    $sqlEmails = $sqlsEmails[$key] ?? null;

    // Пользователя и почту добавляем в транзакции
    mysqli_begin_transaction($mysqli);

    try {
        mysqli_query($mysqli, $sqlUsers);
        mysqli_query($mysqli, $sqlEmails);
        mysqli_commit($mysqli);
    } catch (Exception $e) {
        logger('error', $e->getMessage());
        mysqli_rollback($mysqli);
    }
}
