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

namespace local_downloadcenter; // Match plugin namespace recommendation.

/**
 * Unit tests for locallib.php
 *
 * @package     local_downloadcenter
 * @author      Clemens Marx
 * @copyright   2025 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class locallib_test extends \advanced_testcase {
    /**
     * Ensure the plugin class under test is available.
     */
    protected function setUp(): void {
        parent::setUp();
        global $CFG;
        // Load class definition from plugin.
        $path = $CFG->dirroot . '/public/local/downloadcenter/locallib.php';
        if (file_exists($path)) {
            require_once($path);
        } else if (file_exists($CFG->dirroot . '/local/downloadcenter/locallib.php')) {
            // Fallback to typical plugin location if different in CI.
            require_once($CFG->dirroot . '/local/downloadcenter/locallib.php');
        }
    }

    /**
     * Helper to create a factory instance with dummy course/user.
     */
    private function make_factory(): \local_downloadcenter_factory {
        $course = (object)['id' => 1, 'format' => 'topics', 'shortname' => 'TST'];
        $user = (object)['id' => 2];
        return new \local_downloadcenter_factory($course, $user);
    }

    /**
     * Invoke the private preprocess_resource_names via reflection.
     *
     * @param array $resources
     * @param bool $addprefixnumbering
     * @return array
     */
    private function call_preprocess(array $resources, bool $addprefixnumbering): array {
        $factory = $this->make_factory();
        /** @var array $result */
        $result = $this->call_private($factory, 'preprocess_resource_names', [$resources, $addprefixnumbering]);
        return $result;
    }

    /**
     * Invoke a private factory method via reflection.
     *
     * @param \local_downloadcenter_factory $factory
     * @param string $method
     * @param array $args
     * @return mixed
     */
    private function call_private(\local_downloadcenter_factory $factory, string $method, array $args = []) {
        $ref = new \ReflectionMethod(\local_downloadcenter_factory::class, $method);
        $ref->setAccessible(true);
        return $ref->invokeArgs($factory, $args);
    }

    /**
     * Create a non-subsection resource object.
     *
     * @param string $name
     * @return \stdClass
     */
    private function make_non_sub(string $name): \stdClass {
        return (object) [
            'name' => $name,
            // Explicitly ensure it is treated as non-subsection.
            'issubresource' => false,
        ];
    }

    /**
     * Create a subsection resource object.
     *
     * @param string $name
     * @param string $subname
     * @param int $cmid
     * @return \stdClass
     */
    private function make_sub(string $name, string $subname, int $cmid): \stdClass {
        return (object) [
            'name' => $name,
            'issubresource' => true,
            'subsectionname' => $subname,
            'subsectioncmid' => $cmid,
        ];
    }

    /**
     * No numbering: names are HTML-decoded and non-subsection items remain otherwise untouched.
     */
    public function test_preprocess_no_numbering_decodes_and_leaves_non_subsection(): void {
        $this->resetAfterTest();

        $resources = [
            $this->make_non_sub('Intro &amp; Overview'),
        ];

        $out = $this->call_preprocess($resources, false);

        $this->assertCount(1, $out);
        $this->assertSame('Intro & Overview', $out[0]->name, 'Name should be HTML-decoded.');
        // Non-subsection item should not gain subsection fields.
        $this->assertFalse(property_exists($out[0], 'subsectioncmid'));
        $this->assertFalse(property_exists($out[0], 'subsectionname'));
    }

    /**
     * No numbering: duplicate subsection names should receive numeric suffixes, uniques stay unchanged.
     */
    public function test_preprocess_no_numbering_subsection_duplicates_get_suffix(): void {
        $this->resetAfterTest();

        $r1 = $this->make_sub('Doc &amp; 1', 'Sub', 101);
        $r2 = $this->make_sub('Doc &amp; 2', 'Sub', 102); // Same subsection name, different cmid.
        $r3 = $this->make_sub('Doc &amp; 3', 'Other', 201); // Unique subsection name.

        $out = $this->call_preprocess([$r1, $r2, $r3], false);

        // Names should be HTML-decoded for all items in no-numbering mode.
        $this->assertSame('Doc & 1', $out[0]->name);
        $this->assertSame('Doc & 2', $out[1]->name);
        $this->assertSame('Doc & 3', $out[2]->name);

        // Duplicate subsection name "Sub" becomes Sub1/Sub2 in order of appearance.
        $this->assertSame('Sub1', $out[0]->subsectionname);
        $this->assertSame('Sub2', $out[1]->subsectionname);

        // Unique subsection name remains unchanged.
        $this->assertSame('Other', $out[2]->subsectionname);
    }

    /**
     * With numbering: non-subsection items and subsection groups get prefixes as per algorithm.
     */
    public function test_preprocess_with_numbering_prefixes_applied(): void {
        $this->resetAfterTest();

        // 5 resources total => prefix width 1 ("%01d").
        $r1 = $this->make_non_sub('A');
        $r2 = $this->make_sub('Doc1', 'Sub', 10);
        $r3 = $this->make_sub('Doc2', 'Sub', 10); // Same subsection group.
        $r4 = $this->make_sub('Doc3', 'Sub', 20); // New subsection group.
        $r5 = $this->make_non_sub('B');

        $out = $this->call_preprocess([$r1, $r2, $r3, $r4, $r5], true);

        // Expected behavior:
        // r1 (non-sub): name => 1_A, prefixindex => 1.
        $this->assertSame('1_A', $out[0]->name);
        $this->assertSame('1', $out[0]->prefixindex);
        $this->assertFalse(!empty($out[0]->issubresource));

        // First subsection group (cmid=10): group index becomes 2, item indexes 1 then 2.
        $this->assertSame('2_Sub', $out[1]->subsectionname);
        $this->assertSame('1_Doc1', $out[1]->name);
        $this->assertSame('1', $out[1]->prefixindex);

        $this->assertSame('2_Sub', $out[2]->subsectionname);
        $this->assertSame('2_Doc2', $out[2]->name);
        $this->assertSame('2', $out[2]->prefixindex);

        // Second subsection group (cmid=20): next group index 3, first item index 1.
        $this->assertSame('3_Sub', $out[3]->subsectionname);
        $this->assertSame('1_Doc3', $out[3]->name);
        $this->assertSame('1', $out[3]->prefixindex);

        // Final non-subsection: next global index 4.
        $this->assertSame('4_B', $out[4]->name);
        $this->assertSame('4', $out[4]->prefixindex);
    }

    /**
     * The generated index tree should contain only files and preserve the ZIP directory structure.
     */
    public function test_build_index_file_tree_ignores_directories_and_generated_index(): void {
        $this->resetAfterTest();

        $factory = $this->make_factory();
        $filelist = [
            'Topic One' => null,
            'Topic One/Page.html' => ['<html></html>'],
            'Topic One/Subsection A/File A.pdf' => '/tmp/file-a.pdf',
            \local_downloadcenter_factory::INDEX_FILENAME => ['generated index'],
            'Empty topic' => null,
        ];

        $tree = $this->call_private($factory, 'build_index_file_tree', [$filelist]);

        $this->assertArrayHasKey('Topic One', $tree['dirs']);
        $this->assertArrayNotHasKey('Empty topic', $tree['dirs']);
        $this->assertSame(
            [['name' => 'Page.html', 'path' => 'Topic One/Page.html']],
            $tree['dirs']['Topic One']['files']
        );
        $this->assertSame(
            [['name' => 'File A.pdf', 'path' => 'Topic One/Subsection A/File A.pdf']],
            $tree['dirs']['Topic One']['dirs']['Subsection A']['files']
        );
    }

    /**
     * Generated support files for HTML activities should not become table of contents entries.
     */
    public function test_create_index_entry_uses_primary_glossary_html_only(): void {
        $this->resetAfterTest();

        $factory = $this->make_factory();
        $resource = (object) [
            'modname' => 'glossary',
            'name' => 'Glorssary test',
        ];
        $filepaths = [
            'General/New subsection/Glorssary test/Glorssary test.html',
            'General/New subsection/Glorssary test/data/attachment/3/Screenshot.png',
        ];

        $entry = $this->call_private($factory, 'create_index_entry', [
            $resource,
            'General/New subsection/Glorssary test',
            $filepaths,
        ]);

        $this->assertSame('Glorssary test.html', $entry['title']);
        $this->assertSame('General/New subsection/Glorssary test/Glorssary test.html', $entry['link']);
        $this->assertNull($entry['children']);
    }

    /**
     * The generated HTML should link to archive files and back to the source course.
     */
    public function test_create_index_html_links_files_and_source_course(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course([
            'fullname' => 'Long Course Name',
            'shortname' => 'SHORT',
        ]);
        $factory = new \local_downloadcenter_factory($course, (object)['id' => 2]);
        $sections = [
            [
                'title' => 'Section One',
                'items' => [
                    [
                        'title' => 'Page File.html',
                        'link' => 'Section One/Page File.html',
                        'children' => null,
                    ],
                ],
                'subsections' => [
                    1 => [
                        'title' => 'Sub Section',
                        'items' => [
                            [
                                'title' => 'Doc & Notes.pdf',
                                'link' => 'Section One/Sub Section/Doc & Notes.pdf',
                                'children' => null,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $html = $this->call_private($factory, 'create_index_html', [$sections]);

        $this->assertStringContainsString('Table of contents', $html);
        $this->assertStringContainsString('Long Course Name', $html);
        $this->assertStringContainsString('/course/view.php?id=' . $course->id, $html);
        $this->assertStringContainsString('<h2>Section One</h2>', $html);
        $this->assertStringContainsString('<h3>Sub Section</h3>', $html);
        $this->assertStringContainsString('href="./Section%20One/Page%20File.html"', $html);
        $this->assertStringContainsString('href="./Section%20One/Sub%20Section/Doc%20%26%20Notes.pdf"', $html);
        $this->assertStringNotContainsString('href="./index.html"', $html);
    }
}
