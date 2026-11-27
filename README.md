# Psalm plugin for Nette

Tells [Psalm](https://psalm.dev)'s unused code detection what [Nette](https://nette.org), [Nette Tester](https://tester.nette.org) and the DI container call in your application.

[![PHP Tests](https://github.com/spaze/psalm-plugin-nette/actions/workflows/php.yml/badge.svg)](https://github.com/spaze/psalm-plugin-nette/actions/workflows/php.yml)

Psalm 7 reports a class, a method or a property as unused when nothing that is itself used leads to it. Nette calls a lot of code by its name, like a presenter's `actionDefault()`, and creates services from a config file, and Psalm sees none of that. Without the plugin, most of a Nette application is reported as unused. Suppressing the reports doesn't help much: a suppressed method is still unused for Psalm, so it goes on to report what the method calls.

The plugin records these calls for Psalm, so that what's left in the report is the code nothing uses.

This is a 0.x release. It knows the parts of Nette listed below, and a minor version may change what is reported.

## Installation

```bash
composer require --dev spaze/psalm-plugin-nette
```

It needs PHP 8.2 or newer and Psalm 7.12 or newer, installed either as `vimeo/psalm` or as `psalm/phar`.

## Enabling the plugin

How the plugin is enabled depends on how Psalm is installed.

**`vimeo/psalm`**, in the same `composer.json` as the plugin:

```bash
vendor/bin/psalm-plugin enable spaze/psalm-plugin-nette
```

**`psalm/phar`**, in the same `composer.json` as the plugin and the application. There's no `psalm-plugin` command, add the plugin to `psalm.xml` by hand:

```xml
<plugins>
	<pluginClass class="Spaze\Psalm\Nette\Plugin"/>
</plugins>
```

**`psalm/phar` installed elsewhere**, for example in a directory with development tools that has a `composer.json` of its own. Psalm then loads plugins before it can find the plugin's class, so give it the path to the file, from the directory with `psalm.xml`:

```xml
<plugins>
	<plugin filename="../tools/vendor/spaze/psalm-plugin-nette/src/Plugin.php"/>
</plugins>
```

Psalm finds `nette/neon`, which the plugin reads the config files with, in the application's dependencies then. An application that uses the Nette DI container has it.

Unused code detection has to be on, with `findUnusedCode="true"` in `psalm.xml` or with `--find-unused-code`.

## What the plugin tells Psalm

### Presenters and components

- `action<Action>()` and `render<View>()` methods of presenters
- Methods of presenters whose name starts with `inject`
- `render()` and `render<Mode>()` methods of controls
- `handle<Signal>()` methods of presenters and controls
- `createComponent<Name>()` methods of presenters and controls, also the protected ones

Nette doesn't call a method that isn't public, with the exception of a protected `createComponent<Name>()`, nor a static `action`, `render` or `handle` method, so those are still reported.

### Templates

Public properties of the classes that extend `Nette\Bridges\ApplicationLatte\Template` are used: Latte templates get all of them. Psalm doesn't analyze Latte templates, so neither Psalm nor the plugin knows which of them a template really reads.

### Nette Tester

- `test*()` methods of test cases, those Nette Tester runs
- Methods that provide data to tests, named by a `@dataProvider` annotation or a `#[DataProvider]` attribute
- The constructor of a test case. Psalm sees `new FooTest()`, it doesn't see a test case created by a DI container with its dependencies.

### Services

The plugin reads the `services` section of every `config/*.neon` file, from the directory Psalm resolves paths from, which is the one you run Psalm in, or the one with `psalm.xml` when it has `resolveFromConfigFile="true"`.

```neon
services:
	- App\Mailer                         # the class, and its constructor
	queue:
		create: App\Queue                # the same, also with the older "factory" key
	- App\Clock::fromSystem()            # the static method
	- @App\RouterFactory::createRouter   # the method of the service
	reports: App\ReportFactory
	- @reports::create()                 # the method of the service with that name, also in another config file
	- App\Summary(@reports::getTitle())  # the method called for an argument
	- App\MenuFactory                    # for an interface the container implements, the constructor of what create() returns
	console:
		type: App\Console                # only the class, the container doesn't create a service that only has a type
```

### Methods an interface declares with an annotation

Nette adds a method to an interface with a `@method` annotation when declaring it would break existing code, like `deleteHeader()` of `Nette\Http\IResponse`. Psalm doesn't link a call of such a method to the classes that implement the interface. With the plugin, a method that implements an annotated method of an interface is used, the same as one that implements a declared method.

## What the plugin doesn't know

- Config files elsewhere than in `config/`, and the files they include. The location can't be changed yet.
- `setup` calls, the `search` section, services added by extensions, `inject` enabled for other services than presenters, and the `get()` method of an accessor interface.
- What Latte templates call. A method called only from a template is reported, for example a getter of an object passed to it.
- Persistent parameters and components.

## Psalm's cache

Psalm keeps what the plugin has recorded in its cache. The plugin records what Nette calls for the file that declares it, so the record is dropped and made again whenever Psalm analyzes the file again, and renews what the config files use on every run. A service removed from a config file is reported as unused in the next run, with or without the cache. Only what a deleted config file used stays used until the cache is cleared with `--clear-cache`.

## Development

```bash
composer test
```

runs the linter, PHP_CodeSniffer, PHPStan, Psalm, and the acceptance tests, once with Psalm installed from source and once with `psalm.phar`. See [AGENTS.md](AGENTS.md) for more.
