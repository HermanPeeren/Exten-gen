<?php

/**
 * Set one of Exten-gen's component options on the development site.
 *
 *   php tools/set-option.php allow_install 1
 *   php tools/set-option.php allow_install 0
 *
 * For the browser specs, which have to see the result screen with installing
 * allowed and with it not (step 5.6), and should not have to drive the
 * configuration screen to get there - that screen is Joomla's, not this
 * component's. A spec that turns an option on turns it off again.
 */

declare(strict_types=1);

$root = \dirname(__DIR__);
$name = $argv[1] ?? '';
$site = $argv[3] ?? $root . '/joomla';

if ($name === '' || !isset($argv[2]) || !preg_match('/^[a-z_]+$/', $name)) {
    fwrite(STDERR, "Usage: php tools/set-option.php <name> <value> [site]\n");
    exit(1);
}

$configPath = $site . '/configuration.php';

if (!is_file($configPath)) {
    fwrite(STDERR, "No Joomla configuration at {$configPath}.\n");
    exit(1);
}

// The config file is a class definition guarded by _JEXEC.
\define('_JEXEC', 1);

require_once $configPath;

$config = new JConfig();
$db     = new mysqli($config->host, $config->user, $config->password, $config->db);
$table  = $config->dbprefix . 'extensions';

$row = $db->query(
    "SELECT extension_id, params FROM `{$table}` WHERE element = 'com_extengen' AND type = 'component'"
)->fetch_assoc();

if ($row === null) {
    fwrite(STDERR, "com_extengen is not installed on {$site}.\n");
    exit(1);
}

$params        = json_decode((string) $row['params'], true) ?: [];
$params[$name] = is_numeric($argv[2]) ? (int) $argv[2] : $argv[2];
$json          = json_encode($params);
$id            = (int) $row['extension_id'];

$update = $db->prepare("UPDATE `{$table}` SET params = ? WHERE extension_id = ?");
$update->bind_param('si', $json, $id);
$update->execute();

echo "com_extengen: {$name} = {$argv[2]}\n";
