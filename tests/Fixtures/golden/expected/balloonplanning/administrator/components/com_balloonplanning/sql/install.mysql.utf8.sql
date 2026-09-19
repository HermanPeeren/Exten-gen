CREATE TABLE IF NOT EXISTS `#__balloonplanning_balloon` (
`id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
`call-sign` varchar(255),
`capacity` int NOT NULL DEFAULT 0,
`license` varchar(255),
`planning_color` int NOT NULL DEFAULT 0,
PRIMARY KEY (`id`)
)  ENGINE=InnoDB DEFAULT COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__balloonplanning_plannedflight` (
`id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
`balloon_id` bigint(20) UNSIGNED,
`date` date,
`morning_evening` varchar(255),
`flight_number` int NOT NULL DEFAULT 0,
`departureplace_id` bigint(20) UNSIGNED,
PRIMARY KEY (`id`)
)  ENGINE=InnoDB DEFAULT COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__balloonplanning_passenger` (
`id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
`passenger_name` varchar(255),
`address` varchar(255),
`postal_code` varchar(255),
`place` varchar(255),
`telephone` varchar(255),
`telephone2` varchar(255),
`email` varchar(255),
PRIMARY KEY (`id`)
)  ENGINE=InnoDB DEFAULT COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__balloonplanning_departureplace` (
`id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
`place_name` varchar(255),
`exact_address` varchar(255),
`route` text,
`geo-location` varchar(255),
PRIMARY KEY (`id`)
)  ENGINE=InnoDB DEFAULT COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__balloonplanning_ticket` (
`id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
`number_of_passengers` int NOT NULL DEFAULT 0,
`ticket_number` int NOT NULL DEFAULT 0,
`passenger_id` bigint(20) UNSIGNED,
`ticket_type` varchar(255),
`number_of_kids` int NOT NULL DEFAULT 0,
`total_weight` int NOT NULL DEFAULT 0,
PRIMARY KEY (`id`)
)  ENGINE=InnoDB DEFAULT COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__balloonplanning_reservation` (
`id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
`reservation_number` int NOT NULL DEFAULT 0,
`actual` tinyint unsigned NOT NULL DEFAULT 0,
`ticket_id` bigint(20) UNSIGNED,
`plannedflight_id` bigint(20) UNSIGNED,
PRIMARY KEY (`id`)
)  ENGINE=InnoDB DEFAULT COLLATE utf8mb4_unicode_ci;
