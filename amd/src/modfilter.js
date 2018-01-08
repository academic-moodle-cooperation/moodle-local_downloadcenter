/*
 * @package     local_downloadcenter
 * @author      Simeon Naydenov (moniNaydenov@gmail.com)
 * @copyright   2016 AMC
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @module local_downloadcenter/modfilter
 */
define(['jquery', 'core/str', 'core/url'], function($, Str, url) {

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

        modnames = modnms;

        Str.get_strings([
            {key: 'all', component: 'moodle'},
            {key: 'none', component: 'moodle'},
            {key: 'select', component: 'moodle'},
            {key: 'showtypes', component: 'backup'},
            {key: 'hidetypes', component: 'backup'}
        ]).done(function(strs) {

            // Init strings.. new moodle super cool way.
            strings['all'] = strs[0];
            strings['none'] = strs[1];
            strings['select'] = strs[2];
            strings['showtypes'] = strs[3];
            strings['hidetypes'] = strs[4];

            var firstsection = $('#mform1 .card.block').first();
            formid = firstsection.parent('form').prop('id');

            // Add global select all/none options.
            var html = html_generator('included', strings['select']);
            html += row_generator('(<a id="downloadcenter-bytype" href="#">' + strings['showtypes'] + '</a>)', '');
            var links = $(document.createElement('div'));
            links.addClass('grouped_settings section_level block card');
            links.html(html);

            links.insertBefore(firstsection);

            // For each module type on the course, add hidden select all/none options.
            modlist = $(document.createElement('div'));
            modlist.prop('id', 'mod_select_links');
            modlist.prop('class', 'm-l-2');
            modlist.appendTo(links);
            modlist.hide();

            for (var mod in modnames) {
                // Only include actual values from the list.
                if (!modnames.hasOwnProperty(mod)) {
                    continue;
                }

                var img = '<img src="' + url.imageUrl('icon', 'mod_' + mod) + '" class="activityicon" />';
                html = html_generator('mod_' + mod, img + modnames[mod]);
                var modlinks = $(document.createElement('div'));
                modlinks.addClass('grouped_settings section_level');
                modlinks.html(html);
                modlinks.appendTo(modlist);
                initlinks(modlinks, mod);
            }

            // Attach events to links!
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

    var html_generator = function(idtype, heading) {
        var links = '<a id="downloadcenter-all-' + idtype + '" href="#">' + strings['all'] + '</a> / ';
        links += '<a id="downloadcenter-none-' + idtype + '" href="#">' + strings['none'] + '</a>';
        return row_generator(heading, links);
    };

    var row_generator = function(heading, content) {
        var ret = '<div class="form-group row fitem downloadcenter_selector">';
        ret += '<div class="col-md-3"></div>';
        ret += '<div class="col-md-9">';
        ret += '<label><span class="itemtitle">' + heading + '</span></label>';
        ret += '<span class="text-nowrap">' + content + '</span>';
        ret += '</div>';
        ret += '</div>';
        return ret;
    };

    return {
        init: function(modnames) {
            return new modfilter(modnames);
        }
    };
});