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
    forms/                                  the model language, as Joomla form XML
    forms/metaProjectForms/LIonCore_M3/     the LionWeb meta-model
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
src/Repository/ProjectFormRepository.php  the same, for project forms
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

**One description of an object type.** `ReferenceIndex::TYPES` says both where a
type lives in the stored model and how the browser finds its rows: the class on
the name input, and how that input's element id relates to the hidden id beside
it. The client half is handed over in the same payload. Two descriptions, one in
PHP and one in a hand-written script, is what drifts - and in stage 3 Meta-gen
generates this table from a concept model, which it could not do if half of it
lived in JavaScript.

**The select is a real form control.** The server renders the held value as a
selected option before any script runs. A reference is a uuid nobody can retype,
so a form that posts an empty one because a module failed to load has destroyed
something.

**Testing it.** `referenceOptions()` is a function over plain objects for one
reason: `composer test-js` runs it under `node --test` with no dependencies and
no browser. What is left in the element is reading and writing the DOM, and that
waits for the Cypress spec in 1.11.

## How generation works

```
src/Generator/
  Model/Project.php              one project, as stored
  Target/Joomla6Target.php       which generators run, in what order
  Generator.php                  what every generator shares
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

**What did not change: the generators themselves.** They still build strings and
still read the decoded project. Separating the transformation from the
templating — the intermediate model the plan aims at — is a change to how they
are written, and doing it in the step that moved the I/O would have made the diff
unreadable against the baseline. What this step bought is that it is now
possible: the seam is a `FileCollection`, not a filesystem.

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

## The site this is developed against

`localhost/joomla5` runs the component through symlinks into the *old* Extengen
working copy, not this one. Those symlinks point at
`Extengen/src/com_extengen/administrator/components/com_extengen`, a path that no
longer exists here, so nothing in this repository is live on that site yet.

Repointing them is part of moving development over, and it means **uninstalling
com_extengen on that site would delete the repository it points at**. Remove the
symlinks first, always.
