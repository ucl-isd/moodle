@core @core_course @core_courseformat @core_completion
Feature: Course page activities completion
    In order to check activities completions
    As a student
    I need to see the activity completion criterias dropdown.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following "courses" exist:
      | shortname | fullname | enablecompletion |
      | C1        | Course 1 | 1                |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | teacher1 | C1     | editingteacher |

  Scenario: Teacher does not see manual completion button
    Given the following "activity" exists:
      | activity       | assign          |
      | name           | Activity sample |
      | course         | C1              |
      | completion     | 1               |
      | completionview | 0               |
    When I am on the "C1" "Course" page logged in as "teacher1"
    Then "Mark as done" "button" should not exist in the "Activity sample" "activity"
    And the "Mark as done" item should exist in the "Completion" dropdown of the "Activity sample" "activity"

  @javascript
  Scenario: Student should see the manual completion button
    Given the following "activity" exists:
      | activity       | assign          |
      | name           | Activity sample |
      | course         | C1              |
      | completion     | 1               |
      | completionview | 0               |
    When I am on the "C1" "Course" page logged in as "student1"
    Then the manual completion button for "Activity sample" should exist
    And the manual completion button of "Activity sample" is displayed as "Mark as done"
    And I toggle the manual completion state of "Activity sample"
    And the manual completion button of "Activity sample" is displayed as "Done"

  Scenario: Teacher should see the automatic completion criterias of activities
    Given the following "activity" exists:
      | activity       | assign          |
      | name           | Activity sample |
      | course         | C1              |
      | completion     | 2               |
      | completionview | 1               |
    When I am on the "C1" "Course" page logged in as "teacher1"
    And the "View" item should exist in the "Completion" dropdown of the "Activity sample" "activity"
    # After viewing the activity, the completion criteria dropdown should still display "Completion".
    And I am on the "Activity sample" "assign Activity" page
    And I am on the "Course 1" course page
    And "Completion" "button" should exist in the "Activity sample" "activity"

  Scenario: Student should see the automatic completion criterias statuses of activities
    Given the following "activity" exists:
      | activity       | assign          |
      | name           | Activity sample |
      | course         | C1              |
      | completion     | 2               |
      | completionview | 1               |
    When I am on the "C1" "Course" page logged in as "student1"
    And the "View" item should exist in the "To do" dropdown of the "Activity sample" "activity"
    # After viewing the activity, the completion criteria dropdown should display "Done" instead of "To do".
    And I am on the "Activity sample" "assign Activity" page
    And I am on the "Course 1" course page
    And "To do" "button" should not exist in the "Activity sample" "activity"
    And the "View" item should exist in the "Done" dropdown of the "Activity sample" "activity"
