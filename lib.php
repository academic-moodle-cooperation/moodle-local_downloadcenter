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

/**
 * Not used anymore!
 *
 * @param settings_navigation $settingsnav
 * @param context $context
 * @return null
 */
function local_downloadcenter_extend_settings_navigation(settings_navigation $settingsnav, context $context) {
    return; // Not used anymore!
}

/**
 * Extend course navigation with download center link.
 *
 * @param navigation_node $parentnode Node where the new link is inserted
 * @param stdClass $course current course
 * @param context_course $context current course context
 * @throws coding_exception
 * @throws moodle_exception
 */
function local_downloadcenter_extend_navigation_course(navigation_node $parentnode, stdClass $course, context_course $context) {
    if (!has_capability('local/downloadcenter:view', $context)) {
        return;
    }

    // Find appropriate key where our link should come. Probably won't work, but at least try.
    $keys = [
        'questionbank' => navigation_node::TYPE_CONTAINER,
        'unenrolself' => navigation_node::TYPE_SETTING,
        'fitlermanagement' => navigation_node::TYPE_SETTING,
    ];
    $beforekey = null;
    foreach ($keys as $key => $type) {
        if ($foundnode = $parentnode->find($key, $type)) {
            $beforekey = $key;
            break;
        }
    }

    $url = new moodle_url('/local/downloadcenter/index.php', ['courseid' => $course->id]);
    $title = get_string('navigationlink', 'local_downloadcenter');
    $pix = new pix_icon('icon', $title, 'local_downloadcenter');
    $childnode = navigation_node::create(
        $title,
        $url,
        navigation_node::TYPE_SETTING,
        'downloadcenter',
        'downloadcenter',
        $pix
    );

    $node = $parentnode->add_node($childnode, $beforekey);
    $node->nodetype = navigation_node::TYPE_SETTING;
    $node->collapse = true;
    $node->add_class('downloadcenterlink');
}
/**
 * Not used anymore!
 *
 * @param global_navigation $nav
 * @throws coding_exception
 * @throws moodle_exception
 */
function local_downloadcenter_extend_navigation(global_navigation $nav) {
    return; // Not used anymore!
}

/**
 * Get the fontawesome icon map for this plugin.
 *
 * @return array
 */
function local_downloadcenter_get_fontawesome_icon_map() {
    return [
        'local_downloadcenter:icon' => 'fa-arrow-circle-o-down',
    ];
}
