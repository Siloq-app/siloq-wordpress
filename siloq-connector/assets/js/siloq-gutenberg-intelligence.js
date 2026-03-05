/**
 * siloq-gutenberg-intelligence.js
 *
 * Gutenberg (block editor) Widget Intelligence integration.
 * Registers a sidebar plugin that injects Siloq Intelligence panel
 * into InspectorControls for supported block types.
 *
 * @package Siloq
 * @since   1.5.58
 */
(function () {
    'use strict';

    // Guard: require Gutenberg APIs
    if (
        typeof wp === 'undefined' ||
        !wp.element ||
        !wp.plugins ||
        !wp.blockEditor ||
        !wp.components ||
        !wp.data
    ) {
        return;
    }

    var el              = wp.element.createElement;
    var Fragment        = wp.element.Fragment;
    var registerPlugin  = wp.plugins.registerPlugin;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var useSelect       = wp.data.useSelect;
    var useDispatch     = wp.data.useDispatch;
    var useState        = wp.element.useState;
    var PanelBody       = wp.components.PanelBody;
    var Button          = wp.components.Button;
    var Notice          = wp.components.Notice;

    var TARGET_BLOCKS = [
        'core/paragraph',
        'core/heading',
        'core/image',
        'core/cover',
        'core/list',
    ];

    var cfg  = window.siloqIntelligenceCore || {};
    var core = window.SiloqIntelligenceCore;

    if (!core) return; // core JS must be loaded first

    // ── Page map builder ──────────────────────────────────────────────────

    function buildGutenbergPageMap() {
        try {
            var blocks     = wp.data.select('core/block-editor').getBlocks();
            var headings   = [];
            var containers = [];

            function traverse(list) {
                (list || []).forEach(function (block) {
                    if (block.name === 'core/heading') {
                        headings.push({
                            tag:       'h' + (block.attributes.level || 2),
                            text:      block.attributes.content
                                           ? jQuery('<div>').html(block.attributes.content).text()
                                           : '',
                            widget_id: block.clientId,
                        });
                    }
                    if (block.innerBlocks && block.innerBlocks.length) {
                        containers.push({
                            container_id: block.clientId,
                            children: block.innerBlocks.map(function (b) {
                                return { id: b.clientId, type: b.name };
                            }),
                        });
                        traverse(block.innerBlocks);
                    }
                });
            }

            traverse(blocks);
            return { headings: headings, containers: containers };
        } catch (e) {
            return { headings: [], containers: [] };
        }
    }

    // ── Inspector panel component ─────────────────────────────────────────

    var SiloqInspectorPanel = function (props) {
        var clientId   = props.clientId;
        var blockName  = props.blockName;
        var attributes = props.attributes;

        var updateBlockAttributes = useDispatch('core/block-editor').updateBlockAttributes;

        var _s     = useState({ loading: false, result: null, error: null });
        var state  = _s[0];
        var setState = _s[1];

        function getContent() {
            if (blockName === 'core/heading')   return attributes.content || '';
            if (blockName === 'core/paragraph') return attributes.content || '';
            if (blockName === 'core/image')     return attributes.alt     || '';
            if (blockName === 'core/list')      return attributes.values  || '';
            return attributes.content || '';
        }

        function getWidgetType() {
            if (blockName === 'core/heading') return 'heading';
            if (blockName === 'core/image')   return 'image';
            return 'text-editor';
        }

        function handleAnalyze() {
            setState({ loading: true, result: null, error: null });
            var pageMap = buildGutenbergPageMap();
            var payload = core.buildWidgetAnalysisPayload(
                {
                    type:      getWidgetType(),
                    content:   jQuery('<div>').html(getContent()).text(),
                    widget_id: clientId,
                },
                pageMap,
                cfg
            );
            core.analyzeWidget(payload, {
                success: function (data) {
                    setState({ loading: false, result: data, error: null });
                },
                error: function (msg) {
                    setState({ loading: false, result: null, error: msg });
                },
            });
        }

        function handleApply() {
            if (!state.result) return;
            var suggestion = state.result.suggested_content || '';
            try {
                if (blockName === 'core/image') {
                    updateBlockAttributes(clientId, { alt: suggestion });
                } else if (blockName === 'core/heading' && state.result.suggested_heading_tag) {
                    var level = parseInt(state.result.suggested_heading_tag.replace('h', ''), 10) || 2;
                    updateBlockAttributes(clientId, { content: suggestion, level: level });
                } else {
                    updateBlockAttributes(clientId, { content: suggestion });
                }
                setState({ loading: false, result: Object.assign({}, state.result, { applied: true }), error: null });
            } catch (e) {
                setState({ loading: false, result: state.result, error: 'Could not apply: ' + e.message });
            }
        }

        var layerColors = { hub: '#4f46e5', spoke: '#0891b2', supporting: '#059669' };
        var layerLabels = { hub: 'Hub Page', spoke: 'Spoke Page', supporting: 'Supporting' };
        var layer = state.result && state.result.layer;

        return el(
            InspectorControls,
            null,
            el(
                PanelBody,
                { title: '⚡ Siloq Intelligence', initialOpen: false },

                // Analyze button
                !state.loading && !state.result &&
                    el(Button, { isPrimary: true, onClick: handleAnalyze, style: { width: '100%' } },
                        '⚡ Analyze This Block'),

                // Loading
                state.loading &&
                    el('p', { style: { fontSize: '12px', color: '#6b7280', margin: '8px 0' } }, 'Analyzing…'),

                // Error
                state.error &&
                    el(Notice, { status: 'error', isDismissible: false }, state.error),

                // Results
                state.result && !state.result.applied &&
                    el(Fragment, null,
                        layer && el('p', {
                            style: {
                                fontSize: '11px', fontWeight: 700,
                                color: layerColors[layer] || '#6b7280',
                                margin: '0 0 8px',
                            },
                        }, '🏷 ' + (layerLabels[layer] || layer)),

                        (state.result.layer_violations || []).map(function (v, i) {
                            return el(Notice, { key: 'lv' + i, status: 'warning', isDismissible: false },
                                typeof v === 'string' ? v : v.issue);
                        }),

                        (state.result.heading_violations || []).map(function (v, i) {
                            return el(Notice, { key: 'hv' + i, status: 'warning', isDismissible: false },
                                v.issue + (v.fix ? ' → ' + v.fix : ''));
                        }),

                        el('p', {
                            style: {
                                fontSize: '10px', fontWeight: 700, color: '#6b7280',
                                textTransform: 'uppercase', margin: '8px 0 4px',
                            },
                        }, 'Suggested'),

                        el('p', {
                            style: {
                                fontSize: '12px', background: '#d1fae5', color: '#065f46',
                                padding: '8px', borderRadius: 5, lineHeight: 1.5, margin: '0 0 8px',
                            },
                        }, state.result.suggested_content || ''),

                        el('div', { style: { display: 'flex', gap: 6 } },
                            el(Button, { isPrimary: true, onClick: handleApply, style: { flex: 1 } }, '✅ Apply'),
                            el(Button, {
                                isSecondary: true,
                                onClick: function () { setState({ loading: false, result: null, error: null }); },
                            }, 'Skip')
                        )
                    ),

                // Applied confirmation
                state.result && state.result.applied &&
                    el(Notice, { status: 'success', isDismissible: false }, '✅ Applied! Content updated.')
            )
        );
    };

    // ── Register plugin ───────────────────────────────────────────────────

    registerPlugin('siloq-block-intelligence', {
        render: function () {
            var selectedBlock = useSelect(function (select) {
                return select('core/block-editor').getSelectedBlock();
            });

            if (!selectedBlock || TARGET_BLOCKS.indexOf(selectedBlock.name) === -1) {
                return null;
            }

            return el(SiloqInspectorPanel, {
                clientId:   selectedBlock.clientId,
                blockName:  selectedBlock.name,
                attributes: selectedBlock.attributes,
            });
        },
    });

}());
