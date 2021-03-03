@local @local_downloadcenter @_file_upload
Feature: Within a moodle instance a student should be able to see folders in Download Center.

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
    

  @javascript
  Scenario: Check Folder in Download Center
    Given I log in as "admin"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "Folder" to section "2" and I fill the form with:
      | Name | Test Folder |
      | Description | Test Folder|
    And I follow "Test Folder"
    And I press "Edit"
    And I upload "lib/tests/fixtures/gd-logo.png" file to "Files" filemanager
    And I follow "Download center"
    Then I should see "Test Folder"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Download center"
    Then I should see "Test Folder"
