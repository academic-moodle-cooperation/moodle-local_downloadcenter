<?php

namespace local_downloadcenter\event;

defined('MOODLE_INTERNAL') || die();



class zip_downloaded extends \core\event\base {
    protected function init() {
        $this->data['crud'] = 'c'; // c(reate), r(ead), u(pdate), d(elete)
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'course';
    }

    public static function get_name() {
        return get_string('eventDOWNLOADEDZIP', 'local_downloadcenter');
    }

    public function get_description() {
        return "The user with id {$this->userid} downloaded a ZIP-File at the Downloadcenter for the course with id {$this->objectid}.";
    }

    public function get_url() {
        return new \moodle_url('/local/downloadcenter/index.php', array('courseid' => $this->objectid));
    }
}