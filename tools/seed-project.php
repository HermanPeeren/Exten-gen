<?php

/**
 * Put a golden fixture model into a Joomla site's project table.
 *
 *   php tools/seed-project.php                     # balloonplanning, into ./joomla
 *   php tools/seed-project.php conference          # a different fixture
 *   php tools/seed-project.php conference ../site  # a different site
 *   php tools/seed-project.php conference --saved  # in the spelling the screens write
 *   php tools/seed-project.php conference --saved --owner=extengen-visitor --name=VisitorConference
 *                                                  # a frontend user's own project: step 5.7
 *
 * That last flag matters more than it reads. The fixtures were all written
 * before 3.5, when ER1 became a generated language and its forms started
 * spelling a field's kind with the concept names - so a site seeded from them
 * holds no project in the shape its own screens produce, and the browser gate
 * could not see a defect that only appears in that shape. It did not see one,
 * for months. `--saved` converts the fixture on the way in.
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

$root = \dirname(__DIR__);

// Options may stand anywhere; everything else is positional.
$args    = array_values(array_filter(\array_slice($argv, 1), static fn (string $a): bool => !str_starts_with($a, '--')));
$saved   = \in_array('--saved', $argv, true);
$options = [];

foreach (\array_slice($argv, 1) as $argument) {
    if (preg_match('/^--(owner|name)=(.+)$/', $argument, $match)) {
        $options[$match[1]] = $match[2];
    }
}

$fixture = $args[0] ?? 'balloonplanning';
$site    = $args[1] ?? $root . '/joomla';

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

$json = (string) file_get_contents($modelPath);

if ($saved) {
    require_once $root . '/vendor/autoload.php';

    // The one rule, in the one place that holds it. A second copy here is
    // exactly the mistake this flag exists to catch.
    $json = (string) json_encode(
        Yepr\Component\Extengen\Tests\Support\FormSpelling::asGeneratedFormsWriteIt(
            json_decode($json, true, 512, JSON_THROW_ON_ERROR)
        ),
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
}

$model = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
$name  = (string) ($options['name'] ?? $model->name ?? $fixture);
$table = $config->dbprefix . 'extengen_projects';

// Which language the project says it is written in. Nothing else can decide
// what forms open it, and a project with an empty binding renders the Joomla
// half and no model - so the edit screen comes up without the fields and the
// generate screen is a 500.
//
// It was never set here, and nothing had noticed: the component's install
// stamps any project it finds, so on a development site - seeded first,
// component installed after - every project got bound on the way past. In the
// other order, which is the order CI does it in, nothing ever does.
//
// Read from the site rather than written down, because the version moves: the
// fixtures are ER1 models and the site has whichever ER1 the component
// shipped. If the language is not there at all the binding stays empty, which
// is what it was before and is the honest answer on a site that has no ER1.
$binding = $db->query(
    "SELECT lang_key, version FROM `{$config->dbprefix}extengen_metalanguages`"
    . " WHERE lang_key = 'ER1' ORDER BY version DESC LIMIT 1"
)->fetch_assoc() ?: ['lang_key' => '', 'version' => ''];

// Whose project it is: step 5.7. Nobody's unless asked, which is what every
// project made before the frontend existed is.
$owner = 0;

if (isset($options['owner'])) {
    $lookup = $db->prepare("SELECT id FROM `{$config->dbprefix}users` WHERE username = ?");
    $lookup->bind_param('s', $options['owner']);
    $lookup->execute();
    $owner = (int) ($lookup->get_result()->fetch_assoc()['id'] ?? 0);
    $lookup->close();

    if ($owner === 0) {
        fwrite(STDERR, "No user {$options['owner']} on this site. Run tools/seed-site-user.php first.\n");
        exit(1);
    }
}

$existing = $db->prepare("SELECT id FROM `{$table}` WHERE name = ?");
$existing->bind_param('s', $name);
$existing->execute();
$id = ($existing->get_result()->fetch_assoc()['id'] ?? null);
$existing->close();

if ($id !== null) {
    $update = $db->prepare(
        "UPDATE `{$table}` SET form_data = ?, metalanguage_key = ?, metalanguage_version = ?, created_by = ? WHERE id = ?"
    );
    $update->bind_param('sssii', $json, $binding['lang_key'], $binding['version'], $owner, $id);
    $update->execute();
    $update->close();

    printf("updated %s (id %d)\n", $name, $id);

    exit(0);
}

$insert = $db->prepare(
    "INSERT INTO `{$table}`"
    . ' (name, alias, form_data, metalanguage_key, metalanguage_version,'
    . ' published, access, language, ordering, state, created_by)'
    . ' VALUES (?, ?, ?, ?, ?, 1, 1, ' . "'*'" . ', 0, 1, ?)'
);

$alias = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $name) ?? $fixture);

$insert->bind_param('sssssi', $name, $alias, $json, $binding['lang_key'], $binding['version'], $owner);
$insert->execute();

printf("seeded %s (id %d)\n", $name, $db->insert_id);

$insert->close();
