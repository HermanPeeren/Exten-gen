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
    generator_templates/Joomla4/            Twig templates for the generated extension
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

## Two manifests

There are two, and they have to agree:

- `src/extengen.xml` is what the **installer** reads. It carries `<scriptfile>`,
  `<media>` and the `<administration><files>` inventory.
- `src/administrator/components/com_extengen/extengen.xml` is the copy that ends
  up **on the site**, listed in that inventory.

Two copies of a version number are two places for it to be wrong, so `LayoutTest`
asserts they match. Step 1.6 generates the second from the first and removes the
problem rather than policing it.

Known gaps in the manifest today, both for 1.6: `generator_templates/` is not in
the `<files>` inventory, so a real install ships a generator with no templates;
and the shared library is not referenced at all.

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
src/administrator/components/com_extengen/generator_templates/Joomla4/component/
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

## How generation works

```
src/Generator/
  Model/Project.php              one project, as stored
  Target/Joomla4Target.php       which generators run, in what order
  Template/LegacyTwigRenderer.php  the templates, with the settings they were written under
  Generator.php                  what every generator shares
  Joomla4/*.php                  seven generators, one concern each
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

**`LegacyTwigRenderer` is temporary.** The shared library's renderer turns
`strict_variables` on; these templates have never run that way and would not
survive it — nineteen read `company_namespace` while one reads
`companyNamepace`, and each generator hands a different set of variables to
templates that share a directory. Turning it on would throw where today an empty
string is rendered, which is a change to the output. Step 1.8 sweeps the
templates and deletes this class.

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

### A model that cannot be pinned

`tests/Fixtures/known-breakage/conference.json` has a details page in its
front-end section, and generation throws: `SiteMVC` renders
`tmpl/details/edit.php.twig`, which exists for the administrator and not for the
site. A golden file cannot record a generator that produces nothing, so
`KnownBreakageTest` records it instead. Fixing it turns that test red, which is
the reminder to capture the newly working output and delete it. Step 1.5.

## Building

```
php build/build.php     # -> build/com_extengen-<version>.zip
```

The version comes from `src/extengen.xml`. The build copies `src/` and leaves out
what is a product rather than a source: `generated/`, `compilation_cache/` and
the `node_modules/` tree that exists for one uuid helper.

It currently produces exactly what the manifest describes, which is less than the
component needs — see the manifest gaps above. Step 1.6 fixes both together,
because a build script that ships files the manifest does not list would install
nothing.

## The site this is developed against

`localhost/joomla5` runs the component through symlinks into the *old* Extengen
working copy, not this one. Those symlinks point at
`Extengen/src/com_extengen/administrator/components/com_extengen`, a path that no
longer exists here, so nothing in this repository is live on that site yet.

Repointing them is part of moving development over, and it means **uninstalling
com_extengen on that site would delete the repository it points at**. Remove the
symlinks first, always.
