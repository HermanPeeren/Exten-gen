CREATE TABLE IF NOT EXISTS `#__conference_speaker` (
`id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
`name` varchar(255),
`organisation` text,
`nationality` varchar(255),
`address` text,
PRIMARY KEY (`id`)
)  ENGINE=InnoDB DEFAULT COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__conference_talk` (
`id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
`title` varchar(255),
`description` text,
`speaker_id` bigint(20) UNSIGNED,
PRIMARY KEY (`id`)
)  ENGINE=InnoDB DEFAULT COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__conference_room` (
`id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
`room_name` varchar(255),
`position` text,
PRIMARY KEY (`id`)
)  ENGINE=InnoDB DEFAULT COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__conference_program` (
`id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
`title` varchar(255),
`time` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
`talk_id` bigint(20) UNSIGNED,
`room_id` bigint(20) UNSIGNED,
PRIMARY KEY (`id`)
)  ENGINE=InnoDB DEFAULT COLLATE utf8mb4_unicode_ci;
