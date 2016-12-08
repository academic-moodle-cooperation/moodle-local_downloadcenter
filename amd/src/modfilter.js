/*
 * @package     local_downloadcenter
 * @author      Simeon Naydenov (moniNaydenov@gmail.com)
 * @copyright   2016 AMC
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @module local_downloadcenter/modfilter
 */
define(['jquery', 'core/str'], function($, Str) {

    var strings = {};
    var modnames;
    var formid;
    var currentlyshown = false;
    var modlist;

    /**
     * @constructor
     * @alias module:block_overview/helloworld
     */
    var modfilter = function(modnms) {
        /** @access private */

        modnames = modnms;
        /** @access public */
        Str.get_strings([
                {key: 'all', component: 'moodle'},
                {key: 'none', component: 'moodle'},
                {key: 'select', component: 'moodle'},
                {key: 'showtypes', component: 'backup'},
                {key: 'hidetypes', component: 'backup'}
            ]).done(function(strs) {

            //init strings.. new moodle super cool way
            strings['all'] = strs[0];
            strings['none'] = strs[1];
            strings['select'] = strs[2];
            strings['showtypes'] = strs[3];
            strings['hidetypes'] = strs[4];

            var firstsection = $('#mform1 fieldset').first();
            formid = firstsection.parent('form').prop('id');
            // Add global select all/none options.
            var html = html_generator('include_setting section_level', 'included', strings['select'],
            ' (<a id="downloadcenter-bytype" href="#">' + strings['showtypes'] + '</a>)');
            var links = $(document.createElement('div'));
            links.addClass('grouped_settings section_level block');
            links.html(html);

            links.prependTo(firstsection);

            // For each module type on the course, add hidden select all/none options.
            modlist = $(document.createElement('div'));
            modlist.prop('id', 'mod_select_links');
            modlist.appendTo(links);
            modlist.hide();

            for (var mod in modnames) {
                // Only include actual values from the list.
                if (!modnames.hasOwnProperty(mod)) {
                    continue;
                }

                html = html_generator('include_setting section_level', 'mod_' + mod, modnames[mod]);
                var modlinks = $(document.createElement('div'));
                modlinks.addClass('grouped_settings section_level');
                modlinks.html(html);
                modlinks.appendTo(modlist);
                initlinks(modlinks, mod);
            }

            //attach events to links
            $('#downloadcenter-all-included').click(function(e) { helper(e, true,  'item_'); });
            $('#downloadcenter-none-included').click(function(e) { helper(e, false, 'item_'); });
            $('#downloadcenter-bytype').click(function() { toggletypes(); });
        });

    };

    // Toggles the display of the hidden module select all/none links.
    var toggletypes = function() {
        // Change text of type toggle link.
        var link = $('#downloadcenter-bytype');
        if (currentlyshown) {
            link.text(strings['showtypes']);
        } else {
            link.text(strings['hidetypes']);
        }
        modlist.animate({height: 'toggle' }, 500, 'swing');

        currentlyshown = !currentlyshown;

    };

    var initlinks = function(links, mod) {
        $('#downloadcenter-all-mod_' + mod).click(function(e) { helper(e, true, 'item_', mod); });
        $('#downloadcenter-none-mod_' + mod).click(function(e) { helper(e, false, 'item_', mod); });

    };

    var helper = function(e, check, type, mod) {
        e.preventDefault();
        var prefix = '';
        if (typeof mod !== 'undefined') {
            prefix = 'item_' + mod + '_';
        }

        var len = type.length;

        $('input[type="checkbox"]').each(function(i, checkbox) {
            checkbox = $(checkbox);
            var name = checkbox.prop('name');

            // If a prefix has been set, ignore checkboxes which don't have that prefix.
            if (prefix && name.substring(0, prefix.length) !== prefix) {
                return;
            }
            if (name.substring(0, len) === type) {
                checkbox.prop('checked', check);
            }
        });

        // At this point, we really need to persuade the form we are part of to
        // update all of its disabledIf rules. However, as far as I can see,
        // given the way that lib/form/form.js is written, that is impossible.
        if (formid && M.form) {
            M.form.updateFormState(formid);
        }
    };

    var html_generator = function(classname, idtype, heading, extra) {
        if (typeof extra === 'undefined') {
            extra = '';
        }
        return '<div class="' + classname + '">' +
            '<div class="fitem fitem_fcheckbox downloadcenter_selector">' +
            '<div class="fitemtitle">' + heading + '</div>' +
            '<div class="felement">' +
            '<a id="downloadcenter-all-' + idtype + '" href="#">' + strings['all'] + '</a> / ' +
            '<a id="downloadcenter-none-' + idtype + '" href="#">' + strings['none'] + '</a>' +
            extra +
            '</div>' +
            '</div>' +
            '</div>';
    };



    return {
        init: function(modnames) {
            return new modfilter(modnames);
        }
    };
});