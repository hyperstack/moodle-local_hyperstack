@local @local_hyperstack
Feature: Connect the site to Hyperstack and open a course's credential groups
  In order to issue Hyperstack credentials when learners complete a course
  As an admin and a teacher
  I need to connect the site and reach the course page

  Background:
    Given the following "courses" exist:
      | fullname | shortname | enablecompletion |
      | Python   | PY1       | 1                |
    And the following "users" exist:
      | username | firstname | lastname |
      | teacher1 | Tea       | Cher     |
      | student1 | Stu       | Dent     |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | PY1    | editingteacher |
      | student1 | PY1    | student        |

  Scenario: An admin opens the plugin settings
    Given I log in as "admin"
    When I navigate to "Plugins > Local plugins > Hyperstack Credentials" in site administration
    Then I should see "API key"
    And I should see "test the connection"

  Scenario: A teacher sees that the site is not connected yet
    Given I am on the "PY1" "Course" page logged in as "teacher1"
    When I navigate to "Hyperstack credentials" in current page administration
    Then I should see "This site is not connected to Hyperstack yet"
    And I should not see "Open the plugin settings"

  Scenario: A student does not see the credentials page
    Given I am on the "PY1" "Course" page logged in as "student1"
    Then "Hyperstack credentials" "link" should not exist
