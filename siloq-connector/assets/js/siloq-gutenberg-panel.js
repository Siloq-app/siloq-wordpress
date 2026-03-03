/**
 * Siloq Gutenberg PluginSidebar
 *
 * Registers a PluginSidebar in the block editor that hosts the same
 * 3-tab SEO panel as the floating panel (via SiloqFloatingPanel).
 *
 * @package Siloq_Connector
 * @since   1.5.50
 */

/* global wp, siloqGB, SiloqFloatingPanel, jQuery */

(function () {
    'use strict';

    var registerPlugin  = wp.plugins.registerPlugin;
    var PluginSidebar   = wp.editPost.PluginSidebar;
    var el              = wp.element.createElement;
    var useState        = wp.element.useState;

    // Initialise the shared floating panel with Gutenberg credentials
    jQuery(document).ready(function () {
        if (typeof siloqGB !== 'undefined' && typeof SiloqFloatingPanel !== 'undefined') {
            SiloqFloatingPanel.init(siloqGB.postId, siloqGB.ajaxUrl, siloqGB.nonce);
        }
    });

    registerPlugin('siloq-seo-panel', {
        render: function () {
            return el(
                PluginSidebar,
                {
                    name:  'siloq-seo',
                    title: '⚡ Siloq SEO',
                    icon:  'awards',
                },
                el(
                    'div',
                    {
                        id:    'siloq-gutenberg-root',
                        style: { padding: '12px' },
                    },

                    // Analyze button — delegates to the shared SiloqFloatingPanel
                    el(
                        'div',
                        { id: 'siloq-gb-analyze' },
                        el(
                            'button',
                            {
                                className: 'components-button is-primary',
                                style:     { width: '100%', justifyContent: 'center', marginBottom: '12px' },
                                onClick:   function () {
                                    if (typeof SiloqFloatingPanel !== 'undefined') {
                                        SiloqFloatingPanel.analyze();
                                    }
                                },
                            },
                            '🔍 Analyze Page'
                        )
                    ),

                    // Hint pointing to the floating panel for full functionality
                    el(
                        'p',
                        { style: { fontSize: '12px', color: '#6b7280', lineHeight: '1.5' } },
                        'Full Recommendations, Content, and Structure tools are available in the ⚡ Siloq side panel (bottom-right of the screen).'
                    )
                )
            );
        }
    });

}());
