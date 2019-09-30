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

defined('MOODLE_INTERNAL') || die();

/**
 * Class plugin_viewed
 * @package local_downloadcenter\event
 */
class plugin_viewed extends \core\event\base {
    /**
     *
     */
    protected function init() {
        $this->data['crud'] = 'r'; // C(reate), r(ead), u(pdate), d(elete). Only read is required here!
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'course';
    }

    /**
     * @return string
     * @throws \coding_exception
     */
    public static function get_name() {
        return get_string('eventVIEWED', 'local_downloadcenter');
    }

    /**
     * @return string
     */
    public function get_description() {
        return "The user with id {$this->userid} viewed the Downloadcenter for the course with id {$this->objectid}.";
    }

    /**
     * @return \moodle_url
     * @throws \moodle_exception
     */
    public function get_url() {
        return new \moodle_url('/local/downloadcenter/index.php', array('courseid' => $this->objectid));
    }
}