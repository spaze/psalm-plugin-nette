Feature: Presenters
  In order to find the unused code of presenters
  As a Psalm user
  I need Psalm to know which presenter methods Nette calls

  Background:
    Given I have the Nette plugin enabled
    And I have the following services
      """
      services:
        - App\HomepagePresenter
      """

  Scenario: The methods Nette calls by their names are used
    Given I have the following code
      """
      <?php
      namespace App;

      use Nette\Application\UI\Presenter;

      final class HomepagePresenter extends Presenter
      {
          public function injectMailer(): void {}
          public function injection(): void {}
          public function actionDefault(): void {}
          public function action2fa(): void {}
          public function renderDefault(): void {}
          public function handleDelete(): void {}
          protected function createComponentMenu(): void {}
      }
      """
    When I look for unused code
    Then I see no errors

  Scenario: The methods Nette doesn't call are unused
    Given I have the following code
      """
      <?php
      namespace App;

      use Nette\Application\UI\Presenter;

      final class HomepagePresenter extends Presenter
      {
          public function action(): void {}
          public function reaction(): void {}
          public function actiondefault(): void {}
          public function render(): void {}
          public function renderer(): void {}
          public function handler(): void {}
          public static function actionStatic(): void {}
          protected function renderProtected(): void {}
          protected function injectProtected(): void {}
          public function createComponent2(): void {}
          public function createComponentmenu(): void {}
      }
      """
    When I look for unused code
    Then I see these errors
      | Type                 | Message                                                                    |
      | PossiblyUnusedMethod | /Cannot find any calls to method App\\\\HomepagePresenter::action$/        |
      | PossiblyUnusedMethod | Cannot find any calls to method App\HomepagePresenter::reaction            |
      | PossiblyUnusedMethod | Cannot find any calls to method App\HomepagePresenter::actiondefault       |
      | PossiblyUnusedMethod | /Cannot find any calls to method App\\\\HomepagePresenter::render$/        |
      | PossiblyUnusedMethod | Cannot find any calls to method App\HomepagePresenter::renderer            |
      | PossiblyUnusedMethod | Cannot find any calls to method App\HomepagePresenter::handler             |
      | PossiblyUnusedMethod | Cannot find any calls to method App\HomepagePresenter::actionStatic        |
      | PossiblyUnusedMethod | Cannot find any calls to method App\HomepagePresenter::renderProtected     |
      | PossiblyUnusedMethod | Cannot find any calls to method App\HomepagePresenter::injectProtected     |
      | PossiblyUnusedMethod | Cannot find any calls to method App\HomepagePresenter::createComponent2    |
      | PossiblyUnusedMethod | Cannot find any calls to method App\HomepagePresenter::createComponentmenu |
    And I see no other errors
