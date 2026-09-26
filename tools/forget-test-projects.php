<?php

/**
 * Remove the projects the browser specs create, from the development site.
 *
 *   php tools/forget-test-projects.php
 *
 * The specs in `metalanguages.cy.js` make a project through the screens - that
 * is the point of them: a project written in an imported language has to be
 * creatable, openable and savable by a component that has never heard of that
 * language. What they never did is take it away again, so every run left
 * another row, and the site had reached 64 of them against two real ones.
 *
 * That is not only untidy. The same file already carries a workaround for it -
 * it captures the id of the project it just made, because "the row that says
 * WrittenInTestlang" would otherwise find one from a previous run, possibly one
 * written before the thing being tested existed. A suite whose correctness
 * depends on which leftovers are lying about is a suite that will eventually
 * pass for the wrong reason.
 *
 * So the specs call this first, and it does two jobs at once: it clears the
 * backlog, and it makes each run start from a site with none of these on it.
 *
 * **Exact names, never a pattern.** The two below are typed by the specs as
 * literals - `cy.get('#jform_name').type('WrittenInTestlang')` and its derived
 * counterpart - and anything a person might have named a real project is not
 * one of them. A `LIKE` here would eventually delete somebody's work.
 */

declare(strict_types=1);

$root = \dirname(__DIR__) . '/joomla';

if (!is_file($root . '/configuration.php')) {
    fwrite(STDERR, "No site at {$root}: this is for the development install.\n");
    exit(1);
}

\defined('_JEXEC') || \define('_JEXEC', 1);

require $root . '/configuration.php';

/**
 * The names the browser specs give the projects they create.
 *
 * Kept in step with `tests/cypress/e2e/metalanguages.cy.js` by hand, which is
 * safe in the direction that matters: a name added there and not here means a
 * row is left behind, which is what this was before. A name here that nothing
 * creates deletes nothing.
 */
const SPEC_PROJECT_NAMES = [
    'WrittenInTestlang',
    'WrittenInDerivedER',
];

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
    'DELETE FROM ' . $config->dbprefix . 'extengen_projects WHERE name = ?'
);

$removed = 0;

foreach (SPEC_PROJECT_NAMES as $name) {
    $statement->execute([$name]);

    $count = $statement->rowCount();

    if ($count > 0) {
        printf("forgot %d project(s) named %s\n", $count, $name);
    }

    $removed += $count;
}

printf("%d row(s) removed\n", $removed);
