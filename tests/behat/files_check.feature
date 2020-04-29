@local @local_downloadcenter @_file_upload @amc
Feature: Check File in Download Center

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Tina | Teacher1 | teacher1@example.com |
      | student1 | Sam1 | Student1 | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |
    And the following "activities" exist:
      | activity | name                 | intro                   | course | idnumber | assignsubmission_onlinetext_enabled |
      | assign   | Test assignment name | Submit your online text | C1     | assign1  | 1                                   |

  @javascript
  Scenario: Check File in Download Center
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "File" to section "2" and I fill the form with:
      | Name | Test File |
      | Description | Test Description|
    And I upload "lib/tests/fixtures/gd-logo.png" file to "Select files" filemanager
    And I press "Save and return to course"
    And I follow "Download center"
    Then I should see "Test File"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Download center"
    Then I should see "Test File"
