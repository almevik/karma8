<?php
require_once('vendor/autoload.php');

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$dotenv->required(['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS']);

const NEED_USERS = 1000000;
const MAX_INSERT_CNT = 10000;
const SUBJECT = 'Expiring subscription';
