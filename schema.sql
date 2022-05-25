-- --------------------------------------------------------
-- Хост:                         127.0.0.1
-- Версия сервера:               10.3.22-MariaDB - mariadb.org binary distribution
-- Операционная система:         Win64
-- HeidiSQL Версия:              11.3.0.6295
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

-- Дамп структуры для таблица karma8.emails
CREATE TABLE IF NOT EXISTS `emails` (
    `email` varchar(150) NOT NULL,
    `checked` tinyint(4) DEFAULT 0,
    `valid` tinyint(4) DEFAULT 0,
    PRIMARY KEY (`email`),
    KEY `checked` (`checked`),
    KEY `valid` (`valid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Экспортируемые данные не выделены.

-- Дамп структуры для таблица karma8.notifications
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL DEFAULT 0,
    `from` varchar(150) NOT NULL DEFAULT '0',
    `to` varchar(150) NOT NULL DEFAULT '0',
    `subject` varchar(150) NOT NULL DEFAULT '0',
    `message` text NOT NULL,
    `sendts` int(11) DEFAULT NULL,
    `lock` tinyint(4) DEFAULT 0,
    `is_sended` tinyint(4) DEFAULT 0,
    `expired_at` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `sendts` (`sendts`),
    KEY `lock` (`lock`),
    KEY `is_sended` (`is_sended`),
    KEY `expired_at` (`expired_at`)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;

-- Экспортируемые данные не выделены.

-- Дамп структуры для таблица karma8.users
CREATE TABLE IF NOT EXISTS `users` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(50) NOT NULL DEFAULT '0',
    `email` varchar(50) NOT NULL DEFAULT '0',
    `validts` int(10) unsigned DEFAULT NULL,
    `confirmed` tinyint(3) unsigned NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `email` (`email`),
    KEY `confirmed` (`confirmed`),
    KEY `validts` (`validts`)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;

-- Экспортируемые данные не выделены.

/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
