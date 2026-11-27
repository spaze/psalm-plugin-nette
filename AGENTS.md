# AGENTS.md

This file provides guidance to coding agents when working with code in this repository.

## Commands

```sh
composer test              # everything below, in this order
composer lint              # parallel-lint
composer phpcs             # code style check
composer cs-fix            # auto-fix code style
composer phpstan           # static analysis
composer psalm             # Psalm on the plugin itself
composer acceptance        # the scenarios with Psalm installed from source, the plugin loaded by its class name
composer acceptance-phar   # the scenarios with psalm.phar installed in tests/_phar, the plugin loaded by the path to its file
```

Run one feature or one scenario, add `--env phar` for `psalm.phar`:
```sh
vendor/bin/codecept run acceptance Services.feature
vendor/bin/codecept run acceptance "Services.feature:The container doesn't create a service that only has a type"
```

A failed scenario leaves its files in `tests/_run`, with the `psalm.xml` it ran with, so Psalm can be run there by hand.

## What the plugin does

Psalm 7 finds unused code by reachability: a class, method or property is used only when something that is itself used leads to it, starting from top-level code, functions, code outside the project and the public API. Suppressing an unused-code issue doesn't make the code a starting point. The plugin adds the starting points Psalm can't see, what Nette and Nette Tester call by name and what the DI container creates from its config. `README.md` lists them for users, the features in `tests/acceptance` are the specification.

## Constraints that are not obvious from the code

**One class in one file.** `Plugin` is both the entry point Psalm calls for `<pluginClass>` and the class with the hooks, which is what Psalm registers for `<plugin filename>`. The second way is the only one that works when `psalm.phar` is installed elsewhere than the analyzed project's dependencies, because Psalm loads plugins before the autoloader the config names. The path `src/Plugin.php` is in users' `psalm.xml`, so moving or renaming the file breaks them. A plugin loaded by its file gets no settings from `psalm.xml`, so whatever gets configurable needs a default that works without them.

**Only classes in the `Psalm` namespace.** `psalm.phar` has the libraries Psalm uses under a prefix, `PhpParser\Node` is `PsalmPhar\PhpParser\Node` there. Code that names such a class works with only one of the two ways Psalm is installed. Calling methods on the nodes Psalm hands over is fine, naming their classes is not, in `use`, type declarations or `instanceof`.

**What is used is recorded for a file, not for a framework method.** `Codebase::methodExists()` and the `addReferenceTo*()` methods take the file the reference comes from. For what Nette calls by name it's the file that declares the class, recorded while Psalm analyzes it: Psalm's cache keeps the record while the file doesn't change, and drops it when it analyzes the file again, when the hook makes it again. A record that comes from a method outside the project, which is what Psalm's own PHPUnit plugin does, is never dropped, so a method that stops being called stays used until the cache is cleared.

**Config files are not analyzed by Psalm, so nothing drops what they used.** `afterCodebasePopulated()` drops it itself before recording the services again, with a method of an internal Psalm class. That, and getting the storage of an interface, are the two places that go past Psalm's public API, each has a suppression that says why. If Psalm gets a public way, use it.

**References made in `afterCodebasePopulated()` from a file Psalm is about to analyze are dropped** before the analysis starts. That hook is only good for references from files Psalm doesn't analyze, the config files.

## Versions

`psalm/psalm-plugin-api` in `composer.json` is the version of Psalm's plugin API, Psalm's README for it says plugins must require it, and `vimeo/psalm` requires the exact version it has. `psalm/phar` requires nothing, so the `conflict` with it states the same oldest Psalm version again. The oldest version is also in the test matrix in `.github/workflows/php.yml`, and in `tests/_phar/composer.json`. Change them together.

## Tests

The acceptance tests are [Codeception](https://codeception.com) Gherkin features run with [`weirdan/codeception-psalm-module`](https://github.com/weirdan/codeception-psalm-module), like Psalm's own plugin skeleton. Each scenario writes its files to `tests/_run` and runs Psalm there. The steps this repository adds are in `tests/_support/Helper/Acceptance.php`: enabling the plugin the way the Psalm being tested can load it, writing a file to `config/`, and running Psalm without the cache, or twice with it.

A scenario that says something is used should have a counterpart that says what is not, with the names that almost match, `renderer()` next to `render()`. Check a new rule by breaking it: the suite has to fail when the rule or one of its conditions is removed.

The module matches an expected message anywhere in the reported one, so `App\Foo::action` also matches `App\Foo::actionDefault`. Write the message as a regular expression that ends with `$` when one name is the start of another, with four backslashes for one in a class name.

`tests/_phar` is a Composer project that installs the plugin from the repository root next to `psalm/phar`, as an application using both would. It has no Psalm installed from source, the two can't share dependencies.
