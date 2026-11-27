<?php
declare(strict_types = 1);

namespace Spaze\Psalm\Nette;

use Nette\Neon\Entity;
use Nette\Neon\Neon;
use Override;
use Psalm\Codebase;
use Psalm\Plugin\EventHandler\AfterClassLikeAnalysisInterface;
use Psalm\Plugin\EventHandler\AfterCodebasePopulatedInterface;
use Psalm\Plugin\EventHandler\Event\AfterClassLikeAnalysisEvent;
use Psalm\Plugin\EventHandler\Event\AfterCodebasePopulatedEvent;
use Psalm\Plugin\PluginEntryPointInterface;
use Psalm\Plugin\RegistrationInterface;
use Psalm\Storage\ClassLikeStorage;
use Psalm\Type\Atomic\TNamedObject;
use Psalm\Type\Union;
use SimpleXMLElement;

/**
 * Tells Psalm's unused code detection about the calls Nette, Nette Tester and the DI container make to the code of an application.
 *
 * What the frameworks call by name, like a presenter's action method or a test method, is recorded as used by the file that declares it,
 * and a service as used by the config file that defines it: what a file uses is never unused. Psalm drops what a file uses when it
 * analyzes the file again, so the record is renewed with the code and its cache doesn't keep it for code that has changed.
 *
 * The class is both the entry point and the hooks, so that the plugin can be loaded by the class name (<pluginClass>) and by the path
 * to this file (<plugin filename>), the only way when psalm.phar is installed elsewhere than the analyzed project's dependencies.
 * It names only the classes in the Psalm namespace, not those of the libraries Psalm uses: psalm.phar has them under different names.
 *
 * @api
 */
final class Plugin implements PluginEntryPointInterface, AfterClassLikeAnalysisInterface, AfterCodebasePopulatedInterface
{

	/** Where the DI container config files are, from the directory Psalm resolves paths from, usually the one with psalm.xml */
	private const CONFIG_FILES = 'config/*.neon';

	private const VISIBILITY_PUBLIC = 1;
	private const VISIBILITY_PRIVATE = 3;

	private const TEST_CASE = 'Tester\TestCase';
	private const DATA_PROVIDER_ATTRIBUTE = 'Tester\Attributes\DataProvider';
	private const PRESENTER = 'Nette\Application\UI\Presenter';
	private const CONTROL = 'Nette\Application\UI\Control';
	private const COMPONENT = 'Nette\Application\UI\Component';
	private const CONTAINER = 'Nette\ComponentModel\Container';
	private const TEMPLATE = 'Nette\Bridges\ApplicationLatte\Template';


	#[Override]
	public function __invoke(RegistrationInterface $registration, ?SimpleXMLElement $config = null): void
	{
		$registration->registerHooksFromClass(self::class);
	}


	#[Override]
	public static function afterStatementAnalysis(AfterClassLikeAnalysisEvent $event): ?bool
	{
		$codebase = $event->getCodebase();
		$storage = $event->getClasslikeStorage();
		$file = $storage->location?->file_path;
		if ($file === null) {
			return null;
		}

		foreach (self::getCalledMethods($event) as $method) {
			$codebase->methodExists($storage->name . '::' . $method, source_file_path: $file);
		}
		if (self::isSubclassOf($codebase, $storage, self::TEMPLATE)) {
			// Template::getParameters() reads every public property of the template object to pass it to the Latte template
			foreach ($storage->properties as $name => $property) {
				if ($property->visibility === self::VISIBILITY_PUBLIC) {
					$codebase->addReferenceToProperty(strtolower($storage->name), $name, true, file_path: $file);
				}
			}
		}
		return null;
	}


	/**
	 * @psalm-suppress InternalMethod Psalm has no public way to drop what a file that it doesn't analyze has used
	 */
	#[Override]
	public static function afterCodebasePopulated(AfterCodebasePopulatedEvent $event): void
	{
		$codebase = $event->getCodebase();
		$files = glob(rtrim($codebase->config->base_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . self::CONFIG_FILES);
		$services = [];
		foreach ($files === false ? [] : $files as $file) {
			// Psalm keeps what a file uses in its cache until it analyzes the file again, which it never does with a config file,
			// so what the file used the last time is dropped here, or a service removed from the file would stay used
			$codebase->code_use_graph->removeReferencesFromFile($file); // @phpstan-ignore method.internalClass (Psalm has no public way to do it)
			$config = Neon::decodeFile($file);
			if (is_array($config) && isset($config['services']) && is_array($config['services'])) {
				$services[$file] = $config['services'];
			}
		}

		// A service can be defined in one file and referenced by its name in another
		$classes = [];
		foreach ($services as $definitions) {
			foreach ($definitions as $name => $definition) {
				if (is_string($name)) {
					$classes[$name] = [...$classes[$name] ?? [], ...self::getServiceClasses($codebase, $definition)];
				}
			}
		}

		foreach ($services as $file => $definitions) {
			foreach ($definitions as $definition) {
				foreach (self::getCreators($definition) as $creator) {
					self::callCreator($codebase, $creator, $file);
				}
				foreach (self::getServiceClasses($codebase, $definition) as $class) {
					$codebase->addReferenceToClass(strtolower($class), file_path: $file);
				}
				foreach (self::getServiceMethodCalls($definition) as [$service, $method]) {
					foreach ($codebase->classOrInterfaceExists($service) ? [$service] : $classes[$service] ?? [] as $class) {
						$codebase->methodExists($class . '::' . $method, source_file_path: $file);
					}
				}
			}
		}
	}


	/**
	 * @return list<string> the methods of the class that Nette and Nette Tester call
	 * @psalm-suppress InternalMethod Psalm's own plugins get the storage of a class this way
	 */
	private static function getCalledMethods(AfterClassLikeAnalysisEvent $event): array
	{
		$codebase = $event->getCodebase();
		$storage = $event->getClasslikeStorage();
		$isTestCase = self::isSubclassOf($codebase, $storage, self::TEST_CASE);
		$isPresenter = self::isSubclassOf($codebase, $storage, self::PRESENTER);
		$isControl = self::isSubclassOf($codebase, $storage, self::CONTROL);
		$isComponent = self::isSubclassOf($codebase, $storage, self::COMPONENT);
		$isContainer = self::isSubclassOf($codebase, $storage, self::CONTAINER);

		$called = $isTestCase ? self::getDataProviders($event) : [];
		foreach ($storage->methods as $method) {
			$name = (string)$method->cased_name;
			$public = $method->visibility === self::VISIBILITY_PUBLIC;
			// Nette refuses to call a method by its name unless it's public and not static
			$callable = $public && !$method->is_static;
			if ($isTestCase && $public) {
				// Something creates the test case, often not with a "new" Psalm would see but for example a container giving it its dependencies,
				// then TestCase::run() runs the methods matching #^test[A-Z0-9_]#
				$after = $name[4] ?? '';
				$isTest = str_starts_with($name, 'test') && (ctype_upper($after) || ctype_digit($after) || $after === '_');
				if ($name === '__construct' || $isTest) {
					$called[] = $name;
				}
			}
			// Presenter::run() calls 'action' . ucfirst($action) and 'render' . ucfirst($view)
			if ($isPresenter && $callable && (self::isNamed($name, 'action') || self::isNamed($name, 'render'))) {
				$called[] = $name;
			}
			// The container calls every public method that starts with inject, presenters have that enabled
			if ($isPresenter && $public && str_starts_with($name, 'inject')) {
				$called[] = $name;
			}
			// {control name} in a Latte template calls render(), {control name:mode} calls 'render' . ucfirst($mode)
			if ($isControl && !$isPresenter && $callable && ($name === 'render' || self::isNamed($name, 'render'))) {
				$called[] = $name;
			}
			// Component::signalReceived() calls 'handle' . $signal
			if ($isComponent && $callable && self::isNamed($name, 'handle')) {
				$called[] = $name;
			}
			// Container::createComponent() calls 'createComponent' . ucfirst($name), only for a name that ucfirst() changes
			$component = str_starts_with($name, 'createComponent') ? substr($name, 15) : '';
			if ($isContainer && $method->visibility !== self::VISIBILITY_PRIVATE && lcfirst($component) !== $component) {
				$called[] = $name;
			}
		}

		foreach ($storage->class_implements as $interface) {
			if (!$codebase->classOrInterfaceExists($interface)) {
				continue;
			}
			// A method an interface declares just with a "method" annotation, as Nette does to add one without a BC break,
			// is called through the interface like those it declares for real, but Psalm doesn't link it to the classes implementing it
			$annotated = $codebase->classlike_storage_provider->get($interface)->pseudo_methods; // @phpstan-ignore method.internalClass (Psalm's own plugins get the storage of a class this way)
			foreach ($annotated as $name => $_) {
				if (isset($storage->methods[$name])) {
					$called[] = (string)$storage->methods[$name]->cased_name;
				}
			}
		}
		return $called;
	}


	/**
	 * @return list<string> the methods of the test case that provide data to its tests, named by a DataProvider attribute or annotation
	 */
	private static function getDataProviders(AfterClassLikeAnalysisEvent $event): array
	{
		$storage = $event->getClasslikeStorage();
		$providers = [];
		foreach ($storage->methods as $method) {
			foreach ($method->attributes as $attribute) {
				if (strcasecmp(ltrim($attribute->fq_class_name, '\\'), self::DATA_PROVIDER_ATTRIBUTE) !== 0) {
					continue;
				}
				foreach ($attribute->args as $position => $arg) {
					$isProvider = $arg->name === 'provider' || ($arg->name === null && $position === 0);
					if ($isProvider && $arg->type instanceof Union && $arg->type->isSingleStringLiteral()) {
						$providers[] = $arg->type->getSingleStringLiteral()->value;
					}
				}
			}
		}
		foreach ($event->getStmt()->getMethods() as $method) {
			$docComment = $method->getDocComment();
			if ($docComment === null) {
				continue;
			}
			foreach (explode("\n", $docComment->getText()) as $line) {
				$words = preg_split('/\s+/', trim($line, " \t\r/*"), -1, PREG_SPLIT_NO_EMPTY);
				if ($words !== false && isset($words[0], $words[1]) && strcasecmp($words[0], '@dataProvider') === 0) {
					$providers[] = $words[1];
				}
			}
		}
		// A provider can also be a file with the data, that's not a method the class has
		return array_values(array_filter(
			$providers,
			fn(string $provider): bool => isset($storage->declaring_method_ids[strtolower($provider)]),
		));
	}


	/**
	 * @return list<string> what the container calls to create the service: a class name, Class::method, @service::method or @service
	 */
	private static function getCreators(mixed $definition): array
	{
		if ($definition instanceof Entity) {
			$definition = $definition->value;
		}
		if (is_string($definition)) {
			return [$definition];
		}
		$creators = [];
		if (is_array($definition)) {
			foreach (['create', 'factory', 'implement'] as $key) {
				if (isset($definition[$key])) {
					$creators = [...$creators, ...self::getCreators($definition[$key])];
				}
			}
		}
		return $creators;
	}


	/**
	 * @return list<string> the classes and interfaces the definition names as what creates the service, or as its type
	 */
	private static function getServiceClasses(Codebase $codebase, mixed $definition): array
	{
		$classes = self::getCreators($definition);
		if (is_array($definition) && isset($definition['type']) && is_string($definition['type'])) {
			$classes[] = $definition['type'];
		}
		return array_values(array_filter($classes, fn(string $class): bool => !str_starts_with($class, '@') && !str_contains($class, '::') && $codebase->classOrInterfaceExists($class)));
	}


	/**
	 * @return list<array{string, string}> the service, by its name or class, and the method of each @service::method call anywhere in the definition
	 */
	private static function getServiceMethodCalls(mixed $definition): array
	{
		if ($definition instanceof Entity) {
			return [...self::getServiceMethodCalls($definition->value), ...self::getServiceMethodCalls($definition->attributes)];
		}
		if (is_string($definition)) {
			$separator = strpos($definition, '::');
			return str_starts_with($definition, '@') && $separator !== false
				? [[substr($definition, 1, $separator - 1), substr($definition, $separator + 2)]]
				: [];
		}
		$calls = [];
		if (is_array($definition)) {
			foreach ($definition as $value) {
				$calls = [...$calls, ...self::getServiceMethodCalls($value)];
			}
		}
		return $calls;
	}


	private static function callCreator(Codebase $codebase, string $creator, string $file): void
	{
		if (str_starts_with($creator, '@')) {
			return;
		}
		if (str_contains($creator, '::')) {
			$codebase->methodExists($creator, source_file_path: $file);
			return;
		}
		if (!$codebase->classOrInterfaceExists($creator)) {
			return;
		}
		if (!$codebase->interfaceExists($creator)) {
			$codebase->methodExists($creator . '::__construct', source_file_path: $file);
			return;
		}
		if ($codebase->methodExists($creator . '::create')) {
			// The container generates the class implementing a factory interface, and its create() creates what the interface says it returns
			$selfClass = $creator;
			foreach ($codebase->getMethodReturnType($creator . '::create', $selfClass)?->getAtomicTypes() ?? [] as $type) {
				if ($type instanceof TNamedObject) {
					$codebase->methodExists($type->value . '::__construct', source_file_path: $file);
				}
			}
		}
	}


	private static function isSubclassOf(Codebase $codebase, ClassLikeStorage $storage, string $class): bool
	{
		return $codebase->classOrInterfaceExists($class) && $codebase->classExtends($storage->name, $class);
	}


	/**
	 * Whether the method name is the prefix followed by a name that ucfirst() gave, like renderDefault for the default view.
	 */
	private static function isNamed(string $method, string $prefix): bool
	{
		if (!str_starts_with($method, $prefix)) {
			return false;
		}
		$name = substr($method, strlen($prefix));
		return $name !== '' && ucfirst($name) === $name;
	}

}
