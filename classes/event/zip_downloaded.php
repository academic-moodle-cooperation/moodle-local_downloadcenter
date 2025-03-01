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

namespace local_downloadcenter\event;

/**
 * Class zip_downloaded
 * @package   local_downloadcenter
 * @copyright 2015 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class zip_downloaded extends \core\event\base {
    /**
     *
     */
    protected function init() {
        $this->data['crud'] = 'c'; // C(reate), r(ead), u(pdate), d(elete). Only create is required here!
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'course';
    }

    /**
     * Returns downloadcenter zip downloaded event name
     *
     * @return string
     * @throws \coding_exception
     */
    public static function get_name() {
        return get_string('eventDOWNLOADEDZIP', 'local_downloadcenter');
    }

    /**
     * Returns the description of the event
     *
     * @return string
     */
    public function get_description() {
        return "The user with id {$this->userid} downloaded a ZIP-File at the Downloadcenter" .
               " for the course with id {$this->objectid}.";
    }

    /**
     * Returns the URL to the course download center page
     *
     * @return \moodle_url
     * @throws \moodle_exception
     */
    public function get_url() {
        return new \moodle_url('/local/downloadcenter/index.php', ['courseid' => $this->objectid]);
    }
}
