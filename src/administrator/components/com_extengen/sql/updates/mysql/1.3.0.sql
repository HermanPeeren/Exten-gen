-- Every generator this site has imported from Gen-gen: step 5.4. The built-in
-- ones - one per target - are not in here; they are listed out of the target
-- registry, because they are code and ship with the component.
--
-- The rules are stored whole, as the rule file's JSON. Nothing else from the
-- package is kept: its classes are wiring, and an imported generator runs this
-- component's own classes over the rules, so no PHP from a package is ever
-- loaded. `gen_key` is the package's own identity, and importing the same key
-- again replaces the row.
CREATE TABLE IF NOT EXISTS `#__extengen_generators` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `gen_key` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    `target` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    `metalanguage_key` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    `metalanguage_version` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    `rules` mediumtext COLLATE utf8mb4_unicode_ci,
    `manifest` text COLLATE utf8mb4_unicode_ci,
    `imported` datetime DEFAULT NULL,
    `published` tinyint(1) DEFAULT '1',
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_generator` (`gen_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Who made a project, and when: step 5.7. The frontend shows people their own
-- projects and nobody else's, and that needs an owner. Projects made before
-- 1.3.0 belong to nobody (0), so they show in the administrator only.
ALTER TABLE `#__extengen_projects`
    ADD COLUMN `created` datetime DEFAULT NULL,
    ADD COLUMN `created_by` int(10) UNSIGNED NOT NULL DEFAULT '0',
    ADD KEY `idx_createdby` (`created_by`);
