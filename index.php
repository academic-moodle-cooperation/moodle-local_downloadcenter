<?php
// This file is part of local_downloadcenter for Moodle - http://moodle.org/
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
 * Download center plugin
 *
 * @package       local_downloadcenter
 * @author        Simeon Naydenov (moniNaydenov@gmail.com)
 * @copyright     2015 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

$courseid = required_param('courseid', PARAM_INT);

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);

require_course_login($course);

$context = context_course::instance($course->id);

require_capability('local/downloadcenter:view', $context);

$PAGE->set_url(new moodle_url('/local/downloadcenter/index.php', array('courseid' => $course->id)));
$PAGE->set_pagelayout('incourse');

$PAGE->set_title($course->shortname . ': ' . get_string('navigationlink', 'local_downloadcenter'));
$PAGE->set_heading($course->fullname);


$PAGE->navbar->add(get_string('navigationlink', 'local_downloadcenter'), $PAGE->url);


echo $OUTPUT->header();
echo $OUTPUT->footer();