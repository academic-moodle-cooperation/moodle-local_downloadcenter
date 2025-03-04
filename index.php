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
 * @copyright     2020 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');
require_once(__DIR__ . '/download_form.php');

// Raise timelimit as this could take a while for big archives.
core_php_time_limit::raise();
raise_memory_limit(MEMORY_HUGE);

$courseid = required_param('courseid', PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

require_course_login($course);

$context = context_course::instance($course->id);

require_capability('local/downloadcenter:view', $context);

$PAGE->set_url(new moodle_url('/local/downloadcenter/index.php', ['courseid' => $course->id]));

$PAGE->set_pagelayout('incourse');
$PAGE->add_body_class('limitedwidth');

$downloadcenter = new local_downloadcenter_factory($course, $USER);

$userresources = $downloadcenter->get_resources_for_user();

$PAGE->requires->js_call_amd('local_downloadcenter/modfilter', 'init', $downloadcenter->get_js_modnames());

$downloadform = new local_downloadcenter_download_form(null,
    ['res' => $userresources],
    'post',
    '',
    ['data-double-submit-protection' => 'off']);

$PAGE->set_title(get_string('navigationlink', 'local_downloadcenter') . ': ' . $course->fullname);
$PAGE->set_heading($course->fullname);

if ($data = $downloadform->get_data()) {

    $event = \local_downloadcenter\event\zip_downloaded::create([
        'objectid' => $PAGE->course->id,
        'context' => $PAGE->context,
    ]);
    $event->add_record_snapshot('course', $PAGE->course);
    $event->trigger();

    $downloadcenter->parse_form_data($data);
    $hash = $downloadcenter->create_zip();
} else if ($downloadform->is_cancelled()) {
    redirect(new moodle_url('/course/view.php', ['id' => $course->id]));
    die;
} else {
    $event = \local_downloadcenter\event\plugin_viewed::create([
        'objectid' => $PAGE->course->id,
        'context' => $PAGE->context,
    ]);
    $event->add_record_snapshot('course', $PAGE->course);
    $event->trigger();
    echo $OUTPUT->header();
}

echo $OUTPUT->heading(get_string('navigationlink', 'local_downloadcenter'), 1);

$downloadform->display();

echo $OUTPUT->footer();
