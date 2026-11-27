Feature: Components
  In order to find the unused code of components
  As a Psalm user
  I need Psalm to know which component methods Nette and Latte templates call

  Background:
    Given I have the Nette plugin enabled
    And I have the following services
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
          protected function createComponentMenu(): Menu
          {
              return new Menu();
          }
      }
      """

  Scenario: The methods called by their names are used
    Given I have the following code
      """
      <?php
      namespace App;

      use Nette\Application\UI\Control;

      final class Menu extends Control
      {
          public function render(): void {}
          public function renderCompact(): void {}
          public function handleToggle(): void {}
          public function createComponentItem(): void {}
      }
      """
    When I look for unused code
    Then I see no errors

  Scenario: The methods nothing calls are unused
    Given I have the following code
      """
      <?php
      namespace App;

      use Nette\Application\UI\Control;

      final class Menu extends Control
      {
          public function renderer(): void {}
          public function handles(): void {}
          public function actionDefault(): void {}
          protected function renderCompact(): void {}
      }
      """
    When I look for unused code
    Then I see these errors
      | Type                 | Message                                                 |
      | PossiblyUnusedMethod | Cannot find any calls to method App\Menu::renderer      |
      | PossiblyUnusedMethod | Cannot find any calls to method App\Menu::handles       |
      | PossiblyUnusedMethod | Cannot find any calls to method App\Menu::actionDefault |
      | PossiblyUnusedMethod | Cannot find any calls to method App\Menu::renderCompact |
    And I see no other errors
