<?php

/**
 * Print the id of a project on the development site, by its exact name.
 *
 *   php tools/project-id.php MyConference
 *
 * For the browser specs, which need an id to ask for a project they must not
 * reach - one that is not in the list they are looking at (step 5.7).
 */

declare(strict_types=1);

$name = $argv[1] ?? '';
$site = $argv[2] ?? \dirname(__DIR__) . '/joomla';

if ($name === '') {
    fwrite(STDERR, "Usage: php tools/project-id.php <name> [site]\n");
    exit(1);
}

\define('_JEXEC', 1);

require_once $site . '/configuration.php';

$config = new JConfig();
$db     = new mysqli($config->host, $config->user, $config->password, $config->db);
$query  = $db->prepare("SELECT id FROM `{$config->dbprefix}extengen_projects` WHERE name = ?");

$query->bind_param('s', $name);
$query->execute();

$id = (int) ($query->get_result()->fetch_row()[0] ?? 0);

if ($id === 0) {
    fwrite(STDERR, "No project called {$name}.\n");
    exit(1);
}

echo $id;
