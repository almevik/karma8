<?php
require_once('vendor/autoload.php');

include 'config.php';
include 'logger.php';
include 'database.php';

/**
 * @param mysqli $mysqli
 * @return int
 */
function getUsersCnt(mysqli $mysqli): int
{
    $sql = "SELECT COUNT(id) AS cnt FROM users";

    if (!$result = mysqli_query($mysqli, $sql)) {
        // Тут можно в сентри отправить или че-то еще сделать, пока просто завершение с ошибкой
        logger('error', "Failed to make query ($sql) " . mysqli_error($mysqli));
        exit(1);
    }

    return (int)mysqli_fetch_assoc($result)['cnt'];
}

/**
 * @param mysqli $mysqli
 * @param string $sqlUsers
 * @param null|string $sqlEmails
 * @return void
 */
function addUsers(mysqli $mysqli, string $sqlUsers, ?string $sqlEmails)
{
    // Пользователя и почту добавляем в транзакции
    mysqli_begin_transaction($mysqli);

    try {
        mysqli_query($mysqli, $sqlUsers);

        if (!empty($sqlEmails)) {
            mysqli_query($mysqli, $sqlEmails);
        }
        mysqli_commit($mysqli);
    } catch (Exception $e) {
        logger('error', $e->getMessage());
        mysqli_rollback($mysqli);
    }
}

$mysqli = initDB();

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
        $validTs = (int)(rand(0, 10) == 1) ? 'null' : rand(time() - 5 * 24 * 3600, time() + 10 * 24 * 3600);
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
    addUsers($mysqli, $sqlUsers, $sqlEmails);
}
