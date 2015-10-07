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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');
require_once(__DIR__ . '/locallib.php');

class local_downloadcenter_download_form extends moodleform {
    public function definition() {
        global $DB, $COURSE;
        $mform = $this->_form;

        $resources = $this->_customdata['res'];
        //$this->class
        $mform->addElement('hidden', 'courseid', $COURSE->id);
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('html', html_writer::tag('div', get_string('warningmessage', 'local_downloadcenter'), array('class' => 'warningmessage')));
        $mform->addElement('static', 'warning', '', ''); //hack to work around fieldsets..

        foreach ($resources as $sectionid => $sectioninfo) {
            $sectionname = 'item_topic_' . $sectionid;
            $mform->addElement('html', html_writer::start_tag('div', array('class' => 'block')));
            $mform->addElement('checkbox', $sectionname, $sectioninfo->title, null);

            $mform->setDefault($sectionname, 1);
            foreach ($sectioninfo->res as $res) {
                $name = 'item_' . $res->modname . '_' . $res->instanceid;
                $title = html_writer::span($res->name) . ' ' . $res->icon;
                $title = html_writer::tag('span', $title, array('class' => 'itemtitle'));
                $mform->addElement('checkbox', $name, $title, null);
                $mform->setDefault($name, 1);
                $mform->disabledIf($name, $sectionname);
            }
            $mform->addElement('html', html_writer::end_tag('div'));
        }

        $this->add_action_buttons(true, get_string('createzip', 'local_downloadcenter'));

    }
}

class local_downloadcenter_download_final_form extends moodleform {
    public function definition() {
        global $COURSE;
        $mform = $this->_form;
        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);
        $mform->setDefault('courseid', $COURSE->id);

        $mform->addElement('hidden', 'filehash');
        $mform->setType('filehash', PARAM_ALPHANUM);

        $this->add_action_buttons(false, get_string('download', 'local_downloadcenter'));
    }
}