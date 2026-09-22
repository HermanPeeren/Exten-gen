CREATE TABLE IF NOT EXISTS `#__extengen_projects` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `alias` varchar(400) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT '',
    `checked_out_time` datetime DEFAULT NULL,
    `checked_out` int(10) UNSIGNED DEFAULT '0',
    `params` text COLLATE utf8mb4_unicode_ci,
    `ordering` int(11) DEFAULT '0',
    `language` char(7) COLLATE utf8mb4_unicode_ci DEFAULT '*',
    `publish_down` datetime DEFAULT NULL,
    `publish_up` datetime DEFAULT NULL,
    `published` tinyint(1) DEFAULT '0',
    `state` tinyint(3) DEFAULT '0',
    `catid` int(11) DEFAULT '0',
    `access` int(10) UNSIGNED DEFAULT '0',
    `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    `form_data` text COLLATE utf8mb4_unicode_ci,
    `metalanguage_key` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    `metalanguage_version` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    PRIMARY KEY (`id`),
    KEY `idx_access` (`access`),
    KEY `idx_catid` (`catid`),
    KEY `idx_state` (`published`),
    KEY `idx_language` (`language`),
    KEY `idx_checkout` (`checked_out`)
    ) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every metalanguage this site has imported. A project is written in one of
-- them, and says which by key and version - so two versions of one language
-- can sit side by side and an old project keeps opening with the forms it was
-- written against.
--
-- The files themselves are not in here. They are unpacked under `form_root`,
-- which is what the package's own manifest says it needs, because a subform's
-- `formsource` is resolved against the site root and cannot be package
-- relative. This table is the index over them.
CREATE TABLE IF NOT EXISTS `#__extengen_metalanguages` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `lang_key` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    `version` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    `root` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    `form_root` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    `language_file` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    `manifest` text COLLATE utf8mb4_unicode_ci,
    `imported` datetime DEFAULT NULL,
    `published` tinyint(1) DEFAULT '1',
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_language` (`lang_key`, `version`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
