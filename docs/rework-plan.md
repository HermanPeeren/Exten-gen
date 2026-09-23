# Rework plan: Exten-gen, Gen-gen, Meta-gen

Drawn up 2026-09-18, from an analysis of Extengen at commit `a1acd77`.

Steps are numbered `stage.step` so that a single step can be referenced as a unit of
work, for example "1.4". Each step states what it produces and when it is done.

## Settled constraints

Three decisions shape everything below.

**What the generator projects share lives in one installable Joomla library.** Not one
copy per extension: `lib_yepr_gen`, under the `Yepr\Gen` namespace, carrying the engine
and the third-party packages it needs. Every extension checks for it on install and
installs it when missing, as the Regular Labs and Akeeba libraries do. The same source
tree also publishes a composer package, which keeps the engine usable outside Joomla -
Drupal, Symfony, anything PHP.

**Exten-gen v1 is deliberately minimal.** Joomla's built-in features — categories, tags,
versioning, workflow, custom fields, full ACL, routing, action logs, finder — are out of
v1. They land after Stage 2, when adding a feature is cheap. Some may need no more than a
toggle; others will need more of the model.

**Joomla 6 is the only target for now**, and targets are pluggable from the start. Later
targets need not be Joomla versions at all.

## The shape

### Repositories

| Repo | Contains | Ships as |
|---|---|---|
| `generator-core` *(new)* | framework-agnostic generation engine, at `Yepr\Gen\Core` | `yepr/generator-core` (composer) **+** `lib_yepr_gen` (Joomla library) |
| `Exten-gen` *(new, from Extengen)* | `com_extengen` — models extensions, generates them | component package |
| `Gen-gen` *(new, stage 2)* | models generators | component package |
| `Meta-gen` *(new, stage 3)* | `com_metagen` — models metalanguages, generates their forms | component package |
| `plug-gen` *(exists)* | plugin types | adopts the core at 4.1 |

### Layers

```
source model  ──transformation──▶  target structure model  ──emitters──▶  FileCollection ──▶ zip
(ER1: entities,                    (Joomla 6 component:                   (in memory,
 pages, extension)                  files, classes, forms)                  never disk)
```

A target is a structure metamodel plus emitters plus a template set. Nothing above the
emitters knows what a Joomla is.

### Three artefacts, and what binds them

The shape the components have to agree on, settled before stage 3 could continue:

```
Meta-gen ──── metalanguage ────▶ Exten-gen ── runs a generator over a project ──▶ extension
    │         (concept model +        ▲
    │          forms + ref table)     │
    └──── metalanguage ────▶ Gen-gen ─┘
                              generator (rules, for ONE language → ONE target)
```

- **A project** is *written in* a metalanguage.
- **A generator** maps *one metalanguage* to *one target*.
- **Exten-gen** holds many projects, many metalanguages and many generators, and may run
  generator G over project P only when they name the same language.

A **metalanguage package** is what travels, and one payload serves both consumers:

| Part | Who needs it |
|---|---|
| the concept model | Gen-gen, to know what a rule may select |
| generated forms | Exten-gen, to edit a model written in that language |
| the reference table | both, for the dropdowns |
| a language file, keyed to the language | both, so the forms read as words rather than constants |
| a manifest: key, version, root classifier | both, to bind a project or a generator to it |

Meta-gen is the only writer. Carrying the concept model *and* its derived forms means
neither consumer needs Meta-gen installed.

**Three consequences, named here because each is invisible until it blocks something.**
ER1 stops being implicit: Exten-gen's own hand-written forms become the first
metalanguage package, which makes 3.5 a migration rather than only a proof. Selectors
have to become data, because `Joomla6Selectors::entities()` is PHP hard-keyed to ER1 and
a generator written for an arbitrary language needs a path through *that* language - which
reaches generator-core and Gen-gen, not just the two components. And a project must record
which language it is in, while `Vocabulary` gains a source half: it publishes what a rule
may say about a *target* and nothing yet about what it may select from.

### Why this order

The three components are mutually dependent: Exten-gen's forms should come from Meta-gen,
its generators from Gen-gen, and both of those are themselves components that Exten-gen
should generate. The cycle breaks by hand-writing one layer and bootstrapping from it.

The hand-written layer is the generator *core* — the runtime that executes a generator.
That is not the same thing as Gen-gen, which is the modelling tool that produces one.

Gen-gen cannot come first, because a generator is a transformation between two
metamodels and neither endpoint currently exists as a nameable thing. The model language
is implicit, spread across 25 XML form files with its real semantics living in the PHP
that reads them. The target has no representation at all: "a Joomla component" exists
only as the output side-effect of seven hand-written PHP classes. Stage 1 makes both
endpoints explicit, which is what makes Stage 2 possible.

Writing generators by hand in Stage 1 that Stage 2 will later regenerate is not waste.
It is the reference implementation Gen-gen must reproduce, and without it Gen-gen has
nothing to be checked against. MPS was bootstrapped the same way.

---

## Stage 0 — the core

Independent of Extengen; can start immediately.

**0.1 Create the repository and skeleton.** `generator-core`, following plug-gen's setup:
`composer.json` (`yepr/generator-core`, PHP >= 8.3 — Joomla 6's minimum — and no runtime
dependencies), PHPUnit, PHPStan, php-cs-fixer, phpcs, `docs/`, GitHub release workflow.
*Done when* `composer test` and `composer analyse` run green on an empty suite.

**0.2 Extract the engine from plug-gen.** Move and generalise into `Yepr\Gen\Core\`:
`FileCollection`, `ZipWriter`, `Renderer`, emitters (`PhpEmitter`, `XmlEmitter`,
`IniEmitter`), `Pipeline`, `GeneratorInterface`, `ProtectedRegionMerger`. Port
`NoJoomlaDependencyTest` — that rule is what keeps the core reusable.
*Done when* the suite passes with zero Joomla or CMS imports anywhere in `src/`.

**0.3 The text layer.** *Decided: Twig, as a hard dependency.* The core defines a
`RendererInterface` so that no generator is coupled to an engine, ships `TwigRenderer` as
the default and `PhpRenderer` for a consumer that wants no engine. Twig sits in the
composer package's `require` and ships inside the Joomla library, resolved by composer at
build time. Exactly one class imports it, and a test enforces that, so the choice stays a
registration rather than a rewrite.

Measured on PHP 8.3.6 with Twig 3.29, rendering an identical Joomla Table class from a
Twig template and from a native-PHP template (both produced byte-identical output):

| | Twig | native PHP |
|---|---|---|
| cold, empty cache, 1 render | 2.16 ms | 0.36 ms |
| warm, new Environment per render | 0.284 ms | — |
| warm, one Environment reused | 0.166 ms | 0.121 ms |
| template held as a string, cached | 0.172 ms | needs `eval()` |

Performance is not a deciding factor: on a run of a few hundred files the difference is
around ten milliseconds. Twig is consistently a little *slower*, not faster — it compiles
to PHP and then runs it, with a thin runtime layer on top.

Two commonly assumed advantages do not survive checking:

- Twig does **not** avoid output buffering. `Twig\Template::render()` calls `ob_start()`
  and `ob_get_clean()` (`src/Template.php:178-192`); the buffering is merely hidden.
- Twig's default autoescaping is **wrong** for code generation. It HTML-escapes values
  interpolated into PHP source — `$x = 'O&#039;Brien'` — so `autoescape => false` is
  mandatory, and a code-aware escaper has to replace it.

What genuinely favours Twig here:

- **Templates as data.** Any loader — string, array, database — is first class. Native
  PHP templates come from files; holding one in a database means `eval()` or writing a
  temp file. This was the original reason for choosing Twig and it is a sound one.
- **Untrusted templates.** If a generator's templates become editable (which is where
  Gen-gen leads), a native-PHP template is arbitrary code execution. Twig's sandbox is a
  real answer; native PHP has none.
- **No tag collision.** A native-PHP template that generates PHP must escape its own
  opening tag (`<?php echo "<?php\n"; ?>`), because the literal text it wants to emit is
  also its own syntax. Twig has no such problem.

Two Twig footguns to configure around, both demonstrated:

- `strict_variables` is **off** by default, which is the cause of Extengen's current
  silent-empty-variable failures. It must be `true`. Native PHP emits an "Undefined
  variable" diagnostic by default, so as configured today native PHP is the safer of the
  two — and configured correctly Twig is safer still, since it throws rather than warns.
- Twig's in-process compiled-class reuse is keyed on template **source and name only, not
  on environment options**. Two Environments with the same source and name but different
  `autoescape` settings silently share the first one's compilation. Extengen currently
  builds a new Environment per fragment (`Generator::renderTemplateFragment`), which makes
  this latent rather than theoretical, and also costs the ~70% overhead visible in the
  table above.

Whichever engine wins, the escaping problem is the engine's blind spot: values
interpolated into generated PHP, XML or INI need a format-aware escaper, and neither
Twig's `autoescape` nor PHP's `<?= ?>` provides one. Emitters — as in plug-gen's
`PhpEmitter` / `XmlEmitter` / `IniEmitter` — are required either way, and matter more than
the engine choice.

**0.4 Introduce the Target abstraction.** `TargetInterface` names its structure
metamodel, its emitters and its template set. `Pipeline` becomes target-driven instead of
hardcoding one output type.
*Done when* a second, trivial fake target can be registered and run without touching the
pipeline.

**0.5 Golden-file test harness.** The comparison logic from plug-gen's `GoldenOutputTest`,
generalised into a reusable `TestCase`: comparison in both directions (every generated
file has a golden counterpart, and every golden file is still generated), fixture
auto-discovery, CRLF normalisation, plus a `generate-fixture` command to accept a
reviewed change.
*Done when* Exten-gen and Gen-gen obtain golden tests by extending one class.

**0.6 The shared Joomla library.** One installable library, `lib_yepr_gen`, holding
everything the generator extensions share: the engine and the third-party packages it
needs. Exten-gen, Meta-gen, Gen-gen, Plug-gen and whatever follows take their shared code
from that one copy rather than each carrying its own. The namespace prefix is `Yepr\Gen`,
and the engine is its first occupant at `Yepr\Gen\Core`.

*Our own* classes need no autoloading work. Joomla registers a library's namespace
automatically when the manifest declares one - verified in the Joomla 5 source:

- `libraries/namespacemap.php` builds `administrator/cache/autoload_psr4.php` from
  `getNamespaces('component' | 'module' | 'template' | 'plugin' | **'library'**)`.
- For libraries, `getExtensions()` scans `JPATH_MANIFESTS/libraries/*.xml` and reads the
  `<namespace path="...">` element, mapping it to `JPATH_LIBRARIES . '/<name>/<path>'`.
- The `extension - namespacemap` plugin rebuilds that file on
  `onExtensionAfterInstall`, `onExtensionAfterUninstall` and `onExtensionAfterUpdate`.

So `<namespace path="src">Yepr\Gen</namespace>` is the whole job for `Yepr\Gen\*`. JCB's
`PowerloaderHelper` and Extengen's
`require_once JPATH_LIBRARIES . '/yepr/vendor/autoload.php'` are both working around
something core already does, and neither pattern is carried forward.

*Third-party* packages are a separate matter, because a library manifest registers one
namespace and Twig's is not ours. They are resolved by composer at **build** time and
ship inside the library as `vendor/`, which is ordinary Joomla practice - the installer
not having composer is a packaging detail, not an argument against a composer dependency.
Registering that `vendor/autoload.php` is the library's own business, done lazily from
the one class that needs it, so no consumer ever writes a `require_once`. Regular Labs
does exactly this: `libraries/regularlabs/src/Image.php` requires the bundled autoloader
because it uses intervention/image, and nothing outside the library knows.

That is also the rule the engine-pluggability test enforces (see 0.3): `Twig\` may be
imported by the Twig renderer and by nothing else.

**Presence checking.** Each extension verifies the library on install and installs it when
missing or too old, from a copy carried inside its own package. This is the Regular Labs
and Akeeba pattern, and on this machine `pkg_modals.xml` shows the shape: the package
manifest does not declare the library at all; its `script.install.php` does the work.

No version negotiation is needed. The library is used only within this family of
extensions and all of it is developed in one place, so the check is simply: present and
recent enough, or install the copy carried in the package.

*Done when* the zip installs on a clean Joomla 6; a test component resolves `Yepr\Gen\*`
with no `require_once` and no composer at runtime; a test component resolves a vendored
third-party class; and installing that component on a site without the library brings the
library with it.

**0.7 Release 0.1.0.** Tag, build both artefacts, publish the update server.

---

## Stage 1 — Exten-gen

The large stage. Order matters: behaviour is captured before it is changed.

**1.1 Create the repository.** Mirror push from Extengen, preserving full history.
Restructure to a flat `src/` mirroring the site layout —
`src/administrator/components/com_extengen/`, not `src/com_extengen/administrator/...`.
Composer, CI, docs, `build/build.php`.
*Decision required here:* whether to filter `testForm.json` out of the history. This is
the only cheap moment to do it.

**1.2 Capture the current output as golden files.** Fixture models from the JSON dumps in
`xdiv/`; expected output from `generated/BalloonPlanning/` and `generated/Conference/`.
No code changes in this step.
*Done when* the suite regenerates today's output byte-identically, current bugs included.
That is the point: a baseline, not an endorsement.

**1.3 A real model object.** `ProjectModel` with `fromJson()` / `fromArray()`, a
`modelVersion`, and a validator, replacing the raw `stdClass` AST. This removes the eleven
copies of `initiateAST()`.
*Done when* nothing outside the model layer calls `json_decode` on `form_data`.

**1.4 Port the seven generators onto the core.** Output unchanged, golden files green
throughout. This is where the Joomla 6 component structure model gets designed —
discovered by porting generators that must produce working output, rather than drawn up
front. `AdminEntities` is the natural first candidate, having the most hand-rolled string
building.
*Done when* all generation runs through `Pipeline`, writes into a `FileCollection`, and
the golden files are unchanged.

**1.5 Fix what is broken.** From the analysis:

- `src/Field/LIonCore_M3/LanguageReferenceField.php` declares `class EntityReferenceField`
  with `$type = 'ClassifierReference'` — unloadable.
- `src/Factory/MVCFactory.php` carries the namespace `...\Administrator\Service` while
  living in `src/Factory/`, and `services/provider.php` registers Joomla's stock factory
  anyway. Fixing the path and wiring it in covers the dependency-injection item.
- Corrupted namespace attributes in `forms/metaProjectForms/`:
  `addfieldprefix="...\Administrator\Fieldclassifier.xml"` (3 occurrences), and
  `Yepr\\Component\\Extengen\\\Administrator\\MetaProjectForm\\...` in `projectForm.xml`,
  pointing at an empty directory tree.
- Site-side templates under `generator_templates/Joomla6/component/components/` emit
  `Administrator\View` and `Administrator\Model` namespaces; generated front-end views
  cannot autoload.
- Dead `test.json` reads in `View/ERD/HtmlView.php:48` and
  `View/FormsDiagram/HtmlView.php:48`.

**1.6 Real packaging.** A package manifest covering component, media and the library
dependency; `build.php` producing an installable zip; generation output delivered as a
downloadable zip rather than files written under `generated/`.
*Done when* a generated component installs on a clean Joomla 6 from the zip Exten-gen
produces.

**1.7 Joomla 6 sweep — Exten-gen itself.** `JHtmlSidebar` (7 sites), `Factory::getUser`
(9), `CMSObject` with `getProperties()` (4 files), `Factory::getDbo` (2), `Table::$_db` (2),
`Factory::getDocument` (1), `User::get('id')` (1). `JHtmlSidebar` is the only one that was
broken rather than merely deprecated: it is not a class in Joomla 6 but an alias registered
by the `behaviour - compat6` plugin, so every list view fatals on a site that does not run
that plugin. One of its call sites was in `View/ProjectForms/HtmlViewOLD.php`, a copy of the
view beside it that nothing references; deleted rather than swept.

`getError()` / `setError()` stay. They are not a call the component makes on its own:
`AdminModel::save()` invokes `check()` on a table and reads `getError()` off it, and every
core Joomla 6 table still answers that way. Dropping them changes a contract with code
that is not Exten-gen's, and nothing in the suite runs far enough to see whether it held.
It waits for 1.11, where a Cypress run can drive a real site.

`tests/Unit/JoomlaApiTest.php` holds the removed calls out. It is a grep over the source
and says so: it proves the old calls are gone, not that the new ones behave.
*Done when* the component's own tree is free of them and the guard test fails when one
returns.

**1.8 Joomla 6 sweep — the generated output.** Two calls in the templates were fatal
on a Joomla 6 without the `behaviour - compat6` plugin, not merely deprecated:
`\JHtmlSidebar`, which is an alias that plugin registers rather than a class, and
`$app->input`, which is protected in Joomla 6. `Factory::getUser` and `Table::$_db`
were deprecated. Filter values in generated list models interpolated with
`$db->quote()`; they are bound now.

Reading `delete()` closely turned up a real bug: `$query` was only ever declared by
an m2m fragment, so a generated Table for an entity with no n:n relation called
`$query->clear()` on an undefined variable — a fatal on every delete.

Of 174 files under `generator_templates/`, 29 are rendered. Reachability was
measured with a recording renderer over every golden model and cross-checked
against what the generators' concatenated names can expand to, not guessed. The
other 145 — the Akeeba ATS source the set was copied out of, eleven plugin
skeletons, a module, an entire site template — are gone, along with 634 lines of
commented-out ATS `getListQuery()` that every generated component carried.
`TemplateReachabilityTest` keeps it that way.

`strict_variables` is on and `LegacyTwigRenderer` is deleted. Three names did not
survive it, each a bug: `pageNamelower` gave every list table `id="List"`;
`linkPageName` was assigned only inside the branch that did not need its fallback,
so a page with no links generated `addNew('.add')`; `updateServerURL` is for a
feature the model does not have and now says `|default('')`.

`Joomla4Target` is `Joomla6Target`, which the output has earned. `$outputType` and
`$extengenAdminPath` went with the rename: their only readers built a path under
`generated/` that nothing used once 1.4 stopped writing to disk.

*Done when* generated output calls no API Joomla 6 has removed or deprecated, every
generated PHP file parses, and no template in the set is unreachable.

**1.9 The reference-field rework.** `ReferenceIndex` turns a stored project into
everything that can be pointed at, the edit view puts it in the page once, and
`<extengen-reference>` decides the choices from it plus what the form holds right now.
Three field classes became one, `type="Reference" objecttype="Entity"`, and it queries
nothing: a form with twenty reference dropdowns ran twenty queries for one project, and
options baked into `<option>` tags at render time can only describe the database, which is
where must-save-first came from.

`admin-project.js` is gone entirely, all 708 lines, not only the 300 marked deprecated.
Every inline `onchange` in the form XML went with it. Four of the functions those
attributes named - `editChildConceptList`, `editConceptFieldsList`, `backupClassifierKey`,
`backupID` - had no definition anywhere, so those fields threw on every change; events are
delegated from the document now, which also means a repeating row added after load behaves
like one that was there.

The `<select>` stays a real form control and the server still renders the held value as a
selected option, so a page whose script does not arrive submits what it was given rather
than an empty reference.

Fourteen classes could not load at all: nine field classes and three models called
`ProjectRepository` or `ProjectFormRepository` with no import, which arrived in 1.3 and
made every edit form a fatal. `ResolvableNamesTest` is the rule that catches it.

Not done here: the six LIonCore_M3 reference fields keep their own classes. Their model
has no fixture, and the field classes disagree about its shape - three are byte-identical,
`DataTypeReferenceField` uses a different key scheme, `LanguageReferenceField` reads an
undefined variable. Converting them would mean inventing a projection for a model this
repository cannot check. That is what 3.1 is for.

*Done when* adding an entity and referencing it works without saving first, with a Cypress
spec proving it. **The spec is not written**: Cypress arrives in 1.11 and needs a running
Joomla 6. What is proven here is `ReferenceIndex` against the three stored models, the
markup contract, and every rule in `referenceOptions` under Node's test runner - 22 cases,
no dependencies. The browser wiring itself is unverified until 1.11.

**1.10 Custom code.** `SlotCatalogue` is a closed list of named points in generated
files; `CustomCode` turns what a model object stores into regions; templates emit
`{{ slots['table.check']|raw }}` and never see a body, so one place writes a marker and
cannot drift from the pattern the merger matches. Five slots to start: two on an entity's
table, two on a list model, one on a details model. An entity and each kind of page carry
a `customcode` repeating group, and the dropdown's options are the catalogue rather than a
list in the form XML that would drift from it.

`ProtectedRegionMerger` runs over the previous run's unpacked tree before anything is
written, so an edit made in the output despite the model-side mechanism is carried back.
A region the new output no longer has is reported by path and id, because its content is
then only in the file on disk.

Back end only. Every slot names exactly one generated file per object, which is what lets
a person say where their code will end up; the site templates generate a second list model
with the same method names, so a shared slot would put one body in two files without
saying so. Site slots would be their own ids, and wait on 1.12.

`ProjectValidator` refuses two bodies for one slot. `SlotContractTest` checks the
catalogue, the templates, the forms and the generated output against each other, and
`CustomCodeGenerationTest` runs a real model with custom code through the real generators
and reads the file back - including that it still parses, which is the one thing pasting a
body in verbatim can break.

*Done when* code written in the model appears in the right place in the generated file,
regenerating does not lose it, and an edit made in the output survives a regeneration.

**1.11 Quality gate.** PHPStan at **level 5**, no baseline, over `src/`, `tests/` and
`build/`, with a Joomla 6 unpacked into `/joomla` as reference material - CI fetches its
own. Level 6 was 76 further findings, almost all `array` in a parameter or return type on
code written years before an analyser was pointed at it; 5 is where the findings are about
whether the code works.

159 findings went to zero. Most were repairs: `JPATH_PLATFORM` guards, undefined
variables, a generator whose header had never been copied across, docblocks naming
parameters that were not there. Four exceptions are recorded in `phpstan.neon` with
reasons, and all four are Joomla's own shape rather than this project's: a return typed
to a parent class, a docblock narrower than the body under it, and `setError()` /
`setUseExceptions()`, which are the migration mechanism rather than debt - `setError()`
throws when the caller has opted in.

phpcs covers `src/` now. 7393 findings, of which 6060 were tab indentation, which Joomla
mandates and PSR-12 forbids: Joomla wins, because this is a Joomla extension. 1202 more
were auto-fixed. The rest are scoped with reasons: the `_JEXEC` guard is a side effect
PSR-1 objects to and Joomla requires, `$_tbl` is a name a subclass does not get to change,
and the generators' long lines are code being built as strings.

`GeneratedSyntaxTest` parses everything the generators write - PHP through `token_get_all`
with `TOKEN_PARSE`, XML and INI through the readers Joomla uses - and checks no template
tag survived into the output.

**Cypress runs against a real Joomla 6.** 13 specs: every view renders and throws no
JavaScript error, the reference index reaches the page, an entity added and never saved is
offered by a reference dropdown, and generation produces a package. `tools/seed-project.php`
puts a golden fixture model into a site so the specs have something to open, and
`composer install-local` builds and installs through Joomla's CLI, which needs no login.

What that found, in one afternoon, on code that had passed every other gate for five
steps: `defined('JPATH_PLATFORM') or die;` at the top of the component's Extension class -
a constant Joomla 6 removed, so every request to the component returned 200 with an empty
body and nothing in any log; `boot()` asking the container for a `SiteApplication` on every
administrator request; `joomla.asset.json` missing from the manifest's `<media>` section,
so the 1.9 reference script could not be found; a Twig extension constructed before the
renderer that loads Twig; `JUri` in five layouts; and a placeholder view building a list
toolbar whose `listCheck(true)` made Joomla's own script throw. None of those is visible to
anything that reads source.

*Not in CI:* the Cypress specs. They need a database, a Joomla install and the component
installed into it - a job of its own rather than a step, and one this repository does not
need before it can be released. They run locally with `npm run cypress`.

**1.12 Release 1.0.0.** Version 1.0.0 in `src/extengen.xml`, which is the only place it
lives; `composer build` regenerates `updates.xml` from it. The update server points at this
repository. The installed extension keeps the element name `com_extengen`, so a site
running the old one updates in place.

**The tag did nothing, and said nothing.** `v1.0.0` was pushed and no release appeared:
this repository had no workflow listening for tags. generator-core got one in stage 0 and
this one never did, and neither 1.6, which built the package, nor 1.12, which tagged it,
noticed that the tag had nothing to trigger. A tag is only a release procedure once
something is listening for it, and a missing listener is silent - no failure, no mail,
nothing on the Actions tab at all.

`.github/workflows/release.yml` closes it. It refuses a tag that disagrees with the
manifest, refuses an `updates.xml` that regenerating would change, runs every gate, builds
*with* the library bundled - a runner has no sibling checkout, so `build.php` fetches the
library release `script.php` insists on, and a release needing a library nobody published
fails there rather than on somebody's site - asserts what the package contains, and
publishes it. `v1.0.0` was re-pointed at the commit that carries the workflow, and the
release it produced advertises exactly the URL `updates.xml` promises.

**The front end is in 1.0**, and getting there was not a flag. The generators already
produced site controllers, models, views, layouts and language files with the right
namespaces - and none of it had ever run. Five things were wrong, each hidden behind the
one before it:

- the generated manifest's front-end `<files>` block was commented out, so nothing
  installed;
- no `tmpl/<view>/default.xml`, so no site view could be put on a menu at all;
- the list layout asked the asset manager for `com_x.admin`, an administrator asset no
  generated component declares, which Joomla throws for;
- it rendered Joomla's search tools, which read a filter form a front-end list model does
  not build;
- its language strings were registered into the administrator language file, so a visitor
  saw `COM_X_TABLE_...` rather than a column heading.

The layout was a copy of the administrator's, down to the selection checkboxes and the
`task=x.edit` links. It is a front-end list now: plain headings, no checkboxes, and links
to the details view.

`tests/cypress/e2e/generated-front-end.cy.js` is what makes that a claim rather than a hope. It
generates a component, installs it with `tools/install-generated.php`, points a menu item
at one of its views with `tools/seed-menu-item.php`, and looks at the page a visitor gets.
Nothing else in this repository leaves Exten-gen.

*Not in 1.0:* a Router service, so generated front-end URLs are the ones Joomla builds from
a menu item rather than paths of their own. `/component/<name>/` is a 404 without one.

**Scope of v1.** Entities with properties, n:1 and n:n relations, embeddables, index pages
with filters, detail pages with edit fields, language files, an installable package.
Both, from the start: see 1.12 for what that cost and what now proves it.

**Named gaps in the v1 model.** Three things the model cannot express, written down here
because each is invisible until something needs it and then blocks a whole line of work.

| Gap | Model today | Bites at |
|---|---|---|
| A custom form field type | `editfield.xml` picks among *stock* Joomla types through `htmltype`. A generated extension cannot declare a field type of its own. | 4.2 |
| A custom validation rule | Nothing. Extengen's own `LetterRule` has no counterpart in the model. | 4.2 |
| Tabs and subform layouts on a generated form | Nothing. Generated forms are one fieldset. | 4.2 |

None of the three blocks v1, and that is worth stating rather than assuming: the current
generator emits only stock field types - `text`, `sql` for a relation, `hidden`, `number`,
`calendar`, `list`, `subform`, `editor` - so a generated CRUD component needs no field
class of its own. What the gaps block is **Exten-gen generating itself**, because its own
forms use eleven custom field classes and a rule.

How many of those eleven survive is not fixed yet. Step 1.9 replaces the reference-field
mechanism with one client-side element driven by the model, which is precisely what most
of those eleven classes do by hand. The size of this gap should therefore be re-measured
after 1.9 rather than estimated now.

---

## Stage 2 — Gen-gen

Possible only now, because both endpoints exist: an explicit source model from 1.3 and an
explicit target structure model from 1.4.

**2.1 Extract the transformation rules into data.** Separate *what maps to what* from
*how it is written out*. The mapping becomes a structure; the emitters stay code.

`Yepr\Gen\Core\Rule` in the shared library is the runtime, released as 0.2.0: `Rule`,
`RuleSet`, `RuleEngine` and a validator that checks a set without a model in sight. A rule
reads as one sentence - for each node a selector yields, when it passes these conditions,
render this template to this target path, binding these variables. The vocabularies are
closed on purpose: four condition operators, five binding kinds, four path filters, and
selectors and derivations that must be registered by name. A rule set that could name any
callable would be a program stored as JSON, with no analysis, no debugger and no types.

Exten-gen's own mapping is 27 rules in `Rules/joomla6.rules.json`, four selectors
(`root`, `entities`, `backendPages`, `frontendPages` - the last two being the join between
the flat page list and the sections that reference it, which used to be re-derived inline
twice) and twenty named derivations. The five template-rendering generators are between
four and twelve lines each now; 930 lines became 126 plus the rule file.

**What stayed code: the emitters.** `Forms` builds form XML through DOM, `LanguageFiles`
assembles ini, `AdminEntities` writes the sql. None of them renders a template once per
node - each assembles one file out of the whole model, and the junction tables cannot be
written until every entity has been seen. There is nothing for a rule to say about them,
which is exactly the line this step was drawn along.

**Order had to be preserved, and it is not decoration.** Consecutive rules over one
selector form a block that runs node-major, because templates register language strings as
they render: emitting every controller and then every model produces the same file set and
a different `.ini`.

Writing the mapping down made two things visible that the control flow had hidden.
`SiteMVC` opened with the comment "same as AdminMVC, can we combine that?", which nobody
could answer while both were three hundred lines; as rules the topology is identical and
the real differences are three derivations, each now carrying a docblock that says how it
differs. And `siteIndexEntity` reproduces a bug: the front-end generator reused `$entity`
as its filter loop's variable, so an index model's field lists came from the *last
filter's* entity rather than the page's. Preserved, not fixed - fixing it here would bury
a behaviour change inside a diff whose whole claim is that it has none.

*Done:* byte-identical output across all three golden models, 205 unit tests and the 17
browser specs green, including generating a component through the UI, installing it and
viewing its front end. `RuleSetTest` is the compiler the rule file does not have; it found
a dead derivation the first time it ran. Two defects were fixed on the way: `build.php`
bundled whichever library zip lay newest in the sibling checkout without comparing it to
`LIBRARY_MINIMUM`, so bumping the minimum produced a package whose own install script
rejected the library it carried; and a section referring to a deleted page stopped
generation with an undefined index instead of skipping it.

**2.2 Model the generator.** Forms for transformation rules, MPS-style: source pattern to
target structure, with conditions and iteration.

Done in **Gen-gen**, whose repository now holds something: four forms - a generator, a
rule, a condition, a binding - and the model behind them. One rule reads as the sentence
2.1 made it: for each node this source pattern yields, when it passes these conditions,
render this template to this output path, binding these variables.

**Everything a rule may say is a choice, not a text box**, which is the part that makes it
modelling rather than JSON editing with rounded corners. A target publishes what its rules
may say - `Vocabulary` in the library at 0.3.0, written by Exten-gen's
`build/vocabulary.php` off the live registries and the template directory - and
`VocabularyLibrary` finds those descriptors among the installed components. So Gen-gen
depends on none of the components it models generators for, a new target appears in the
list by being installed, and a rule naming a selector that does not exist cannot be
written. The operators and binding kinds come from the library instead: a target may add
selectors and derivations, it does not get to add operators.

`GeneratorDefinition` is the one translation between the two shapes, and it exists because
Joomla's form layer cannot hold a rule the way the engine wants it - a repeating group is
an object keyed `binding0`, `binding1`, and a field holds a scalar, not a key that is
itself the meaning.

*Done:* Exten-gen's own twenty-seven rules, through the form shape and back, identical -
and then checked against the target's vocabulary, because two arrays matching proves
nothing if both are nonsense. 24 tests, PHPStan level 5, phpcs. Three things the
translation gets right that a naive one would not: an empty literal is a value and not an
absence (`getFK` is bound to `""` and a template reads it); a valueless operator writes no
value, because the form's hidden box still holds whatever was typed before the operator
changed; and rule order survives, which decides what a generated language file contains.

`FormsTest` is the compiler a form file does not have: every subform source exists, every
custom field type has a class, every class declares the type its form asks for, and every
`showon` names a value the library actually has. A `formsource` Joomla cannot resolve
raises nothing - the subform renders empty, which looks like a feature nobody filled in -
and a field type it cannot resolve falls back to a plain text box, silently turning a
closed list into a place to type anything at all.

Three defects, two of them found by CI on the first push and invisible on this machine:

- **A field type that only resolves on Windows.** `FormHelper::loadClass()` builds the
  class name as `ucfirst(ucwords($type))`, and `ucwords` only touches letters after
  whitespace - so `type="ruleselector"` asks for `RuleselectorField`, one letter from the
  class and, on a case-insensitive filesystem, no letters at all. On the Linux server this
  will run on, the class is not found and the closed list degrades into a text box: the
  exact failure the test beside it described and was too case-insensitive to catch. The
  types are spelled `RuleSelector` now, and the test resolves them the way Joomla does.
- **PHPStan scanning the shared library twice**, once from composer and once from the copy
  installed into the development site, analysing against whichever resolved first. It
  surfaced in Gen-gen as "undefined constant `Binding::KINDS`" against a constant that very
  much exists; here it had not surfaced yet, which is not the same as not being there.
- And the fix for that broke both builds, because PHPStan errors on an `excludePath` that
  does not exist and a freshly fetched Joomla has no extensions installed in it. A wildcard
  matching nothing is accepted.

*Not in 2.2, deliberately:* the component's MVC, its manifest, its package and its release
workflow. That is 2.4, and 2.3 comes first.

**2.3 Generate a generator, and check it.** Acceptance criterion: byte-identical output to
the hand-written generator it replaces, measured against Stage 1's golden files. Not a
judgement call.

**Met.** `composer acceptance` in Gen-gen generates the generator, runs it over all three
golden models and compares every file:

> 228 files compared, all identical to the approved output.

The model is a description of a generator and the output is a generator, and nothing in
the core changed to allow that. `ModelInterface` is a marker, `Pipeline` never looks inside
a model, and a target is a structure plus emitters plus a template set - a generator
happens to be describable that way, so it is one.

Four files come out. The rule file is the mapping, **emitted** as data, because
`RuleSet::toJson()` already writes it correctly and rendering JSON from a Twig template
would mean reimplementing quoting in Twig. The three classes are wiring, **rendered** from
a template: a rule file does not run by itself, so something has to be a class, claim a
slice of the rules and sit in the order Joomla runs them in.

**It does not generate the groups that emit**, which is 2.1's line drawn once more.
`AdminGeneral` writes language strings, `AdminEntities` writes sql for a schema only
knowable once every entity has been seen; a generated class replacing either would delete
that code and leave something that looks complete. The group declares `emits` in the model.

Three things the check needed, each of which is the interesting part of it:

- **A process of its own.** The generated classes have the same fully qualified names as
  the hand-written ones - that is the point - so they are required before anything can
  autoload the originals, and a class is defined once per process.
- **The library's own normalisation.** Compared as raw bytes, every rendered file matched
  and every sql and language file did not: a template's output ends with exactly one
  newline and a file assembled by `implode()` ends with none. `GoldenFiles::normalise()` is
  what Stage 1's golden test uses, so it is what this uses.
- **A canonical rule file.** "Gen-gen generates this file" is only checkable if there is
  one way to write a given rule set down, so Exten-gen's committed `joomla6.rules.json` is
  now `RuleSet::toJson()` output rather than the hand formatting it had, and a test keeps
  it that way. `RuleDrivenGenerator` also gained an overridable `ruleFile()`, which is how
  a generated generator runs beside the committed one.

Gen-gen's CI checks Exten-gen out beside it so the criterion actually runs there. A skipped
test that is the whole point of a repository is worse than no test.

**2.4 Repository, package, release.** Gen-gen is an installable Joomla 6 component and
0.1.0 is released. Model a generator in its forms, press Generate, get a generator.

The row is thin on purpose: a name, a target, and the whole modelled generator as JSON.
Its shape is the forms; putting it in columns would mean maintaining the same structure
twice - once as a form and once as a schema - with a migration every time a rule gains a
field. `GeneratorTable::check()` reads that JSON back and asks for the rules, so a rule
missing its selector is refused on save rather than three screens later.

Packaging is this repository's, already debugged the hard way, so nothing there was new.
What was new is **`tools/smoke.php`, which asks about the install rather than the working
copy** - 33 checks, no login - and it earned its place immediately:

- it built the component through its own `services/provider.php` instead of calling
  `class_exists`, and found `GengenComponent` missing `HTMLRegistryAwareTrait`. The
  provider calls `setRegistry()` on it; nothing static sees that call, because it is in a
  closure in a file Joomla includes at run time. It looks like a working component right
  up until the first request reaches it.
- it reported that no target published a vocabulary - correctly, because the *installed*
  Exten-gen predated 2.2. A checkout cannot tell you that.

**Cypress covers the one thing none of that sees: whether a screen renders.** Five specs,
against the same Joomla 6: the list comes up, a stored generator opens for editing, and
the dropdowns hold `root`, `entities`, `backendPages`, `frontendPages` and
`componentNameUcfirst` - the target's real vocabulary rather than empty boxes. Two of the
specs were wrong before the component was: a tab selector matched a hidden accordion
title, and Joomla 6 renders `<joomla-tab-element>` where an older Joomla rendered
`.tab-pane`.

Two CI failures, both of them this machine hiding something. PHPStan was scanning a
`/joomla` that was a copy of the development site, so it resolved `com_extengen`'s classes
- it now scans `joomla/libraries` only, and the local copy is a clean Joomla. And
`include JPATH_LIBRARIES . '/vendor/autoload.php'` folds to an absolute `/vendor/...` that
Linux checks and Windows does not.

**Building Gen-gen by hand is also the evidence for 4.2.** Its forms need custom field
types and nested subforms, which are two of the three model gaps named as blocking
self-hosting. Exten-gen could not have generated this component today.

---

## Stage 3 — Meta-gen

**Read this before 3.1 and 3.2.** Both were built *inside Exten-gen*, which was wrong: the
repository table at the top of this plan has always said Meta-gen is its own component
package. "3.4 Repository, package, release" was read as permission to build in place and
move it later, the way the old Extengen had everything in one component. It is its own
repository now - [Meta-gen](https://github.com/HermanPeeren/Meta-gen) - and 3.0 below is
that move. The two records that follow describe work that is now over there.

**3.0 Meta-gen becomes its own component.** Everything about modelling a language left
Exten-gen: the LionCore M3 forms, the CRUD around a stored language, the reference table
for it, and the forms generator from 3.2. Exten-gen's own side referenced it exactly once,
in a stale comment, so this was a move rather than an untangling.

**The entity is a Metalanguage**, and getting there cost a rename. It was a
`ModelLanguage` first, and Joomla would not have it: `BaseModel::getName()` matches
`/Model(.*)/i` against the *fully qualified* class name - so it matches the `Model`
namespace segment first - and then strips every lowercase `model` from what it captured.
`...\Model\ModelLanguageModel` came out as `language`, so Joomla looked for a table by
that name and the edit screen died with *"Table language not supported."*
`ListModel::getFilterForm()` has its own version of the same assumption and asked for
`filter_.xml`, so the list view died on a null `filterForm` inside Joomla's own searchtools
layout. Neither failure names the class that caused it. **An entity name must not contain
"model", in any case**, and `JoomlaNamingTest` over there is that rule written down.

**The reference dropdown moved into the shared library**, released as 0.4.0. Three
components edit models with one - Exten-gen a project, Meta-gen a metalanguage, Gen-gen a
generator - and Exten-gen was carrying the mechanism for all three. What is shared is the
mechanism; what stays with each component is its *table*, because that is the only thing
that differs. `ReferenceIndex` said exactly this in its own docblock at 1.9, as the reason
its types were a map rather than a method each.

That needed a second half to the library. `Yepr\Gen\Core` imports no framework, ever, and
a `FormField` subclass cannot live under it, so `Yepr\Gen\Joomla` is the rest of the
library - which the plan anticipated when it called the engine the library's *first*
occupant. `NoFrameworkDependencyTest` scopes its rules to `src/Core` and gains two more:
nothing in the core may import the Joomla half, and the Joomla half has to exist.

**Two naming rules, found on the way, that apply to every component here.** A view class
directory is `ucfirst` of the view name exactly; a tmpl directory is `strtolower` of it
exactly, because `AbstractView::getName()` lowercases the last namespace segment. Both broken
here, in opposite directions, and both resolve on a case-insensitive filesystem and 404 on a
Linux server - the third time this family has turned up, after `HtmltypesField` at 3.1 and
`RuleselectorField` in Gen-gen at 2.2.

**Applying the rule found something worse than a casing defect.** `src/extengen.xml` opened
with `<menu view="extengen">`, and that attribute is what Joomla writes into `#__menu` at
install time - so the component's own entry in the administrator sidebar had pointed at a
`View\Extengen` that was never written, since before this plan. Clicking it returned *"View
not found [name, type, prefix]: extengen, html, Administrator"*. The submenu still offered
`view=projectforms` as well, which is a 500 behind a link nothing opened once that view had
moved to Meta-gen. Both are gone, with the forty-two language strings the project-form
screens used and nothing names any more.

`ViewNamesTest` is the rule, and it was checked by breaking things rather than by passing:
a link naming a view that is not there, a link to a view that moved, and `view=erd` against
a `View/ERD` directory each fail it. It reads directories with `scandir` rather than
`is_dir`, because `is_dir` answers yes to the wrong case on the machine this was written on
and no on the one it will run on, which is the whole defect.

**And the browser spec that should have caught it had to be written twice.** The first
version clicked the menu link, and passed against a manifest that was broken - the
component's entry renders with `class="has-arrow"` because it has a submenu, so Joomla's own
menu script swallows the click to open the dropdown and the browser never goes anywhere. It
reads the hrefs and visits them now. A spec that exercises the thing a person does is worth
nothing if the thing it does is not that.

**And one about loading a shared script.** A library's asset file is not registered the way
the active component's is, so a layout asks for it by name - and the `uri` inside it must
be `lib_yepr_gen/reference.js`, not `lib_yepr_gen/js/reference.js`, because Joomla's
relative resolution inserts the `js/` folder itself. Spelled the long way the asset is
looked for one directory too deep, is not found, and is dropped without a word: no
exception, no tag, and every dropdown keeps whatever the server rendered. The same silent
failure 3.1 spent a step on, by a new route, and the browser specs are what found it.

**The split is deliberately unreleased.** `src/extengen.xml` was bumped to 1.1.0 while this
was being written, and `updates.xml` is generated from it - so `main` spent a few hours
offering every site running 1.0.0 an update whose download 404s, which is the failure 1.12
added that file's test for, arriving from the other direction. It says 1.0.0 again.

Tagging 1.1.0 is held until 3.3 and 3.4, and not for tidiness: the 1.1.0 update script
drops `#__extengen_projectforms`, Meta-gen has no release, and nothing can export or import
a metalanguage yet. An update that removes a feature *and* the data behind it, with no way
to move that data first, is not one to offer. The version goes back up when there is
somewhere for the data to go.

*Done:* generator-core 0.4.0 with 135 unit and 27 browser-JavaScript tests; Meta-gen with
132 unit tests and 12 Cypress specs against its own Joomla; Exten-gen with 228 unit tests
and 16 Cypress specs, and every static gate green in all three.

**3.1 Repair or rebuild the LionWeb model.** The concept forms work; the field classes and
namespace prefixes around them do not (see 1.5). 1.9 left those six reference fields on
their own mechanism and removed the inline handlers they called, three of which had no
definition anywhere - so those dropdowns render their options from the database and no
longer refresh as the form is edited. Adopting `<extengen-reference>` needs object types
for `languageEntities` in `ReferenceIndex`, and a stored projectForm to check them
against.

**Done: repaired, and made live.** Four defects, none of which showed up as an error
anywhere, and an audit rather than a hunt is what found them:

- **`annotation.xml` did not exist.** `classifier.xml` has offered "Annotation" as one of
  the three kinds of Classifier since the model was written and pointed at that file the
  whole time. Joomla does not report a `formsource` it cannot resolve; the subform renders
  with no fields in it, which looks like a feature nobody had filled in.
- **`editfield.xml` asked for `type="htmltypes"`** and the class is `HtmlTypesField`.
  Joomla builds the class name with `ucwords`, which only touches letters after whitespace,
  so it looked for `HtmltypesField` - one letter out, and on this filesystem no letters out
  at all. The same defect Gen-gen's CI caught in its own forms at 2.2.
- **`concept.xml` named a field type under a prefix that did not hold it.**
- **`interface.xml` pointed at a `classifier_property.xml` that never existed**, and at an
  ER1 form from inside the meta-model. Nothing reached it at all, which is why nobody had
  noticed - so it goes, with the two `Interfaces/` files that nothing reached either.

**The substantive half.** The six M3 dropdowns are the shared `<extengen-reference>` now,
so a concept added a minute ago can be extended and one renamed on screen shows its new
name everywhere at once. All six field classes and the whole `Field\LIonCore_M3` namespace
are gone.

That needed one thing ER1 did not: **conditions**. Every M3 type lives in the same
repeating group and they differ only by what the row says it is, so `ReferenceIndex` now
carries a table per model and each type may name conditions - written twice, once as a path
through stored JSON and once as a token in an element id, because neither spelling can be
derived from the other. `liveEntries` applies the same conditions in the browser, reading a
radio through the fieldset Joomla puts the id on.

**There was no stored projectForm anywhere**, on this machine or in the repository, so
nothing had ever exercised any of it. `tests/Fixtures/projectforms/er1.json` is one: two
datatypes, a concept interface, two concepts, an annotation, and a row somebody added and
never named. Modelling ER1 properly is 3.3; this is enough to check the mechanism.

*Done:* 221 unit tests, 27 browser-JavaScript tests, PHPStan, phpcs, and 24 Cypress specs
green - including seven new ones that open the projectForm on a real Joomla and read what
the dropdowns hold.

`FormsTest` is the compiler these files do not have, and it is what found all four: every
subform source exists, every field type resolves the way Joomla resolves it, every class
declares the type its form asks for, and nothing in the meta-model is unreachable from its
root. `ReferenceContractTest` now checks each form against its own model rather than the
union of both, so an ER1 objecttype on a meta-model form is refused rather than quietly
rendering an empty list.

**And one thing only the browser could see.** Everything else passed while the meta-model
dropdowns still held nothing but a raw uuid, because the view never put the index in the
page and the layout never loaded the script - two lines, in two files no unit test reads.
A patch script had aborted before reaching them and I had taken its earlier output as
proof it had not. The spec caught it on the first run.

**3.2 The forms generator.** Concept model to form XML, reference field elements, and the
JavaScript the 1.9 mechanism needs.

**Done, and checked against the meta-model it generates.** `tests/Fixtures/projectforms/
lioncore-m3.json` is LionCore M3 modelled in LionCore M3 - the meta-model describing
itself - so "does the generator produce the meta-model" has an answer rather than an
opinion, in the way 2.3's acceptance criterion did. The table half is exact:

> the generated reference table indexes a stored projectForm into the same answers, type
> for type and condition for condition, as the hand-written `ReferenceIndex::PROJECT_FORM`

**Both** hand-written tables, in fact, and that matters because each exercises a half the
other does not. M3 puts every type in one `languageEntities` group, so every path is one
step and the work is all in the conditions. `ReferenceIndex::PROJECT` is the opposite
shape - three different groups, no conditions at all, and a child type nested inside its
parent's with a `parentKey` and a `parentCut` - and a language shaped like ER1 reproduces
it as well, `_field__field` included. Modelling ER1 properly is still 3.3; this is the
part of it the containment walk has to get right.

`ReferenceIndex` said in its own docblock, at 1.9, that its type table was a map rather
than a method per type *because Meta-gen generates this from a concept model in stage 3*.
It does now, and the two are compared by running them - a table is not checked by matching
two arrays, because two arrays matching proves nothing if both are nonsense. Both halves
of an entry come from one walk of the language, which is the whole reason to generate
them: a condition is written twice, once as a path through stored JSON and once as a token
in an element id, and the hand-written pair could drift with no symptom but a dropdown
that is right until somebody changes a radio.

**Subtyping is what makes a forms generator possible at all.** A classifier that others
extend does not hand them its fields; it gets a radio saying which one this row is, and a
subform each. That is how the hand-written meta-model is arranged, and it is what lets one
repeating group hold five kinds of thing. Written down as `LanguageStructure`, it produces
the same file names, the same `showon` attributes, the same nesting - and the same fifteen
qualified names (`LanguageEntity.Classifier.Concept`, `Feature.Link.Containment`) that
fifteen separate files spell out by hand. Those are derived from the extends chain, not
read: the stored `LIonWeb_key` on a row says the row is a LanguageEntity, which is true of
every row and not what a form needs to know.

Abstractness is the one model fact that decides an option list. `classifier_type` offers
Concept, ConceptInterface and Annotation and not Classifier, because a row cannot be a
Classifier and nothing more specific - so a concrete classifier is the first of its own
choices and an abstract one is not.

**There was a generator here and it could not have run.** `Generator\ProjectForms` was a
copy of the ER1 forms generator with a dead first half bolted on - a `$createForm` closure
nothing called, and a tree walk commented out beside it. Its live code opened
`foreach ($projectForm->datamodel as $entity)`, and a projectForm has `languageEntities`
and no `datamodel` at all, so the one screen that called it would have thrown on its first
statement.

Nothing had found that out because nothing could reach the screen either, and this is the
part worth keeping: there were **three names for one view and no two of them agreed**. The
link in `projectForms/default.php` said `view=generateform`; the directory was
`GenerateProjectForm`; the class inside it declared `View\GenerateForm`. A screen nobody
can open is a screen nobody finds out is broken, and a generator behind one can be wrong
for years.

`ResolvableNamesTest` has the rule that would have caught the third of those, and it is
one string comparison: a file declares the namespace its own path spells out. PSR-4 is
that correspondence and nothing else. It catches 1.5's `MVCFactory.php` too, which
declared `...\Administrator\Service` from `src/Factory/` while `services/provider.php`
quietly went on registering Joomla's stock factory. Neither the analyser nor the coding
standard asks the question.

**Which property is the identity is a convention, and it is written down.** LionCore M3
cannot say "this property is the key" or "this one is the label", so `Naming` finds them
by name: `key` or `id` or something ending in `_id`, and `name` or something ending in
`_name`. It is a convention rather than a guess because it reads both hand-written tables
correctly without being tuned to either - M3's `key`/`name` and ER1's
`entity_id`/`entity_name`. Marking them in the model belongs with the other things the
meta-model cannot express, at 4.2.

**Not byte-identical to the hand-written forms, and not claimed to be.** That is 3.3, and
these are what it has to reconcile:

| Difference | Which side is right |
|---|---|
| `size="1"`, `class="custom-select-color-state QualifierRef"` | Presentation a concept model cannot hold. The model needs somewhere to put it, or the generator needs a defaults table. |
| The root form's Joomla chrome: alias, published, access, catid, ordering, `params` | Not derivable from a language at all. A projectForm is a Joomla item as well as a model, and only the second half is generated. |
| `COM_EXTENGEN_PROJECTFORM_*` against the generated `COM_EXTENGEN_LIONCORE_M3_*` | Settled at 3.3, and neither: `YEPR_LIONCORE_M3_*`, scoped by the language rather than by whichever component loads it. The package defines them. |
| `languageEntity.xml` names the DataType subform `datatype`, where every other group is lowerCamel | The hand-written file. The generated `dataType` is what the convention says, and `ReferenceIndex` never exposed the difference because DataType needs no group-path condition. |
| `link.xml` names the Reference subform `link`, pointing at `reference.xml` | The hand-written file. |
| A hidden `extends` field in `classifier.xml`, `dataType.xml`, `link.xml` and three more | Neither. It duplicates what `LIonWeb_key` already says, and `extends` means something else entirely one form away, where it is a real reference to a concept. |

**Not in 3.2, deliberately.** No `scope` is emitted on a reference field, and that is a
decision rather than an omission: the hand-written ER1 form that scopes one points at
`entity_reference_id`, the hidden backup of the *sibling dropdown* where somebody picked
the entity, not at a property of the row. A child's `parentKey` and a dropdown's `scope`
look like one idea and are two, and which of them a modelled language means is a question
the real ER1 model answers at 3.3. Emitting one on a hunch would narrow dropdowns to the
wrong thing, and an over-narrow list reads as an empty one rather than as a mistake.

An annotation's `annotates` does not change the form of the classifier it attaches to -
the mechanism for that is a language feature nothing in this repository can check yet. No
language file is generated, so every generated label is a constant nothing defines. And a
generated language is output rather than something
installed: the old generator wrote straight into the running component's own `forms/`
directory, one `mkdir` and `save()` at a time, so generating forms edited the component
from inside itself and a run that failed half way left a language half replaced. It
produces a package and a tree beside it, the way a generated component does; installing
one is 3.4.

*Done:* 332 unit tests, 27 browser-JavaScript tests, PHPStan, phpcs and 29 Cypress specs
green - five of them new, and the reason they exist is that the unit suite cannot see the
button. It found the one defect of this step that no other gate could: `writeToDirectory()`
refuses a root that is not there, and the model created that root's *parent*.

**3.3 The metalanguage package, and exporting one.** Meta-gen writes a zip: the concept
model, the generated forms, the reference table, a language file and a manifest naming the
language, its version and its root classifier. Nothing installs it yet.
*Done when* a package round-trips - exported, read back, and the forms in it are the forms
the generator produced.

**Done, and the round-trip is read back two ways.** From the archive and from the tree the
generate screen unpacks beside it, because until now nothing said those were the same
package - which is the kind of thing that stays true until somebody adds a file to one
writer and not the other. The package is

```
manifest.json                 the language, its version, its root classifier, a hash per file
model.json                    the concept model it was generated from
forms/<classifier>.xml        one form per classifier
forms/references.json         the table the reference dropdowns read
language/en-GB/<lang>.ini     every label on those forms
```

**Nothing in it names a component, and that turned out to be the shape of the whole step.**
The decision recorded below settled it for language strings - a package is loaded by
com_extengen *and* com_gengen, so a key naming either is wrong whichever one it names - and
the same argument applies to every path in it. 3.2 wrote every generated file under
`administrator/components/com_metagen/forms/generated/`: the producer's own directory,
inside a file set the producer never reads. Constants are `YEPR_LIONCORE_M3_*` now, scoped
by the language.

**Which forced a decision this step could not defer.** Joomla resolves a subform's
`formsource` as `JPATH_ROOT . '/' . $formsource` and nothing else - `SubformField::__set()`,
and there is no package-relative spelling - so the directory a package will be unpacked
into is baked into the XML when the forms are generated. It is
`media/yepr_metalanguages/<language>/<version>/`: `media/` because it is the one shared site
directory no single extension's uninstall owns, and `<language>/<version>/` because 3.4 has
a project record both, which means nothing unless two versions can sit side by side.
Installing is then a plain unpack with no XML rewritten on the way in, which is what makes
the round-trip worth proving - the forms that run are the bytes that were generated. The
root is *recorded* in the manifest rather than assumed, so an importer that must put a
language elsewhere can see the mismatch and regenerate, instead of unpacking a set of forms
whose subforms all point at a directory that is not there. That is the silent failure this
plan has now met three times, and the spec that would not have caught it is the one that
checks a `formsource` against the file set without checking where it points.

**`addruleprefix` was the last thing in a generated form that named a component**, and it
named one that does not exist: every generated fieldset carried
`Yepr\Component\Metagen\Administrator\Rule`, and this component has never had a `Rule`
directory. A modelled language cannot name a validation rule - that is one of the three
model gaps 4.2 lists - and an attribute pointing at an empty namespace is not a head start
on closing it. It is gone from the generator; the hand-written meta-model still carries it,
where it is equally dead and equally harmless.

**The manifest hashes every other file**, which is the only way *the forms in it are the
forms the generator produced* is a question with an answer rather than an opinion. A reader
that merely finds the files it expects cannot tell a truncated zip from a complete one, and
a form file missing its last bytes still parses far enough for Joomla to render a fieldset
with nothing in it. Both halves were checked by breaking a package rather than by passing:
a changed file and a missing one each produce one exact complaint, and reordering the
target's generators so the manifest runs first is caught as well - it would otherwise
describe an empty package, with every hash in it correct because there would be none.

**And the language file is the other half of 3.2's labels.** Every generated label was a
constant nothing defined, so a generated form showed a person its own constant names in
capitals - no error anywhere, because that is what Joomla does with a string it has not got.
`FormXml` asks for a constant and hands over the words in the same call, which is the only
arrangement in which the two cannot drift, and the suite checks it in both directions: every
constant a form names is defined, and nothing is defined that no form asks for. The
`label` and `description` the meta-model gained are what fills it, falling back to the name -
so `er1.json` and `lioncore-m3.json`, both modelled before there was anywhere to put one,
generate exactly as they did.

**Exporting is the same run to a different destination.** One `package()` on the model,
called by the generate screen and by a new `metalanguage.export` task; two code paths
producing "the package" would be two package formats the day one of them changed. The task
checks a token although it changes nothing, because a GET that runs a whole generation is
worth somebody else's cpu on every image tag pointing at it.

*Done:* 199 unit tests, PHPStan, phpcs, and the browser specs - three of them new, and they
are there for the part no unit test can see: a controller that streams a file and forgets
`$app->close()` appends the administrator template to the bytes it just sent, and the result
is an archive that will not open with nothing reported anywhere.

*Not in 3.3.* Nothing installs a package - that is 3.4, in the two components that consume
one. `PackageReader` lives in Meta-gen and will not stay: a format two components read is a
mechanism, which belongs in `Yepr\Gen\Core` by the argument 3.0 already made, and the
reference dropdown set the precedent for moving it when the second consumer appears rather
than before. A metalanguage has one label per feature and no notion of a translation, so one
`en-GB` file is generated and translating a language is a model gap rather than a format
gap. And the manifest records when it was built, so two exports of one language differ in
that one field - the forms do not.

**And one thing that looked worse than it was.**
`metalanguage-references.cy.js` had been failing *follows a rename without saving* about two
runs in three, across several commits, and the screenshots showed the *dashboard*: rows 7 to
0, subforms 29 to 0, an empty `location.search`. Read straight, that says clearing a field on
the metalanguage edit screen throws away an unsaved model - which would be data loss, and
would have had to be fixed before 3.4 put an import screen on that same screen.

It is not that. Herman could not reproduce it by hand - add, delete and change all behave -
and it has not failed in five consecutive runs since, including one started immediately
after a reinstall. That run is the useful one: it took 19 seconds for the single test against
the usual three. A slow admin page re-rendering the subform between `clear()` and `type()` is
what fits, and the subject being detached is what Cypress actually reported. The spec
re-queries between commands now, which is what its own error message says to do.

Worth writing down anyway, because the symptom pointed hard at the wrong thing: a detached
subject in a repeating subform screenshots as a page that navigated away. Ruling out the
serious reading first cost a day and was still the right order.

*Decided: the package carries its own language file, with keys scoped to the language.*
Not `COM_EXTENGEN_*`, which is what the old Extengen would have needed and the reason it
never generated any: `ProjectForms` took a `LanguageStringUtil` and left it unused, with a
comment saying the strings "should have to be added to the existing ones of this
component". That reason is gone now - a package is consumed by com_extengen *and*
com_gengen, so a key naming one of them is wrong by construction - and the answer is a
key naming the *language*. The consumer loads the file from the package's own path.

**`LanguageStringUtil` is not the mechanism here, and that is structural rather than a
preference.** It is a Twig extension: `addLanguageString` is a Twig *function*, so strings
are collected while templates render, which is why `LanguageFiles` runs last in
`Joomla6Target`. Meta-gen's forms generator renders no templates at all - it builds XML
through DOM, which is the line 2.1 drew - so there is no render pass to collect during.
And `initLangTree()` reads `$AST->extensions->component->languages`, the *project's*
translation list; a metalanguage has no `extensions` node, so the tree comes out empty and
the first call fatals. It stays where it is, generating a component's strings, and Meta-gen
collects its own as it builds each form.

*Decided: the meta-model gains a `label`, and a `description`, on a Feature and a
Classifier.* Optional, falling back to the name. Without it the only human text in a
metalanguage is an identifier, and every generated form would read `isValueObject` where
it should read "Is this a value object?" - so the package format would be fixed around
text nobody chose. It lands before the package rather than after, because adding it later
changes that format.

**3.4 Importing one.** Exten-gen and Gen-gen each grow an import screen and a store, so a
site can hold several metalanguages at once. A project records which language it is written
in; a generator records which language it is *for*, and Exten-gen refuses to run one over a
project in a different language.

*Decided: they sit beside each other, and a project binds to one when it is created.* A
site holds as many metalanguages as have been imported, and starting a new model means
choosing one from a dropdown of them - so the binding is made once, visibly, by the person
making the model, rather than inferred from whatever the component happens to ship. ER1 is
one entry in that list like any other; until 3.5 turns it into a package, the entry is the
forms Exten-gen ships, which is what keeps every existing project openable while the
mechanism lands.

Two things follow. A project row has to carry its language - key and version - because
nothing else can say which forms to open it with. And the dropdown is the join 3.5 needs:
once ER1 arrives as a generated package it becomes another row in the same list, and
nothing above it changes.
*Done when* Exten-gen edits a project through imported forms rather than through forms it
ships, and Gen-gen offers an imported language's concepts when a rule names what to select.

**Exten-gen's half is done; Gen-gen's is not, and that order is forced.** Gen-gen's CI
checks Exten-gen out beside itself and compares against its current `main`, so its side
cannot land until this one has. The library moved first for the same reason: Exten-gen and
Gen-gen both read a package now, so `Package\*` is `Yepr\Gen\Core`'s at **0.5.0** -
written in Meta-gen at 3.3 and moved when there was a second reader, which is the order the
reference dropdown set at 3.0. `PackageReader::model()` returns decoded JSON there rather
than Meta-gen's `ConceptModel`: a library that handed back one consumer's model type would
make every consumer depend on that consumer's idea of what a language is.

**A site holds a catalogue, and ER1 is a row in it.** `MetalanguageCatalogue` answers "what
can a project be written in" with the imported languages *and* the built-in, and everything
above it asks the catalogue rather than asking whether a language was imported - so 3.5
turning ER1 into a package removes one method and changes nothing else. A project records
`metalanguage_key` and `metalanguage_version`; an empty binding reads as ER1, because that
is what every project in every existing database is written in and reading it as "no
language" would have made all of them unopenable.

**`project.xml` is two files now**, and that split is what makes an import possible at all.
`project_chrome.xml` is the half that is a Joomla item - alias, published, access, catid,
ordering, params - which 3.2 recorded as not derivable from a language; `project_er1.xml`
is ER1's model half, in exactly the position an imported language's root classifier form
occupies. `getForm()` loads the chrome and merges one root form onto it, and the built-in
arrives by that same route, so there is no "imported" code path and no "shipped" one.

**Three things only the browser could have found.**

`Form::load()` takes an xpath, and `'/form'` looks like the obvious one to pass. It merges
the `<form>` element *itself* rather than its children, so the result is a form nested
inside a form: the edit screen renders with no fields on it and nothing is reported
anywhere. Passing no xpath is what merges children.

The edit layout rendered `datamodel`, `pages` and `extensions` by name - ER1's own fields -
so a template like that can only ever edit one language however good the model layer is.
It renders an imported language's fields generically now, skipping the ones the chrome
already drew. ER1 keeps its three tabs behind a branch that goes at 3.5.

And **a project is created, then modelled.** Choosing the language on a form that is
already showing another language's model half does not work: those fields carry `required`,
and Joomla's validator refuses the save over fields belonging to a language the project is
not going to be written in. A new project gets the chrome alone - which is what binding *at
creation* actually means, rather than binding while pretending to model.

**One trap worth writing down, which cost half an hour.** Joomla never re-runs an update
file it has already applied: `#__schemas` records the last version applied per extension, so
editing `1.1.0.sql` after a site has run it is invisible on that site. No real site has run
this one - 1.1.0 was never released - but this machine's had, from the hours 3.0 spent at
1.1.0. It is the sharper form of the trap already in `docs/development.md` about install SQL
not re-running.

**The version goes back up.** 3.0 held 1.1.0 because the update script drops
`#__extengen_projectforms` and there was nowhere for that data to go; Meta-gen exports a
package now and this imports one, so the condition the plan set is met. `src/extengen.xml`
says 1.1.0 and `updates.xml` is regenerated to match, which means the tag should follow the
push rather than wait - an update server offering a version with no release is the failure
1.12 added a test for.

*Done:* 255 unit tests, PHPStan, phpcs and 24 Cypress specs green - seven of them new. The
one that matters is the step's own criterion: a project bound to an imported language
renders that language's fields, with that language's labels out of the package's own
language file, and none of ER1's. Nothing in this component knows those field names.

**Gen-gen's half, and 3.4 is done.** A generator is written *for* a metalanguage the same
way a project is written *in* one: same store, same import screen, same two columns. What
differs is that Gen-gen ships no language of its own, and that is an answer rather than a
gap - a generator written before 3.4 names selectors from its target's vocabulary,
`Joomla6Selectors` hard-keyed to ER1, and every one of them keeps working. So the dropdown
offers "no metalanguage" first and means it.

The step's own criterion is `RuleSelectorField`: when a language is bound, a rule's *what to
select* offers that language's concepts instead of the target's selectors. Gen-gen reads
them from the package's manifest and never from the concept model - how a language is stored
is Meta-gen's business, and teaching a third component would be teaching it to three. The
value stored is the concept's **key** and the label is its name, because a rule that stored
the name would come unpicked the moment somebody renamed a concept, silently, since it would
still be a string and still look like one.

**The store is the library's now**, at 0.6.0, for the reason two components keeping one kind
of list always means: `Joomla\Metalanguage` holds Entry, Catalogue, Installer and Importer,
and what each component passes in is its table and whatever language it ships. Exten-gen's
four copies are gone. The library's entry no longer knows what ER1 is, so 3.5 turning it into
a package is one argument fewer at one call site.

**Gen-gen 0.1.0 could not save a generator at all**, and that had nothing to do with this
step. `checked_out` arrives from the form as an empty string, `checked_out` is an unsigned
int, and MySQL in strict mode refuses the row - so every save from the edit screen failed
with the edit still on screen. The form already declares `filter="unset"` on it, which is
what core components do, and Joomla's own `UnsetFilter` returns null; the value arrives as
`''` anyway. The model drops it rather than sending an empty string to an integer column.

Nothing had noticed because nothing had ever saved a generator: the browser spec opened every
screen and read it, and every other test works on stored JSON directly. Reading a screen is
not using it, which is the same lesson 3.2 learned about a button nobody pressed - one step
further in.

**Two things the specs taught about their own hygiene.** The first version of the new spec
bound the *seeded* generator, saved it through the form and put it back - and saving a
generator through the form rewrites its stored JSON from what the form posted, which is not
what was seeded. `component-renders.cy.js` then failed on rules that had quietly changed
shape, and the fix was to re-seed and to stop writing shared fixture state: the spec makes
its own generator now, under a name unique per run, because two runs leaving two rows with
one name means finding it again by name picks the wrong one.

*Done:* generator-core 0.6.0 with 155 unit tests; Meta-gen 194; Exten-gen 245 and 24 Cypress
specs; Gen-gen 48 and 9 Cypress specs - PHPStan and phpcs green in all four. The criterion
is checked in a browser on both sides: a project bound to an imported language renders that
language's fields with that language's labels, and a generator bound to one offers that
language's concepts where a rule says what to select.

*Not done, and moved to 3.6:* Exten-gen refusing to run a generator over a project in a
different language. Both halves now record a language, so the check is a comparison - but a
generator's selectors are still `Joomla6Selectors`, PHP hard-keyed to ER1, so today every
modelled generator is honestly written for ER1 whatever its binding says. Refusing on a
binding that nothing downstream reads would be theatre. 3.6 makes selectors a path through a
modelled language, and the refusal belongs with it.

**3.5 ER1 as a package, and the round-trip proof.** Model ER1 in LionCore M3, generate its
forms, and compare against Exten-gen's hand-written ones as golden files - then keep the
generated set, which makes this the migration rather than only a proof. The table of six
differences under 3.2 is what has to be reconciled, and each one is a decision: presentation
attributes the model cannot hold, the Joomla item chrome on a root form, how language
strings are named, and three places where the hand-written files are internally
inconsistent.

**Started: ER1 is modelled, and the differences are an inventory rather than an argument.**
`tools/import-forms.php` in Meta-gen reads a set of Joomla forms back into a language, and
`tests/Fixtures/languages/er1-full.json` is what it made of Exten-gen's twenty-six files: 19
classifiers, 4 datatypes, `Project` at the root, and `Field` abstract over `Property` and
`EntityReferenceField`.

**Why a reader rather than typing it.** ER1 is about a hundred and fifty fields. Hand-written
JSON of that size is something nobody reviews and everybody trusts. What the reader produces
can be read beside the files it came from - and it says out loud every judgement it makes,
so the notes are as much the output as the model is.

**What the round trip does and does not prove.** Generating forms from a model derived *from*
forms shows the two are inverses; it does not show the model is a good one. Two things keep
it honest: the input is hand-written, so anything the generator does differently shows up as
a difference instead of being absorbed, and the reader is deliberately naive, so a generator
convention cannot be quietly encoded in it to make a diff go away. `Er1ModelTest` pins the
model rather than the reader, because the reader ran once and the model is what ships.

**Two findings, and the second is the one worth having.**

*Five of ER1's twenty-six forms are dead.* `pages.xml` is pointed at by nothing, and it is the
root of a subtree of four more - `indexpage.xml`, `detailspage.xml` and their two customcode
forms. The root form reaches `page.xml`, singular, which is a different design. So the
language is 21 forms, and the generated set is not missing anything. This is 3.1's finding in
a new place, and Exten-gen has no test for reachability the way Meta-gen's `FormsTest` does;
adding one belongs here.

*`showon` means two different things, and only one of them is subtyping.* `field.xml` offers
property or reference and answers each with exactly one subform: every choice covered, none
shared, the subform *is* the content for that kind. That is a classifier with two subtypes.
`page.xml` offers five kinds of page and then shows `filters` and `presentationcolumns` for
an index page and `editfields` for two of the others - which are not kinds of page at all,
but ordinary containments that only apply sometimes. A page still has a name and an entity
whichever it is.

The reader calls it subtyping only when the conditional subforms *partition* the choices, and
reports the rest as a gap - because **LionCore M3 as modelled here cannot say "only when"**. A
feature has no condition. That is a fourth model gap to set beside the three under 4.2, and
it is the first one found by trying to model something real rather than by looking for them.

**The rest of 3.5, as an inventory.** Comparing the generated set against the hand-written one
sorts every difference into six kinds, and each is now a decision with a number on it rather
than a category:

| Difference | Count | What it needs |
|---|---|---|
| ~~Presentation the model cannot hold~~ | ~~62~~ | **Done, as a defaults table.** See below - the 62 was three different things |
| A plain-text label became a language-scoped constant | 44 | Nothing: this is 3.3's decision working. The text moves into the package's `.ini` |
| `COM_EXTENGEN_*` became `YEPR_ER1_*` | 10 | Nothing, same decision - but the forty-odd existing strings need carrying across |
| A generated `LIonWeb_key` per form | 20 | Nothing: it is how a stored node says what it is |
| A field changed type: `editor`, `Slot`, `HtmlTypes` | 3 | The custom-field-type gap 4.2 already names |
| ~~A closed list became a text box~~ | ~~3~~ | **Done.** `page_type`, `link_type` and a Property's `type` are enumerations, and generate the dropdown they came from |
| ~~The identity property renders as a text box, not `hidden`~~ | ~~5~~ | **Done.** A property says whether its value is assigned or entered |

**Presentation is the generator's, not the model's**, and counting it properly showed the 62
was three different things.

*Uniform by kind, and now in `Presentation`.* Every reference dropdown and every closed list
carries the same tint, a repeating subform gets add, remove and move, a single one gets a
layout and no buttons. Those were spelled out inside `FormXml` beside the fields they applied
to - which works, and means nobody can answer "what does a generated form look like" without
reading the whole emitter. Now they are one file.

*Per-field, and lost on purpose.* Sixteen `size` attributes running 60, 40, 20, 2 and 1;
fourteen `min`s on seventeen of thirty-five subforms. Somebody made each of those one at a
time. A table guessing one number would make sixteen fields differently wrong instead of
uniformly plain, so it guesses none. That is what choosing not to model presentation costs,
and it is the cost that was chosen.

*Not differences at all.* Twelve were `required="false"`, which means exactly what leaving
the attribute out means. The comparison was over-reporting, and a number nobody checked would
have made the remaining work look half again as large as it is.

**And one that is not presentation, found by counting - now done.** Seven were default
*values*: `default="detailspage"` on a page's type, `default="NOW"` on a creation date,
`default="en"` on a language code. A property carries one now.

The line between this and `Presentation` is whether another target would want it. "A new page
is a detail page unless you say otherwise" is part of what the language means and is the same
wherever it is generated to; `size="40"` is about this Joomla form and nothing else. It sits
on the property rather than the feature, because a default is a value of the property's
datatype - what a link would default to is a reference to something that does not exist yet.

The model checks one thing about itself here: a default on a closed list is one of that
list's own answers. A default naming something the list does not offer renders as nothing
selected, which looks exactly like having no default - invisible on screen, and surviving
until somebody saves a row without touching the field.

**The identity row is fixed**, because it was the only one that made a generated form *wrong*
rather than different. "Hide the identity" is the obvious rule and it is wrong: LionCore M3's
identity is `key` and a person types it into a visible field, while ER1's `entity_id` is a
surrogate nobody enters. Hiding is what *assigned* means, not what identity means, and only
the language knows which a property is - so a Feature carries `is_assigned`, and that is a
fact about the language rather than presentation. `MetaFormsTest` guards the other direction,
so the fix cannot be "corrected" later into hiding every identity.

Splitting the type row in two was the other thing that fell out: three of those nine are
custom field types and belong to 4.2, and three were closed lists the reader flattened to
String when the generator can already emit an Enumeration. Those three are **done** - the
literals keep each option's own value and text, so `page_type` generates ER1's five kinds of
page in order, with its own wording. Three type differences remain, all custom field types.


**3.6 Selectors as data.** `Joomla6Selectors::entities()` is PHP hard-keyed to ER1. A
generator written for an arbitrary metalanguage needs a selector that is a path through
*that* language's concept model, so `Vocabulary` gains a source half and the rule engine
learns to walk one. The largest item in this stage, and it reaches generator-core and
Gen-gen rather than only the two components.

**3.7 Package and release Meta-gen.** 0.1.0 is unreleased: the repository exists, the
gates run, and nothing is published yet.

---

## Stage 4 — convergence

**4.1 Plug-gen adopts the core**, dropping its private copy.

**4.2 Close the model gaps that block self-hosting.** The three named under Stage 1: a
custom form field type, a custom validation rule, and tabs or subform layouts on a
generated form. Re-measure first - 1.9 may have removed most of the need - then add only
what is still missing.

**4.3 Self-hosting.** Exten-gen generates Exten-gen. Everything it needs exists by now:
the engine from Stage 0, working generation from Stage 1, modelled generators from Stage
2, generated forms and an imported metalanguage from Stage 3, and the model gaps closed in
4.2. The criterion is
byte-identical output against the hand-written component, the same way 2.3 checks a
modelled generator.

**4.4 A second target**, Drupal or WordPress, which is the real proof that 0.4 was done
correctly.

---

## Decisions outstanding

Each is flagged at the step where it bites.

| Step | Decision |
|---|---|
| 1.1 | Whether to filter `testForm.json` out of the history during the mirror push |

## Suggested entry point

**0.1.** Self-contained, unblocks everything downstream, and touches nothing the current
component depends on.
