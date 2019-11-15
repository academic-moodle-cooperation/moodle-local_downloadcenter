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
    /**
     * @var
     */
    private $course;
    /**
     * @var
     */
    private $user;
    /**
     * @var
     */
    private $sortedresources;
    /**
     * @var
     */
    private $filteredresources;
    /**
     * @var array
     */
    private $availableresources = array('resource', 'folder', 'publication', 'page', 'book');
    /**
     * @var array
     */
    private $jsnames = array();
    /**
     * @var
     */
    private $progress;

    /**
     * local_downloadcenter_factory constructor.
     * @param $course
     * @param $user
     */
    public function __construct($course, $user) {
        $this->course = $course;
        $this->user = $user;
    }

    /**
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function get_resources_for_user() {
        global $DB, $CFG;

        // Only downloadable resources should be shown!
        if (!empty($this->sortedresources)) {
            return $this->sortedresources;
        }

        $modinfo = get_fast_modinfo($this->course);
        $usesections = course_format_uses_sections($this->course->format);

        $sorted = array();
        if ($usesections) {
            $sections = $DB->get_records('course_sections', array('course' => $this->course->id), 'section');
            $sectionsformat = $DB->get_record('course_format_options', array(
                'courseid' => $this->course->id,
                'name' => 'numsections')
            );
            $max = count($sections);
            if ($sectionsformat) {
                $max = intval($sectionsformat->value);
            }
            $unnamedsections = array();
            $namedsections = array();
            foreach ($sections as $section) {
                if (intval($section->section) > $max) {
                    break;
                }
                if (!isset($sorted[$section->section]) && $section->visible) {
                    $sorted[$section->section] = new stdClass;
                    $title = trim(clean_filename(get_section_name($this->course, $section->section)));
                    $title = self::shorten_filename($title);
                    $sorted[$section->section]->title = $title;
                    if (empty($title)) {
                        $unnamedsections[] = $section->section;
                    } else {
                        $namedsections[$title] = true;
                    }
                    $sorted[$section->section]->res = array(); // TODO: fix empty names here!!!
                }
            }
            foreach ($unnamedsections as $sectionid) {
                $untitled = get_string('untitled', 'local_downloadcenter');
                $title = $untitled;
                $i = 1;
                while (isset($namedsections[$title])) {
                    $title = $untitled . ' ' . strval($i);
                    $i++;
                }
                $namedsections[$title] = true;
                $sorted[$sectionid]->title = $title;
            }
        } else {
            $sorted['default'] = new stdClass;// TODO: fix here if needed!
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
            if (!$cm->has_view() && $cm->modname != 'folder') {
                // Exclude label and similar!
                continue;
            }
            $cms[$cm->id] = $cm;
            $resources[$cm->modname][] = $cm->instance;
        }

        // Preload instances!
        foreach ($resources as $modname => $instances) {
            $resources[$modname] = $DB->get_records_list($modname, 'id', $instances, 'id');
        }
        $availablesections = array_keys($sorted);
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
                if (!in_array($currentsection, $availablesections)) {
                    continue;
                }
            } else {
                $currentsection = 'default';
            }

            if (!isset($this->jsnames[$cm->modname])) {
                $this->jsnames[$cm->modname] = get_string('modulenameplural', 'mod_' . $cm->modname);
            }

            $icon = '<img src="'.$cm->get_icon_url().'" class="activityicon" alt="'.$cm->get_module_type_name().'" /> ';
            // TODO: $cm->visible..
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

    /**
     * @return array
     */
    public function get_js_modnames() {
        return array($this->jsnames);
    }

    /**
     * @return string
     * @throws coding_exception
     * @throws dml_exception
     */
    public function create_zip() {
        global $DB, $CFG, $USER, $OUTPUT;

        if (file_exists($CFG->dirroot . '/mod/publication/locallib.php')) {
            require_once($CFG->dirroot . '/mod/publication/locallib.php');
        } else {
            define('PUBLICATION_MODE_UPLOAD', 0);
            define('PUBLICATION_MODE_IMPORT', 1);
        }

        require_once($CFG->dirroot . '/mod/book/tool/print/locallib.php');

        // Zip files and sent them to a user.
        $tempzip = tempnam($CFG->tempdir.'/', 'downloadcenter');
        $zipper = new zip_packer();
        $fs = get_file_storage();

        $filelist = array();
        $filteredresources = $this->filteredresources;

        // Needed for mod_publication!
        $ufields = user_picture::fields('u');
        $useridentityfields = $CFG->showuseridentity != '' ? 'u.'.str_replace(', ', ', u.', $CFG->showuseridentity) . ', ' : '';

        foreach ($filteredresources as $topicid => $info) {
            $basedir = clean_filename($info->title);
            $basedir = self::shorten_filename($basedir);
            $filelist[$basedir] = null;
            foreach ($info->res as $res) {
                $resdir = $basedir . '/' . self::shorten_filename(clean_filename($res->name));
                $filelist[$resdir] = null;
                $context = context_module::instance($res->cm->id);
                if ($res->modname == 'resource') {
                    $files = $fs->get_area_files($context->id, 'mod_resource', 'content', 0, 'sortorder DESC, id ASC', false);
                    $file = array_shift($files); // Get only the first file - such are the requirements!
                    $filename = $resdir . '/' . self::shorten_filename($file->get_filename());
                    $extension = mimeinfo_from_type('extension', $file->get_mimetype());
                    $currentextension = mb_substr($filename, mb_strlen($extension) * -1);
                    if (strcmp($extension, $currentextension) != 0) {
                        $filename .= $extension;
                    }
                    $filelist[$filename] = $file;
                } else if ($res->modname == 'folder') {
                    $folder = $fs->get_area_tree($context->id, 'mod_folder', 'content', 0);
                    $this->add_folder_contents($filelist, $folder, $resdir);
                } else if ($res->modname == 'publication') {

                    $cm = $res->cm;

                    $conditions = array();
                    $conditions['publication'] = $res->instanceid;

                    $filesforzipping = array();
                    $filearea = 'attachment';

                    // Find out current groups mode.
                    $groupmode = groups_get_activity_groupmode($cm);
                    $currentgroup = groups_get_activity_group($cm, true);

                    // Get group name for filename.
                    $groupname = '';

                    // Get all ppl that are allowed to submit assignments.
                    list($esql, $params) = get_enrolled_sql($context, 'mod/publication:view', $currentgroup);
                    $showall = false;

                    if (has_capability('mod/publication:approve', $context) ||
                        has_capability('mod/publication:grantextension', $context)) {
                        $showall = true;
                    }

                    if ($showall) {
                        $sql = 'SELECT u.id FROM {user} u '.
                            'LEFT JOIN ('.$esql.') eu ON eu.id=u.id '.
                            'WHERE u.deleted = 0 AND eu.id=u.id';
                    } else {
                        $sql = 'SELECT u.id FROM {user} u '.
                            'LEFT JOIN ('.$esql.') eu ON eu.id=u.id '.
                            'LEFT JOIN {publication_file} files ON (u.id = files.userid) '.
                            'WHERE u.deleted = 0 AND eu.id=u.id '.
                            'AND files.publication = '. $res->instanceid . ' ';

                        if ($res->resource->mode == PUBLICATION_MODE_UPLOAD) {
                            // Mode upload.
                            // SN 11.07.2016 - feature #2738:
                            // in mod/publication/locallib : line 81, publication::__construct() { ...
                            // .....$this->instance->obtainteacherapproval = !$this->obtainteacherapproval ...
                            // ..} ...
                            // So flag has to be actually inverted!
                            if (!$res->resource->obtainteacherapproval) {
                                // Need teacher approval.

                                $where = 'files.teacherapproval = 1';
                            } else {
                                // No need for teacher approval.
                                // Teacher only hasnt rejected.
                                $where = '(files.teacherapproval = 1 OR files.teacherapproval IS NULL)';
                            }
                        } else {
                            // Mode import.
                            if (!$res->resource->obtainstudentapproval) {
                                // No need to ask student and teacher has approved.
                                $where = 'files.teacherapproval = 1';
                            } else {
                                // Student and teacher have approved.
                                $where = 'files.teacherapproval = 1 AND files.studentapproval = 1';
                            }
                        }

                        $sql .= 'AND ' . $where . ' ';
                        $sql .= 'GROUP BY u.id';
                    }

                    $users = $DB->get_records_sql($sql, $params);

                    if (!empty($users)) {
                        $users = array_keys($users);
                    }

                    // If groupmembersonly used, remove users who are not in any group.
                    if ($users and !empty($CFG->enablegroupmembersonly) and $cm->groupmembersonly) {
                        if ($groupingusers = groups_get_grouping_members($cm->groupingid, 'u.id', 'u.id')) {
                            $users = array_intersect($users, array_keys($groupingusers));
                        }
                    }

                    $userfields = get_all_user_name_fields();
                    $userfields['id'] = 'id';
                    $userfields['username'] = 'username';
                    $userfields = implode(', ', $userfields);

                    $viewfullnames = has_capability('moodle/site:viewfullnames', $context);

                    // Get all files from each user.
                    foreach ($users as $uploader) {
                        $auserid = $uploader;

                        $conditions['userid'] = $uploader;
                        $records = $DB->get_records('publication_file', $conditions);

                        // Get user firstname/lastname.
                        $auser = $DB->get_record('user', array('id' => $auserid), $userfields);

                        foreach ($records as $record) {

                            $haspermission = false;

                            if ($res->resource->mode == PUBLICATION_MODE_UPLOAD) {
                                // Mode upload.
                                // SN 11.07.2016 - feature #2738 - check comment above!
                                if (!$res->resource->obtainteacherapproval) {
                                    // Need teacher approval.
                                    if ($record->teacherapproval == 1) {
                                        // Teacher has approved.
                                        $haspermission = true;
                                    }
                                } else {
                                    // No need for teacher approval.
                                    if (is_null($record->teacherapproval) || $record->teacherapproval == 1) {
                                        // Teacher only hasnt rejected.
                                        $haspermission = true;
                                    }
                                }
                            } else {
                                // Mode import.
                                if (!$res->resource->obtainstudentapproval && $record->teacherapproval == 1) {
                                    // No need to ask student and teacher has approved.
                                    $haspermission = true;
                                } else if ($res->resource->obtainstudentapproval &&
                                    $record->teacherapproval == 1 &&
                                    $record->studentapproval == 1) {
                                    // Student and teacher have approved.
                                    $haspermission = true;
                                }
                            }

                            if (has_capability('mod/publication:approve', $context) || $haspermission) {
                                // Is teacher or file is public.

                                $file = $fs->get_file_by_id($record->fileid);

                                // Get files new name.
                                $fileext = strstr($file->get_filename(), '.');
                                $fileoriginal = str_replace($fileext, '', $file->get_filename());
                                $fileforzipname = clean_filename(($viewfullnames ? fullname($auser) : '') .
                                    '_' . $fileoriginal.'_'.$auserid.$fileext);
                                $fileforzipname = $resdir . '/' . self::shorten_filename($fileforzipname);
                                // Save file name to array for zipping.
                                $filelist[$fileforzipname] = $file;
                            }
                        }
                    } // End of foreach.

                } else if ($res->modname == 'page') {
                    $fsfiles = $fs->get_area_files($context->id,
                        'mod_page',
                        'content');
                    if (count($fsfiles) > 0) {
                        foreach ($fsfiles as $file) {
                            if ($file->get_filesize() == 0) {
                                continue;
                            }
                            $filename = $resdir . '/data' . $file->get_filepath() . self::shorten_filename($file->get_filename());
                            $filelist[$filename] = $file;
                        }
                    }
                    $filename = $resdir . '/' . self::shorten_filename($res->name . '.html');
                    $content = str_replace('@@PLUGINFILE@@', 'data', $res->resource->content);
                    $content = <<<HTML
<!doctype html>
<html>
<head>
    <title>{$res->name}</title>
    <meta charset="utf-8">
</head>
<body>
$content
</body>
</html>
HTML;
                    $filelist[$filename] = array($content); // Needs to be array to be saved as file.

                } else if ($res->modname == 'book') {
                    $book = $res->resource;
                    $cm = $res->cm;
                    $chapters = book_preload_chapters($book);

                    $fsfiles = $fs->get_area_files($context->id,
                        'mod_book',
                        'chapter');
                    if (count($fsfiles) > 0) {
                        foreach ($fsfiles as $file) {
                            if ($file->get_filesize() == 0) {
                                continue;
                            }
                            $filename = $resdir . '/data' . $file->get_filepath() . self::shorten_filename($file->get_filename());
                            $filelist[$filename] = $file;
                        }
                    }

                    $filename = $resdir . '/' . self::shorten_filename($res->name . '.html');

                    // Taken from mod/book/tool/print/index.php!
                    $allchapters = $DB->get_records('book_chapters', array('bookid' => $book->id), 'pagenum');

                    $book->intro = str_replace('@@PLUGINFILE@@', 'data', $book->intro);
                    $content = '<a name="top"></a>';
                    $content .= $OUTPUT->heading(format_string($book->name, true, array('context' => $context)), 1);
                    $content .= '<p class="book_summary">' .
                        format_text($book->intro, $book->introformat, array('noclean' => true, 'context' => $context))  .
                        '</p>';
                    list($toc, $titles) = booktool_print_get_toc($chapters, $book, $cm);
                    $content .= $toc;
                    // Chapters!
                    $link1 = $CFG->wwwroot.'/mod/book/view.php?id='.$this->course->id.'&chapterid=';
                    $link2 = $CFG->wwwroot.'/mod/book/view.php?id='.$this->course->id;
                    foreach ($chapters as $ch) {
                        $chapter = $allchapters[$ch->id];
                        if ($chapter->hidden) {
                            continue;
                        }
                        $content .= '<div class="book_chapter"><a name="ch'.$ch->id.'"></a>';
                        if (!$book->customtitles) {
                            if (!$chapter->subchapter) {
                                $content .= $OUTPUT->heading($titles[$ch->id]);
                            } else {
                                $content .= $OUTPUT->heading($titles[$ch->id], 3);
                            }
                        }
                        $chaptercontent = str_replace($link1, '#ch', $chapter->content);
                        $chaptercontent = str_replace($link2, '#top', $chaptercontent);

                        $chaptercontent = str_replace('@@PLUGINFILE@@', 'data', $chaptercontent);
                        $content .= format_text($chaptercontent,
                                                $chapter->contentformat,
                                                array('noclean' => true, 'context' => $context));
                        $content .= '</div>';
                        $content .= '<a href="#toc">&uarr; ' . get_string('top', 'mod_book') . '</a>';
                    }
                    $content = <<<HTML
<!doctype html>
<html>
<head>
    <title>{$res->name}</title>
    <meta charset="utf-8">
</head>
<body>
$content
</body>
</html>
HTML;
                    $filelist[$filename] = array($content); // Needs to be array to be saved as file.
                }
            }
        }

        if (@$zipper->archive_to_pathname($filelist, $tempzip)) {
            $filename = sprintf('%s_%s.zip', $this->course->shortname, userdate(time(), '%Y%m%d_%H%M'));
            return $this->add_file_to_session($tempzip, clean_filename($filename));

        } else {
            debugging("Problems with archiving the files.", DEBUG_DEVELOPER);
            die;
        }
    }

    /**
     * @param $tempfilename
     * @param $realfilename
     * @return string
     */
    private function add_file_to_session($tempfilename, $realfilename) {
        global $SESSION;
        if (!isset($SESSION->local_downloadcenter_filelist)) {
            $SESSION->local_downloadcenter_filelist = array();
        }
        $hash = sha1($tempfilename . $realfilename);
        $info = new stdClass;
        $info->tempfilename = $tempfilename;
        $info->realfilename = $realfilename;
        $SESSION->local_downloadcenter_filelist[$hash] = $info;
        return $hash;
    }

    /**
     * @param $hash
     */
    public function get_file_from_session($hash) {
        global $SESSION;
        if (isset($SESSION->local_downloadcenter_filelist)) {
            if (isset($SESSION->local_downloadcenter_filelist[$hash])) {
                $info = $SESSION->local_downloadcenter_filelist[$hash];
                unset($SESSION->local_downloadcenter_filelist[$hash]);
                send_temp_file($info->tempfilename, $info->realfilename);
            }
        }
        debugging("Problems with getting the files from server.", DEBUG_DEVELOPER);
        die;
    }

    /**
     * @param $filelist
     * @param $folder
     * @param $path
     */
    private function add_folder_contents(&$filelist, $folder, $path) {
        if (!empty($folder['subdirs'])) {
            foreach ($folder['subdirs'] as $foldername => $subfolder) {
                $foldername = self::shorten_filename($foldername);
                $this->add_folder_contents($filelist, $subfolder, $path . '/' . $foldername);
            }
        }
        foreach ($folder['files'] as $filename => $file) {
            $filelist[$path . '/' . self::shorten_filename($filename)] = $file;
        }
    }

    /**
     * @param $data
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
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

    /**
     * @param $filename
     * @param int $maxlength
     * @return string
     */
    public static function shorten_filename($filename, $maxlength = 64) {
        $filename = (string)$filename;
        if (strlen($filename) <= $maxlength) {
            return $filename;
        }
        $limit = round($maxlength / 2) - 1;
        return substr($filename, 0, $limit) . '___' . substr($filename, (1 - $limit));
    }

}