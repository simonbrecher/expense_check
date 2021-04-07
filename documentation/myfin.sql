-- Adminer 4.8.0 MySQL 8.0.22 dump

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

SET NAMES utf8mb4;

DROP TABLE IF EXISTS `ba_import`;
CREATE TABLE `ba_import` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bank_account_id` int NOT NULL,
  `balance_start` decimal(12,2) DEFAULT NULL,
  `balance_end` decimal(12,2) DEFAULT NULL,
  `d_statement_start` date DEFAULT NULL,
  `d_statement_end` date DEFAULT NULL,
  `dt_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `bank_account_id` (`bank_account_id`),
  CONSTRAINT `ba_import_ibfk_1` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_account` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;


DROP TABLE IF EXISTS `bank`;
CREATE TABLE `bank` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL,
  `bank_code` varchar(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL,
  `export_file_name` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL,
  `import_template_filename` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `bank_code` (`bank_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

INSERT INTO `bank` (`id`, `name`, `bank_code`, `export_file_name`, `import_template_filename`) VALUES
(1,	'FIO banka, a.s.',	'2010',	'CSV API',	'FioBankImportTemplate.php');

DROP TABLE IF EXISTS `bank_account`;
CREATE TABLE `bank_account` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bank_id` int NOT NULL,
  `user_id` int NOT NULL,
  `number` varchar(17) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL,
  `is_active` tinyint NOT NULL DEFAULT '1',
  `dt_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `d_deactivated` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bank_id_number_user_id` (`bank_id`,`number`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `bank_account_ibfk_1` FOREIGN KEY (`bank_id`) REFERENCES `bank` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `bank_account_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;


DROP TABLE IF EXISTS `card`;
CREATE TABLE `card` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bank_account_id` int NOT NULL,
  `user_id` int NOT NULL,
  `number` varchar(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL,
  `name` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL,
  `is_active` tinyint NOT NULL DEFAULT '1',
  `dt_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `dt_deactivated` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `bank_account_id` (`bank_account_id`),
  CONSTRAINT `card_ibfk_1` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_account` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `card_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;


DROP TABLE IF EXISTS `cash_account`;
CREATE TABLE `cash_account` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `dt_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `cash_account_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;


DROP TABLE IF EXISTS `cash_account_balance`;
CREATE TABLE `cash_account_balance` (
  `id` int NOT NULL AUTO_INCREMENT,
  `cash_account_id` int NOT NULL,
  `d_balance` date NOT NULL,
  `czk_amount` decimal(12,2) NOT NULL,
  `dt_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `cash_account_id` (`cash_account_id`),
  CONSTRAINT `cash_account_balance_ibfk_1` FOREIGN KEY (`cash_account_id`) REFERENCES `cash_account` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;


DROP TABLE IF EXISTS `category`;
CREATE TABLE `category` (
  `id` int NOT NULL AUTO_INCREMENT,
  `family_id` int DEFAULT NULL,
  `name` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL,
  `is_active` tinyint NOT NULL DEFAULT '1',
  `is_cash_account_balance` tinyint NOT NULL DEFAULT '0',
  `dt_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name_family_id` (`name`,`family_id`),
  KEY `family_id` (`family_id`),
  CONSTRAINT `category_ibfk_1` FOREIGN KEY (`family_id`) REFERENCES `family` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;


DROP TABLE IF EXISTS `family`;
CREATE TABLE `family` (
  `id` int NOT NULL AUTO_INCREMENT,
  `dt_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;


DROP TABLE IF EXISTS `invoice_head`;
CREATE TABLE `invoice_head` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `card_id` int DEFAULT NULL,
  `cash_account_balance_id` int DEFAULT NULL,
  `d_issued` date NOT NULL,
  `dt_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `counter_account_number` varchar(17) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL DEFAULT '',
  `counter_account_bank_code` varchar(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL DEFAULT '',
  `var_symbol` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL DEFAULT '',
  `type_paidby` enum('PAIDBY_CASH','PAIDBY_CARD','PAIDBY_BANK','PAIDBY_ATM','PAIDBY_FEE') CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL,
  `is_auto_created` tinyint NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `card_id` (`card_id`),
  KEY `cash_account_balance_id` (`cash_account_balance_id`),
  CONSTRAINT `invoice_head_ibfk_5` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `invoice_head_ibfk_6` FOREIGN KEY (`card_id`) REFERENCES `card` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `invoice_head_ibfk_8` FOREIGN KEY (`cash_account_balance_id`) REFERENCES `cash_account_balance` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;


DROP TABLE IF EXISTS `invoice_item`;
CREATE TABLE `invoice_item` (
  `id` int NOT NULL AUTO_INCREMENT,
  `invoice_head_id` int NOT NULL,
  `category_id` int DEFAULT NULL,
  `consumer_id` int DEFAULT NULL,
  `is_main` tinyint NOT NULL DEFAULT '0',
  `czk_amount` decimal(12,2) NOT NULL,
  `description` varchar(35) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `invoice_head_id` (`invoice_head_id`),
  KEY `category_id` (`category_id`),
  KEY `member_id` (`consumer_id`),
  CONSTRAINT `invoice_item_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `category` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `invoice_item_ibfk_6` FOREIGN KEY (`invoice_head_id`) REFERENCES `invoice_head` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `invoice_item_ibfk_8` FOREIGN KEY (`consumer_id`) REFERENCES `user` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;


DROP TABLE IF EXISTS `payment`;
CREATE TABLE `payment` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `bank_account_id` int DEFAULT NULL,
  `ba_import_id` int DEFAULT NULL,
  `cash_account_id` int DEFAULT NULL,
  `invoice_head_id` int DEFAULT NULL,
  `card_id` int DEFAULT NULL,
  `payment_channel_id` int DEFAULT NULL,
  `d_payment` date NOT NULL,
  `czk_amount` decimal(12,2) NOT NULL,
  `bank_operation_id` bigint DEFAULT NULL,
  `counter_account_number` varchar(17) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL DEFAULT '',
  `counter_account_bank_code` varchar(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL DEFAULT '',
  `counter_account_name` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL DEFAULT '',
  `var_symbol` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL DEFAULT '',
  `message_recipient` varchar(35) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL DEFAULT '',
  `message_payer` varchar(35) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL DEFAULT '',
  `description` varchar(35) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL DEFAULT '',
  `type_paidby` enum('PAIDBY_CASH','PAIDBY_CARD','PAIDBY_BANK','PAIDBY_ATM','PAIDBY_FEE') CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci DEFAULT NULL,
  `is_consumption` tinyint NOT NULL DEFAULT '1',
  `is_identified` tinyint NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `bank_account_id` (`bank_account_id`),
  KEY `cash_account_id` (`cash_account_id`),
  KEY `invoice_id` (`invoice_head_id`),
  KEY `ba_import_id` (`ba_import_id`),
  KEY `card_id` (`card_id`),
  KEY `payment_channel_id` (`payment_channel_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `payment_ibfk_1` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_account` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `payment_ibfk_10` FOREIGN KEY (`ba_import_id`) REFERENCES `ba_import` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `payment_ibfk_2` FOREIGN KEY (`cash_account_id`) REFERENCES `cash_account` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `payment_ibfk_5` FOREIGN KEY (`card_id`) REFERENCES `card` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `payment_ibfk_6` FOREIGN KEY (`invoice_head_id`) REFERENCES `invoice_head` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `payment_ibfk_7` FOREIGN KEY (`payment_channel_id`) REFERENCES `payment_channel` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `payment_ibfk_8` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;


DROP TABLE IF EXISTS `payment_channel`;
CREATE TABLE `payment_channel` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `bank_account_id` int NOT NULL,
  `category_id` int DEFAULT NULL,
  `var_symbol` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL,
  `description` varchar(35) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL,
  `is_active` tinyint NOT NULL DEFAULT '1',
  `dt_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `dt_deactivated` datetime DEFAULT NULL,
  `is_consumption` tinyint NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `bank_account_id` (`bank_account_id`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `payment_channel_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `payment_channel_ibfk_2` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_account` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `payment_channel_ibfk_3` FOREIGN KEY (`category_id`) REFERENCES `category` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;


DROP TABLE IF EXISTS `user`;
CREATE TABLE `user` (
  `id` int NOT NULL AUTO_INCREMENT,
  `family_id` int NOT NULL,
  `name` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL,
  `surname` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL,
  `username` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci DEFAULT NULL,
  `email` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci DEFAULT NULL,
  `role` enum('ROLE_EDITOR','ROLE_VIEWER','ROLE_CONSUMER') CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL,
  `is_active` tinyint NOT NULL DEFAULT '1',
  `dt_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `dt_deactivated` datetime DEFAULT NULL,
  `password_hash` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name_family_id` (`name`,`family_id`),
  UNIQUE KEY `username` (`username`),
  KEY `family_id` (`family_id`),
  CONSTRAINT `user_ibfk_1` FOREIGN KEY (`family_id`) REFERENCES `family` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;


-- 2021-04-07 21:12:10
