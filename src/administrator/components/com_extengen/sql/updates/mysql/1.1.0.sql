-- Project forms moved to Meta-gen, which has its own table and its own
-- component. A site that ran an earlier Exten-gen still carries this one;
-- export anything in it from there before updating, because this drops it.
DROP TABLE IF EXISTS `#__extengen_projectforms`;

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

-- Which metalanguage a project is written in. Nothing else can say which forms
-- to open it with, and a project made before 3.4 is written in ER1 - which is
-- what the empty default means, and what `MetalanguageCatalogue` reads it as.
ALTER TABLE `#__extengen_projects`
    ADD COLUMN `metalanguage_key` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    ADD COLUMN `metalanguage_version` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '';
