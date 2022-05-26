<?php
require_once('vendor/autoload.php');

include 'config.php';
include 'logger.php';
include 'database.php';

/**
 * @param mysqli $mysqli
 * @param int $expiredAt
 * @return array
 */
function getExpiredSubscription(mysqli $mysqli, int $expiredAt): array
{
    $currTs = time();
    $validTs = $currTs + $expiredAt;

    $sql = sprintf("
            SELECT
                u.id,u.name,u.email,u.validts
            FROM users AS u
            LEFT JOIN notifications AS n ON u.id = n.user_id
            WHERE u.validts < %d
                AND u.validts >= %d
                AND u.confirmed = 1
                AND (
                    n.id IS NULL
                    OR n.expired_at < %d
                )
        ", $validTs, $currTs, $currTs);

    if (!$result = mysqli_query($mysqli, $sql)) {
        logger('error', "Failed to make query ($sql) " . mysqli_error($mysqli));
        exit(1);
    }

    $users = [];

    while ($user = mysqli_fetch_assoc($result)) {
        $users[] = $user;
    }

    return $users;
}

/**
 * @param mysqli $mysqli
 * @param array $users
 * @return void
 */
function addNotificationTasks(mysqli $mysqli, array $users)
{
    $notificationsForInsert = [];
    $notificationsArr = [];
    $i = 1;

    // Собираем массив уведомлений для генерации запроса
    foreach ($users as $user) {
        if ($i % MAX_INSERT_CNT == 0) {
            $notificationsForInsert[] = $notificationsArr;
            $notificationsArr = [];
        }

        $notificationsArr[] = sprintf("(%d, '%s', '%s', '%s', '%s', %d)",
            $user['id'],
            $_SERVER['EMAIL_FROM'],
            $user['email'],
            SUBJECT,
            "{$user['name']}, your subscription is expiring soon",
            $user['validts']);

        $i++;
    }

    // Добавляем последнюю часть в массив
    $notificationsForInsert[] = $notificationsArr;
    $sqlsNotifications = [];

    // Генерим несколько запросов максимум по MAX_INSERT_CNT строк на запрос
    foreach ($notificationsForInsert as $notifications) {
        $sqlsNotifications[] = "
                INSERT INTO notifications
                    (user_id, `from`, `to`, subject, message, expired_at)
                VALUES
                    " . implode(',', $notifications) . "
            ";
    }

    // Исполняем запросы в цикле
    foreach ($sqlsNotifications as $sqlNotifications) {
        try {
            mysqli_query($mysqli, $sqlNotifications);
        } catch (Exception $e) {
            logger('error', $e->getMessage());
        }
    }
}

$mysqli = initDB();

$expiredAt = $_SERVER['EXPIRED_DAYS'] * 24 * 3600;

// Выбираем пользователей с истекающей подпиской, валидной почтой и отсутствием задания на отправку уведомления (истекшим заданием)
$users = getExpiredSubscription($mysqli, $expiredAt);

if (!empty($users)) {
    // Создаем задания на отправку писем
    addNotificationTasks($mysqli, $users);
}
