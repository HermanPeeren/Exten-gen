<?php

/**
 * Remove a metalanguage row from the development site.
 *
 *   php tools/forget-metalanguage.php BrokenER 1.0
 *
 * What the Forget button on the metalanguages screen does, without a browser.
 *
 * It exists for the specs that check a *refusal*. "The language was not
 * installed" is only observable on a site where it is not already installed, and
 * a spec that imported a bad package once would then pass or fail depending on
 * whether an earlier run had left the row behind - which is how a green suite
 * stops meaning anything. 4.5 found that out: the import guard was broken, a
 * run imported the package it should have refused, and the next run failed on
 * the leftover rather than on the bug.
 *
 * Only the row. The unpacked files stay where they are, exactly as Forget
 * leaves them, because a site that has the files and not the row is the state
 * this is undoing rather than a new one to invent.
 */

declare(strict_types=1);

$root = \dirname(__DIR__) . '/joomla';

if (!is_file($root . '/configuration.php')) {
    fwrite(STDERR, "No site at {$root}: this is for the development install.\n");
    exit(1);
}

require $root . '/configuration.php';

$arguments = \array_slice($argv, 1);

if (\count($arguments) !== 2) {
    fwrite(STDERR, "Usage: php tools/forget-metalanguage.php <key> <version>\n");
    exit(1);
}

[$key, $version] = $arguments;

$config = new JConfig();

try {
    $database = new PDO(
        'mysql:host=' . $config->host . ';dbname=' . $config->db,
        $config->user,
        $config->password
    );
} catch (PDOException $e) {
    fwrite(STDERR, 'Cannot reach the site database: ' . $e->getMessage() . "\n");
    exit(1);
}

$statement = $database->prepare(
    'DELETE FROM ' . $config->dbprefix . 'extengen_metalanguages WHERE lang_key = ? AND version = ?'
);

$statement->execute([$key, $version]);

printf("forgot %s %s (%d row(s))\n", $key, $version, $statement->rowCount());
