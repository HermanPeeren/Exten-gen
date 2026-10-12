CREATE TABLE IF NOT EXISTS `#__balloonledger_account` (
`id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
`code` varchar(255),
`name` varchar(255),
`nature` varchar(255),
`normal_balance` varchar(255),
`postable` tinyint unsigned NOT NULL DEFAULT 0,
`reconcilable` tinyint unsigned NOT NULL DEFAULT 0,
`active` tinyint unsigned NOT NULL DEFAULT 0,
PRIMARY KEY (`id`),
UNIQUE KEY `idx_code` (`code`)
)  ENGINE=InnoDB DEFAULT COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__balloonledger_journal` (
`id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
`code` varchar(255),
`name` varchar(255),
`journal_type` varchar(255),
`gapless` tinyint unsigned NOT NULL DEFAULT 0,
PRIMARY KEY (`id`),
UNIQUE KEY `idx_code` (`code`)
)  ENGINE=InnoDB DEFAULT COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__balloonledger_party` (
`id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
`name` varchar(255),
`party_type` varchar(255),
`email` varchar(255),
PRIMARY KEY (`id`)
)  ENGINE=InnoDB DEFAULT COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__balloonledger_taxcode` (
`id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
`code` varchar(255),
`name` varchar(255),
`rate` decimal(10,2),
`tax_type` varchar(255),
`valid_from` date,
`valid_until` date,
PRIMARY KEY (`id`),
UNIQUE KEY `idx_code` (`code`)
)  ENGINE=InnoDB DEFAULT COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__balloonledger_journalentry` (
`id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
`number` varchar(255),
`entry_date` date,
`journal_id` bigint(20) UNSIGNED,
`description` text,
`status` varchar(255),
`origin` varchar(255),
`external_id` varchar(255),
PRIMARY KEY (`id`)
)  ENGINE=InnoDB DEFAULT COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__balloonledger_entryline` (
`id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
`journalentry_id` bigint(20) UNSIGNED,
`account_id` bigint(20) UNSIGNED,
`description` varchar(255),
`debit` decimal(10,2),
`credit` decimal(10,2),
`taxcode_id` bigint(20) UNSIGNED,
`party_id` bigint(20) UNSIGNED,
PRIMARY KEY (`id`)
)  ENGINE=InnoDB DEFAULT COLLATE utf8mb4_unicode_ci;
