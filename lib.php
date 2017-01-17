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


function local_downloadcenter_extend_settings_navigation(settings_navigation $settings_nav, context $context) {
    global $COURSE, $PAGE, $OUTPUT;

    return; //not used anymore

    if ($COURSE->id == SITEID) {
        return;
    }

    if (!has_capability('local/downloadcenter:view', $context)) {
        return;
    }

    $beforenode = null;
    if ($adminnode = $settings_nav->find('siteadministration', navigation_node::TYPE_SITE_ADMIN)) {
        $beforenode = 'siteadministration';
    } else if ($adminnode = $settings_nav->find('root', navigation_node::TYPE_SITE_ADMIN)) {
        $beforenode = 'root';
    }


    $url = new moodle_url('/local/downloadcenter/index.php', array('courseid' => $COURSE->id));



    $title = get_string('navigationlink', 'local_downloadcenter');
    if ($PAGE->url->compare($url)) {
        $pix = $OUTPUT->pix_icon('t/collapsed_empty', $title);
    } else {
        $pix = $OUTPUT->pix_icon('t/collapsed', $title);
    }

    $childnode = navigation_node::create(
        $title,
        $url,
        //navigation_node::TYPE_SITE_ADMIN,
        navigation_node::TYPE_CUSTOM,
        'downloadcenter',
        'downloadcenter'
    );
    $node = $settings_nav->add_node($childnode, $beforenode);
    //$node->id = 'downloadcenter';
    $node->nodetype = navigation_node::NODETYPE_LEAF;
    $node->collapse = true;
    $node->add_class('downloadcenterlink');

    $hacknode = $node->add('', null); //yet another retarded hack to work around moodle's strange removal of leaf nodes from the settings navigation
    $hacknode->display = false; //we need the empty invisible row, otherwise in lib/navigationlib.php, settings_navigation/initialize(), at the end of the function the empty nodes are removed for some really strange unknown moodle reason.

}

function local_downloadcenter_extend_navigation(global_navigation $nav) {
    global $PAGE, $OUTPUT;

    if ($PAGE->course->id == SITEID) {
        return;
    }

    $context = context_course::instance($PAGE->course->id);

    if (!has_capability('local/downloadcenter:view', $context)) {
        return;
    }


    $courses = $nav->find('courses', navigation_node::TYPE_ROOTNODE);
    if (empty($courses)) {
        $courses = $nav->find('mycourses', navigation_node::TYPE_ROOTNODE);
    }
    $coursenode = $courses->find($PAGE->course->id, navigation_node::TYPE_COURSE);
    if ($coursenode == false) {
        return;
    }

    $beforekey = null;
    $activitiesnode = $coursenode->find('activitiescategory', navigation_node::TYPE_CATEGORY);
    if ($activitiesnode == false) {
        $sections = $coursenode->find_all_of_type(navigation_node::TYPE_SECTION);
        $firstsection = reset($sections);
        $beforekey = empty($sections) ? null : $firstsection->key;
    } else {
        $beforekey = 'activitiescategory';
    }


    $url = new moodle_url('/local/downloadcenter/index.php', array('courseid' => $PAGE->course->id));



    $title = get_string('navigationlink', 'local_downloadcenter');

    $pix = new pix_icon('icon', $title, 'local_downloadcenter');

    $childnode = navigation_node::create(
        $title,
        $url,
        navigation_node::TYPE_CUSTOM,
        'downloadcenter',
        'downloadcenter',
        $pix
    );
    $node = $coursenode->add_node($childnode, $beforekey);
    //$node->id = 'downloadcenter';
    $node->nodetype = navigation_node::NODETYPE_LEAF;
    $node->collapse = true;
    $node->add_class('downloadcenterlink');

}
