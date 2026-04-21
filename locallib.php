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
class local_downloadcenter_factory {
    /**
     * Name of the generated table of contents file in the ZIP archive.
     */
    const INDEX_FILENAME = 'index.html';

    /**
     * @var mixed|object
     */
    private $course;
    /**
     * @var mixed|object
     */
    private $user;
    /**
     * @var array
     */
    private $sortedresources;
    /**
     * @var array
     */
    private $filteredresources;
    /**
     * @var array
     */
    private $downloadoptions;
    /**
     * @var array
     */
    private $availableresources = [
        'resource',
        'folder',
        'publication',
        'page',
        'book',
        'lightboxgallery',
        'assign',
        'glossary',
        'etherpadlite',
        'subsection',
    ];
    /**
     * @var array
     */
    private $jsnames = [];
    /**
     * Array to keep track of the path duplicates to ensure unique paths.
     * This is needed when numbering is not used, so that different sections, or subsections with the same
     * name do not land in the same folder. Also that activites with the same name do not get overwritten.
     * @var array
     */
    private $pathcount = [];

    /**
     * local_downloadcenter_factory constructor.
     * @param mixed|object $course
     * @param mixed|object $user
     */
    public function __construct($course, $user) {
        $this->course = $course;
        $this->user = $user;
        $this->downloadoptions = [
            'createindex' => true,
            'filesrealnames' => false,
            'addnumbering' => false,
        ];
    }

    /**
     * Returns an array of all the resources for the download center of the course for the user.
     *
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
        $canviewhiddensections = has_capability(
            'moodle/course:viewhiddensections',
            context_course::instance($this->course->id)
        );
        $canviewhiddenactivities = has_capability(
            'moodle/course:viewhiddenactivities',
            context_course::instance($this->course->id)
        );
        $sorted = [];
        if ($usesections) {
            $sections = $DB->get_records('course_sections', ['course' => $this->course->id], 'section');
            // Thanks to https://github.com/marinaglancy for the fix!
            $max = course_get_format($this->course)->get_format_options()['numsections'] ?? count($sections);
            $unnamedsections = [];
            $namedsections = [];
            foreach ($sections as $section) {
                if (intval($section->section) > $max) {
                    break;
                }
                if (!isset($sorted[$section->section]) && ($section->visible || $canviewhiddensections)) {
                    $sorted[$section->section] = new stdClass();
                    $title = trim(get_section_name($this->course, $section->section));
                    $title = self::shorten_filename($title);
                    $sorted[$section->section]->title = $title;
                    $sorted[$section->section]->visible = $section->visible;
                    // Item id is needed to find the corresponding subsection.
                    $sorted[$section->section]->itemid = $section->itemid;
                    if (empty($title)) {
                        $unnamedsections[] = $section->section;
                    } else {
                        $namedsections[$title] = true;
                    }
                    $sorted[$section->section]->res = [];
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
            $sorted['default'] = new stdClass();
            $sorted['default']->title = '0';
            $sorted['default']->res = [];
            $sorted['default']->itemid = -1;
        }
        $cms = [];
        $resources = [];
        foreach ($modinfo->cms as $cm) {
            if (!in_array($cm->modname, $this->availableresources)) {
                continue;
            }
            if (!$cm->uservisible && $cm->modname != 'subsection') {
                continue;
            }
            if (!$cm->has_view() && $cm->modname != 'folder' && $cm->modname != 'subsection') {
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

            if ($cm->is_stealth() &&  !$canviewhiddenactivities) {
                continue; // Don't allow stealth activities for students!
            }

            $cmcontext = context_module::instance($cm->id);
            if ($cm->modname == 'glossary') {
                if (!has_capability('mod/glossary:manageentries', $cmcontext) && !$resource->allowprintview) {
                    continue;
                }
            }

            if (!isset($this->jsnames[$cm->modname]) && $cm->modname != 'subsection') {
                $this->jsnames[$cm->modname] = get_string('modulenameplural', 'mod_' . $cm->modname);
            }

            $icon = '<img src="' . $cm->get_icon_url() . '" class="activityicon" alt="' . $cm->get_module_type_name() . '" /> ';
            $res = new stdClass();
            $res->icon = $icon;
            $res->cmid = $cm->id;
            $res->name = $cm->get_formatted_name();
            $res->modname = $cm->modname;
            $res->instanceid = $cm->instance;
            $res->resource = $resource;
            $res->cm = $cm;
            $res->visible = $cm->visible;
            $res->isstealth = $cm->is_stealth();
            $res->context = $cmcontext;
            $sorted[$currentsection]->res[] = $res;
        }

        $this->replace_subsection_resources($sorted);

        // Filter out subsections.
        $filtered = [];
        foreach ($sorted as $section) {
            if (empty($section->itemid)) {
                $filtered[] = $section;
            }
        }

        $this->sortedresources = $filtered;

        return $filtered;
    }

    /**
     * Replaces the subsection resource with the actual resources from the subsection.
     *
     * @param array $sections All sections with the resources.
     */
    private function replace_subsection_resources(&$sections) {
        foreach ($sections as $section) {
            $resources = $section->res;
            $newresources = [];
            foreach ($resources as $resource) {
                if ($resource->modname == 'subsection') {
                    $subsectionresources = $this->get_resources_from_subsection($sections, $resource->instanceid);
                    foreach ($subsectionresources as $subresource) {
                        $subresource->issubresource = true;
                        $subresource->subsectionname = $resource->name;
                        $subresource->subsectioncmid = $resource->cmid;
                        $newresources[] = $subresource;
                    }
                } else {
                    $newresources[] = $resource;
                }
            }
            $section->res = $newresources;
        }
    }

    /**
     * Returns all the resources from a subsection.
     *
     * @param array $allsections
     * @param int $sectionitemid
     * @return array
     */
    private function get_resources_from_subsection($allsections, $sectionitemid) {
        $subsection = $this->get_subsection_from_sections($allsections, $sectionitemid);
        return $subsection->res;
    }

    /**
     * Returns a subsection from all sections based on the section item id.
     *
     * @param array $allsections
     * @param int $sectionitemid
     * @return stdClass|null
     */
    private function get_subsection_from_sections($allsections, $sectionitemid) {
        foreach ($allsections as $section) {
            if ($section->itemid == $sectionitemid) {
                return $section;
            }
        }
        return null;
    }

    /**
     * Returns the module names for the JS.
     *
     * @return array
     */
    public function get_js_modnames() {
        return [$this->jsnames];
    }

    /**
     * Checks if the resource is in a subsection.
     *
     * @param mixed $resource
     * @return bool
     */
    public function is_subsection_resource($resource) {
        return !empty($resource->issubresource);
    }

    /**
     * Filters out empty sections from the resource list.
     *
     * @return array Containing only the sections with resources.
     */
    private function filter_empty_sections() {
        $sections = [];
        $filteredresources = $this->filteredresources;
        foreach ($filteredresources as $section) {
            if (!empty($section->res)) {
                $sections[] = $section;
            }
        }
        return $sections;
    }

    /**
     * Builds a dictionary of section base directory names that have duplicates.
     * Key is the cleaned section title, value is 1 if duplicate, 0 otherwise.
     * Needed to preprocess the section names (to avoid overwriting duplicates).
     *
     * @param array $sections
     * @return array
     */
    private function return_duplicates_dictionary($sections) {
        $titlecounts = [];
        // Count occurrences of each cleaned section title.
        foreach ($sections as $section) {
            if (!isset($section->title)) {
                continue;
            }
            $title = html_entity_decode($section->title);
            $basedir = clean_filename($title);
            if (!isset($titlecounts[$basedir])) {
                $titlecounts[$basedir] = 0;
            }
            $titlecounts[$basedir]++;
        }
        // Build the dictionary: 1 if duplicate, 0 otherwise.
        $duplicates = [];
        foreach ($titlecounts as $basedir => $count) {
            $duplicates[$basedir] = $count > 1 ? 1 : 0;
        }
        return $duplicates;
    }

    /**
     * Returns an array of a dictionary with the section path names with cleaned duplicates.
     * The keys are the cleaned section titles, and the values are the resource arrays.
     *
     * @return array
     */
    private function section_pathnames() {
        $pathlist = [];
        $sections = $this->filter_empty_sections();
        $duplicates = $this->return_duplicates_dictionary($sections);

        $addnumbering = $this->downloadoptions['addnumbering'];
        $topicprefixid = 1;
        $topicscount = count($sections);
        $topicprefixformat = '%0' . strlen($topicscount) . 'd';
        foreach ($sections as $section) {
            $title = html_entity_decode($section->title);
            $basedir = clean_filename($title);
            if ($addnumbering) {
                $basedir = sprintf($topicprefixformat, $topicprefixid) . '_' . $basedir;
                $topicprefixid++;
            } else if (!$addnumbering) {
                if ($duplicates[$basedir] > 0) {
                    $basedir .= $duplicates[$basedir]++;
                }
            }
            $basedir = self::shorten_filename($basedir);
            $pathlist[$basedir] = $section->res;
        }
        return $pathlist;
    }

    /**
     * Preprocesses resource names for subsections, handling duplicate names and optional prefix numbering.
     *
     * If $addprefixnumbering is false: Finds all resources that are in a subsection and adds suffix numbering to resource
     * names that are duplicate.
     *
     * If $addprefixnumbering is true: Adds a numeric prefix to all subsection names and resource names, regardless of duplicates.
     *
     * Returns the modified $resources array, with updated name and subsectionname properties.
     *
     * @param array $resources Array of resource objects.
     * @param bool $addprefixnumbering If true, all names get a numeric prefix; if false, only duplicates get a suffix.
     * @return array The modified array of resource objects, with updated name and subsectionname properties.
     */
    private function preprocess_resource_names($resources, $addprefixnumbering) {
        if (!$addprefixnumbering) {
            $result = [];
            $duplicateids = [];
            foreach ($resources as $res) {
                if ($this->is_subsection_resource($res)) {
                    $name = $res->subsectionname;
                    $id = $res->subsectioncmid;
                    if (!isset($duplicateids[$name])) {
                        $duplicateids[$name] = [];
                    }
                    if (!in_array($id, $duplicateids[$name])) {
                        $duplicateids[$name][] = $id;
                    }
                }
            }
            // Original logic: only add suffix for duplicates, unique names as-is.
            foreach ($duplicateids as $name => $ids) {
                if (count($ids) > 1) {
                    $index = 1;
                    foreach ($ids as $id) {
                        $result[$id] = $name . $index;
                        $index++;
                    }
                } else {
                    $result[$ids[0]] = $name;
                }
            }
            // Update resource names; only touch subsection properties when applicable.
            foreach ($resources as $res) {
                $res->name = html_entity_decode($res->name);
                if ($this->is_subsection_resource($res) && isset($res->subsectioncmid)) {
                    $subsecid = $res->subsectioncmid;
                    if (isset($result[$subsecid])) {
                        $res->subsectionname = $result[$subsecid];
                    }
                }
            }
        } else if ($addprefixnumbering) {
            $resourceindex = 0;
            $subresourceindex = 1;
            $currentsubseccmid = -1;
            $count = count($resources);
            $prefixformat = '%0' . strlen($count) . 'd';
            foreach ($resources as $res) {
                if ($this->is_subsection_resource($res)) {
                    if ($currentsubseccmid != $res->subsectioncmid) {
                        $currentsubseccmid = $res->subsectioncmid;
                        $subresourceindex = 1;
                        $resourceindex++;
                    }
                    $res->subsectionname = sprintf($prefixformat, $resourceindex) . '_' . $res->subsectionname;
                    $res->name = sprintf($prefixformat, $subresourceindex) . '_' . $res->name;
                    $res->prefixindex = sprintf($prefixformat, $subresourceindex);
                    $subresourceindex++;
                } else {
                    $resourceindex++;
                    $res->name = sprintf($prefixformat, $resourceindex) . '_' . $res->name;
                    $res->prefixindex = sprintf($prefixformat, $resourceindex);
                }
            }
        }
        return $resources;
    }

    /**
     * Handles the mod type resource files.
     *
     * @param mixed $resource The resource being handled.
     * @param string $resdir The directory where the resource files are saved at the end in the ZIP.
     * @param array $filelist The array of files to be included in the ZIP with its files.
     * @param string $basedir The base directory for the resource files.
     * @return void
     */
    private function handle_resource($resource, $resdir, &$filelist, $basedir) {
        $fs = get_file_storage();
        $filesrealnames = $this->downloadoptions['filesrealnames'];
        $addnumbering = $this->downloadoptions['addnumbering'];
        $context = $resource->context;
        $files = $fs->get_area_files($context->id, 'mod_resource', 'content', 0, 'sortorder DESC, id ASC', false);
        $file = array_shift($files); // Get only the first file - such are the requirements!

        if ($filesrealnames) {
            $realfilename = $file->get_filename();
            if ($addnumbering) {
                $realfilename = $resource->prefixindex . '_' . $realfilename;
            }
            if ($this->is_subsection_resource($resource)) {
                $filename = $basedir . '/' . $resource->subsectionname . '/' .
                    self::shorten_filename(clean_filename($realfilename));
            } else {
                $filename = $basedir . '/' . self::shorten_filename(clean_filename($realfilename));
            }
        } else {
            $filename = $resdir;
        }
        unset($filelist[$resdir]);

        $currentextension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (empty($currentextension)) {
            $extension = mimeinfo_from_type('extension', $file->get_mimetype());
        } else {
            $filename = mb_substr($filename, 0, -mb_strlen($currentextension) - 1);
            $extension = ".{$currentextension}";
        }
        $fullfilename = $filename . $extension;
        $filei = 1;
        while (isset($filelist[$fullfilename]) && $filei < 200) {
            $fullfilename = $filename . '_' . $filei . $extension;
            $filei++;
        }
        $filelist[$fullfilename] = $file;
    }

    /**
     * Handles the mod type publication files.
     *
     * @param mixed $resource The resource being handled.
     * @param string $resdir The directory where the resource files are saved at the end in the ZIP.
     * @param array $filelist The array of files to be included in the ZIP with its files.
     * @return void
     */
    private function handle_publication($resource, $resdir, &$filelist) {
        global $DB, $USER, $CFG;
        $userfields = \core_user\fields::for_userpic();
        $context = $resource->context;
        $fs = get_file_storage();

        $cm = $resource->cm;

        $conditions = [];
        $conditions['publication'] = $resource->instanceid;

        // Find out current groups mode.
        $currentgroup = groups_get_activity_group($cm, true);

        // Get all ppl that are allowed to submit assignments.
        [$esql, $params] = get_enrolled_sql($context, 'mod/publication:view', $currentgroup);
        $showall = false;

        if (
            has_capability('mod/publication:approve', $context) ||
            has_capability('mod/publication:grantextension', $context)
        ) {
            $showall = true;
        }

        if ($showall) {
            $sql = 'SELECT u.id FROM {user} u ' .
                'LEFT JOIN (' . $esql . ') eu ON eu.id=u.id ' .
                'WHERE u.deleted = 0 AND eu.id=u.id';
        } else {
            $sql = 'SELECT u.id FROM {user} u ' .
                'LEFT JOIN (' . $esql . ') eu ON eu.id=u.id ' .
                'LEFT JOIN {publication_file} files ON (u.id = files.userid) ' .
                'WHERE u.deleted = 0 AND eu.id=u.id ' .
                'AND files.publication = ' . $resource->instanceid . ' ';

            $where = [];

            if ($resource->resource->obtainteacherapproval) {
                // Need teacher approval.
                $where[] = 'files.teacherapproval = 1';
            }
            if ($resource->resource->obtainstudentapproval) {
                $where[] = 'files.studentapproval = 1';
            }

            if (!empty($where)) {
                $sql .= ' AND ' . implode(' AND ', $where) . ' ';
            }
            $sql .= 'GROUP BY u.id';
        }

        $users = $DB->get_records_sql($sql, $params);

        if (!empty($users)) {
            $users = array_keys($users);
        }

        // If groupmembersonly used, remove users who are not in any group.
        if ($users && !empty($CFG->enablegroupmembersonly) && $cm->groupmembersonly) {
            if ($groupingusers = groups_get_grouping_members($cm->groupingid, 'u.id', 'u.id')) {
                $users = array_intersect($users, array_keys($groupingusers));
            }
        }

        $userfields = [];
        foreach (\core_user\fields::get_name_fields() as $field) {
            $userfields[$field] = $field;
        }
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
            $auser = $DB->get_record('user', ['id' => $auserid], $userfields);

            foreach ($records as $record) {
                $hasteacherapproval = !$resource->resource->obtainteacherapproval || $record->teacherapproval == 1;
                $hasstudentapproval = !$resource->resource->obtainstudentapproval || $record->studentapproval == 1;
                $haspermission = $auser->id == $USER->id || $hasteacherapproval && $hasstudentapproval;

                if (has_capability('mod/publication:approve', $context) || $haspermission) {
                    // Is teacher or file is public.

                    $file = $fs->get_file_by_id($record->fileid);

                    // Get files new name.
                    $fileext = strstr($file->get_filename(), '.');
                    $fileoriginal = str_replace($fileext, '', $file->get_filename());
                    $fileforzipname = clean_filename(($viewfullnames ? (fullname($auser) . '_') : '') .
                        $fileoriginal . '_' . $auserid . $fileext);
                    $fileforzipname = $resdir . '/' . self::shorten_filename($fileforzipname);
                    // Save file name to array for zipping.
                    $filelist[$fileforzipname] = $file;
                }
            }
        } // End of foreach.
    }

    /**
     * Handles the mod type page files.
     *
     * @param mixed $resource The resource being handled.
     * @param string $resdir The directory where the resource files are saved at the end in the ZIP.
     * @param array $filelist Array of files to be included in the ZIP with its data.
     * @return void
     */
    private function handle_page($resource, $resdir, &$filelist) {
        $fs = get_file_storage();
        $context = $resource->context;
        $fsfiles = $fs->get_area_files($context->id, 'mod_page', 'content');
        if (count($fsfiles) > 0) {
            foreach ($fsfiles as $file) {
                if ($file->get_filesize() == 0) {
                    continue;
                }
                $filename = $resdir . '/data' . $file->get_filepath() . self::shorten_filename($file->get_filename());
                $filelist[$filename] = $file;
            }
        }
        $filename = $resdir . '.html';
        $content = str_replace('@@PLUGINFILE@@', 'data', $resource->resource->content);
        $content = self::convert_content_to_html_doc($resource->name, $content);
        $filelist[$filename] = [$content]; // Needs to be array to be saved as file.
    }

    /**
     * Handles the mod type book files.
     *
     * @param mixed $resource The resource object being handled.
     * @param string $resdir The directory where the resource files are saved at the end in the ZIP.
     * @param array $filelist Array of files to be included in the ZIP with its data.
     * @return void
     */
    private function handle_book($resource, $resdir, &$filelist) {
        global $PAGE, $OUTPUT, $DB, $CFG;
        $fs = get_file_storage();
        $bookrenderer = $PAGE->get_renderer('booktool_print');
        $book = $resource->resource;
        $cm = $resource->cm;
        $chapters = book_preload_chapters($book);
        $context = $resource->context;

        $fsfiles = $fs->get_area_files($context->id, 'mod_book', 'chapter');
        if (count($fsfiles) > 0) {
            foreach ($fsfiles as $file) {
                if ($file->get_filesize() == 0) {
                    continue;
                }
                $filename = $resdir . '/data' . $file->get_filepath() . self::shorten_filename($file->get_filename());
                $filelist[$filename] = $file;
            }
        }
        $filename = $resdir . '.html';

        // Taken from mod/book/tool/print/index.php!
        $allchapters = $DB->get_records('book_chapters', ['bookid' => $book->id], 'pagenum');

        $book->intro = str_replace('@@PLUGINFILE@@', 'data', $book->intro);
        $content = '<a name="top"></a>';
        $content .= $OUTPUT->heading(format_string($book->name, true, ['context' => $context]), 1);
        $content .= '<p class="book_summary">' .
            format_text($book->intro, $book->introformat, ['noclean' => true, 'context' => $context])  .
            '</p>';

        $toc = $bookrenderer->render_print_book_toc($chapters, $book, $cm);
        $content .= $toc;
        // Chapters!
        $link1 = $CFG->wwwroot . '/mod/book/view.php?id=' . $this->course->id . '&chapterid=';
        $link2 = $CFG->wwwroot . '/mod/book/view.php?id=' . $this->course->id;
        foreach ($chapters as $ch) {
            $chapter = $allchapters[$ch->id];
            if ($chapter->hidden) {
                continue;
            }
            $content .= '<div class="book_chapter"><a name="ch' . $ch->id . '"></a>';
            $title = book_get_chapter_title($chapter->id, $chapters, $book, $context);
            if (!$book->customtitles) {
                if (!$chapter->subchapter) {
                    $content .= $OUTPUT->heading($title);
                } else {
                    $content .= $OUTPUT->heading($title, 3);
                }
            }
            $chaptercontent = str_replace($link1, '#ch', $chapter->content);
            $chaptercontent = str_replace($link2, '#top', $chaptercontent);

            $chaptercontent = str_replace('@@PLUGINFILE@@', 'data', $chaptercontent);
            $content .= format_text(
                $chaptercontent,
                $chapter->contentformat,
                ['noclean' => true, 'context' => $context]
            );
            $content .= '</div>';
            $content .= '<a href="#toc">&uarr; ' . get_string('top', 'mod_book') . '</a>';
        }
        $content = self::convert_content_to_html_doc($resource->name, $content);
        $filelist[$filename] = [$content]; // Needs to be array to be saved as file.
    }

    /**
     * Handles the mod type lightboxgallery files.
     *
     * @param mixed $resource The resource object being handled.
     * @param string $resdir The directory where the resource files are saved at the end in the ZIP.
     * @param array $filelist Array of files to be included in the ZIP with its data.
     * @return void
     */
    private function handle_lightboxgallery($resource, $resdir, &$filelist) {
        $context = $resource->context;
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'mod_lightboxgallery', 'gallery_images');

        foreach ($files as $storedfile) {
            if (!$storedfile->is_valid_image()) {
                continue;
            }

            $filename = $resdir . '/' . self::shorten_filename($storedfile->get_filename());
            $filelist[$filename] = $storedfile;
        }
    }

    /**
     * Handles the mod type assign files.
     *
     * @param mixed $resource The resource object being handled.
     * @param string $resdir The directory where the resource files are saved at the end in the ZIP.
     * @param array $filelist Array of files to be included in the ZIP with its data.
     * @return void
     */
    private function handle_assign($resource, $resdir, &$filelist) {
        global $CFG, $DB, $USER;
        $context = $resource->context;
        $fs = get_file_storage();
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        require_once($CFG->dirroot . '/mod/assign/externallib.php');
        $isstudent = !has_capability('mod/assign:viewgrades', $context);

        if ($resource->resource->allowsubmissionsfromdate < time() || $resource->resource->alwaysshowdescription) {
            if (!$isstudent || ($isstudent && $resource->resource->submissionattachments == false)) {
                $fsfiles = $fs->get_area_files($context->id, 'mod_assign', 'introattachment', 0, 'id', false);
                foreach ($fsfiles as $file) {
                    if ($file->get_filesize() == 0) {
                        continue;
                    }
                    $filename = $resdir . '/intro' . $file->get_filepath() .
                        self::shorten_filename($file->get_filename());
                    $filelist[$filename] = $file;
                }
                $fsfiles = $fs->get_area_files($context->id, 'mod_assign', 'intro', 0, 'id', false);
                foreach ($fsfiles as $file) {
                    if ($file->get_filesize() == 0) {
                        continue;
                    }
                    $filename = $resdir . '/intro/files' . $file->get_filepath() .
                        self::shorten_filename($file->get_filename());
                    $filelist[$filename] = $file;
                }

                $introtitle = get_string('description') . ' ' . $resource->name;

                $introcontent = str_replace('@@PLUGINFILE@@', 'files', $resource->resource->intro);
                $introcontent = self::convert_content_to_html_doc($introtitle, $introcontent);
                $filelist[$resdir . '/intro/intro.html'] = [$introcontent];
            }
        }

        $submissionsstr = get_string('gradeitem:submissions', 'assign');
        $assign = new assign($context, null, null);
        $assignplugins = $assign->get_submission_plugins();
        $feedbackplugins = $assign->get_feedback_plugins();

        $params = ['assignment' => $resource->instanceid];
        if ($isstudent) {
            // When student, fetch only own submissions!
            $submissions = $assign->get_all_submissions($USER->id);
        } else {
            $submissions = $DB->get_records('assign_submission', $params, 'attemptnumber ASC');
        }
        foreach ($submissions as $submission) {
            $user = null;
            $group = null;
            if ($submission->userid != 0) {
                $user = $DB->get_record('user', ['id' => $submission->userid]);
                $fullname = $resdir .  '/' . $submissionsstr . '/' . self::shorten_filename(fullname($user));
            } else if ($submission->groupid != 0) {
                $group = $DB->get_record('groups', ['id' => $submission->groupid]);
                $groupname = get_string('group', 'group') . ': ' . $group->name;
                $fullname = $resdir .  '/' . $submissionsstr . '/' . self::shorten_filename($groupname);
            } else {
                $groupname = get_string('group', 'group') . ': ' . get_string('defaultteam', 'assign');
                $fullname = $resdir .  '/' . $submissionsstr . '/' . self::shorten_filename($groupname);
            }

            // Submission!
            foreach ($assignplugins as $assignplugin) {
                if (!$assignplugin->is_enabled() || !$assignplugin->is_visible()) {
                    continue;
                }

                // Subtype is 'assignsubmission', type is currently 'file' or 'onlinetext'.
                $component = $assignplugin->get_subtype() . '_' . $assignplugin->get_type();
                $fileareas = $assignplugin->get_file_areas();
                foreach ($fileareas as $filearea => $name) {
                    $areafiles = $fs->get_area_files(
                        $context->id,
                        $component,
                        $filearea,
                        $submission->id,
                        'itemid, filepath, filename',
                        false
                    );
                    if ($areafiles) {
                        foreach ($areafiles as $file) {
                            $filename = $fullname . $file->get_filepath() .
                                self::shorten_filename($file->get_filename());
                            $filelist[$filename] = $file;
                        }
                    }
                }
                if ($assignplugin->get_type() == 'onlinetext') {
                    $onlinetext = $assignplugin->get_editor_text('onlinetext', $submission->id);
                    $onlinetext = str_replace('@@PLUGINFILE@@/', '', $onlinetext);
                    if (mb_strlen(trim($onlinetext)) > 0) {
                        $onlinetext = self::convert_content_to_html_doc($assignplugin->get_name(), $onlinetext);
                        $filename = $fullname . '/' . self::shorten_filename($assignplugin->get_name() . '.html');
                        $filelist[$filename] = [$onlinetext];
                    }
                }
            }

            // Feedback!
            if (empty($user)) {
                if ($isstudent) {
                    $user = $USER; // Applicable with group submissions!
                } else {
                    continue; // There is no feedback per group AFAIK.
                }
            }
            $feedback = $assign->get_assign_feedback_status_renderable($user);
            // The feedback for our latest submission.
            if ($feedback && $feedback->grade) {
                $fullname .= '/' . get_string('feedback', 'grades');

                foreach ($feedbackplugins as $feedbackplugin) {
                    if (!$feedbackplugin->is_enabled() || !$feedbackplugin->is_visible()) {
                        continue;
                    }
                    $component = $feedbackplugin->get_subtype() . '_' . $feedbackplugin->get_type();
                    $fileareas = $feedbackplugin->get_file_areas();
                    foreach ($fileareas as $filearea => $name) {
                        $areafiles = $fs->get_area_files(
                            $context->id,
                            $component,
                            $filearea,
                            $feedback->grade->id,
                            'itemid, filepath, filename',
                            false
                        );
                        if ($areafiles) {
                            foreach ($areafiles as $file) {
                                $filename = $fullname . $file->get_filepath() .
                                    self::shorten_filename($file->get_filename());
                                $filelist[$filename] = $file;
                            }
                        }
                    }

                    if ($feedbackplugin->get_type() == 'comments') {
                        $comments = $feedbackplugin->get_editor_text('comments', $feedback->grade->id);
                        $comments = str_replace('@@PLUGINFILE@@/', '', $comments);
                        if (mb_strlen(trim($comments)) > 0) {
                            $comments = self::convert_content_to_html_doc($feedbackplugin->get_name(), $comments);
                            $filename = $fullname . '/' . self::shorten_filename($feedbackplugin->get_name() . '.html');
                            $filelist[$filename] = [$comments];
                        }
                    }
                }
            }
        }
    }

    /**
     * Handles the mod type glossary files.
     *
     * @param mixed $resource The resource object being handled.
     * @param string $resdir The directory where the resource files are saved at the end in the ZIP.
     * @param array $filelist Array of files to be included in the ZIP with its data.
     * @return void
     */
    private function handle_glossary($resource, $resdir, &$filelist) {
        global $CFG, $SITE;
        $fs = get_file_storage();
        $context = $resource->context;
        $hook = 'ALL'; // Setting up default values as taken from mod/glossary/print.php!
        $pivotkey = 'concept';
        $fullpivot = false;
        $currentpivot = '';
        $mode = '';
        $fmtoptions = ['context' => $context];
        $glossary = $resource->resource;
        $displayformat = $glossary->displayformat;
        $course = $this->course;
        $cm = $resource->cm;
        $content = '';
        ob_start();
        $sitename = get_string("site") . ': <span class="strong">' . format_string($SITE->fullname) . '</span>';
        echo html_writer::tag('div', $sitename, ['class' => 'sitename']);

        $coursename = get_string("course") . ': <span class="strong">' .
            format_string($course->fullname) . ' (' . format_string($course->shortname) . ')</span>';
        echo html_writer::tag('div', $coursename, ['class' => 'coursename']);

        $modname = get_string("modulename", "glossary") . ': <span class="strong">' .
            format_string($glossary->name, true) . '</span>';
        echo html_writer::tag('div', $modname, ['class' => 'modname']);

        [$allentries, $count] = glossary_get_entries_by_letter($glossary, $context, 'ALL', 0, null);
        if ($allentries) {
            foreach ($allentries as $entry) {
                $pivot = $entry->{$pivotkey};
                $upperpivot = core_text::strtoupper($pivot);
                $pivottoshow = core_text::strtoupper(format_string($pivot, true, $fmtoptions));

                // Reduce pivot to 1cc if necessary.
                if (!$fullpivot) {
                    $upperpivot = core_text::substr($upperpivot, 0, 1);
                    $pivottoshow = core_text::substr($pivottoshow, 0, 1);
                }

                // If there's a group break.
                if ($currentpivot != $upperpivot) {
                    $currentpivot = $upperpivot;
                    echo html_writer::tag('div', clean_text($pivottoshow), ['class' => 'mdl-align strong']);
                }
                glossary_print_entry($course, $cm, $glossary, $entry, $mode, $hook, 1, $displayformat, true);
            }
            // The all entries value may be a recordset or an array.
            if ($allentries instanceof moodle_recordset) {
                $allentries->close();
            }
        }
        $content .= ob_get_contents();
        ob_end_clean();

        $fileurl = $CFG->wwwroot . '/pluginfile.php/' . $context->id . '/mod_glossary/';
        $content = str_replace($fileurl, 'data/', $content);
        $filename = $resdir . '/' . self::shorten_filename($resource->name . '.html');
           $linkrel = '<style>' .
            '.img-fluid { max-width: 100%; height: auto; } ' .
            'table.glossarypost.dictionary, table.glossarypost.dictionary td.entry { width: 100%; } ' .
            '.attachments { display: flex; align-items: center; gap: .25rem; } ' .
            '.attachments a:first-child { flex: 0 0 auto; } ' .
            '.attachments a:first-child img.icon { width: 24px; height: 24px; flex: 0 0 24px; display: inline-block; } ' .
            '.attachments a + a { flex: 1 1 auto; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }' .
            '</style>';
        $content = '<div class="path-mod-glossary" id="page-mod-glossary-print">' . $content . '</div>';
        $content = self::convert_content_to_html_doc($resource->name, $content, $linkrel);
        $filelist[$filename] = [$content];

        // Handle attachments.
        $fsfiles = $fs->get_area_files(
            $context->id,
            'mod_glossary',
            'attachment'
        );
        if (count($fsfiles) > 0) {
            foreach ($fsfiles as $file) {
                if ($file->get_filesize() == 0) {
                    continue;
                }
                $filename = $resdir . '/data/attachment/' . $file->get_itemid() . '/' . $file->get_filename();
                $filelist[$filename] = $file;
            }
        }
        // Handle entries.
        $fsfiles = $fs->get_area_files(
            $context->id,
            'mod_glossary',
            'entry'
        );
        if (count($fsfiles) > 0) {
            foreach ($fsfiles as $file) {
                if ($file->get_filesize() == 0) {
                    continue;
                }
                $filename = $resdir . '/data/entry/' . $file->get_itemid() . '/' . $file->get_filename();
                $filelist[$filename] = $file;
            }
        }
    }

    /**
     * Handles the mod type etherpadlite files.
     *
     * @param mixed $resource The resource object being handled.
     * @param string $resdir The directory where the resource files are saved at the end in the ZIP.
     * @param array $filelist Array of files to be included in the ZIP with its data.
     * @return void
     */
    private function handle_etherpadlite($resource, $resdir, &$filelist) {
        global $CFG;

        require_once($CFG->dirroot . '/mod/etherpadlite/lib.php');
        $etherpadconfig = get_config('etherpadlite');
        $domain = $etherpadconfig->url;
        $padid = $resource->resource->uri;
        // If not working, try $domain.'api' instead.
        $etherpadclient = \mod_etherpadlite\api\client::get_instance($etherpadconfig->apikey, $domain);
        // Handle groups here.
        $groupmode = groups_get_activity_groupmode($resource->cm);
        if ($groupmode) {
            if ($groupmode == VISIBLEGROUPS || has_capability('moodle/course:managegroups', $resource->context)) {
                $htmlcontent = $etherpadclient->get_html($padid);
                if (!empty($htmlcontent)) {
                    $htmlcontent = self::append_etherpadlite_css($htmlcontent->html);
                    $filename = $resdir . '/' . self::shorten_filename($resource->name . '_' .
                        get_string('allparticipants') . '.html');
                    $filelist[$filename] = [$htmlcontent]; // Needs to be array to be saved as file.
                }
            }
            $allgroups = groups_get_activity_allowed_groups($resource->cm);
            foreach ($allgroups as $group) {
                $htmlcontent = $etherpadclient->get_html($padid . $group->id);
                if (!empty($htmlcontent)) {
                    $htmlcontent = self::append_etherpadlite_css($htmlcontent->html);
                    $filename = $resdir . '/' . self::shorten_filename($resource->name . '_' . $group->name . '.html');
                    $filelist[$filename] = [$htmlcontent]; // Needs to be array to be saved as file.
                }
            }
        } else {
            $htmlcontent = $etherpadclient->get_html($padid);
            if (!empty($htmlcontent)) {
                $htmlcontent = self::append_etherpadlite_css($htmlcontent->html);
                $filename = $resdir . '/' . self::shorten_filename($resource->name . '.html');
                $filelist[$filename] = [$htmlcontent]; // Needs to be array to be saved as file.
            }
        }
    }

    /**
     * Creates a zip file with all the resources that the user wants to download and downloads it.
     *
     * @return string
     * @throws coding_exception
     * @throws dml_exception
     */
    public function create_zip() {
        global $CFG;

        if (file_exists($CFG->dirroot . '/mod/publication/locallib.php')) {
            require_once($CFG->dirroot . '/mod/publication/locallib.php');
        } else {
            define('PUBLICATION_MODE_UPLOAD', 0);
            define('PUBLICATION_MODE_IMPORT', 1);
        }

        $modbookmissing = true;
        if (file_exists($CFG->dirroot . '/mod/book/locallib.php')) {
            require_once($CFG->dirroot . '/mod/book/locallib.php');
            $modbookmissing = false;
        }

        // Zip files and sent them to a user.
        $fs = get_file_storage();

        $filelist = [];
        $indexsections = [];

        $addnumbering = $this->downloadoptions['addnumbering'];
        $pathlist = $this->section_pathnames();
        foreach ($pathlist as $basedir => $sectionresources) {
            $filelist[$basedir] = null;
            $sectionresources = $this->preprocess_resource_names($sectionresources, $addnumbering);
            $indexsection = [
                'title' => $basedir,
                'items' => [],
                'subsections' => [],
            ];

            foreach ($sectionresources as $res) {
                $res->name = html_entity_decode($res->name);
                if ($this->is_subsection_resource($res)) {
                    $resdir = $basedir . '/' . $res->subsectionname . '/' . self::shorten_filename(clean_filename($res->name));
                } else {
                    $resdir = $basedir . '/' . self::shorten_filename(clean_filename($res->name));
                }
                if (!$addnumbering) {
                    // This ensures that activities with the same name do not get overwritten.
                    $resdir = self::get_and_update_filepath($resdir, $filelist);
                }
                $filelist[$resdir] = null;
                $filepathsbefore = array_keys($filelist);

                if ($res->modname == 'resource') {
                    $this->handle_resource($res, $resdir, $filelist, $basedir);
                } else if ($res->modname == 'folder') {
                    $folder = $fs->get_area_tree($res->context->id, 'mod_folder', 'content', 0);
                    $this->add_folder_contents($filelist, $folder, $resdir);
                } else if ($res->modname == 'publication') {
                    $this->handle_publication($res, $resdir, $filelist);
                } else if ($res->modname == 'page') {
                    $this->handle_page($res, $resdir, $filelist);
                } else if ($res->modname == 'book' && !$modbookmissing) {
                    $this->handle_book($res, $resdir, $filelist);
                } else if ($res->modname == 'lightboxgallery') {
                    $this->handle_lightboxgallery($res, $resdir, $filelist);
                } else if ($res->modname == 'assign') {
                    $this->handle_assign($res, $resdir, $filelist);
                } else if ($res->modname == 'glossary') {
                    $this->handle_glossary($res, $resdir, $filelist);
                } else if ($res->modname == 'etherpadlite') {
                    $this->handle_etherpadlite($res, $resdir, $filelist);
                }

                $entry = $this->create_index_entry($res, $resdir, $this->get_added_file_paths($filepathsbefore, $filelist));
                if (empty($entry)) {
                    continue;
                }

                if ($this->is_subsection_resource($res)) {
                    $subsectionid = $res->subsectioncmid;
                    if (!isset($indexsection['subsections'][$subsectionid])) {
                        $indexsection['subsections'][$subsectionid] = [
                            'title' => $res->subsectionname,
                            'items' => [],
                        ];
                    }
                    $indexsection['subsections'][$subsectionid]['items'][] = $entry;
                } else {
                    $indexsection['items'][] = $entry;
                }
            }

            if (!empty($indexsection['items']) || !empty($indexsection['subsections'])) {
                $indexsections[] = $indexsection;
            }
        }

        if ($this->downloadoptions['createindex']) {
            $filelist[self::INDEX_FILENAME] = [$this->create_index_html($indexsections)];
        }

        \core\session\manager::write_close();

        $filename = sprintf('%s_%s.zip', format_string($this->course->shortname), userdate(time(), '%Y%m%d_%H%M'));

        $zipwriter = \core_files\archive_writer::get_stream_writer($filename, \core_files\archive_writer::ZIP_WRITER);

        // Stream the files into the zip.
        foreach ($filelist as $pathinzip => $file) {
            if ($file instanceof \stored_file) {
                // Most of cases are \stored_file.
                $zipwriter->add_file_from_stored_file($pathinzip, $file);
            } else if (is_array($file)) {
                // Save $file as contents, from onlinetext subplugin.
                $content = reset($file);
                $zipwriter->add_file_from_string($pathinzip, $content);
            } else if (is_string($file)) {
                $zipwriter->add_file_from_filepath($pathinzip, $file);
            }
        }

        // Finish the archive.
        $zipwriter->finish();
        die;
    }

    /**
     * Ensures unique file paths in the zip by tracking and renaming duplicates.
     *
     * If the given $filepath has already been used, appends a number to the path to make it unique.
     * If this is the first duplicate, also renames any existing keys in $filelist to start with suffix 1.
     *
     * @param string $filepath The file path to check and possibly rename for uniqueness.
     * @param array $filelist Reference to the array of file paths (keys) and files (values) being added to the zip.
     * @return string The unique file path, possibly with a numeric suffix appended.
     */
    private function get_and_update_filepath($filepath, &$filelist) {
        $countnumber = '';
        if (array_key_exists($filepath, $this->pathcount)) {
            if ($this->pathcount[$filepath] == 1) {
                $matchingpaths = preg_grep('/^' . preg_quote($filepath, '/') . '/', array_keys($filelist));
                foreach ($matchingpaths as $key) {
                    $newkey = $filepath . '1' . substr($key, strlen($filepath));
                    $filelist[$newkey] = $filelist[$key];
                    unset($filelist[$key]);
                }
            }
            $this->pathcount[$filepath]++;
            $countnumber = $this->pathcount[$filepath];
        } else {
            $this->pathcount[$filepath] = 1;
        }
        $filepath .= $countnumber;
        return $filepath;
    }

    /**
     * Adds the contents of a folder to the filelist.
     *
     * @param array $filelist
     * @param array $folder
     * @param string $path
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
     * Parse the data from the form where the user selects the resources to download and the options.
     *
     * @param stdClass|null $data
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function parse_form_data($data) {
        $data = (array)$data;
        $filtered = [];

        $sortedresources = $this->get_resources_for_user();

        foreach ($sortedresources as $sectionid => $info) {
            if (!isset($data['item_topic_' . $sectionid])) {
                continue;
            }
            $filtered[$sectionid] = new stdClass();
            $filtered[$sectionid]->title = $info->title;
            $filtered[$sectionid]->res = [];
            foreach ($info->res as $res) {
                $name = 'item_' . $res->modname . '_' . $res->instanceid;
                if (!isset($data[$name])) {
                    continue;
                }
                $filtered[$sectionid]->res[] = $res;
            }
        }

        $this->filteredresources = $filtered;
        $this->downloadoptions['createindex'] = isset($data['createindex']);
        $this->downloadoptions['filesrealnames'] = isset($data['filesrealnames']);
        $this->downloadoptions['addnumbering'] = isset($data['addnumbering']);
    }

    /**
     * Creates the HTML table of contents for the ZIP archive.
     *
     * @param array $sections The structured section data for the table of contents.
     * @return string
     */
    private function create_index_html($sections) {
        global $CFG, $SITE;

        $coursecontext = context_course::instance($this->course->id);
        $coursename = format_string($this->course->fullname, true, ['context' => $coursecontext]);
        $courseshortname = format_string($this->course->shortname, true, ['context' => $coursecontext]);
        $courselink = (new moodle_url('/course/view.php', ['id' => $this->course->id]))->out(false);
        $sitecontext = context_system::instance();
        $sitename = format_string($SITE->fullname, true, ['context' => $sitecontext]);

        $summarydata = (object) [
            'courselink' => s($courselink),
            'coursename' => s($coursename),
        ];

        $title = get_string('index:title', 'local_downloadcenter');
        $lang = s(get_html_lang_attribute_value(current_language()));
        $direction = right_to_left() ? ' dir="rtl"' : '';

        $content = html_writer::start_tag('main', ['class' => 'downloadcenter-index']);
        $content .= html_writer::start_tag('header', ['class' => 'downloadcenter-index-header']);
        $content .= html_writer::tag('div', html_writer::link($CFG->wwwroot, s($sitename)), [
            'class' => 'downloadcenter-index-site',
        ]);
        $content .= html_writer::tag('p', s($title), [
            'class' => 'downloadcenter-index-kicker',
        ]);
        $content .= html_writer::tag('h1', html_writer::link($courselink, s($coursename)));
        $content .= html_writer::tag('p', s($courseshortname), ['class' => 'downloadcenter-index-shortname']);
        $content .= html_writer::tag(
            'p',
            get_string('index:summary', 'local_downloadcenter', $summarydata),
            ['class' => 'downloadcenter-index-summary']
        );
        $content .= html_writer::end_tag('header');

        if (empty($sections)) {
            $content .= html_writer::tag(
                'p',
                get_string('index:nofiles', 'local_downloadcenter'),
                ['class' => 'downloadcenter-index-empty']
            );
        } else {
            $content .= $this->render_index_sections($sections);
        }

        $content .= html_writer::end_tag('main');
        $pagetitle = s($title . ': ' . $coursename);
        $css = $this->get_index_css();

        return <<<HTML
<!doctype html>
<html lang="$lang"$direction>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>$pagetitle</title>
    $css
</head>
<body>
$content
</body>
</html>
HTML;
    }

    /**
     * Gets file paths that were added to the file list after an activity was handled.
     *
     * @param array $filepathsbefore
     * @param array $filelist
     * @return array
     */
    private function get_added_file_paths($filepathsbefore, $filelist) {
        $knownpaths = array_flip($filepathsbefore);
        $paths = [];
        foreach ($filelist as $path => $file) {
            if (isset($knownpaths[$path]) || !$this->is_filelist_file($file)) {
                continue;
            }
            $paths[] = $path;
        }
        sort($paths, SORT_NATURAL | SORT_FLAG_CASE);
        return $paths;
    }

    /**
     * Creates one table of contents entry for an activity or resource.
     *
     * @param stdClass $resource
     * @param string $resdir
     * @param array $filepaths
     * @return array|null
     */
    private function create_index_entry($resource, $resdir, $filepaths) {
        $filepaths = $this->get_index_paths_for_resource($resource, $resdir, $filepaths);
        if (empty($filepaths)) {
            return null;
        }

        if (!$this->should_group_index_paths($resource, $filepaths)) {
            $path = reset($filepaths);
            return [
                'title' => basename($path),
                'link' => $path,
                'children' => null,
            ];
        }

        return [
            'title' => html_entity_decode($resource->name),
            'link' => null,
            'children' => $this->build_index_file_tree_from_paths($filepaths, $resdir),
        ];
    }

    /**
     * Filters the generated files down to the paths that should be shown in the table of contents.
     *
     * @param stdClass $resource
     * @param string $resdir
     * @param array $filepaths
     * @return array
     */
    private function get_index_paths_for_resource($resource, $resdir, $filepaths) {
        if (empty($filepaths)) {
            return [];
        }

        $htmlpaths = [];
        foreach ($filepaths as $path) {
            if (preg_match('/\.html?$/i', $path)) {
                $htmlpaths[] = $path;
            }
        }

        if ($resource->modname == 'page' || $resource->modname == 'book') {
            $mainhtml = $resdir . '.html';
            return in_array($mainhtml, $filepaths) ? [$mainhtml] : $htmlpaths;
        }

        if ($resource->modname == 'glossary') {
            $paths = [];
            foreach ($htmlpaths as $path) {
                if (strpos($path, $resdir . '/data/') !== 0) {
                    $paths[] = $path;
                }
            }
            return $paths;
        }

        if ($resource->modname == 'etherpadlite') {
            return $htmlpaths;
        }

        return $filepaths;
    }

    /**
     * Checks whether a resource should be rendered as a grouped file list.
     *
     * @param stdClass $resource
     * @param array $filepaths
     * @return bool
     */
    private function should_group_index_paths($resource, $filepaths) {
        $groupedmods = [
            'assign',
            'folder',
            'lightboxgallery',
            'publication',
        ];

        return count($filepaths) > 1 || in_array($resource->modname, $groupedmods);
    }

    /**
     * Renders the structured course sections.
     *
     * @param array $sections
     * @return string
     */
    private function render_index_sections($sections) {
        $content = '';
        foreach ($sections as $section) {
            $content .= html_writer::start_tag('section', [
                'class' => 'downloadcenter-index-section downloadcenter-index-depth-0',
            ]);
            $content .= html_writer::tag('h2', s($section['title']));
            $content .= $this->render_index_entries($section['items']);

            foreach ($section['subsections'] as $subsection) {
                $content .= html_writer::start_tag('section', [
                    'class' => 'downloadcenter-index-section downloadcenter-index-depth-1',
                ]);
                $content .= html_writer::tag('h3', s($subsection['title']));
                $content .= $this->render_index_entries($subsection['items']);
                $content .= html_writer::end_tag('section');
            }

            $content .= html_writer::end_tag('section');
        }

        return $content;
    }

    /**
     * Renders table of contents entries.
     *
     * @param array $entries
     * @return string
     */
    private function render_index_entries($entries) {
        if (empty($entries)) {
            return '';
        }

        $content = html_writer::start_tag('ul', ['class' => 'downloadcenter-index-files']);
        foreach ($entries as $entry) {
            if (!empty($entry['link'])) {
                $link = html_writer::tag('a', s($entry['title']), [
                    'href' => './' . self::encode_index_href_path($entry['link']),
                    'title' => $entry['link'],
                ]);
                $content .= html_writer::tag('li', $link);
                continue;
            }

            $inner = html_writer::tag('span', s($entry['title']), ['class' => 'downloadcenter-index-entry-title']);
            $inner .= $this->render_index_file_tree($entry['children']);
            $content .= html_writer::tag('li', $inner, ['class' => 'downloadcenter-index-entry']);
        }
        $content .= html_writer::end_tag('ul');

        return $content;
    }

    /**
     * Builds a nested directory tree from the file paths that will be written to the archive.
     *
     * @param array $filelist
     * @return array
     */
    private function build_index_file_tree($filelist) {
        $paths = [];
        foreach ($filelist as $path => $file) {
            if ($path === self::INDEX_FILENAME || !$this->is_filelist_file($file)) {
                continue;
            }
            $path = trim($path, '/');
            if ($path === '') {
                continue;
            }
            $paths[] = $path;
        }

        return $this->build_index_file_tree_from_paths($paths);
    }

    /**
     * Builds a nested directory tree from the given file paths.
     *
     * @param array $paths
     * @param string $basepath
     * @return array
     */
    private function build_index_file_tree_from_paths($paths, $basepath = '') {
        sort($paths, SORT_NATURAL | SORT_FLAG_CASE);

        $tree = [
            'dirs' => [],
            'files' => [],
        ];

        foreach ($paths as $path) {
            $displaypath = $path;
            if ($basepath !== '' && strpos($displaypath, $basepath . '/') === 0) {
                $displaypath = substr($displaypath, strlen($basepath) + 1);
            }

            $parts = array_values(array_filter(explode('/', $displaypath), 'strlen'));
            if (empty($parts)) {
                continue;
            }

            $filename = array_pop($parts);
            $node = &$tree;
            foreach ($parts as $directory) {
                if (!isset($node['dirs'][$directory])) {
                    $node['dirs'][$directory] = [
                        'dirs' => [],
                        'files' => [],
                    ];
                }
                $node = &$node['dirs'][$directory];
            }
            $node['files'][] = [
                'name' => $filename,
                'path' => $path,
            ];
            unset($node);
        }

        return $tree;
    }

    /**
     * Checks whether a file list entry will be streamed as an actual file.
     *
     * @param mixed $file
     * @return bool
     */
    private function is_filelist_file($file) {
        return $file instanceof \stored_file || is_array($file) || is_string($file);
    }

    /**
     * Renders the nested archive file tree.
     *
     * @param array $tree
     * @return string
     */
    private function render_index_file_tree($tree) {
        if (empty($tree['files']) && empty($tree['dirs'])) {
            return '';
        }

        $content = html_writer::start_tag('ul', ['class' => 'downloadcenter-index-files downloadcenter-index-filetree']);
        if (!empty($tree['files'])) {
            foreach ($tree['files'] as $file) {
                $link = html_writer::tag('a', s($file['name']), [
                    'href' => './' . self::encode_index_href_path($file['path']),
                    'title' => $file['path'],
                ]);
                $content .= html_writer::tag('li', $link);
            }
        }

        foreach ($tree['dirs'] as $directory => $subtree) {
            $inner = html_writer::tag('span', s($directory), ['class' => 'downloadcenter-index-folder-title']);
            $inner .= $this->render_index_file_tree($subtree);
            $content .= html_writer::tag('li', $inner, ['class' => 'downloadcenter-index-folder']);
        }
        $content .= html_writer::end_tag('ul');

        return $content;
    }

    /**
     * Encodes a ZIP path for use as a relative URL while preserving path separators.
     *
     * @param string $path
     * @return string
     */
    private static function encode_index_href_path($path) {
        return implode('/', array_map('rawurlencode', explode('/', $path)));
    }

    /**
     * Returns the CSS used by the generated table of contents.
     *
     * @return string
     */
    private function get_index_css() {
        return <<<CSS
<style>
html {
    background: #f5f7fb;
}

body {
    color: #1f2933;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    line-height: 1.5;
    margin: 0;
}

a {
    color: #1b5aa7;
}

a:hover,
a:focus {
    color: #0f3f79;
}

.downloadcenter-index {
    margin: 0 auto;
    max-width: 960px;
    padding: 32px 24px 48px;
}

.downloadcenter-index-header {
    background: #fff;
    border: 1px solid #d8dee9;
    border-left: 6px solid #3f7d20;
    border-radius: 8px;
    box-shadow: 0 4px 18px rgba(31, 41, 51, 0.08);
    margin-bottom: 28px;
    padding: 24px;
}

.downloadcenter-index-site,
.downloadcenter-index-kicker,
.downloadcenter-index-shortname,
.downloadcenter-index-summary {
    margin: 0 0 10px;
}

.downloadcenter-index-kicker {
    color: #52606d;
    font-size: 0.9rem;
    font-weight: 700;
    letter-spacing: 0;
    text-transform: uppercase;
}

.downloadcenter-index h1 {
    font-size: 2rem;
    line-height: 1.2;
    margin: 0 0 12px;
}

.downloadcenter-index-section {
    background: #fff;
    border: 1px solid #e4e7eb;
    border-radius: 8px;
    margin: 16px 0;
    padding: 18px 20px;
}

.downloadcenter-index-section .downloadcenter-index-section {
    background: #fbfcfe;
    box-shadow: none;
    margin-left: 18px;
}

.downloadcenter-index h2,
.downloadcenter-index h3,
.downloadcenter-index h4,
.downloadcenter-index h5,
.downloadcenter-index h6 {
    color: #102a43;
    font-size: 1.2rem;
    line-height: 1.3;
    margin: 0 0 10px;
}

.downloadcenter-index-files {
    margin: 0 0 4px;
    padding-left: 1.4rem;
}

.downloadcenter-index-files li {
    margin: 4px 0;
}

.downloadcenter-index-entry-title,
.downloadcenter-index-folder-title {
    color: #243b53;
    font-weight: 700;
}

.downloadcenter-index-filetree {
    margin-top: 6px;
}

.downloadcenter-index-filetree .downloadcenter-index-filetree {
    margin-bottom: 6px;
}

.downloadcenter-index-empty {
    background: #fff;
    border: 1px solid #d8dee9;
    border-radius: 8px;
    margin: 0;
    padding: 18px 20px;
}

@media (max-width: 640px) {
    .downloadcenter-index {
        padding: 20px 14px 32px;
    }

    .downloadcenter-index-header,
    .downloadcenter-index-section {
        padding: 18px;
    }

    .downloadcenter-index-section .downloadcenter-index-section {
        margin-left: 0;
    }
}
</style>
CSS;
    }

    /**
     * Replace slash with underscore and shorten the filename based on the maxlength.
     *
     * @param string $filename
     * @param int $maxlength
     * @return string
     */
    public static function shorten_filename($filename, $maxlength = 64) {
        $filename = (string)$filename;
        $filename = str_replace('/', '_', $filename);
        if (mb_strlen($filename) <= $maxlength) {
            return $filename;
        }
        $limit = round($maxlength / 2) - 1;
        return mb_substr($filename, 0, $limit) . '___' . mb_substr($filename, (1 - $limit));
    }

    /**
     * Converts content to a full HTML document.
     *
     * @param string $title
     * @param string $content
     * @param string $additionalhead
     * @return string
     */
    public static function convert_content_to_html_doc($title, $content, $additionalhead = '') {
        return <<<HTML
<!doctype html>
<html>
<head>
    <title>$title</title>
    <meta charset="utf-8">
    $additionalhead
</head>
<body>
$content
</body>
</html>
HTML;
    }

    /**
     * Appends CSS to the HTML content of an EtherpadLite document.
     *
     * @param string $htmlcontent
     * @return string
     */
    public static function append_etherpadlite_css($htmlcontent) {
        $csscontent = <<<CSS
<style>
ol {
  counter-reset: item;
}

ol > li {
  counter-increment: item;
}

ol ol > li {
  display: block;
}

ol > li {
  display: block;
}

ol > li:before {
  content: counters(item, ".") ". ";
}

ol ol > li:before {
  content: counters(item, ".") ". ";
  margin-left: -20px;
}

ul.indent {
  list-style-type: none;
}


</style>
</body>
CSS;
        return str_replace('</body>', $csscontent, $htmlcontent);
    }
}
