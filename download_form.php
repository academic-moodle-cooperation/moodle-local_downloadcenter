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

        $mform->addElement('hidden', 'courseid', $COURSE->id);
        $mform->setType('courseid', PARAM_INT);

        foreach ($resources as $sectionid => $sectioninfo) {
            $sectionname = 'topic' . $sectionid;
            $mform->addElement('html', html_writer::start_tag('div', array('class' => 'block')));
            $mform->addElement('checkbox', $sectionname, null, $sectioninfo->title);
            $mform->setDefault($sectionname, 1);
            foreach ($sectioninfo->res as $res) {
                $title = $res->name . ' ' . $res->icon;
                $name = $res->modname . $res->instanceid;
                $mform->addElement('checkbox', $name, null, $title);
                $mform->setDefault($name, 1);
                $mform->disabledIf($name, $sectionname);
            }
            $mform->addElement('html', html_writer::end_tag('div'));
        }

        $this->add_action_buttons(true, get_string('createzip', 'local_downloadcenter'));

    }
}