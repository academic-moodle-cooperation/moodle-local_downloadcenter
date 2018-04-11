<?php
/**
 *
 * @package       moodle34
 * @author        Simeon Naydenov (moniNaydenov@gmail.com)
 * @copyright     2018
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_downloadcenter\privacy;


class provider implements \core_privacy\local\metadata\null_provider {

    /**
     * Get the language string identifier with the component's language
     * file to explain why this plugin stores no data.
     *
     * @return  string
     */
    public static function get_reason() : string {
        return 'privacy:null_reason';
    }
}