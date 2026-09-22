# Developing Exten-gen

How this repository is put together and how to work on it. What Exten-gen is
*for* is in the [README](../README.md); where it is going, step by step, is in
[rework-plan.md](rework-plan.md).

## Layout

`src/` is the root of the installable package, so every file sits at the path it
will occupy on a Joomla site. The manifest's `folder=` attributes then name real
directories rather than translating between two layouts, and a part that does not
exist yet — site code, an API application — is a folder in the obvious place
rather than a decision.

```
src/
  extengen.xml                              the manifest the installer reads
  script.php                                install script: minimum PHP and Joomla checks
  administrator/components/com_extengen/
    extengen.xml                            a second copy, installed with the component
    forms/                                  ER1, as Joomla form XML
    generator_templates/Joomla6/            Twig templates for the generated extension
    src/                                    the component's PHP
    tmpl/ language/ services/ sql/
  media/com_extengen/js/
  libraries/yepr/                           Twig, until the shared library replaces it
tests/Unit/                                 the suite
build/build.php                             assembles the installable zip
docs/                                       this, and the plan
```

This replaced `src/com_extengen/administrator/...`, where the package root was a
directory named after the component and every path had to be read twice.
`LayoutTest` checks it has not crept back.

## One manifest

`src/extengen.xml` is the only one. There used to be a second copy inside the
component folder, listed in the first's own `<files>`, kept in step by hand — and
it was never needed: `Installer::copyManifest()` puts the manifest into the
component folder during installation, which is why a core component like
com_content ships exactly one.

`PackageTest` reads the manifest rather than a list written beside it, and checks
both directions: everything it claims exists, and every folder that exists is
claimed. The second is the one that bit. `generator_templates` was not listed, so
a real install shipped a generator with no templates — invisible in development,
where the component is a symlink to the working copy and every file is present
whatever the manifest says.

## The shared library

The generation engine lives in
[generator-core](https://github.com/HermanPeeren/generator-core) under the
`Yepr\Gen` namespace, shared with Gen-gen, Meta-gen and Plug-gen. It is a
composer dependency here, and on a Joomla site it is an installed library —
`lib_yepr_gen` — that every extension in the family uses one copy of.

Composer resolves it straight from its repository, since it is not on Packagist:

```json
"repositories": [{ "type": "vcs", "url": "https://github.com/HermanPeeren/generator-core" }]
```

`src/libraries/yepr/` is the older arrangement — a bare `composer.json` asking
for Twig, loaded by a `require_once` in the generator. It stays until step 1.4
moves generation onto the shared core, and then goes.

## The model layer

A stored project is a type, not a decoded stdClass passed around by hand:

```
src/Generator/Model/Project.php           one project, as the component stores it
src/Generator/Model/ProjectValidator.php  what it must contain before generating
src/Repository/ProjectRepository.php      the one place that knows where it lives
```

There were **thirteen** copies of "load a project": the same five-line query and
a `json_decode`, in five field classes and four MVC models — and two of the
thirteen had drifted to a different method name for it, which is how a
duplicated fragment announces that nobody can see all its copies at once.

The cost was never the typing. It was that a question like *what format is this
model stored in* had nowhere to be asked.

`Project::modelVersion()` answers it now. Models saved before this carry no
version and read as `1.0`, which is what they are rather than a guess;
`ProjectModel::save()` stamps it from here on, and `ProjectValidator` refuses a
version it has never heard of rather than reading it hopefully.

`Project::raw()` is transitional. The generators still walk the decoded object
themselves, and making them consume the type is step 1.4 — doing it here would
have changed generated output in the same commit that introduced the model,
which is the one thing the golden baseline exists to prevent.

`ModelLayerBoundaryTest` keeps the count at one. It does *not* forbid naming the
storage tables: the modal project picker, the associations helper and the
administrator HTML service all query `#__extengen_projects` for a name or an id,
which is an ordinary query and not a second copy of how a model is loaded.

## Quality gates

```
composer install
composer test           # phpunit
composer analyse        # phpstan, level 6
composer cs             # phpcs, PSR-12
composer cs-fix-dry     # php-cs-fixer, dry run with a diff
```

All three run in CI on every push and pull request.

**They are scoped to new code for now.** `phpstan.neon`, `phpcs.xml.dist` and
`.php-cs-fixer.dist.php` all list `tests` and `build`, not `src`. That is
deliberate and temporary: the component's own tree is eighteen thousand lines
written against Joomla 4, and pointing the tools at it today would produce
thousands of findings that say nothing about whether the code is correct, which
is worse than not running them — a gate everybody has learned to ignore is not a
gate.

`src` joins at step 1.11, after the Joomla 6 sweep, together with the Joomla
reference material the analyser needs:

```
composer analyse    # add `scanDirectories: [joomla]` back to phpstan.neon first
```

Unpack a Joomla 6 package into `/joomla` (git-ignored). It is reference material,
not code under analysis: it supplies the base classes the component extends.

### Before pushing

Run the gates against a fresh clone, not the working tree. Git does not track
empty directories, and a working tree that passes says nothing about what a
runner checks out.

### Long paths on Windows

Clone into a short directory. The deepest file here is 169 characters:

```
src/administrator/components/com_extengen/generator_templates/Joomla6/component/
administrator/components/com_componentname/src/Controller/AdminDetailsController.php.twig
```

That is inherent — the generator templates mirror a Joomla component, inside a
repository that mirrors a Joomla installation, so the two layouts nest. Windows
stops at 260 characters unless `core.longpaths` is on, which it is not by
default, so a clone into a directory more than about ninety characters deep fails
part way through with `Filename too long` and leaves a repository that looks
cloned but is missing files.

Either keep the clone shallow in the filesystem, or:

```
git config --global core.longpaths true
```

Linux CI never sees this, so it will not be caught for you. The flattening in 1.1
took fourteen characters off every one of these paths, which helps and does not
solve it.

## Running it

```
composer test           # PHPUnit
composer analyse        # PHPStan level 5, needs /joomla
composer cs             # coding standard
composer install-local  # build the package and install it into ./joomla
npm run cypress         # the browser specs, against that install
```

**`/joomla` is reference material and a test site at once.** Unpack a Joomla 6
package there - it is git-ignored. PHPStan resolves the component's base classes
against it, and `composer install-local` installs the built package into it
through Joomla's own CLI, which needs no login and no browser.

**Cypress needs a login, and it comes from `cypress.env.json`.** Copy
`cypress.env.json.dist`, fill in the two fields; the file is git-ignored.
`tools/seed-project.php` puts a golden fixture model into the site so the specs
have a project to open:

```
php tools/seed-project.php conference
```

**Releasing.** The version lives in `src/extengen.xml` and nowhere else.
Bump it there, run `composer build` to regenerate `updates.xml`, commit both,
and push a tag:

```
git tag v1.1.0 && git push origin v1.1.0
```

That is the whole procedure. `.github/workflows/release.yml` listens for `v*`,
and PhpStorm can create and push a tag from its Git menu, so releasing needs no
terminal. The workflow refuses a tag that disagrees with the manifest, refuses
an `updates.xml` that regenerating would change, runs every gate, builds, checks
what the package contains, and publishes it.

It builds *with* the library. There is no sibling checkout on a runner, so
`build.php` fetches the library release `script.php` insists on - which means
what ships is the artefact that was published and verified, and a release
needing a library nobody released fails on the runner rather than on somebody's
site.

`updates.xml` is the update server: it repeats the element, the version and the
platform beside the URL a site downloads from, and it is generated from the
manifest and `script.php` so it cannot promise a Joomla or a PHP the install
script will refuse. It names a release asset by file name, which is why the
workflow checks it: a stale one either hides the release or offers a download
that 404s, and no test on your own machine can see either.

1.0.0 was tagged and pushed before any of this existed, and nothing happened at
all - no release, no failure, no mail. A tag is only a release procedure once
something is listening for it.

**Driving a whole generated component.** The last spec leaves Exten-gen: it
generates a component, installs it, and visits its front end.

```
php tools/install-generated.php BalloonPlanning
php tools/seed-menu-item.php com_balloonplanning flights Flights
```

A generated component has no Router service, so `index.php?option=...` lands on
the default menu item and `/component/<name>/` is a 404. A menu item is the
supported way in, which is what the generated `tmpl/<view>/default.xml` exists
for.

**What each gate can and cannot see.** Everything above `npm run cypress` reads
source or runs generation with two constants standing in for Joomla. None of it
boots the framework, and for five steps that was enough to miss a component
whose Extension class opened with `defined('JPATH_PLATFORM') or die;` - a
constant Joomla 6 removed, so every request returned 200 with an empty body and
nothing in any log. If a change touches a view, a layout, a service or the
manifest, the browser specs are the only thing that will tell you.

## How a reference field works

```
src/Reference/ReferenceIndex.php     what a stored project offers to point at
src/Reference/ReferenceMarkup.php    the markup, which is the contract with the script
src/Field/ReferenceField.php         the Joomla adapter, four lines of it
media/js/reference-options.js        the rules, as a function over plain data
media/js/admin-extengen-reference.js <extengen-reference>, which reads and writes the DOM
```

A reference in the model is a uuid. The edit view asks the model for
`getReferenceIndex()` and puts it in the page with `addScriptOptions`, once.
`<extengen-reference>` merges that with what the form holds right now - entities
somebody added and has not saved - and fills its `<select>`. Live wins, which is
the whole of "you should not have to save before you can refer to something".

**One description of an object type.** A table in `ReferenceIndex` says both
where a type lives in the stored model and how the browser finds its rows: the
class on the name input, and how that input's element id relates to the hidden
id beside it. The client half is handed over in the same payload. Two
descriptions, one in PHP and one in a hand-written script, is what drifts - and
Meta-gen is meant to generate this table from a concept model, which it could
not do if half of it lived in JavaScript.

**One model here, and the mechanism is not here at all.**
`Yepr\Gen\Core\Reference\ReferenceIndex` in the shared library reads a *table*,
and `src/Reference/Er1.php` is this component's: entities, pages and fields, in
three different repeating groups, with a field carrying which entity it belongs
to so a scoped dropdown can filter before anything is saved.

The mechanism used to be here, carrying two tables — this one and LionCore M3's
— and saying in its own docblock that they were a map rather than a method per
type *because Meta-gen generates this from a concept model*. Meta-gen does now,
and Meta-gen is [its own component](https://github.com/HermanPeeren/Meta-gen).
Three components edit models with reference dropdowns, so what is shared is the
mechanism and what stays is the table.

**The select is a real form control.** The server renders the held value as a
selected option before any script runs. A reference is a uuid nobody can retype,
so a form that posts an empty one because a module failed to load has destroyed
something.

**Testing it.** `referenceOptions()` and `liveEntries()` are functions over
plain objects for one reason: they run under `node --test` with no dependencies
and no browser. They live in generator-core now, with the script they describe,
and `composer test-js` is a gate there rather than here. What is left in the
element is reading and writing the DOM, and `tests/cypress/e2e/reference-fields.cy.js`
covers that against a real Joomla.

**What only the browser could see.** Everything above passed with the meta-model
dropdowns still holding nothing but a raw uuid, because the view never put the
index in the page and the layout never loaded the script. Two lines, in two
files that no unit test reads. The spec caught it on the first run: the
dropdowns contained `['c-entity']` where they should have contained
`['Entity', 'Field']`.

## How custom code works

```
src/CustomCode/SlotCatalogue.php   the closed list of places, and what is in scope at each
src/CustomCode/CustomCode.php      one object's code, as regions a template emits
src/Field/SlotField.php            the dropdown, whose options are the catalogue
forms/customcode*.xml              a repeating group on an entity and on each kind of page
```

A generator that covers everything does not exist. The two usual answers are
both bad: telling people to edit the output means the next run destroys their
work, and letting the generator write anything at all means the model stops
describing the extension.

**Slots are the mechanism.** A slot is a named point in one generated file. The
code lives in the model, so it survives a regeneration by construction rather
than by rescue, it is in the same version history as the rest of the model, and
it can later be replaced by a nested sub-model that generates the same body - at
which point the slot disappears and nothing else moves.

The list is closed on purpose. Somebody reading a model can see every place the
generator was overruled, and code stored against a slot that no longer exists is
reported instead of quietly going nowhere.

**The merger is the safety net.** `ProtectedRegionMerger` lifts the regions out
of the previous run's unpacked tree and puts them into the new files, so an edit
made in the output despite all of the above is not eaten. A region the new
output no longer has is reported by path and id, because its content is then
only in the file on disk.

**One place writes a marker.** Templates emit `{{ slots['table.check']|raw }}`
and never see a body. A template that wrote its own markers could drift from the
pattern the merger matches, and the drift would show up only as somebody's code
failing to come back.

Adding a slot means adding it to `SlotCatalogue` and emitting it from one
template. `SlotContractTest` then checks the two agree, against the generated
output rather than against a list written beside it.

## How generation works

```
src/Generator/
  Model/Project.php              one project, as stored
  Target/Joomla6Target.php       which generators run, in what order
  Generator.php                  what every generator shares
  RuleDrivenGenerator.php        runs its slice of the rule file
  Rules/joomla6.rules.json       the mapping: 27 rules, as data
  Rules/Joomla6Selectors.php     which source elements a rule can be written for
  Rules/Joomla6Derivations.php   the computed bindings, each with a name
  Joomla6/*.php                  seven generators, one concern each
```

A generator contributes files to a `FileCollection` held in memory and never
opens a file. `GenerateModel` runs the shared `Pipeline` over the target and
writes the whole set to disk afterwards, all at once — so a run that fails part
way through leaves nothing behind, where before it left half a component.

**Order is part of the target's definition.** `LanguageFiles` runs last because
every other generator adds language strings while its templates render, so the
set is only complete once they have finished. That generator was lifted out of
`GenerateModel`, where it had been the one piece of generated output produced
outside any generator.

**The renderer is the shared one, with `strict_variables` on.** A mistyped name
is an error rather than an empty string in a generated file. `LegacyTwigRenderer`
existed only to hold that flag off until the templates could survive it, and was
deleted in 1.8.

Three names did not survive, and each was a bug rather than a style:
`pageNamelower` for `pageName|lower`, which gave every generated list table
`id="List"`; `linkPageName`, which `SiteMVC` computed a fallback for and then
assigned only inside the branch that did not need it, so a page with no links
got `addNew('.add')` and `task=.edit`; and `updateServerURL`, for a feature the
model does not have, in a block the manifest comments out — that one is written
`|default('')` now, which renders what it always rendered and says out loud that
it may be absent.

`strict_variables` did not catch the `companyNamepace` typo, and could not:
`AdminEntities` supplied it and `Table.php.twig` read it, both misspelled the
same way, so the two agreed. The flag catches a template asking for a name
nobody supplies, not a name that is simply wrong. Both sides say
`company_namespace` now; the output is identical.

## Rules and emitters

Stage 2.1 separated *what maps to what* from *how it is written out*. Read one
rule and it is a sentence:

```json
{
    "id": "admin.entity.table",
    "for": "entities",
    "when": [{ "operator": "missing", "path": "isvalueobject" }],
    "template": "component/administrator/.../src/Table/Table.php.twig",
    "target": "administrator/components/com_{componentName|lower}/src/Table/{entityName}Table.php",
    "bind": { "entityName": { "derive": "entityNameUcfirst" }, ... }
}
```

For each node a selector yields, when it passes these conditions, render this
template to this target path, binding these variables. All 27 of them are in
`Rules/joomla6.rules.json`, and `RuleEngine` in the shared library walks them.

**Five kinds of binding, and the split between them is the point.** `literal`,
`path` (from the model root) and `node` (from the matched element) are data.
`derive` names a function, and `fragments` names one that yields a list, each
entry rendering a smaller template that is concatenated into the file. What is
a lookup is data; what is a computation stays PHP with a name and a test. There
is deliberately no expression language: a rule set that could compute would be a
program stored as JSON, with no analysis, no debugger and no types.

**Order is meaning.** Later rules overwrite earlier ones at the same path, and
consecutive rules over one selector form a block that runs *node-major* — for
each back-end page, its controller, model, view and layout, rather than every
controller and then every model. Both orders produce the same file set. They do
not produce the same language files, because templates register strings while
they render.

**What stayed code, on purpose: the emitters.** `Forms` builds form XML through
DOM, `LanguageFiles` assembles ini, and `AdminEntities` writes the sql. None of
them renders a template once per node: each assembles one file out of the whole
model, and a junction table cannot be written until every entity has been seen.
There is nothing for a rule to say about them.

Writing the mapping down made two things visible that three hundred lines of
control flow had hidden. `AdminMVC` and `SiteMVC` opened with the comment "same
as AdminMVC, can we combine that?" and nobody could answer it; as rules, the
topology is identical and the real differences are three derivations, each of
which now has a docblock saying how it differs. And `siteIndexEntity` reproduces
a bug: the front-end generator reused the name `$entity` as its filter loop's
variable, so the index model's field lists were built from the *last filter's*
entity. It is preserved rather than fixed, because fixing it in a refactoring
would bury a behaviour change in a diff that is supposed to have none.

**What a rule set is checked for.** Data has no compiler, so `RuleSetTest` is
the substitute: every selector and derivation a rule names is registered, every
template it names exists, every `{placeholder}` in a target path is bound,
nothing escapes the package, every rule is run by exactly one generator, and
nothing is registered that no rule uses. It found a dead derivation the first
time it ran.

**What did not change: the output.** Byte for byte, against all three golden
models. That is the only acceptable result for this step — the rules either say
what the code said or they are wrong.

## Golden files

What the generators produce today, pinned byte for byte:

```
tests/Fixtures/golden/models/balloonplanning.json     the input
tests/Fixtures/golden/expected/balloonplanning/...    the 64 files it must produce
php tools/capture-golden.php                          accept a change, after reading the diff
```

The comparison comes from the shared library's `GoldenTestCase`, so
`GoldenOutputTest` is two methods: where the fixtures are, and how to turn one
into files.

**It is a baseline, not an endorsement.** The approved output was captured with
the current bugs in it, on purpose, so that the port at step 1.4 can be done as a
diff: anything that changes is either an improvement somebody can see, or a
regression that would otherwise have shipped.

The models are real, taken from the development database rather than written for
the occasion. Fidelity was checked rather than assumed: for `balloonplanning` the
harness reproduces, byte for byte, all 64 files the component generated through
its own interface.

`tests/Support/LegacyGeneratorRunner` is scaffolding. It runs today's generators
into a scratch directory and reads the result back, because they write to disk;
at 1.4 they write into a `FileCollection` and it goes. The set and order of
generators, and the language-file loop, are transcribed from
`GenerateModel::generate()` — that class extends Joomla's `AdminModel`, so using
it directly would mean bootstrapping the CMS to test a transformation that turns
out not to need one.

The generated fixtures are excluded from PHPStan, phpcs and php-cs-fixer. They
are generated Joomla code: analysing them says whether the *generator* is right,
which the golden comparison already answers, and reformatting them would break
the very thing they pin.

### Duplicated entries in a model

A repeating group must not name the same thing twice. Every duplicate means the
same file, table or column produced twice, with the second one winning — which
used to be invisible, because the generator opened every file with
`fopen(..., 'w')` and a second write to the same path is just a write.

The rule covers entities, pages, languages, the page references in each section,
and the fields within one entity. Not fields *across* entities: `name` and `id`
appear in almost every table, and a rule that could not tell those apart would
refuse nearly every real model.

Two things make the message usable. A section stores a uuid, so the duplicate is
reported by the page name somebody chose rather than by the reference; and
comparison ignores case — `Flight` and `flight` are one table — while the report
uses the spelling that was typed, because that is what they will search the form
for.

```
the back-end page list names "Tracks" 2 times; each entry has to be distinct
the language list names "en-GB" 2 times; each entry has to be distinct
```

Both of those come from real stored models, kept in `tests/Fixtures/invalid/`
exactly as they were saved, so the rule is tested against the data that
motivated it rather than against something written to pass. The same two models
appear in the golden set with the duplicate removed, which is what made them
valid input again — and the entire difference in generated output was one
duplicated submenu entry in `eventschedule.xml`.

## Building

```
php build/build.php                        -> build/com_extengen-<version>.zip
php build/build.php --library=path/to.zip  bundle a locally built library
php build/build.php --no-library           leave it out, deliberately
```

The version comes from `src/extengen.xml`. The build copies `src/` and leaves out
what is a product rather than a source: `generated/`, `compilation_cache/` and
the `node_modules/` tree that exists for one uuid helper.

**The package carries the shared library.** Joomla has no way for a package
manifest to declare a dependency on another extension, so `lib_yepr_gen` rides
along under `library/` and `src/script.php` installs it when the site has none or
has an older one — the Regular Labs and Akeeba pattern. The check runs on update
as well as install, because a site can be updated to a version of Exten-gen that
needs a newer library.

`script.php` names the library version it needs, and the build reads that number
to decide what to bundle, so the two cannot drift. It prefers a sibling
`generator-core` checkout that has been built, and otherwise fetches the release
— what ships is then the artefact that was released and verified rather than one
assembled on the way past.

The script also refuses Joomla below 6.0 and PHP below 8.3. It used to insist on
Joomla 4.0 while the output targeted 6, so it would have let the component onto a
site it could not run on.

## What generation produces

A run writes two things next to each other:

```
generated/<Name>/com_<name>-<version>.zip    the installable package
generated/<Name>/Joomla6/com_<name>/...      the same files, unpacked
```

The archive is the deliverable — it is the only form in which the output is one
thing that can be handed to Joomla — and its name carries the version, because a
downloads folder full of identically named packages says nothing about which is
which. The tree stays beside it because that is how generated output gets read
here: opened, compared, looked through. Offering the archive as a download from
the component's own interface is the part still missing.

Generating the forms of a modelled language writes the same two things, beside
those:

```
(Generating the forms of a modelled language is Meta-gen's job now, and it
writes its output the same way, in its own repository.)
```

## Metalanguages

A project is written in one, and says which by key and version in two columns on
its own row. An empty binding is ER1 — which is what every project made before
3.4 is written in, and reading it as "no language" would make all of them
unopenable.

`MetalanguageCatalogue` answers *what can a project be written in*: the imported
languages, and ER1 as an entry like any other. Everything above it asks the
catalogue rather than asking whether a language was imported, so when 3.5 turns
ER1 into a generated package the built-in entry goes and nothing else changes.

`project.xml` is two files. `project_chrome.xml` is the half that is a Joomla
item — alias, published, access, catid, ordering, params — and
`project_er1.xml` is ER1's model half, in exactly the position an imported
language's root classifier form occupies. `ProjectModel::getForm()` loads the
chrome and merges one root form onto it, whichever language it belongs to.

Two things that are not obvious and cost a rebuild each:

- **`Form::load()` with `$xpath = '/form'` merges the `<form>` element itself**,
  not its children — so the result is a form nested in a form, which renders as
  a screen with no fields and reports nothing. Pass no xpath.
- **Joomla never re-runs an update file it has already applied.** `#__schemas`
  holds the last version applied per extension, so editing
  `sql/updates/mysql/1.1.0.sql` after a site has run it is invisible on that
  site. Reset that row, or use a new version file.

A new project gets the chrome alone and is modelled after it is saved. The model
half carries required fields, so a form that showed one language's model while
somebody chose another could not be saved at all.

## The site this is developed against

`localhost/joomla5` runs the component through symlinks into the *old* Extengen
working copy, not this one. Those symlinks point at
`Extengen/src/com_extengen/administrator/components/com_extengen`, a path that no
longer exists here, so nothing in this repository is live on that site yet.

Repointing them is part of moving development over, and it means **uninstalling
com_extengen on that site would delete the repository it points at**. Remove the
symlinks first, always.
