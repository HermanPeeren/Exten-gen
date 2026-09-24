CREATE TABLE IF NOT EXISTS `#__extengen_project` (
`id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
`name` varchar(255),
`form_data` text,
`metalanguage_key` varchar(255),
`metalanguage_version` varchar(255),
PRIMARY KEY (`id`)
)  ENGINE=InnoDB DEFAULT COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__extengen_metalanguage` (
`id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
`lang_key` varchar(255),
`version` varchar(255),
`name` varchar(255),
`root` varchar(255),
`form_root` varchar(255),
`language_file` varchar(255),
`manifest` text,
`imported` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
PRIMARY KEY (`id`)
)  ENGINE=InnoDB DEFAULT COLLATE utf8mb4_unicode_ci;
