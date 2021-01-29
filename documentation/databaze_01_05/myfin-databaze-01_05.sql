-- Adminer 4.7.7 MySQL dump

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

SET NAMES utf8mb4;

DROP DATABASE IF EXISTS `myfin`;
CREATE DATABASE `myfin` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `myfin`;

DROP TABLE IF EXISTS `ba_import`;
CREATE TABLE `ba_import` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bank_account_id` int NOT NULL,
  `balance_start` decimal(10,2) DEFAULT NULL,
  `balance_end` decimal(10,2) DEFAULT NULL,
  `d_statement_start` date DEFAULT NULL,
  `d_statement_end` date DEFAULT NULL,
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
  `d_create` date NOT NULL,
  `d_deactivate` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bank_id_number_account` (`bank_id`,`number`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `bank_account_ibfk_1` FOREIGN KEY (`bank_id`) REFERENCES `bank` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `bank_account_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

INSERT INTO `bank_account` (`id`, `bank_id`, `user_id`, `number`, `is_active`, `d_create`, `d_deactivate`) VALUES
(1,	1,	1,	'2200120120',	1,	'2021-01-15',	NULL),
(2,	1,	2,	'4400140140',	1,	'2021-01-15',	NULL);

DROP TABLE IF EXISTS `card`;
CREATE TABLE `card` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bank_account_id` int NOT NULL,
  `user_id` int NOT NULL,
  `number` varchar(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL,
  `name` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL,
  `is_active` tinyint NOT NULL DEFAULT '1',
  `d_create` date NOT NULL,
  `d_deactivate` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bank_account_id_public_card_num` (`bank_account_id`,`number`),
  UNIQUE KEY `name` (`name`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `card_ibfk_1` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_account` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `card_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

INSERT INTO `card` (`id`, `bank_account_id`, `user_id`, `number`, `name`, `is_active`, `d_create`, `d_deactivate`) VALUES
(1,	1,	1,	'2200',	'Karel - Mastercard',	1,	'2021-01-15',	NULL),
(2,	2,	2,	'4400',	'Bára - Mastercard',	1,	'2021-01-15',	NULL),
(3,	1,	1,	'0022',	'Karel - Druhá karta',	1,	'2021-01-15',	NULL);

DROP TABLE IF EXISTS `cash_account`;
CREATE TABLE `cash_account` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `cash_account_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

INSERT INTO `cash_account` (`id`, `user_id`) VALUES
(1,	1),
(2,	2);

DROP TABLE IF EXISTS `cash_account_balance`;
CREATE TABLE `cash_account_balance` (
  `id` int NOT NULL AUTO_INCREMENT,
  `cash_account_id` int NOT NULL,
  `d_balance` date NOT NULL,
  `amount_balance_physical` decimal(8,0) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cash_account_id_d_balance` (`cash_account_id`,`d_balance`),
  CONSTRAINT `cash_account_balance_ibfk_1` FOREIGN KEY (`cash_account_id`) REFERENCES `cash_account` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

INSERT INTO `cash_account_balance` (`id`, `cash_account_id`, `d_balance`, `amount_balance_physical`) VALUES
(1,	1,	'2017-12-31',	12700),
(2,	2,	'2017-12-31',	2500);

DROP TABLE IF EXISTS `category`;
CREATE TABLE `category` (
  `id` int NOT NULL AUTO_INCREMENT,
  `family_id` int DEFAULT NULL,
  `name` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL,
  `is_cash_account_balance` tinyint NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `family_id` (`family_id`),
  CONSTRAINT `category_ibfk_1` FOREIGN KEY (`family_id`) REFERENCES `family` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

INSERT INTO `category` (`id`, `family_id`, `name`, `description`, `is_cash_account_balance`) VALUES
(1,	1,	'Potraviny',	'Potraviny, nápoje, útrata v restauracích a kavárnách spíše do osobní spotřeby.',	0),
(2,	1,	'Osobní spotřeba',	'Kulturní akce, knížky, drobné osobní předměty (hodinky, holicí strojek), sauna, parkování, nezapočítávají se jiné kategorie - léky, sport, dovolená, potravinové doplňky, atd.',	0),
(3,	1,	'Dovolená',	'Dovolená a výkendy mimo bydliště.',	0),
(4,	1,	'Auto',	'Pořízení auta, servis, doplňky, pohonné hmoty.',	0),
(5,	1,	'Sport',	'Sportovní akce, vstup, vybavení.',	0),
(6,	1,	'Léky',	'Léky.',	0),
(7,	1,	'Vzdělání',	'Školy, školení, odborná literatura, pomůcky.',	0),
(8,	1,	'Různé',	'Výdaje, které nejde zařadit do jiných kategorií.',	0),
(9,	1,	'Drobné výdaje v hotovosti',	'Používá pouze aplikace.',	1);

DROP TABLE IF EXISTS `consumer`;
CREATE TABLE `consumer` (
  `id` int NOT NULL AUTO_INCREMENT,
  `family_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `name` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL,
  `surname` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL,
  `is_active` tinyint NOT NULL DEFAULT '1',
  `d_create` date NOT NULL,
  `d_deactivate` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `family_id` (`family_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `consumer_ibfk_1` FOREIGN KEY (`family_id`) REFERENCES `family` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `consumer_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

INSERT INTO `consumer` (`id`, `family_id`, `user_id`, `name`, `surname`, `is_active`, `d_create`, `d_deactivate`) VALUES
(1,	1,	1,	'Karel',	'Jouda',	1,	'0000-00-00',	NULL),
(2,	1,	2,	'Bára',	'Joudová',	1,	'0000-00-00',	NULL),
(3,	1,	NULL,	'Ema',	'Joudová',	1,	'0000-00-00',	NULL),
(4,	1,	NULL,	'Kryštof',	'Jouda',	1,	'0000-00-00',	NULL);

DROP TABLE IF EXISTS `db_version`;
CREATE TABLE `db_version` (
  `id` int NOT NULL AUTO_INCREMENT,
  `number` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL,
  `dt_version` datetime NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

INSERT INTO `db_version` (`id`, `number`, `dt_version`, `description`) VALUES
(1,	'01.01',	'2021-01-26 14:56:35',	'Vstupní návrh databáze, testovací data - rodina, uživatelé, členové rodiny, banka, bankovní účty, karty, pokladny, počáteční stav v pokladně.'),
(2,	'01.02',	'2021-01-27 14:11:53',	'Oprava chyby - přidané pole a cizí klíč family_id do category.'),
(3,	'01.03',	'2021-01-28 17:01:03',	'Oprava chyb:\r\n- přejmenovaný sloupec invoice_id v payment na invoice_head_id.\r\n- Přidané pole counter_account_number a counter_account_bank_code do invoice_head.'),
(5,	'01.04',	'2021-01-28 23:08:48',	'Přidané výchozí hodnoty pro nepovinné varchary.'),
(6,	'01.05',	'2021-01-29 12:20:40',	'Přejmenované tabulky\r\nmember -> consumer\r\norder -> payment_channel\r\n\r\ninvoice_item: amount - Výdajové faktury jsou záporné.\r\n\r\nNespotřební platby nebudou mít záznam v invoice_head.\r\n\r\nHodně malých změn.\r\n');

DROP TABLE IF EXISTS `family`;
CREATE TABLE `family` (
  `id` int NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

INSERT INTO `family` (`id`) VALUES
(1);

DROP TABLE IF EXISTS `invoice_head`;
CREATE TABLE `invoice_head` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `card_id` int DEFAULT NULL,
  `cash_account_balance_id` int DEFAULT NULL,
  `d_issue` date NOT NULL,
  `counter_account_number` varchar(17) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL DEFAULT '',
  `counter_account_bank_code` varchar(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL DEFAULT '',
  `var_symbol` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL DEFAULT '',
  `is_paidbybank` tinyint NOT NULL DEFAULT '0',
  `is_paidbycard` tinyint NOT NULL DEFAULT '0',
  `is_paidbycash` tinyint NOT NULL DEFAULT '0',
  `is_cash_account_balance` tinyint NOT NULL DEFAULT '0',
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
  `amount` decimal(10,2) NOT NULL,
  `description` varchar(35) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `invoice_head_id` (`invoice_head_id`),
  KEY `category_id` (`category_id`),
  KEY `member_id` (`consumer_id`),
  CONSTRAINT `invoice_item_ibfk_1` FOREIGN KEY (`invoice_head_id`) REFERENCES `invoice_head` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `invoice_item_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `category` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `invoice_item_ibfk_3` FOREIGN KEY (`consumer_id`) REFERENCES `consumer` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;


DROP TABLE IF EXISTS `payment`;
CREATE TABLE `payment` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bank_account_id` int DEFAULT NULL,
  `ba_import_id` int DEFAULT NULL,
  `cash_account_id` int DEFAULT NULL,
  `invoice_head_id` int DEFAULT NULL,
  `card_id` int DEFAULT NULL,
  `payment_channel_id` int DEFAULT NULL,
  `d_payment` date NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `bank_operation_id` int DEFAULT NULL,
  `counter_account_number` varchar(17) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL DEFAULT '',
  `counter_account_bank_code` varchar(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL DEFAULT '',
  `counter_account_name` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL DEFAULT '',
  `var_symbol` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL DEFAULT '',
  `message_recipient` varchar(35) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL DEFAULT '',
  `message_payer` varchar(35) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL DEFAULT '',
  `description` varchar(35) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL DEFAULT '',
  `is_card_bankpayment` tinyint NOT NULL DEFAULT '0',
  `is_bankfee_bankpayment` tinyint NOT NULL DEFAULT '0',
  `is_identified` tinyint NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `bank_account_id` (`bank_account_id`),
  KEY `cash_account_id` (`cash_account_id`),
  KEY `invoice_id` (`invoice_head_id`),
  KEY `ba_import_id` (`ba_import_id`),
  KEY `card_id` (`card_id`),
  KEY `payment_channel_id` (`payment_channel_id`),
  CONSTRAINT `payment_ibfk_1` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_account` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `payment_ibfk_2` FOREIGN KEY (`cash_account_id`) REFERENCES `cash_account` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `payment_ibfk_4` FOREIGN KEY (`ba_import_id`) REFERENCES `ba_import` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `payment_ibfk_5` FOREIGN KEY (`card_id`) REFERENCES `card` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `payment_ibfk_6` FOREIGN KEY (`invoice_head_id`) REFERENCES `invoice_head` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `payment_ibfk_7` FOREIGN KEY (`payment_channel_id`) REFERENCES `payment_channel` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;


DROP TABLE IF EXISTS `payment_channel`;
CREATE TABLE `payment_channel` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `bank_account_id` int NOT NULL,
  `category_id` int NOT NULL,
  `var_symbol` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL,
  `counter_account_number` varchar(17) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL,
  `counter_account_bank_code` varchar(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL,
  `description` varchar(35) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL,
  `is_active` tinyint NOT NULL DEFAULT '1',
  `d_create` date NOT NULL,
  `d_deactivate` date DEFAULT NULL,
  `is_consumption_type` tinyint NOT NULL DEFAULT '1',
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
  `email` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL,
  `password` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL,
  `is_active` tinyint NOT NULL DEFAULT '1',
  `d_create` date NOT NULL,
  `d_deactivate` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `family_id` (`family_id`),
  CONSTRAINT `user_ibfk_1` FOREIGN KEY (`family_id`) REFERENCES `family` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

INSERT INTO `user` (`id`, `family_id`, `name`, `surname`, `email`, `password`, `is_active`, `d_create`, `d_deactivate`) VALUES
(1,	1,	'Karel',	'Jouda',	'karel.jouda@gmail.com',	'password',	1,	'0000-00-00',	NULL),
(2,	1,	'Bára',	'Joudová',	'bara.joudova@gmail.com',	'password',	1,	'0000-00-00',	NULL);

-- 2021-01-29 14:23:08
