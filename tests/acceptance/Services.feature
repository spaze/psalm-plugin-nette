Feature: Services
  In order to find the unused code of services
  As a Psalm user
  I need Psalm to know what the DI container creates and calls according to its config

  Background:
    Given I have the Nette plugin enabled

  Scenario: The container creates the services it has in the config
    Given I have the following services
      """
      services:
        - App\Mailer
        - App\Sender('noreply@example.com')
        queue:
          create: App\Queue
        oldQueue:
          factory: App\OldQueue
      """
    And I have the following code
      """
      <?php
      namespace App;

      final class Mailer
      {
          public function __construct() {}
      }

      final class Sender
      {
          public function __construct(string $from)
          {
              if ($from === '') {
                  throw new \InvalidArgumentException('No sender');
              }
          }
      }

      final class Queue
      {
          public function __construct() {}
      }

      final class OldQueue
      {
          public function __construct() {}
      }

      final class Forgotten
      {
          public function __construct() {}
      }
      """
    When I look for unused code
    Then I see these errors
      | Type        | Message                           |
      | UnusedClass | Class App\Forgotten is never used |
    And I see no other errors

  Scenario: The container calls the methods the config says create a service
    Given I have the following services
      """
      services:
        - App\RouterFactory
        - @App\RouterFactory::createRouter
        - App\Clock::fromSystem()
        reports: App\ReportFactory
        - @reports::create()
        - App\Summary(@reports::getTitle())
      """
    And I have the following code
      """
      <?php
      namespace App;

      final class RouterFactory
      {
          public function createRouter(): Router
          {
              return new Router();
          }

          public function createUnusedRouter(): Router
          {
              return new Router();
          }
      }

      final class Router
      {
      }

      final class Clock
      {
          public static function fromSystem(): self
          {
              return new self();
          }

          public static function fromNowhere(): self
          {
              return new self();
          }
      }

      final class ReportFactory
      {
          public function create(): Report
          {
              return new Report();
          }

          public function getTitle(): string
          {
              return 'Report';
          }

          public function getSubtitle(): string
          {
              return 'Subtitle';
          }
      }

      final class Report
      {
      }

      final class Summary
      {
          public function __construct(string $title)
          {
              if ($title === '') {
                  throw new \InvalidArgumentException('No title');
              }
          }
      }
      """
    When I look for unused code
    Then I see these errors
      | Type                 | Message                                                               |
      | PossiblyUnusedMethod | Cannot find any calls to method App\RouterFactory::createUnusedRouter |
      | PossiblyUnusedMethod | Cannot find any calls to method App\Clock::fromNowhere                |
      | PossiblyUnusedMethod | Cannot find any calls to method App\ReportFactory::getSubtitle        |
    And I see no other errors

  Scenario: The container generates the factory for an interface, and the factory creates what the interface returns
    Given I have the following services
      """
      services:
        - App\MenuFactory
        banners:
          implement: App\BannerFactory
      """
    And I have the following code
      """
      <?php
      namespace App;

      interface MenuFactory
      {
          public function create(): Menu;
      }

      final class Menu
      {
          public function __construct() {}
      }

      interface BannerFactory
      {
          public function create(): Banner;
      }

      final class Banner
      {
          public function __construct() {}
      }

      interface FooterFactory
      {
          public function create(): Footer;
      }

      final class Footer
      {
          public function __construct() {}
      }

      /**
       * @return list<object>
       */
      function createPage(MenuFactory $menuFactory, BannerFactory $bannerFactory, FooterFactory $footerFactory): array
      {
          return [$menuFactory->create(), $bannerFactory->create(), $footerFactory->create()];
      }
      """
    When I look for unused code
    Then I see these errors
      | Type                 | Message                                                 |
      | PossiblyUnusedMethod | Cannot find any calls to method App\Footer::__construct |
    And I see no other errors

  Scenario: The container doesn't create a service that only has a type
    Given I have the following services
      """
      services:
        console:
          type: App\Console
          imported: true
      """
    And I have the following code
      """
      <?php
      namespace App;

      final class Console
      {
          public function __construct() {}
      }
      """
    When I look for unused code
    Then I see these errors
      | Type                 | Message                                                  |
      | PossiblyUnusedMethod | Cannot find any calls to method App\Console::__construct |
    And I see no other errors

  Scenario: A service is found in any file in the config directory
    Given I have the following services
      """
      services:
        mailer: App\Mailer
      """
    And I have the following services in "tests.neon"
      """
      services:
        mailer: App\NullMailer
        - @mailer::flush()
      """
    And I have the following services in "parameters.neon"
      """
      parameters:
        mailer: App\Forgotten
      """
    And I have the following code
      """
      <?php
      namespace App;

      final class Mailer
      {
          public function __construct() {}
          public function flush(): void {}
      }

      final class NullMailer
      {
          public function __construct() {}
          public function flush(): void {}
          public function reset(): void {}
      }

      final class Forgotten
      {
      }
      """
    When I look for unused code
    Then I see these errors
      | Type                 | Message                                               |
      | PossiblyUnusedMethod | Cannot find any calls to method App\NullMailer::reset |
      | UnusedClass          | Class App\Forgotten is never used                     |
    And I see no other errors
