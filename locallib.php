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

class local_downloadcenter_factory {
    private $course;
    private $user;
    private $sortedresources;
    private $filteredresources;
    private $availableresources = array('resource', 'folder');
    private $jsnames = array();

    public function __construct($course, $user) {
        $this->course = $course;
        $this->user = $user;
    }

    public function get_resources_for_user() {
        global $DB, $CFG;

        //only downloadable resources should be shown
        if (!empty($this->sortedresources)) {
            return $this->sortedresources;
        }

        $modinfo = get_fast_modinfo($this->course);
        $usesections = course_format_uses_sections($this->course->format);

        $sorted = array();

        if ($usesections) {
            $sections = $DB->get_records('course_sections', array('course' => $this->course->id));
            foreach ($sections as $section) {
                if (!isset($sorted[$section->section])) {
                    $sorted[$section->section] = new stdClass;
                    $sorted[$section->section]->title = get_section_name($this->course, $section->section);
                    $sorted[$section->section]->res = array();
                }
            }
        } else {
            $sorted['default'] = new stdClass;//TODO: fix here if needed
            $sorted['default']->title = '0';
            $sorted['default']->res = array();
        }
        $cms = array();
        $resources = array();
        foreach ($modinfo->cms as $cm) {
            if (!in_array($cm->modname, $this->availableresources)) {
                continue;
            }
            if (!$cm->uservisible) {
                continue;
            }
            if (!$cm->has_view()) {
                // Exclude label and similar
                continue;
            }
            $cms[$cm->id] = $cm;
            $resources[$cm->modname][] = $cm->instance;
        }

        // preload instances
        foreach ($resources as $modname=>$instances) {
            $resources[$modname] = $DB->get_records_list($modname, 'id', $instances, 'id');
        }

        $currentsection = '';
        foreach ($cms as $cm) {
            if (!isset($resources[$cm->modname][$cm->instance])) {
                continue;
            }
            $resource = $resources[$cm->modname][$cm->instance];

            if ($usesections) {
                if ($cm->sectionnum !== $currentsection) {
                    $currentsection = $cm->sectionnum;
                }
            } else {
                $currentsection = 'default';
            }

            if (!isset($this->jsnames[$cm->modname])) {
                $this->jsnames[$cm->modname] = get_string('modulenameplural', 'mod_' . $cm->modname);
            }


            $icon = '<img src="'.$cm->get_icon_url().'" class="activityicon" alt="'.$cm->get_module_type_name().'" /> ';
            //TODO: $cm->visible..
            $res = new stdClass;
            $res->icon = $icon;
            $res->cmid = $cm->id;
            $res->name = $cm->get_formatted_name();
            $res->modname = $cm->modname;
            $res->instanceid = $cm->instance;
            $res->resource = $resource;
            $res->cm = $cm;
            $sorted[$currentsection]->res[] = $res;
        }



        $this->sortedresources = $sorted;
        return $sorted;

    }

    public function get_js_modnames() {
        return array($this->jsnames);
    }


    public function create_zip() {
        global $DB, $CFG;

        // Zip files and sent them to a user.
        $tempzip = tempnam($CFG->tempdir.'/', 'downloadcenter');
        $zipper = new zip_packer();
        $fs = get_file_storage();


        /*
        // Get file
        $file = $fs->get_file($fileinfo['contextid'], $fileinfo['component'], $fileinfo['filearea'],
            $fileinfo['itemid'], $fileinfo['filepath'], $fileinfo['filename']);
        */
// Read contents
        /*if ($file) {
            $contents = $file->get_content();
        } else {
            // file doesn't exist - do something
        }
        die;*/
        $filelist = array();
        $filteredresources = $this->filteredresources;

        if (empty($filteredresources)) {
           // return false;
        }

        foreach ($filteredresources as $topicid => $info) {
            $basedir = clean_filename($info->title);
            $filelist[$basedir] = null;
            foreach ($info->res as $res) {
                $resdir = $basedir . '/' . clean_filename($res->name);
                $filelist[$resdir] = null;
                $context = context_module::instance($res->cm->id);
                if ($res->modname == 'resource') {
                    $files = $fs->get_area_files($context->id, 'mod_resource', 'content', 0, 'sortorder DESC, id ASC', false);
                    $file = array_shift($files); //get only the first file - such are the requirements
                    $filename = $resdir . '/' . $file->get_filename();
                    $filelist[$filename] = $file;
                } else if ($res->modname == 'folder') {
                    $folder = $fs->get_area_tree($context->id, 'mod_folder', 'content', 0);
                    $this->add_folder_contents($filelist, $folder, $resdir);
                }
            }
        }

        if ($zipper->archive_to_pathname($filelist, $tempzip)) {
            send_temp_file($tempzip, $this->course->shortname . '.zip');
        } else {
            debugging("Problems with archiving the files.", DEBUG_DEVELOPER);
            die;
        }
    }

    private function add_folder_contents(&$filelist, $folder, $path) {
        if (!empty($folder['subdirs'])) {
            foreach ($folder['subdirs'] as $foldername => $subfolder) {
                $this->add_folder_contents($filelist, $subfolder, $path . '/' . $foldername);
            }
        }
        foreach ($folder['files'] as $filename => $file) {
            $filelist[$path . '/' . $filename] = $file;
        }
    }

    public function parse_form_data($data) {
        $data = (array)$data;
        $filtered = array();


        $sortedresources = $this->get_resources_for_user();
        foreach ($sortedresources as $sectionid => $info) {
            if (!isset($data['item_topic_' . $sectionid])) {
                continue;
            }
            $filtered[$sectionid] = new stdClass;
            $filtered[$sectionid]->title = $info->title;
            $filtered[$sectionid]->res = array();
            foreach ($info->res as $res) {
                $name = 'item_' . $res->modname . '_' . $res->instanceid;
                if (!isset($data[$name])) {
                    continue;
                }
                $filtered[$sectionid]->res[] = $res;
            }
        }

        $this->filteredresources = $filtered;
    }

}
