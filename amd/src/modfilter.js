/*
 * @package     local_downloadcenter
 * @author      Simeon Naydenov (moniNaydenov@gmail.com)
 * @copyright   2020 AMC
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @module local_downloadcenter/modfilter
 */
define(['jquery', 'core/str', 'core/url'], function($, Str, url) {

    /**
     * @constructor
     */
    var ModFilter = function(modnames) {

        var instance = this;
        this.modnames = modnames;
        this.strings = {};
        this.formid = null;
        this.currentlyshown = false;
        this.modlist = null;

        Str.get_strings([
            {key: 'all', component: 'moodle'},
            {key: 'none', component: 'moodle'},
            {key: 'select', component: 'moodle'},
            {key: 'showtypes', component: 'backup'},
            {key: 'hidetypes', component: 'backup'}
        ]).done(function(strs) {
            // Init strings.. new moodle super cool way...
            instance.strings['all'] = strs[0];
            instance.strings['none'] = strs[1];
            instance.strings['select'] = strs[2];
            instance.strings['showtypes'] = strs[3];
            instance.strings['hidetypes'] = strs[4];

            var firstsection = $('div[role="main"] > form .card.block').first();
            instance.formid = firstsection.closest('form').prop('id');

            // Add global select all/none options...
            var html = instance.html_generator('included', instance.strings['select']);
            html += instance.row_generator(
                '(<a id="downloadcenter-bytype" href="#">' + instance.strings['showtypes'] + '</a>)',
                ''); // I hope this looks better than on one line :)!
            var links = $(document.createElement('div'));
            links.addClass('grouped_settings section_level block card');
            links.html(html);

            links.insertBefore(firstsection);

            // For each module type on the course, add hidden select all/none options.
            instance.modlist = $(document.createElement('div'));
            instance.modlist.prop('id', 'mod_select_links');
            instance.modlist.prop('class', 'm-l-2');
            instance.modlist.appendTo(links);
            instance.modlist.hide();

            for (var mod in instance.modnames) {
                // Only include actual values from the list..
                if (!instance.modnames.hasOwnProperty(mod)) {
                    continue;
                }

                var img = '<img src="' + url.imageUrl('icon', 'mod_' + mod) + '" class="activityicon" />';
                html = instance.html_generator('mod_' + mod, img + instance.modnames[mod]);
                var modlinks = $(document.createElement('div'));
                modlinks.addClass('grouped_settings section_level');
                modlinks.html(html);
                modlinks.appendTo(instance.modlist);
                instance.initlinks(modlinks, mod);
            }

            // Attach events to links!
            $('#downloadcenter-all-included').click(function(e) { instance.helper(e, true,  'item_'); });
            $('#downloadcenter-none-included').click(function(e) { instance.helper(e, false, 'item_'); });
            $('#downloadcenter-bytype').click(function(e) { e.preventDefault(); instance.toggletypes(); });
            // Attach event to checkboxes!
            $('input.form-check-input').click(function() { instance.checkboxhandler($(this)); instance.updateFormState(); });
        });

    };

    ModFilter.prototype.checkboxhandler = function($checkbox) {
        var prefix = 'item_topic';
        var shortprefix = 'item_';
        var name = $checkbox.prop('name');
        var checked = $checkbox.prop('checked');
        if (name.substring(0, shortprefix.length) === shortprefix) {
            var $parent = $checkbox.parentsUntil('form', '.card');
            if (name.substring(0, prefix.length) === prefix) {
                $parent.find('input.form-check-input').prop('checked', checked);
            } else {
                if (checked) {
                    $parent.find('input.form-check-input[name^="item_topic"]').prop('checked', true);
                }
            }
        }
    };

    ModFilter.prototype.updateFormState = function() {
        // At this point, we really need to persuade the form we are part of to
        // update all of its disabledIf rules. However, as far as I can see,
        // given the way that lib/form/form.js is written, that is impossible.
        if (this.formid && M.form && M.form.updateFormState) {
            M.form.updateFormState(this.formid);
        }
    };

    // Toggles the display of the hidden module select all/none links.
    ModFilter.prototype.toggletypes = function() {
        // Change text of type toggle link.
        var link = $('#downloadcenter-bytype');
        if (this.currentlyshown) {
            link.text(this.strings['showtypes']);
        } else {
            link.text(this.strings['hidetypes']);
        }
        this.modlist.animate({height: 'toggle' }, 500, 'swing');

        this.currentlyshown = !this.currentlyshown;

    };

    ModFilter.prototype.initlinks = function(links, mod) {
        var instance = this;
        $('#downloadcenter-all-mod_' + mod).click(function(e) { instance.helper(e, true, 'item_', mod); });
        $('#downloadcenter-none-mod_' + mod).click(function(e) { instance.helper(e, false, 'item_', mod); });

    };

    ModFilter.prototype.helper = function(e, check, type, mod) {
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
            if (check) {
                checkbox.closest('.card.block').find('.form-group:first-child input').prop('checked', check);
            }
        });

        this.updateFormState();
    };

    ModFilter.prototype.html_generator = function(idtype, heading) {
        var links = '<a id="downloadcenter-all-' + idtype + '" href="#">' + this.strings['all'] + '</a> / ';
        links += '<a id="downloadcenter-none-' + idtype + '" href="#">' + this.strings['none'] + '</a>';
        return this.row_generator(heading, links);
    };

    ModFilter.prototype.row_generator = function(heading, content) {
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
            return new ModFilter(modnames);
        }
    };
});