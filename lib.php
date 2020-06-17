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


/**
 *
 * @param settings_navigation $settingsnav
 * @param context $context
 * @return null
 */
function local_downloadcenter_extend_settings_navigation(settings_navigation $settingsnav, context $context) {
    return; // Not used anymore!
}


/**
 * @param global_navigation $nav
 * @throws coding_exception
 * @throws moodle_exception
 */
function local_downloadcenter_extend_navigation(global_navigation $nav) {
    global $PAGE;

    if ($PAGE->course->id == SITEID) {
        return;
    }

    $context = context_course::instance($PAGE->course->id);

    if (!has_capability('local/downloadcenter:view', $context)) {
        return;
    }

    $rootnodes = array($nav->find('mycourses', navigation_node::TYPE_ROOTNODE),
                       $nav->find('courses', navigation_node::TYPE_ROOTNODE));

    foreach ($rootnodes as $rootnode) {
        if (empty($rootnode)) {
            continue;
        }

        $coursenode = $rootnode->find($PAGE->course->id, navigation_node::TYPE_COURSE);
        if ($coursenode == false) {
            continue;
        }

        $beforekey = null;

        $gradesnode = $coursenode->find('grades', navigation_node::TYPE_SETTING);

        if ($gradesnode) { // Add the navnode either after grades or after checkmark report
            $keys = $gradesnode->parent->get_children_key_list();
            $igrades = array_search('grades', $keys);
            $icheckmark = array_search('checkmarkreport' . $PAGE->course->id, $keys);
            if ($icheckmark !== false) {
                if (isset($keys[$icheckmark + 1])) {
                    $beforekey = $keys[$icheckmark + 1];
                }
            } else if ($igrades !== false) {
                if (isset($keys[$igrades + 1])) {
                    $beforekey = $keys[$igrades + 1];
                }
            }
        }

        if ($beforekey == null) { // No grades or checkmark report found, fall back to other variants!
            $activitiesnode = $coursenode->find('activitiescategory', navigation_node::TYPE_CATEGORY);
            if ($activitiesnode == false) {
                $custom = $coursenode->find_all_of_type(navigation_node::TYPE_CUSTOM);
                $sections = $coursenode->find_all_of_type(navigation_node::TYPE_SECTION);
                if (!empty($custom)) {
                    $first = reset($custom);
                    $beforekey = $first->key;
                } else if (!empty($sections)) {
                    $first = reset($sections);
                    $beforekey = $first->key;
                }
            } else {
                $beforekey = 'activitiescategory';
            }
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
        $node->nodetype = navigation_node::NODETYPE_LEAF;
        $node->collapse = true;
        $node->add_class('downloadcenterlink');
        break;
    }

}

/**
 * @return array
 */
function local_downloadcenter_get_fontawesome_icon_map() {
    return [
        'local_downloadcenter:icon' => 'fa-arrow-circle-o-down',
    ];
}
