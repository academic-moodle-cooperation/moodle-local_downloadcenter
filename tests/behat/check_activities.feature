@local @local_downloadcenter @_file_upload
Feature: Both students and teachers should be able to supported activities in the Download Center.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Tina      | Teacher1 | teacher1@example.com |
      | student1 | Sam1      | Student1 | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |

  @javascript
  Scenario: Check for prefence of a mockup folder activity module in the Download Center
    Given I log in as "admin"
    And I add a folder activity to course "Course 1" section "2" and I fill the form with:
      | Name        | Test Folder |
      | Description | Test Folder |
    And I follow "Test Folder"
    And I press "Edit"
    And I upload "lib/tests/fixtures/gd-logo.png" file to "Files" filemanager
    And I am on "Course 1" course homepage
    And I navigate to "Download center" in current page administration
    Then I should see "Test Folder"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I navigate to "Download center" in current page administration
    Then I should see "Test Folder"

  @javascript
  Scenario: Check for prefence of a mockup resource activity module in the Download Center
    Given I log in as "teacher1"
    And I add a resource activity to course "Course 1" section "2" and I fill the form with:
      | Name        | Test File        |
      | Description | Test Description |
    And I upload "lib/tests/fixtures/gd-logo.png" file to "Select files" filemanager
    And I press "Save and return to course"
    And I navigate to "Download center" in current page administration
    Then I should see "Test File"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I navigate to "Download center" in current page administration
    Then I should see "Test File"

  Scenario: Check for prefence of a mockup page activity module in the Download Center
    Given I log in as "teacher1"
    And I add a page activity to course "Course 1" section "2" and I fill the form with:
      | Name         | Test Page                                                                                  |
      | Description  | Test description                                                                           |
      | Page content | https://www.climbing.com/.image/t_share/MTM1MjQ3MjI2NTYwNjM4OTQ2/cerrotorre-west_16999.jpg |
    And I am on "Course 1" course homepage
    And I navigate to "Download center" in current page administration
    Then I should see "Test Page"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I navigate to "Download center" in current page administration
    Then I should see "Test Page"

  Scenario: Check for prefence of a mockup book activity module in the Download Center
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add a book activity to course "Course 1" section "2" and I fill the form with:
      | Name        | Test Book |
      | Description | Test Book |
    And I navigate to "Download center" in current page administration
    Then I should see "Test Book"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I navigate to "Download center" in current page administration
    Then I should see "Test Book"