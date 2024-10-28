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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');
require_once(__DIR__ . '/locallib.php');

/**
 * Class local_downloadcenter_download_form
 */
class local_downloadcenter_download_form extends moodleform {
    /**
     * @throws coding_exception
     */
    public function definition() {
        global $COURSE, $OUTPUT;
        $mform = $this->_form;

        $resources = $this->_customdata['res'];

        $mform->addElement('hidden', 'courseid', $COURSE->id);
        $mform->setType('courseid', PARAM_INT);
        $coursecontext = \context_course::instance($COURSE->id);
        $infomessagestring = has_capability('moodle/course:update', $coursecontext) ?
            get_string('infomessage_teachers', 'local_downloadcenter') :
            get_string('infomessage_students', 'local_downloadcenter');

        $mform->addElement('html',
            html_writer::tag('div',
                $infomessagestring,
                array('class' => 'alert alert-info alert-block')
            )
        );
        $mform->addElement('html', $OUTPUT->render_from_template('local_downloadcenter/searchbox', []));
        $mform->addElement('static', 'warning', '', ''); // Hack to work around fieldsets!

        $empty = true;
        $excludeempty = get_config('local_downloadcenter', 'exclude_empty_topics');
        foreach ($resources as $sectionid => $sectioninfo) {
            if ($excludeempty && empty($sectioninfo->res)) { // Only display the sections that are not empty.
                continue;
            }

            $empty = false;
            $sectionname = 'item_topic_' . $sectionid;
            $mform->addElement('html', html_writer::start_tag('div', array('class' => 'card block mb-3')));
            $sectiontitle = html_writer::span($sectioninfo->title, 'sectiontitle');

            if (!$sectioninfo->visible) {
                $sectiontitle .= html_writer::tag('span', get_string('hiddenfromstudents'), array('class' => 'badge bg-info text-white ml-1 sectiontitlebadge'));
            }
            $mform->addElement('checkbox', $sectionname, $sectiontitle);

            $mform->setDefault($sectionname, 1);
            foreach ($sectioninfo->res as $res) {
                $name = 'item_' . $res->modname . '_' . $res->instanceid;
                $title = html_writer::span($res->name) . ' ' . $res->icon;
                $badge = '';
                if (!$res->visible) {
                    $badge = html_writer::tag('span', get_string('hiddenfromstudents'), array('class' => 'badge bg-info text-white mb-1'));
                }
                if ($res->isstealth) {
                    $badge = html_writer::tag('span', get_string('hiddenoncoursepage'), array('class' => 'badge bg-info text-white mb-1'));

                }
                $title = html_writer::tag('span', $title . $badge, array('class' => 'itemtitle'));
                $mform->addElement('checkbox', $name, $title);
                $mform->setDefault($name, 1);
            }
            $mform->addElement('html', html_writer::end_tag('div'));
        }

        if ($empty) {
            $mform->addElement('html', html_writer::tag('h2', get_string('no_downloadable_content', 'local_downloadcenter')));
        }
        // Create a new section for the download options!
        $mform->addElement('header', 'downloadoptions', get_string('downloadoptions', 'local_downloadcenter'));
        $mform->addElement('checkbox', 'filesrealnames', get_string('downloadoptions:filesrealnames', 'local_downloadcenter'));
        $mform->setDefault('filesrealnames', 0);
        $mform->addHelpButton('filesrealnames', 'downloadoptions:filesrealnames', 'local_downloadcenter');
        $mform->addElement('checkbox', 'addnumbering', get_string('downloadoptions:addnumbering', 'local_downloadcenter'));
        $mform->setDefault('addnumbering', 0);
        $mform->addHelpButton('addnumbering', 'downloadoptions:addnumbering', 'local_downloadcenter');

        $this->add_action_buttons(true, get_string('createzip', 'local_downloadcenter'));

    }
}
