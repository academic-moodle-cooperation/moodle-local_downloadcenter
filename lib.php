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


function local_downloadcenter_extends_settings_navigation(settings_navigation $settings_nav, context $context) {
    global $COURSE, $PAGE;

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

    $title = get_string('navigationlink', 'local_downloadcenter');
    $url = new moodle_url('/local/downloadcenter/index.php', array('courseid' => $COURSE->id));

    if ($PAGE->url->compare($url)) {
        $pix = new pix_icon('t/collapsed_empty', $title);
    } else {
        $pix = new pix_icon('t/collapsed', $title);
    }


    $childnode = navigation_node::create(
        $title,
        $url,
        navigation_node::TYPE_SITE_ADMIN,
        'downloadcenter',
        'downloadcenter',
        $pix
    );

    $node = $settings_nav->add_node($childnode, $beforenode);
    $node->nodetype = navigation_node::NODETYPE_LEAF;
}
