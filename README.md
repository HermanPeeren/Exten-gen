# Exten-gen

Exten-gen is a Joomla component that models CMS extensions and generates them. A
model here is what Model Driven Engineering means by the word: a complete
description of the thing you want — its data model, its pages, and what kind of
extension it is — stated in the vocabulary of the domain rather than in PHP, and
precise enough that the implementation can be derived from it.

The model is collected through Joomla forms and stored as JSON. Generators turn
that JSON into a complete, installable extension.

Note the collision with Joomla's own vocabulary: this is not a Model in the MVC
sense, the class that fetches data for a view. That is one layer of one
extension, while a model here describes an entire extension.

Models are called **projects**, and a project is in three parts:

- the **data model** — entities, their fields, and the relations between them;
- the **pages** that interact with those data;
- the **extension** — what kind it is, which pages appear in the front end and
  the back end, and the manifest information. Only this part is Joomla-specific.

## Where this came from

Exten-gen succeeds [Extengen](https://github.com/HermanPeeren/Extengen), whose
history it carries. Before that the same ideas were an XText language, eJSL, in
[JooMDD](https://github.com/HermanPeeren/JooMDD), and then a port to JetBrains
MPS in [eJSL-MPS](https://github.com/HermanPeeren/eJSL-MPS).

It is one of a family:

| | |
|---|---|
| [generator-core](https://github.com/HermanPeeren/generator-core) | the shared generation engine, `Yepr\Gen` |
| **Exten-gen** | models extensions and generates them |
| Gen-gen | models the generators themselves |
| Meta-gen | models the model language, and generates the forms that collect it |
| [Plug-gen](https://github.com/HermanPeeren/plug-gen) | plugin types, developed separately first |

## Status

Under construction, and not production ready. The rework it is going through is
written down step by step in [docs/rework-plan.md](docs/rework-plan.md); this
repository is the result of step 1.1.

The 2025 status note that used to be this README is kept as
[docs/extengen-status-2025.md](docs/extengen-status-2025.md), because it records
what was planned at the time and why.

## Layout

`src/` is the installable package: every file sits at the path it will occupy on
a Joomla site.

```
src/
  extengen.xml                                the manifest the installer reads
  script.php                                  the install script
  administrator/components/com_extengen/      the component
  media/com_extengen/                         js
  libraries/yepr/                             Twig, until the shared library replaces it
tests/                                        the suite
build/build.php                               assembles the installable zip
docs/                                         how to work on it, and the plan
```

## Development

```
composer install
composer test           # phpunit
composer analyse        # phpstan
composer cs             # phpcs
php build/build.php     # -> build/com_extengen-<version>.zip
```

Requires PHP 8.3 or later, which is Joomla 6's minimum.

How the component is put together and how to work on it:
[docs/development.md](docs/development.md).

## Licence

GNU General Public License version 3 or later; see LICENSE.
