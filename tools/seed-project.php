<?php

/**
 * Put a golden fixture model into a Joomla site's project table.
 *
 *   php tools/seed-project.php                     # balloonplanning, into ./joomla
 *   php tools/seed-project.php conference          # a different fixture
 *   php tools/seed-project.php conference ../site  # a different site
 *
 * The Cypress specs need a project to open and generate from, and a freshly
 * installed component has none. Building one through the form would be a slow
 * and brittle way to arrive at a model the repository already holds three
 * known-good examples of - and those are the models the golden baseline is
 * built from, so a spec that runs against one is running against output this
 * suite can already describe exactly.
 *
 * Idempotent: seeding the same fixture twice updates the row rather than
 * adding a second one.
 */

declare(strict_types=1);

$root    = \dirname(__DIR__);
$fixture = $argv[1] ?? 'balloonplanning';
$site    = $argv[2] ?? $root . '/joomla';

$modelPath = $root . '/tests/Fixtures/golden/models/' . $fixture . '.json';

if (!is_file($modelPath)) {
    fwrite(STDERR, "No fixture model at {$modelPath}.\n");
    exit(1);
}

$configPath = $site . '/configuration.php';

if (!is_file($configPath)) {
    fwrite(STDERR, "No Joomla configuration at {$configPath}.\n");
    exit(1);
}

// The config file is a class definition guarded by _JEXEC.
\defined('_JEXEC') || \define('_JEXEC', 1);

require_once $configPath;

$config = new JConfig();

$db = new mysqli($config->host, $config->user, $config->password, $config->db);

if ($db->connect_error) {
    fwrite(STDERR, 'Cannot reach the database: ' . $db->connect_error . "\n");
    exit(1);
}

$db->set_charset('utf8mb4');

$json  = (string) file_get_contents($modelPath);
$model = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
$name  = (string) ($model->name ?? $fixture);
$table = $config->dbprefix . 'extengen_projects';

$existing = $db->prepare("SELECT id FROM `{$table}` WHERE name = ?");
$existing->bind_param('s', $name);
$existing->execute();
$id = ($existing->get_result()->fetch_assoc()['id'] ?? null);
$existing->close();

if ($id !== null) {
    $update = $db->prepare("UPDATE `{$table}` SET form_data = ? WHERE id = ?");
    $update->bind_param('si', $json, $id);
    $update->execute();
    $update->close();

    printf("updated %s (id %d)\n", $name, $id);

    exit(0);
}

$insert = $db->prepare(
    "INSERT INTO `{$table}` (name, alias, form_data, published, access, language, ordering, state)"
    . ' VALUES (?, ?, ?, 1, 1, ' . "'*'" . ', 0, 1)'
);

$alias = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $name) ?? $fixture);

$insert->bind_param('sss', $name, $alias, $json);
$insert->execute();

printf("seeded %s (id %d)\n", $name, $db->insert_id);

$insert->close();
