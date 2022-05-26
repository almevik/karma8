<?php

require_once('vendor/autoload.php');

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$dotenv->required(['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS']);

include 'logger.php';
include 'database.php';

/**
 * @param mysqli $mysqli
 * @return array|null
 */
function getTask(mysqli $mysqli): ?array
{
    // Отправку писем делаем в транзакции, чтобы не было конфликтов в нескольких процессах
    mysqli_begin_transaction($mysqli);

    try {
        $select = "
            SELECT id, user_id, `from`, `to`, subject, message, sendts, `lock`, is_sended, expired_at
            FROM notifications
            WHERE is_sended = 0
                AND `lock` = 0
            ORDER BY expired_at
            LIMIT 1
        ";

        if (!$result = mysqli_query($mysqli, $select)) {
            logger('error', "Failed to make query ($select) " . mysqli_error($mysqli));
            exit(1);
        }

        if ($task = mysqli_fetch_assoc($result)) {
            // Лочим сразу сообщение
            mysqli_query($mysqli, "UPDATE notifications SET `lock` = 1 WHERE id = {$task['id']}");
            logger('info', "Задание {$task['id']} взято в работу");
        }

        mysqli_commit($mysqli);
    } catch (Exception $e) {
        logger('error', $e->getMessage());
        mysqli_rollback($mysqli);
    }

    return $task;
}

/**
 * @param mysqli $mysqli
 * @param int $id
 * @return int
 */
function closeTask(mysqli $mysqli, int $id): int
{
    return updateTask($mysqli, $id, ['lock' => 0, 'is_sended' => 1, 'sendts' => time()]);
}

/**
 * @param mysqli $mysqli
 * @param int $id
 * @return int
 */
function cancelTask(mysqli $mysqli, int $id): int
{
    return updateTask($mysqli, $id, ['lock' => 0]);
}

/**
 * @param mysqli $mysqli
 * @param int $id
 * @param array $params
 * @return int
 */
function updateTask(mysqli $mysqli, int $id, array $params): int
{
    if (empty($params)) {
        return 0;
    }

    // Форматируем параметры в строку вида `lock` = '0',`is_sended` = '1',`sendts` = '11111'
    $strParams = implode(',', array_map(function ($k, $v) {
        return "`$k` = '$v'";
    }, array_keys($params), array_values($params)));

    mysqli_query($mysqli, "UPDATE notifications SET $strParams WHERE id = $id");

    return mysqli_affected_rows($mysqli);
}

function send_email(string $from, string $to, string $subject, string $message)
{
    // Рандомно выполняется 1-10 сек
    sleep(rand(1, 10));
    return 1;
}

$mysqli = initDB();

// TODO Добавить обработку сигналов остановки процесса
// Если остановить выполнение вручную, задание останется залоченным и не выполнится
// Тут можно использовать rabbitMQ
while (true) {
    if ($task = getTask($mysqli)) {
        try {
            if (send_email($task['from'], $task['to'], $task['subject'], $task['message'])) {
                logger('info', "Уведомление {$task['id']} отправлено");
                closeTask($mysqli, $task['id']);
            }
        } catch (Exception $e) {
            logger('error', $e->getMessage());
            cancelTask($mysqli, $task['id']);
        }
    } else {
        sleep(1);
    }
}
