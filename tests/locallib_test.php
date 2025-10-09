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
 * Unit tests for locallib.php
 *
 * @package     local_downloadcenter
 * @author      Clemens Marx
 * @copyright   2025 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class locallib_test extends \advanced_testcase {
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
        $ref = new \ReflectionMethod(\local_downloadcenter_factory::class, 'preprocess_resource_names');
        $ref->setAccessible(true);
        /** @var array $result */
        $result = $ref->invoke($factory, $resources, $addprefixnumbering);
        return $result;
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
}
