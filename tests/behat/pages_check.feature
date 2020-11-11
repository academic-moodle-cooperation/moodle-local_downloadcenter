@local @local_downloadcenter
Feature: Check  Pages in Download Center

  Background:
    Given the following "users" exist:
  | username | firstname | lastname | email |
  | teacher1 | Tina | Teacher1 | teacher1@example.com |
  | student1 | Sam1 | Student1 | student1@example.com |
  | student2 | Sam2 | Student2 | student2@example.com |
    And the following "courses" exist:
  | fullname | shortname | category |
  | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
  | user | course | role |
  | teacher1 | C1 | editingteacher |
  | student1 | C1 | student |
  | student2 | C1 | student |
    And the following "activities" exist:
  | activity | name                 | intro                   | course | idnumber | assignsubmission_onlinetext_enabled |
  | assign   | Test assignment name | Submit your online text | C1     | assign1  | 1                                   |

  @javascript
  Scenario: Check Pages in Download Center
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "Page" to section "2" and I fill the form with:
      | Name | Test Page |
      | Description | Test description |
      | Page content | https://www.climbing.com/.image/t_share/MTM1MjQ3MjI2NTYwNjM4OTQ2/cerrotorre-west_16999.jpg |
    And I follow "Download center"
    Then I should see "Test Page"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Download center"
    Then I should see "Test Page"
