<?php

/**
 * Point a site menu item at a generated component's view.
 *
 *   php tools/seed-menu-item.php com_balloonplanning flights Flights
 *   php tools/seed-menu-item.php com_balloonplanning flights Flights ../site
 *
 * A generated component has no Router service, so `/component/<name>/` is a
 * 404 and `index.php?option=...` lands on the default menu item. The supported
 * way to reach a front-end view is a menu item, which is what the generated
 * `tmpl/<view>/default.xml` makes possible - and what this creates, so the
 * browser specs can check that the whole chain works rather than assuming it.
 *
 * `#__menu` is a nested set, so this goes through Joomla's own Menu table
 * rather than writing lft and rgt by hand. It needs a database and nothing
 * else: no session, no application.
 *
 * Idempotent: running it twice updates the item rather than adding a second.
 *
 * One known limitation, so that nobody hunts it twice: a row written this way
 * makes Joomla's own mod_menu emit a `htmlspecialchars(): Passing null`
 * deprecation, because it reads an `flink` that Joomla computes somewhere this
 * insert does not reach. It is the fixture's notice, not the component's - the
 * site's own Home page shows it too as soon as this item is published, and
 * stops when it is unpublished. Specs therefore assert on what the component
 * put on the page rather than on the absence of every notice.
 */

declare(strict_types=1);

$root      = \dirname(__DIR__);
$option    = $argv[1] ?? null;
$view      = $argv[2] ?? null;
$title     = $argv[3] ?? ucfirst((string) $view);
$site      = $argv[4] ?? $root . '/joomla';

if ($option === null || $view === null) {
    fwrite(STDERR, "Usage: php tools/seed-menu-item.php <option> <view> [title] [site]\n");
    exit(1);
}

$config = null;

\defined('_JEXEC') || \define('_JEXEC', 1);

require_once $site . '/configuration.php';

$config = new JConfig();

$db = new mysqli($config->host, $config->user, $config->password, $config->db);

if ($db->connect_error) {
    fwrite(STDERR, 'Cannot reach the database: ' . $db->connect_error . "
");
    exit(1);
}

$db->set_charset('utf8mb4');

$prefix = $config->dbprefix;
$link   = 'index.php?option=' . $option . '&view=' . $view;
$alias  = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $title) ?? $view);

$menutype = scalar($db, "SELECT menutype FROM `{$prefix}menu_types` LIMIT 1");

if ($menutype === null) {
    fwrite(STDERR, "The site has no menu to put this in.
");
    exit(1);
}

$componentId = scalar(
    $db,
    "SELECT extension_id FROM `{$prefix}extensions` WHERE type = 'component' AND element = ?",
    's',
    $option
);

if ($componentId === null) {
    fwrite(STDERR, "{$option} is not installed on that site.
");
    exit(1);
}

// What Joomla writes for an ordinary menu item. Left at `{}`, mod_menu reads
// keys that are not there and htmlspecialchars() is handed null - a notice on
// every page of the site, which is a poor thing for a test fixture to leave
// behind while a spec is asserting there are none.
$params = '{"menu-anchor_title":"","menu-anchor_css":"","menu_image":"","menu_text":1,"menu_show":1,"page_title":"","show_page_heading":0,"page_heading":"","pageclass_sfx":"","menu-meta_description":"","robots":""}';

$existing = scalar($db, "SELECT id FROM `{$prefix}menu` WHERE link = ? AND menutype = ?", 'ss', $link, $menutype);

if ($existing !== null) {
    $update = $db->prepare("UPDATE `{$prefix}menu` SET title = ?, params = ?, published = 1 WHERE id = ?");
    $update->bind_param('ssi', $title, $params, $existing);
    $update->execute();
    $update->close();

    printf("updated menu item %d: %s -> %s
", $existing, $title, $link);

    exit(0);
}

// #__menu is a nested set. Appending as the last child of the root is the one
// case that needs no shifting of anything else: the root's right edge is the
// largest number in the tree, so the new node takes it and the root moves out
// by two. Anything less simple than this belongs in Joomla's Table class,
// which cannot be used here because its constructor wants an application.
$rootRight = scalar($db, "SELECT rgt FROM `{$prefix}menu` WHERE id = 1");

if ($rootRight === null) {
    fwrite(STDERR, "That site's menu table has no root.
");
    exit(1);
}

$db->begin_transaction();

try {
    $insert = $db->prepare(
        "INSERT INTO `{$prefix}menu`"
        . ' (menutype, title, alias, path, link, type, published, parent_id, level, component_id,'
        . '  access, language, client_id, params, lft, rgt, home, browserNav, checked_out_time, img, note)'
        . " VALUES (?, ?, ?, ?, ?, 'component', 1, 1, 1, ?, 1, '*', 0, ?, ?, ?, 0, 0, NULL, '', '')"
    );

    $lft = $rootRight;
    $rgt = $rootRight + 1;

    $insert->bind_param('sssssisii', $menutype, $title, $alias, $alias, $link, $componentId, $params, $lft, $rgt);
    $insert->execute();

    $id = $db->insert_id;

    $insert->close();

    $db->query("UPDATE `{$prefix}menu` SET rgt = rgt + 2 WHERE id = 1");

    $db->commit();

    printf("created menu item %d: %s -> %s
", $id, $title, $link);
} catch (\Throwable $e) {
    $db->rollback();

    fwrite(STDERR, 'Cannot create the menu item: ' . $e->getMessage() . "
");
    exit(1);
}

/**
 * One value, or null when the query found nothing.
 */
function scalar(mysqli $db, string $sql, string $types = '', mixed ...$values): int|string|null
{
    $statement = $db->prepare($sql);

    if ($types !== '') {
        $statement->bind_param($types, ...$values);
    }

    $statement->execute();

    $row = $statement->get_result()->fetch_row();

    $statement->close();

    return $row === null ? null : $row[0];
}
