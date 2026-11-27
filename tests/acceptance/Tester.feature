Feature: Nette Tester
  In order to find the unused code of tests
  As a Psalm user
  I need Psalm to know which test case methods Nette Tester calls

  Background:
    Given I have the Nette plugin enabled
    And I have the following code
      """
      <?php
      namespace App;

      final class TestRunner
      {
          /** @param class-string<\Tester\TestCase> $test */
          public static function run(string $test): void
          {
              (new $test('Hello'))->run();
          }
      }
      """

  Scenario: The methods Nette Tester calls are used
    Given I have the following code
      """
      <?php
      namespace App;

      use Tester\Attributes\DataProvider;
      use Tester\TestCase;

      final class GreeterTest extends TestCase
      {
          public function __construct(string $greeting)
          {
              if ($greeting === '') {
                  throw new \InvalidArgumentException('No greeting');
              }
          }

          public function testGreets(): void {}
          public function test_greets_politely(): void {}
          public function test2ndGreeting(): void {}

          /**
           * @dataProvider getNames
           */
          public function testNames(string $name): void
          {
              if ($name === '') {
                  throw new \InvalidArgumentException('No name');
              }
          }

          /** @return list<list<string>> */
          public function getNames(): array
          {
              return [['Foo']];
          }

          #[DataProvider('getAges')]
          public function testAges(int $age): void
          {
              if ($age < 0) {
                  throw new \InvalidArgumentException('No age');
              }
          }

          /** @return list<list<int>> */
          protected function getAges(): array
          {
              return [[42]];
          }
      }

      TestRunner::run(GreeterTest::class);
      """
    When I look for unused code
    Then I see no errors

  Scenario: The methods Nette Tester doesn't call are unused
    Given I have the following code
      """
      <?php
      namespace App;

      use Tester\TestCase;

      final class GreeterTest extends TestCase
      {
          public function testing(): void {}
          public function tests(): void {}
          protected function testProtected(): void {}

          /**
           * @dataProvider greetings.ini
           */
          public function testGreetings(): void {}

          /** @return list<list<string>> */
          public function getNames(): array
          {
              return [['Foo']];
          }
      }

      TestRunner::run(GreeterTest::class);
      """
    When I look for unused code
    Then I see these errors
      | Type                 | Message                                                       |
      | PossiblyUnusedMethod | Cannot find any calls to method App\GreeterTest::testing      |
      | PossiblyUnusedMethod | /Cannot find any calls to method App\\\\GreeterTest::tests$/  |
      | PossiblyUnusedMethod | Cannot find any calls to method App\GreeterTest::testProtected |
      | PossiblyUnusedMethod | Cannot find any calls to method App\GreeterTest::getNames     |
    And I see no other errors

  Scenario: The methods of a class that is not a test case are unused
    Given I have the following code
      """
      <?php
      namespace App;

      final class GreeterTest
      {
          public function __construct() {}
          public function testGreets(): void {}
      }

      TestRunner::run(GreeterTest::class);
      """
    When I look for unused code
    Then I see these errors
      | Type                 | Message                                                      |
      | PossiblyUnusedMethod | Cannot find any calls to method App\GreeterTest::__construct |
      | PossiblyUnusedMethod | Cannot find any calls to method App\GreeterTest::testGreets  |
    And I see no other errors
