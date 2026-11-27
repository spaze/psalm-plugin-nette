Feature: Templates
  In order to find the unused properties of template classes
  As a Psalm user
  I need Psalm to know that Latte templates get the public properties

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
          public function formatTemplateClass(): string
          {
              return HomepageTemplate::class;
          }
      }
      """

  Scenario: The public properties are used, the others are not
    Given I have the following code
      """
      <?php
      namespace App;

      use Nette\Bridges\ApplicationLatte\Template;

      final class HomepageTemplate extends Template
      {
          /** @var list<\stdClass> */
          public array $flashes = [];
          public string $title = '';
          protected string $notForTemplates = '';
          private int $neverRead = 0;
      }
      """
    When I look for unused code
    Then I see these errors
      | Type                   | Message                                                                           |
      | PossiblyUnusedProperty | Cannot find any references to property App\HomepageTemplate::$notForTemplates     |
      | UnusedProperty         | Cannot find any references to private property App\HomepageTemplate::$neverRead   |
    And I see no other errors

  Scenario: The public properties of other classes are not used
    Given I have the following code
      """
      <?php
      namespace App;

      final class HomepageTemplate
      {
          public string $title = '';
      }
      """
    When I look for unused code
    Then I see these errors
      | Type                   | Message                                                             |
      | PossiblyUnusedProperty | Cannot find any references to property App\HomepageTemplate::$title |
    And I see no other errors
