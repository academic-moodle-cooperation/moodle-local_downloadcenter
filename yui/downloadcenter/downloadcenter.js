

YUI.add('moodle-local_downloadcenter-downloadcenter', function(Y) {

    /**
     * Adds select all/none links to the top of the downloadcenter page.
     *
     * @module moodle-local-downloadcenter
     */
    M.local_downloadcenter = M.local_downloadcenter || {};
// Namespace for the downloadcenter
    /**
     * Adds select all/none links to the top of the downloadcenter page.
     *
     * @class M.local_downloadcenter.downloadselectall
     */
    M.local_downloadcenter.downloadselectall = function(modnames) {
        var formid = null;

        var helper = function(e, check, type, mod) {
            e.preventDefault();
            var prefix = '';
            if (typeof mod !== 'undefined') {
                prefix = 'item_' + mod + '_';
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
                '<div class="fitem fitem_fcheckbox downloadcenter_selector">' +
                '<div class="fitemtitle">' + heading + '</div>' +
                '<div class="felement">' +
                '<a id="downloadcenter-all-' + idtype + '" href="#">' + M.util.get_string('all', 'moodle') + '</a> / ' +
                '<a id="downloadcenter-none-' + idtype + '" href="#">' + M.util.get_string('none', 'moodle') + '</a>' +
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
            ' (<a id="downloadcenter-bytype" href="#">' + M.util.get_string('showtypes', 'backup') + '</a>)');
        var links = Y.Node.create('<div class="grouped_settings section_level block">' + html + '</div>');
        //firstsection.insert(links, 'before');
        firstsection.prepend(links);

        // Add select all/none for each module type.
        var initlinks = function(links, mod) {
            Y.one('#downloadcenter-all-mod_' + mod).on('click', function(e) { helper(e, true, 'item_', mod); });
            Y.one('#downloadcenter-none-mod_' + mod).on('click', function(e) { helper(e, false, 'item_', mod); });

        };

        // For each module type on the course, add hidden select all/none options.
        var modlist = Y.Node.create('<div id="mod_select_links">');
        modlist.hide();
        modlist.currentlyshown = false;
        links.appendChild(modlist);
        for (var mod in modnames) {
            // Only include actual values from the list.
            if (!modnames.hasOwnProperty(mod)) {
                continue;
            }
            html = html_generator('include_setting section_level', 'mod_' + mod, modnames[mod]);

            var modlinks = Y.Node.create(html );
            modlist.appendChild(modlinks);
            initlinks(modlinks, mod);
        }

        // Toggles the display of the hidden module select all/none links.
        var toggletypes = function() {
            // Change text of type toggle link.
            var link = Y.one('#downloadcenter-bytype');
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

        };
        Y.one('#downloadcenter-bytype').on('click', function() { toggletypes(); });

        Y.one('#downloadcenter-all-included').on('click',  function(e) { helper(e, true,  'item_'); });
        Y.one('#downloadcenter-none-included').on('click', function(e) { helper(e, false, 'item_'); });
    };
}, '@VERSION@', {requires: ["node", "event", "node-event-simulate", "anim"]});
