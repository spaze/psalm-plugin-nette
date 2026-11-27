Feature: Psalm's cache
  In order to trust what Psalm says when it runs with its cache
  As a Psalm user
  I need the plugin to renew what it has told Psalm when the code or the config changes

  Background:
    Given I have the Nette plugin enabled

  Scenario: A service removed from the config is not used anymore
    Given I have the following services
      """
      services:
        - App\Mailer
        - App\Sender
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
          public function __construct() {}
      }
      """
    And Psalm has looked for unused code before
    And I have the following services
      """
      services:
        - App\Mailer
      """
    When I look for unused code again
    Then I see these errors
      | Type        | Message                        |
      | UnusedClass | Class App\Sender is never used |
    And I see no other errors

  Scenario: A method Nette no longer calls is not used anymore
    Given I have the following services
      """
      services:
        - App\HomepagePresenter
      """
    And I have the following code in "HomepagePresenter.php"
      """
      <?php
      namespace App;

      use Nette\Application\UI\Presenter;

      final class HomepagePresenter extends Presenter
      {
          public function actionDefault(): void {}
          public function renderDefault(): void {}
      }
      """
    And Psalm has looked for unused code before
    And I have the following code in "HomepagePresenter.php"
      """
      <?php
      namespace App;

      use Nette\Application\UI\Presenter;

      final class HomepagePresenter extends Presenter
      {
          public function actionDefault(): void {}
          protected function renderDefault(): void {}
      }
      """
    When I look for unused code again
    Then I see these errors
      | Type                 | Message                                                              |
      | PossiblyUnusedMethod | Cannot find any calls to method App\HomepagePresenter::renderDefault |
    And I see no other errors

  Scenario: What Nette calls stays used when nothing has changed
    Given I have the following services
      """
      services:
        - App\HomepagePresenter
      """
    And I have the following code
      """
      <?php
      namespace App;

      use Nette\Application\UI\Presenter;

      final class HomepagePresenter extends Presenter
      {
          public function actionDefault(): void {}
      }
      """
    And Psalm has looked for unused code before
    When I look for unused code again
    Then I see no errors
