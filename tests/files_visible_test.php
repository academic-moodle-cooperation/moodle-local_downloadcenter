<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Basic downloadcenter PHP Unit tests.
 *
 * @author     Simeon Naydenov (moniNaydenov@gmail.com)
 * @package    local_downloadcenter
 * @category   phpunit
 * @copyright  2020 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/*
 * TODO tests:
 *   - add resources, files and check if the students
 *
 */
class local_downloadcenter_files_visible_testcase extends advanced_testcase {

    public function test_empty() {
        global $DB, $CFG;
        require_once(__DIR__ . '/../locallib.php');

        $this->resetAfterTest(true);

        $teacherrole = $DB->get_record('role', array('shortname' => 'editingteacher'));
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));

        $student1 = $this->getDataGenerator()->create_user();
        $teacher1 = $this->getDataGenerator()->create_user();

        $this->setAdminUser();

        $course1 = $this->getDataGenerator()->create_course();

        $this->getDataGenerator()->enrol_user($student1->id, $course1->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($teacher1->id, $course1->id, $teacherrole->id);

        $this->setUser($student1);

        $downloadcenter = new local_downloadcenter_factory($course1, null);
        $userresources = $downloadcenter->get_resources_for_user();

        foreach ($userresources as $resources) {
            $this->assertEmpty($resources->res);
        }

        $this->setUser($teacher1);

        $downloadcenter = new local_downloadcenter_factory($course1, null);
        $userresources = $downloadcenter->get_resources_for_user();

        foreach ($userresources as $resources) {
            $this->assertEmpty($resources->res);
        }
    }

    public function test_student_visibility() {
        global $DB, $CFG;
        require_once(__DIR__ . '/../locallib.php');

        $this->resetAfterTest(true);

        $teacherrole = $DB->get_record('role', array('shortname' => 'editingteacher'));
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));

        $student1 = $this->getDataGenerator()->create_user();
        $teacher1 = $this->getDataGenerator()->create_user();

        $this->setAdminUser();

        $course1 = $this->getDataGenerator()->create_course();

        $this->setUser($student1);

        $this->getDataGenerator()->enrol_user($student1->id, $course1->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($teacher1->id, $course1->id, $teacherrole->id);

        $resources = $this->helper_add_resources_to_course($course1, $teacher1);

        // Test for student  - must not see not visible resources.
        $downloadcenter = new local_downloadcenter_factory($course1, $student1);
        $userresources = $downloadcenter->get_resources_for_user();

        $this->assertCount($resources->visiblefilecount, $userresources[$resources->filesection]->res);
        $this->assertCount($resources->visiblefoldercount, $userresources[$resources->foldersection]->res);
        $this->assertCount($resources->visiblepagecount, $userresources[$resources->pagesection]->res);
        $this->assertCount($resources->visiblebookcount, $userresources[$resources->booksection]->res);

    }

    public function test_teacher_visibility() {
        global $DB, $CFG;
        require_once(__DIR__ . '/../locallib.php');

        $this->resetAfterTest(true);

        $teacherrole = $DB->get_record('role', array('shortname' => 'editingteacher'));

        $this->setAdminUser();

        $teacher1 = $this->getDataGenerator()->create_user();
        $course1 = $this->getDataGenerator()->create_course();

        $this->getDataGenerator()->enrol_user($teacher1->id, $course1->id, $teacherrole->id);

        $resources = $this->helper_add_resources_to_course($course1, $teacher1);

        // Test for teacher - must see all resources.

        $this->setUser($teacher1);

        $downloadcenter = new local_downloadcenter_factory($course1, $teacher1);
        $userresources = $downloadcenter->get_resources_for_user();

        $this->assertCount($resources->filecount, $userresources[$resources->filesection]->res);
        $this->assertCount($resources->foldercount, $userresources[$resources->foldersection]->res);
        $this->assertCount($resources->pagecount, $userresources[$resources->pagesection]->res);
        $this->assertCount($resources->bookcount, $userresources[$resources->booksection]->res);
    }

    private function helper_add_file_to_context($filename, $filecontent , $context) {
        // Pick a random context id for specified user.
        $fileid = file_get_unused_draft_itemid();

        // Add actual file there.
        $filerecord = array('component' => 'user', 'filearea' => 'draft',
                            'contextid' => $context->id, 'itemid' => $fileid,
                            'filename' => $filename, 'filepath' => '/');
        $fs = get_file_storage();
        $fs->create_file_from_string($filerecord, $filecontent);

        return $fileid;
    }

    private function helper_add_resources_to_course($course, $teacher) {

        $filesection = 0;
        $foldersection = 1;
        $pagesection = 2;
        $booksection = 3;
        $publicationsection = 5;

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_resource');

        $record = new stdClass;
        $record->course = $course->id;
        $usercontext = context_user::instance($teacher->id);

        // Add 10 files with random visibility.
        $filecount = 10;
        $visiblefilecount = 0;
        $record->section = $filesection;
        for ($i = 0; $i < $filecount; $i++) {
            $record->visible = rand(0, 1000) > 500;
            $visiblefilecount += intval($record->visible);
            $record->files = $this->helper_add_file_to_context('resource' . ($i + 1) . '.jpg', 'some random content', $usercontext);
            $generator->create_instance($record);
        }

        // Add 10 folders with random visibility.
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_folder');

        $record->section = $foldersection;
        $foldercount = 10;
        $visiblefoldercount = 0;
        for ($i = 0; $i < $foldercount; $i++) {

            $record->visible = rand(0, 1000) > 500;
            $visiblefoldercount += intval($record->visible);
            $record->files = $this->helper_add_file_to_context('resource' . ($i + 1) . '.jpg', 'some random content', $usercontext);
            $generator->create_instance($record);
        }

        // Add 10 pages with random visibility.
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_page');

        unset($record->files);
        $record->section = $pagesection;
        $pagecount  = 10;
        $visiblepagecount = 0;
        for ($i = 0; $i < $pagecount; $i++) {
            $record->visible = rand(0, 1000) > 500;
            $visiblepagecount += intval($record->visible);
            $record->content = 'Some random content';
            $record->contentformat = FORMAT_HTML;

            $generator->create_instance($record);
        }

        // Add 10 books with random visibility.
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_book');

        unset($record->content);
        unset($record->contentformat);

        $record->section = $booksection;
        $bookcount  = 10;
        $visiblebookcount = 0;
        for ($i = 0; $i < $bookcount; $i++) {
            $record->visible = rand(0, 1000) > 500;
            $visiblebookcount += intval($record->visible);
            $record->content = 'Some random content';
            $record->contentformat = FORMAT_HTML;

            $book = $generator->create_instance($record);
            // Add 5 chapters to each book.
            for ($j = 0; $j < 5; $j++) {
                $generator->create_chapter(['bookid' => $book->id]);
            }

        }

        $result = new stdClass;
        $result->filecount = $filecount;
        $result->visiblefilecount = $visiblefilecount;
        $result->filesection = $filesection;

        $result->foldercount = $foldercount;
        $result->visiblefoldercount = $visiblefoldercount;
        $result->foldersection = $foldersection;

        $result->pagecount = $pagecount;
        $result->visiblepagecount = $visiblepagecount;
        $result->pagesection = $pagesection;

        $result->bookcount = $bookcount;
        $result->visiblebookcount = $visiblebookcount;
        $result->booksection = $booksection;

        return $result;
    }


}