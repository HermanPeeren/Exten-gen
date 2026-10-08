<?php

/**
 * Prepare the development site for the frontend specs: step 5.7.
 *
 *   php tools/seed-site-user.php            # onto ./joomla
 *
 * Three things, each of which the frontend needs and none of which the
 * administrator does:
 *
 * - a visitor who is not an administrator: `extengen-visitor`, Registered only,
 *   with a password made up here on every run;
 * - permission for Registered users to make projects and edit their own,
 *   which a SaaS site would grant the same way, in the component's options;
 * - a "My projects" menu item, so the frontend has a way in.
 *
 * **The credentials go to a git-ignored fixture**, `tests/cypress/fixtures/site-user.json`,
 * not to `cypress.env.json`, which is Herman's and holds the administrator's
 * login. They are a test visitor's on a local site, made up fresh each run.
 */

declare(strict_types=1);

$root = \dirname(__DIR__);
$site = $argv[1] ?? $root . '/joomla';
$cli  = $site . '/cli/joomla.php';

if (!is_file($cli)) {
    fwrite(STDERR, "No Joomla CLI at {$cli}.\n");
    exit(1);
}

\define('_JEXEC', 1);

require_once $site . '/configuration.php';

$config   = new JConfig();
$db       = new mysqli($config->host, $config->user, $config->password, $config->db);
$prefix   = $config->dbprefix;
$username = 'extengen-visitor';
// Letters and digits only. On Windows `escapeshellarg()` turns `!`, `%` and
// `"` into spaces, so a password containing one is stored as something other
// than what the fixture records - which is how the first version of this tool
// made a visitor nobody could log in as.
$password = 'V' . bin2hex(random_bytes(12)) . 'x9';

$run = static function (string $arguments) use ($cli): int {
    exec('php ' . escapeshellarg($cli) . ' ' . $arguments . ' -n 2>&1', $output, $status);

    return $status;
};

$exists = $db->prepare("SELECT id FROM `{$prefix}users` WHERE username = ?");
$exists->bind_param('s', $username);
$exists->execute();

$found = $exists->get_result()->fetch_assoc();

$status = $found === null
    ? $run(
        '--username=' . escapeshellarg($username) . ' --name=' . escapeshellarg('Exten-gen visitor')
        . ' --password=' . escapeshellarg($password) . ' --email=' . escapeshellarg('visitor@extengen.test')
        . ' --usergroup=Registered user:add'
    )
    : $run('--username=' . escapeshellarg($username) . ' --password=' . escapeshellarg($password) . ' user:reset-password');

if ($status !== 0) {
    fwrite(STDERR, "Could not create or reset {$username}.\n");
    exit(1);
}

// Registered (2) may create projects and edit their own.
$asset = $db->query("SELECT id, rules FROM `{$prefix}assets` WHERE name = 'com_extengen'")->fetch_assoc();

if ($asset === null) {
    fwrite(STDERR, "com_extengen has no asset row; is it installed?\n");
    exit(1);
}

$rules = json_decode((string) $asset['rules'], true) ?: [];

foreach (['core.create', 'core.edit.own'] as $action) {
    $rules[$action]      = \is_array($rules[$action] ?? null) ? $rules[$action] : [];
    $rules[$action]['2'] = 1;
}

$json = json_encode($rules);
$id   = (int) $asset['id'];
$save = $db->prepare("UPDATE `{$prefix}assets` SET rules = ? WHERE id = ?");
$save->bind_param('si', $json, $id);
$save->execute();

file_put_contents(
    $root . '/tests/cypress/fixtures/site-user.json',
    json_encode(['username' => $username, 'password' => $password], \JSON_PRETTY_PRINT) . "\n"
);

passthru('php ' . escapeshellarg(__DIR__ . '/seed-menu-item.php') . ' com_extengen projects ' . escapeshellarg('My projects') . ' ' . escapeshellarg($site));

echo "{$username} is ready, and Registered users may create and edit their own projects.\n";
