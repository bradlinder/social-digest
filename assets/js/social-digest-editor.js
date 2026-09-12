(function() {
    'use strict';

    tinymce.PluginManager.add('social_digest_split_plugin', function(editor) {
        var insertSplitter = function() {
            editor.insertContent(
                '<p class="social-digest-split-marker" ' +
                'style="text-align:center;background:#eee;padding:6px;' +
                'border:1px dashed #999;color:#555;font-weight:bold;' +
                'user-select:none;">' +
                '<!--digest_split-->--- POSTS APPEAR HERE (Header / Footer Split) ---' +
                '</p><p></p>'
            );
        };

        // WordPress currently uses TinyMCE's UI registry API. Keep a fallback
        // for older WordPress/TinyMCE versions that still expose addButton().
        if (editor.ui && editor.ui.registry) {
            editor.ui.registry.addButton('social_digest_split_button', {
                text: 'Insert Post Splitter',
                tooltip: 'Insert Post Splitter (Separates Header and Footer)',
                onAction: insertSplitter
            });
        } else if (typeof editor.addButton === 'function') {
            editor.addButton('social_digest_split_button', {
                text: 'Insert Post Splitter',
                icon: 'hr',
                tooltip: 'Insert Post Splitter (Separates Header and Footer)',
                onclick: insertSplitter
            });
        }

        return {
            getMetadata: function() {
                return {
                    name: 'Social Digest Splitter'
                };
            }
        };
    });
})();
