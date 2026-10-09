-- A metalanguage, and a project written in one, no longer have to fit in 64 KB.
--
-- `text` holds 65,535 bytes, which ER1 never came close to: its manifest is a
-- few kilobytes and the projects written in it are smaller still. A language
-- imported from somewhere else is a different size. JCB's, read in from
-- LionWeb through JcbInOut and Meta-gen, has 126 concepts and a 175 KB
-- manifest, and installing it failed with "Data too long for column
-- 'manifest'".
--
-- `form_data` goes with it. A project is a model written in the installed
-- language, so a language this size implies projects this size: a JCB
-- blueprint holds components, views, fields and powers in one row.
--
-- `mediumtext` holds 16 MB. `rules` on the generators table has been that
-- since it was written, for the same reason.

ALTER TABLE `#__extengen_metalanguages`
    MODIFY `manifest` mediumtext COLLATE utf8mb4_unicode_ci;

ALTER TABLE `#__extengen_projects`
    MODIFY `form_data` mediumtext COLLATE utf8mb4_unicode_ci;
