

    /**
     * Adds select all/none links to the top of the backup/restore/import schema page.
     *
     * @module moodle-local-downloadcenter
     */

// Namespace for the backupc
    M.local_downloadcenter = M.local_downloadcenter || {};
    /**
     * Adds select all/none links to the top of the backup/restore/import schema page.
     *
     * @class M.local_downloadcenter.backupselectall
     */
    M.local_downloadcenter.backupselectall = function(modnames) {
        console.log(modnames);
        var formid = null;

        var helper = function(e, check, type, mod) {
            e.preventDefault();
            var prefix = '';
            if (typeof mod !== 'undefined') {
                prefix = 'setting_activity_' + mod + '_';
            }

            var len = type.length;
            Y.all('input[type="checkbox"]').each(function(checkbox) {
                var name = checkbox.get('name');
                console.log(name.substring(0, len));

                // If a prefix has been set, ignore checkboxes which don't have that prefix.
                if (prefix && name.substring(0, prefix.length) !== prefix) {
                    return;
                }
                if (name.substring(0, len) === type) {
                    checkbox.set('checked', check);
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
                '<div class="fitem fitem_fcheckbox backup_selector">' +
                '<div class="fitemtitle">' + heading + '</div>' +
                '<div class="felement">' +
                '<a id="backup-all-' + idtype + '" href="#">' + M.util.get_string('all', 'moodle') + '</a> / ' +
                '<a id="backup-none-' + idtype + '" href="#">' + M.util.get_string('none', 'moodle') + '</a>' +
                extra +
                '</div>' +
                '</div>' +
                '</div>';
        };

        var firstsection = Y.one('#mform1 fieldset');

        if (!firstsection) {
            // This is not a relevant page.
            return;
        }
        if (!firstsection.one('.felement.fcheckbox')) {
            // No checkboxes.
            return;
        }
        formid = firstsection.ancestor('form').getAttribute('id');

        // Add global select all/none options.
        var html = html_generator('include_setting section_level', 'included', M.util.get_string('select', 'moodle'),
            ' (<a id="backup-bytype" href="#">' + M.util.get_string('showtypes', 'backup') + '</a>)');
        var links = Y.Node.create('<div class="grouped_settings section_level">' + html + '</div>');
        firstsection.insert(links, 'before');

        // Add select all/none for each module type.
        var initlinks = function(links, mod) {
            Y.one('#backup-all-mod_' + mod).on('click', function(e) { helper(e, true, '_included', mod); });
            Y.one('#backup-none-mod_' + mod).on('click', function(e) { helper(e, false, '_included', mod); });

        };

        // For each module type on the course, add hidden select all/none options.
        var modlist = Y.Node.create('<div id="mod_select_links">');
        modlist.hide();
        modlist.currentlyshown = false;
        links.appendChild(modlist);
        /*for (var mod in modnames) {
            // Only include actual values from the list.
            if (!modnames.hasOwnProperty(mod)) {
                continue;
            }
            html = html_generator('include_setting section_level', 'mod_' + mod, modnames[mod]);

            var modlinks = Y.Node.create(
                '<div class="grouped_settings section_level">' + html + '</div>');
            modlist.appendChild(modlinks);
            initlinks(modlinks, mod);
        }

        // Toggles the display of the hidden module select all/none links.
        var toggletypes = function() {
            // Change text of type toggle link.
            var link = Y.one('#backup-bytype');
            if (modlist.currentlyshown) {
                link.setHTML(M.util.get_string('showtypes', 'backup'));
            } else {
                link.setHTML(M.util.get_string('hidetypes', 'backup'));
            }

            // The link has now been toggled (from show to hide, or vice-versa).
            modlist.currentlyshown = !modlist.currentlyshown;

            // Either hide or show the links.
            var animcfg = { node: modlist, duration: 0.2 },
                anim;
            if (modlist.currentlyshown) {
                // Animate reveal of the module links.
                modlist.show();
                animcfg.to = { maxHeight: modlist.get('clientHeight') + 'px' };
                modlist.setStyle('maxHeight', '0px');
                anim = new Y.Anim(animcfg);
                anim.on('end', function() { modlist.setStyle('maxHeight', 'none'); });
                anim.run();
            } else {
                // Animate hide of the module links.
                animcfg.to = { maxHeight: '0px' };
                modlist.setStyle('maxHeight', modlist.get('clientHeight') + 'px');
                anim = new Y.Anim(animcfg);
                anim.on('end', function() { modlist.hide(); modlist.setStyle('maxHeight', 'none'); });
                anim.run();
            }

        };*/
        Y.one('#backup-bytype').on('click', function() { toggletypes(); });

        Y.one('#backup-all-included').on('click',  function(e) { helper(e, true,  'item_'); });
        Y.one('#backup-none-included').on('click', function(e) { helper(e, false, 'item_'); });
    };

