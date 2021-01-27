-- Adminer 4.7.7 MySQL dump

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

SET NAMES utf8mb4;

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
  `number_account` varchar(17) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bank_id_number_account` (`bank_id`,`number_account`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `bank_account_ibfk_1` FOREIGN KEY (`bank_id`) REFERENCES `bank` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `bank_account_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

INSERT INTO `bank_account` (`id`, `bank_id`, `user_id`, `number_account`) VALUES
(1,	1,	1,	'2200120120'),
(2,	1,	2,	'4400140140');

DROP TABLE IF EXISTS `card`;
CREATE TABLE `card` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bank_account_id` int NOT NULL,
  `user_id` int NOT NULL,
  `public_card_num` varchar(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL,
  `card_name` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL,
  `is_active` tinyint NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `bank_account_id_public_card_num` (`bank_account_id`,`public_card_num`),
  UNIQUE KEY `name` (`card_name`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `card_ibfk_1` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_account` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `card_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

INSERT INTO `card` (`id`, `bank_account_id`, `user_id`, `public_card_num`, `card_name`, `is_active`) VALUES
(1,	1,	1,	'2200',	'Karel - Mastercard',	1),
(2,	2,	2,	'4400',	'Bára - Mastercard',	1),
(3,	1,	1,	'0022',	'Karel - Druhá karta',	1);

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
(2,	'01.02',	'2021-01-27 14:11:53',	'Oprava chyby - přidané pole a cizí klíč family_id do category.');

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
  `order_id` int DEFAULT NULL,
  `d_issue` date NOT NULL,
  `var_symbol` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL,
  `is_paidbybank` tinyint NOT NULL DEFAULT '0',
  `is_paidbycard` tinyint NOT NULL DEFAULT '0',
  `is_paidbycash` tinyint NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `card_id` (`card_id`),
  KEY `order_id` (`order_id`),
  CONSTRAINT `invoice_head_ibfk_5` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `invoice_head_ibfk_6` FOREIGN KEY (`card_id`) REFERENCES `card` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `invoice_head_ibfk_7` FOREIGN KEY (`order_id`) REFERENCES `order` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

INSERT INTO `invoice_head` (`id`, `user_id`, `card_id`, `order_id`, `d_issue`, `var_symbol`, `is_paidbybank`, `is_paidbycard`, `is_paidbycash`) VALUES
(1,	1,	1,	NULL,	'2021-01-26',	'2200',	0,	1,	0),
(2,	1,	NULL,	NULL,	'2001-01-10',	'',	0,	0,	1);

DROP TABLE IF EXISTS `invoice_item`;
CREATE TABLE `invoice_item` (
  `id` int NOT NULL AUTO_INCREMENT,
  `invoice_head_id` int NOT NULL,
  `category_id` int DEFAULT NULL,
  `member_id` int DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `invoice_head_id` (`invoice_head_id`),
  KEY `category_id` (`category_id`),
  KEY `member_id` (`member_id`),
  CONSTRAINT `invoice_item_ibfk_1` FOREIGN KEY (`invoice_head_id`) REFERENCES `invoice_head` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `invoice_item_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `category` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `invoice_item_ibfk_3` FOREIGN KEY (`member_id`) REFERENCES `member` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

INSERT INTO `invoice_item` (`id`, `invoice_head_id`, `category_id`, `member_id`, `amount`, `description`) VALUES
(1,	1,	1,	NULL,	100.00,	'Kaufland - chleba'),
(2,	2,	1,	1,	200.00,	'Kaufland - kafe pro Karla'),
(3,	2,	1,	2,	400.00,	'Kaufland - čokoláda pro Báru.');

DROP TABLE IF EXISTS `member`;
CREATE TABLE `member` (
  `id` int NOT NULL AUTO_INCREMENT,
  `family_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `name` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL,
  `surname` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `family_id` (`family_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `member_ibfk_1` FOREIGN KEY (`family_id`) REFERENCES `family` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `member_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

INSERT INTO `member` (`id`, `family_id`, `user_id`, `name`, `surname`) VALUES
(1,	1,	1,	'Karel',	'Jouda'),
(2,	1,	2,	'Bára',	'Joudová'),
(3,	1,	NULL,	'Ema',	'Joudová'),
(4,	1,	NULL,	'Kryštof',	'Jouda');

DROP TABLE IF EXISTS `order`;
CREATE TABLE `order` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `bank_account_id` int NOT NULL,
  `category_id` int NOT NULL,
  `is_active` tinyint NOT NULL DEFAULT '1',
  `var_symbol` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL,
  `counter_account_number` varchar(17) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL,
  `counter_account_bank_code` varchar(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL,
  `description` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL,
  `d_create` date NOT NULL,
  `d_deactivate` date NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `bank_account_id` (`bank_account_id`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `order_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `order_ibfk_2` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_account` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `order_ibfk_3` FOREIGN KEY (`category_id`) REFERENCES `category` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;


DROP TABLE IF EXISTS `payment`;
CREATE TABLE `payment` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bank_account_id` int DEFAULT NULL,
  `ba_import_id` int DEFAULT NULL,
  `cash_account_id` int DEFAULT NULL,
  `invoice_id` int DEFAULT NULL,
  `card_id` int DEFAULT NULL,
  `d_payment` date NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `bank_operation_id` int DEFAULT NULL,
  `counter_account_number` varchar(17) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL,
  `counter_account_bank_code` varchar(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL,
  `counter_account_name` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL,
  `var_symbol` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL,
  `message_recipient` varchar(35) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL,
  `message_payer` varchar(35) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL,
  `is_card_payment_type` tinyint NOT NULL DEFAULT '0',
  `is_bankfee_payment_type` tinyint NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `bank_account_id` (`bank_account_id`),
  KEY `cash_account_id` (`cash_account_id`),
  KEY `invoice_id` (`invoice_id`),
  KEY `ba_import_id` (`ba_import_id`),
  KEY `card_id` (`card_id`),
  CONSTRAINT `payment_ibfk_1` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_account` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `payment_ibfk_2` FOREIGN KEY (`cash_account_id`) REFERENCES `cash_account` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `payment_ibfk_3` FOREIGN KEY (`invoice_id`) REFERENCES `invoice_head` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `payment_ibfk_4` FOREIGN KEY (`ba_import_id`) REFERENCES `ba_import` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `payment_ibfk_5` FOREIGN KEY (`card_id`) REFERENCES `card` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;


DROP TABLE IF EXISTS `user`;
CREATE TABLE `user` (
  `id` int NOT NULL AUTO_INCREMENT,
  `family_id` int NOT NULL,
  `name` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL,
  `surname` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL,
  `email` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL,
  `password` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `family_id` (`family_id`),
  CONSTRAINT `user_ibfk_1` FOREIGN KEY (`family_id`) REFERENCES `family` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

INSERT INTO `user` (`id`, `family_id`, `name`, `surname`, `email`, `password`) VALUES
(1,	1,	'Karel',	'Jouda',	'karel.jouda@gmail.com',	'password'),
(2,	1,	'Bára',	'Joudová',	'bara.joudova@gmail.com',	'password');

-- 2021-01-27 13:14:06
