<?php
declare(strict_types = 1);

namespace Spaze\Psalm\Nette\Tests\Helper;

use Behat\Gherkin\Node\PyStringNode;
use Codeception\Module;
use Weirdan\Codeception\Psalm\Module as PsalmModule;

/**
 * @psalm-api
 */
class Acceptance extends Module
{

	/**
	 * Writes the Psalm config with the plugin loaded the way the Psalm being tested can load it.
	 *
	 * Psalm installed from source finds the plugin class with the autoloader of this package. psalm.phar runs in tests/_run where there
	 * is no autoloader to find, and it loads plugins before the one the config names, so it gets the plugin by the path to its file,
	 * and the classes the scenarios use from the dependencies installed next to it in tests/_phar.
	 *
	 * @Given I have the Nette plugin enabled
	 */
	public function haveTheNettePluginEnabled(): void
	{
		$phar = ($this->config['psalm'] ?? 'source') === 'phar';
		$autoloader = $phar ? ' autoloader="../_phar/vendor/autoload.php"' : '';
		$plugin = $phar ? '<plugin filename="../_phar/vendor/spaze/psalm-plugin-nette/src/Plugin.php"/>' : '<pluginClass class="Spaze\Psalm\Nette\Plugin"/>';
		$this->getPsalmModule()->haveTheFollowingConfig(
			<<<XML
			<?xml version="1.0"?>
			<psalm errorLevel="8" runTaintAnalysis="false" ensureOverrideAttribute="false"{$autoloader}>
				<projectFiles>
					<directory name="."/>
				</projectFiles>
				<plugins>
					{$plugin}
				</plugins>
			</psalm>
			XML,
		);
	}


	/**
	 * Runs Psalm without its cache, it would keep what the previous scenario left in the same files.
	 *
	 * @When I look for unused code
	 */
	public function lookForUnusedCode(): void
	{
		$this->getPsalmModule()->runPsalmIn($this->getDirectory(), ['--find-dead-code', '--no-cache']);
	}


	/**
	 * Fills an empty cache with the code as it is now, so that the next run finds in it what the plugin has recorded for this code.
	 *
	 * @Given Psalm has looked for unused code before
	 */
	public function psalmHasLookedForUnusedCodeBefore(): void
	{
		$this->getPsalmModule()->runPsalmIn($this->getDirectory(), ['--clear-cache']);
		$this->getPsalmModule()->runPsalmIn($this->getDirectory(), ['--find-dead-code']);
	}


	/**
	 * @When I look for unused code again
	 */
	public function lookForUnusedCodeAgain(): void
	{
		$this->getPsalmModule()->runPsalmIn($this->getDirectory(), ['--find-dead-code']);
	}


	/**
	 * @Given I have the following services :neon
	 */
	public function haveTheFollowingServices(string|PyStringNode $neon): void
	{
		$this->haveTheFollowingServicesIn('services.neon', $neon);
	}


	/**
	 * Writes a file to the config directory the plugin reads the DI container config from.
	 *
	 * @Given I have the following services in :file :neon
	 */
	public function haveTheFollowingServicesIn(string $file, string|PyStringNode $neon): void
	{
		$directory = $this->getDirectory() . '/config';
		if (!is_dir($directory)) {
			mkdir($directory);
		}
		file_put_contents($directory . '/' . $file, (string)$neon);
	}


	private function getPsalmModule(): PsalmModule
	{
		$psalm = $this->getModule('\\' . PsalmModule::class);
		assert($psalm instanceof PsalmModule);
		return $psalm;
	}


	/**
	 * The directory the scenario's files are written to and Psalm runs in.
	 */
	private function getDirectory(): string
	{
		$directory = $this->getPsalmModule()->_getConfig('default_dir');
		assert(is_string($directory));
		return rtrim($directory, '/');
	}

}
