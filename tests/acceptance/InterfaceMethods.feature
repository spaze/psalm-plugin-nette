Feature: Methods an interface declares with an annotation
  In order to find the unused methods of classes implementing a Nette interface
  As a Psalm user
  I need Psalm to know that a method the interface declares just with a "method" annotation is called through it

  Background:
    Given I have the Nette plugin enabled
    And I have the following services
      """
      services:
        - App\Visitor
      """

  Scenario: The annotated method is used, a method the interface doesn't have is not
    Given I have the following code
      """
      <?php
      namespace App;

      use Nette\Security\IIdentity;

      final class Visitor implements IIdentity
      {
          public function getId(): int
          {
              return 1;
          }

          public function getRoles(): array
          {
              return [];
          }

          public function getData(): array
          {
              return [];
          }

          public function getName(): string
          {
              return 'Visitor';
          }
      }
      """
    When I look for unused code
    Then I see these errors
      | Type                 | Message                                              |
      | PossiblyUnusedMethod | Cannot find any calls to method App\Visitor::getName |
    And I see no other errors
