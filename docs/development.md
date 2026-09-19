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
