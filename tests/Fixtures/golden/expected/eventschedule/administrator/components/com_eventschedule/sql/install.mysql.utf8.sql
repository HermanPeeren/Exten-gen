CREATE TABLE IF NOT EXISTS `#__eventschedule_dayschedule` (
`id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
`name` varchar(255),
PRIMARY KEY (`id`)
)  ENGINE=InnoDB DEFAULT COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__eventschedule_track` (
`id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
`name` varchar(255),
PRIMARY KEY (`id`)
)  ENGINE=InnoDB DEFAULT COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__eventschedule_speaker` (
`id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
`name` varchar(255),
`biography` text,
PRIMARY KEY (`id`)
)  ENGINE=InnoDB DEFAULT COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__eventschedule_presentation` (
`id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
`presentation_name` varchar(255),
`short_description` varchar(255),
`long_description` varchar(255),
`duration` int NOT NULL DEFAULT 0,
`locators` TEXT,
`presentation_type_id` bigint(20) UNSIGNED,
PRIMARY KEY (`id`)
)  ENGINE=InnoDB DEFAULT COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__eventschedule_presentation_type` (
`id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
`presentation_type_name` text,
`css_class` text,
`background_color` text,
PRIMARY KEY (`id`)
)  ENGINE=InnoDB DEFAULT COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__eventschedule_dayschedule_track` (
`dayschedule_id` bigint(20) UNSIGNED,
`track_id` bigint(20) UNSIGNED,
PRIMARY KEY (`dayschedule_id`, `track_id`)
)  ENGINE=InnoDB DEFAULT COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__eventschedule_presentation_speaker` (
`presentation_id` bigint(20) UNSIGNED,
`speaker_id` bigint(20) UNSIGNED,
PRIMARY KEY (`presentation_id`, `speaker_id`)
)  ENGINE=InnoDB DEFAULT COLLATE utf8mb4_unicode_ci;
