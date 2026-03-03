<?php
/**
 * Siloq Elementor Editor Floating Panel
 *
 * Injects a slide-out Siloq SEO panel into the Elementor page editor.
 * Uses elementor/editor/footer (HTML) and elementor/editor/after_enqueue_scripts (CSS/JS)
 * hooks — works across Elementor 2.x, 3.x, and 3.20+.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Siloq_Elementor_Panel {

    /**
     * Register hooks. Called only when Elementor is active.
     */
    public static function init() {
        add_action( 'elementor/editor/footer',               [ __CLASS__, 'render_panel_html' ] );
        add_action( 'elementor/editor/after_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );

        // AJAX handlers
        add_action( 'wp_ajax_siloq_generate_content_snippet',  [ __CLASS__, 'ajax_generate_content_snippet' ] );
        add_action( 'wp_ajax_siloq_get_supporting_pages',      [ __CLASS__, 'ajax_get_supporting_pages' ] );
        add_action( 'wp_ajax_siloq_create_supporting_draft',   [ __CLASS__, 'ajax_create_supporting_draft' ] );
    }

    /* -----------------------------------------------------------------------
     * HTML
     * -------------------------------------------------------------------- */

    public static function render_panel_html() {
        $icon_url = esc_url( SILOQ_PLUGIN_URL . 'assets/images/siloq-icon.png' );
        ?>
        <!-- Siloq Elementor Floating Panel -->

        <!-- Trigger button (vertical tab on right edge) -->
        <div id="siloq-elementor-trigger" title="Open Siloq SEO Panel">
            <img src="<?php echo $icon_url; ?>" alt="Siloq" onerror="this.style.display='none'" />
            <span>&#9889; Siloq</span>
        </div>

        <!-- Slide-out panel -->
        <div id="siloq-elementor-panel" class="siloq-ep-panel siloq-ep-closed">

            <div class="siloq-ep-header">
                <span>&#9889; Siloq SEO</span>
                <button class="siloq-ep-close" title="Close">&#x2715;</button>
            </div>

            <div class="siloq-ep-body">

                <!-- Tab Nav -->
                <div class="siloq-ep-tabs">
                    <button class="siloq-ep-tab active" data-tab="recommendations">Recommendations</button>
                    <button class="siloq-ep-tab" data-tab="content">Content</button>
                    <button class="siloq-ep-tab" data-tab="structure">Structure</button>
                </div>

                <!-- Tab: Recommendations -->
                <div class="siloq-ep-tab-content active" id="siloq-tab-recommendations">
                    <div id="siloq-ep-score-widget"></div>
                    <div id="siloq-ep-recs-list"></div>
                    <button id="siloq-ep-analyze-btn" class="siloq-ep-btn siloq-ep-btn-primary">&#128269; Analyze This Page</button>
                    <div id="siloq-ep-apply-row" style="display:none;">
                        <button id="siloq-ep-apply-meta-btn" class="siloq-ep-btn">&#9989; Apply Title &amp; Meta</button>
                        <button id="siloq-ep-apply-schema-btn" class="siloq-ep-btn">&#128203; Apply Schema</button>
                    </div>
                </div>

                <!-- Tab: Content -->
                <div class="siloq-ep-tab-content" id="siloq-tab-content">
                    <p class="siloq-ep-hint">Generate SEO content to add to this page.</p>
                    <div id="siloq-ep-content-type-row">
                        <label><input type="radio" name="siloq_content_type" value="faq" checked /> FAQ Section</label><br/>
                        <label><input type="radio" name="siloq_content_type" value="services" /> Services List</label><br/>
                        <label><input type="radio" name="siloq_content_type" value="about" /> About / Trust Section</label>
                    </div>
                    <button id="siloq-ep-generate-btn" class="siloq-ep-btn siloq-ep-btn-primary">&#10024; Generate Content</button>
                    <div id="siloq-ep-generated-content" style="display:none;">
                        <textarea id="siloq-ep-content-output" rows="8" readonly style="width:100%;box-sizing:border-box;margin-top:8px;font-size:12px;border:1px solid #d1d5db;border-radius:4px;padding:8px;"></textarea>
                        <p class="siloq-ep-hint">Copy this content and paste it into an Elementor Text or Accordion widget.</p>
                        <button id="siloq-ep-copy-btn" class="siloq-ep-btn">&#128203; Copy to Clipboard</button>
                    </div>
                </div>

                <!-- Tab: Structure -->
                <div class="siloq-ep-tab-content" id="siloq-tab-structure">
                    <p class="siloq-ep-section-label">Supporting Pages Needed</p>
                    <div id="siloq-ep-supporting-list"></div>
                    <button id="siloq-ep-create-draft-btn" class="siloq-ep-btn">&#10133; Create Draft Supporting Page</button>
                </div>

            </div><!-- /.siloq-ep-body -->

            <div id="siloq-ep-status-bar"></div>

        </div><!-- /#siloq-elementor-panel -->
        <?php
    }

    /* -----------------------------------------------------------------------
     * Assets (CSS + JS inlined onto elementor-editor handle)
     * -------------------------------------------------------------------- */

    public static function enqueue_assets() {
        // Localize data onto the already-registered elementor-editor script
        wp_localize_script(
            'elementor-editor',
            'siloqEP',
            [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'siloq_ai_nonce' ),
                'postId'  => (int) get_the_ID(),
                'siteId'  => get_option( 'siloq_site_id', '' ),
            ]
        );

        // ---- CSS ----
        $css = self::get_panel_css();
        wp_add_inline_style( 'elementor-editor', $css );

        // ---- JS ----
        $js = self::get_panel_js();
        wp_add_inline_script( 'elementor-editor', $js, 'after' );
    }

    /* -----------------------------------------------------------------------
     * CSS
     * -------------------------------------------------------------------- */

    private static function get_panel_css() {
        return '
/* ===== Siloq Elementor Floating Panel ===== */

#siloq-elementor-trigger {
    position: fixed;
    right: 0;
    top: 50%;
    transform: translateY(-50%);
    z-index: 9999;
    background: #4f46e5;
    padding: 12px 8px;
    border-radius: 8px 0 0 8px;
    cursor: pointer;
    color: #fff;
    writing-mode: vertical-lr;
    font-size: 13px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    font-weight: 600;
    box-shadow: -3px 0 12px rgba(0,0,0,0.25);
    display: flex;
    align-items: center;
    gap: 6px;
    user-select: none;
    transition: background 0.2s;
}
#siloq-elementor-trigger:hover {
    background: #4338ca;
}
#siloq-elementor-trigger img {
    width: 20px;
    height: 20px;
    object-fit: contain;
}

#siloq-elementor-panel {
    position: fixed;
    right: -380px;
    top: 0;
    width: 360px;
    height: 100vh;
    background: #fff;
    z-index: 99999;
    box-shadow: -4px 0 24px rgba(0,0,0,0.18);
    transition: right 0.3s ease;
    overflow-y: auto;
    border-left: 3px solid #4f46e5;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    display: flex;
    flex-direction: column;
}
.siloq-ep-panel.siloq-ep-open {
    right: 0 !important;
}

.siloq-ep-header {
    background: #4f46e5;
    color: #fff;
    padding: 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-weight: 700;
    font-size: 15px;
    flex-shrink: 0;
}
.siloq-ep-close {
    background: none;
    border: none;
    color: #fff;
    font-size: 18px;
    cursor: pointer;
    line-height: 1;
    padding: 0;
    opacity: 0.8;
}
.siloq-ep-close:hover { opacity: 1; }

.siloq-ep-body {
    flex: 1;
    overflow-y: auto;
}

.siloq-ep-tabs {
    display: flex;
    border-bottom: 1px solid #e5e7eb;
    padding: 0 16px;
    background: #f9fafb;
    flex-shrink: 0;
}
.siloq-ep-tab {
    flex: 1;
    padding: 10px 4px;
    border: none;
    background: none;
    cursor: pointer;
    font-size: 12px;
    border-bottom: 2px solid transparent;
    color: #6b7280;
    font-weight: 500;
    transition: color 0.15s, border-color 0.15s;
}
.siloq-ep-tab.active {
    border-bottom-color: #4f46e5;
    color: #4f46e5;
    font-weight: 600;
}
.siloq-ep-tab:hover:not(.active) {
    color: #374151;
}

.siloq-ep-tab-content {
    display: none;
    padding: 16px;
}
.siloq-ep-tab-content.active {
    display: block;
}

.siloq-ep-btn {
    width: 100%;
    padding: 10px;
    margin: 4px 0;
    border-radius: 6px;
    border: 1px solid #d1d5db;
    background: #fff;
    cursor: pointer;
    font-size: 13px;
    font-family: inherit;
    text-align: center;
    transition: background 0.15s, border-color 0.15s;
}
.siloq-ep-btn:hover {
    background: #f3f4f6;
}
.siloq-ep-btn-primary {
    background: #4f46e5;
    color: #fff;
    border-color: #4f46e5;
    font-weight: 600;
}
.siloq-ep-btn-primary:hover {
    background: #4338ca;
    border-color: #4338ca;
}
.siloq-ep-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.siloq-ep-section-label {
    font-size: 11px;
    text-transform: uppercase;
    color: #9ca3af;
    font-weight: 600;
    margin-bottom: 8px;
    letter-spacing: 0.05em;
}
.siloq-ep-hint {
    font-size: 12px;
    color: #6b7280;
    margin: 6px 0;
    line-height: 1.5;
}

#siloq-ep-score-widget {
    text-align: center;
    margin-bottom: 12px;
}
.siloq-ep-score {
    display: inline-block;
    font-size: 28px;
    font-weight: 800;
    padding: 8px 16px;
    border-radius: 8px;
    margin-bottom: 4px;
}
.siloq-ep-score-green  { background: #d1fae5; color: #065f46; }
.siloq-ep-score-yellow { background: #fef9c3; color: #854d0e; }
.siloq-ep-score-red    { background: #fee2e2; color: #991b1b; }

.siloq-ep-rec-item {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    padding: 8px 0;
    border-bottom: 1px solid #f3f4f6;
    font-size: 13px;
    line-height: 1.4;
}
.siloq-ep-rec-icon { flex-shrink: 0; }

.siloq-ep-supporting-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #f3f4f6;
    font-size: 13px;
    gap: 8px;
}
.siloq-ep-supporting-item .siloq-ep-topic-title {
    flex: 1;
    font-weight: 500;
}
.siloq-ep-badge {
    font-size: 10px;
    padding: 2px 7px;
    border-radius: 99px;
    font-weight: 600;
    text-transform: uppercase;
}
.siloq-ep-badge-subpage { background: #e0e7ff; color: #3730a3; }
.siloq-ep-badge-blog    { background: #d1fae5; color: #065f46; }
.siloq-ep-create-one-btn {
    font-size: 11px;
    padding: 4px 10px;
    width: auto;
    margin: 0;
    white-space: nowrap;
}

#siloq-ep-content-type-row {
    margin: 10px 0 14px;
    font-size: 13px;
    line-height: 1.9;
    color: #374151;
}
#siloq-ep-content-type-row label {
    display: block;
}

#siloq-ep-status-bar {
    padding: 8px 16px;
    font-size: 12px;
    min-height: 32px;
    border-top: 1px solid #e5e7eb;
    color: #374151;
    background: #f9fafb;
    flex-shrink: 0;
}
';
    }

    /* -----------------------------------------------------------------------
     * JavaScript
     * -------------------------------------------------------------------- */

    private static function get_panel_js() {
        return '
(function($){
    "use strict";

    var siloqAnalysisLoaded = false;

    /* ---- helpers ---- */
    function setStatus(msg) {
        $("#siloq-ep-status-bar").html(msg);
    }

    function scoreClass(score) {
        if (score >= 70) return "siloq-ep-score-green";
        if (score >= 40) return "siloq-ep-score-yellow";
        return "siloq-ep-score-red";
    }

    function scoreLabel(score) {
        if (score >= 70) return "Good SEO score";
        if (score >= 40) return "Needs improvement";
        return "Needs significant work";
    }

    /* ---- Panel open/close ---- */
    $(document).on("click", "#siloq-elementor-trigger", function() {
        var $panel = $("#siloq-elementor-panel");
        $panel.toggleClass("siloq-ep-open siloq-ep-closed");

        // Auto-trigger analysis on first open
        if ($panel.hasClass("siloq-ep-open") && !siloqAnalysisLoaded) {
            setTimeout(function(){ $("#siloq-ep-analyze-btn").trigger("click"); }, 300);
        }
    });

    $(document).on("click", ".siloq-ep-close", function() {
        $("#siloq-elementor-panel").removeClass("siloq-ep-open").addClass("siloq-ep-closed");
    });

    /* ---- Tab switching ---- */
    $(document).on("click", ".siloq-ep-tab", function() {
        var tab = $(this).data("tab");
        $(".siloq-ep-tab").removeClass("active");
        $(this).addClass("active");
        $(".siloq-ep-tab-content").removeClass("active");
        $("#siloq-tab-" + tab).addClass("active");

        // Lazy-load structure tab
        if (tab === "structure" && $("#siloq-ep-supporting-list").children().length === 0) {
            loadSupportingPages();
        }
    });

    /* ---- Analyze page ---- */
    $(document).on("click", "#siloq-ep-analyze-btn", function() {
        var $btn = $(this);
        $btn.prop("disabled", true).text("Analyzing…");
        setStatus("Analyzing page…");

        $.post(siloqEP.ajaxUrl, {
            action:  "siloq_analyze_page",
            post_id: siloqEP.postId,
            nonce:   siloqEP.nonce
        })
        .done(function(res) {
            if (res.success && res.data) {
                var d     = res.data;
                var score = parseInt(d.score, 10) || 0;
                var cls   = scoreClass(score);
                var label = scoreLabel(score);

                $("#siloq-ep-score-widget").html(
                    \'<div class="siloq-ep-score \' + cls + \'">\' + score + \'/100</div>\' +
                    \'<p style="font-size:13px;color:#6b7280;margin:4px 0 0;">\' + label + \'</p>\'
                );

                var recs = d.recommendations || [];
                var html = "";
                recs.forEach(function(rec) {
                    var icon = rec.type === "success" ? "✅" : rec.type === "warning" ? "⚠️" : "❌";
                    html += \'<div class="siloq-ep-rec-item"><span class="siloq-ep-rec-icon">\' + icon + \'</span><span>\' + $("<div>").text(rec.message || rec).html() + \'</span></div>\';
                });
                if (!html) html = \'<p class="siloq-ep-hint">No recommendations returned.</p>\';
                $("#siloq-ep-recs-list").html(html);
                $("#siloq-ep-apply-row").show();
                siloqAnalysisLoaded = true;
                setStatus("Analysis complete.");
            } else {
                var msg = (res.data && res.data.message) ? res.data.message : "Analysis failed — check your Siloq connection.";
                $("#siloq-ep-score-widget").html(\'<p class="siloq-ep-hint">\' + msg + \'</p>\');
                setStatus("Could not load analysis.");
            }
        })
        .fail(function() {
            setStatus("Network error during analysis.");
        })
        .always(function() {
            $btn.prop("disabled", false).text("🔍 Analyze This Page");
        });
    });

    /* ---- Apply meta ---- */
    $(document).on("click", "#siloq-ep-apply-meta-btn", function() {
        var $btn = $(this);
        $btn.prop("disabled", true);
        setStatus("Applying title & meta…");

        $.post(siloqEP.ajaxUrl, {
            action:  "siloq_apply_meta",
            post_id: siloqEP.postId,
            nonce:   siloqEP.nonce
        })
        .done(function(res) {
            if (res.success) {
                setStatus("✅ Title & meta applied.");
            } else {
                var msg = (res.data && res.data.message) ? res.data.message : "Failed to apply meta.";
                setStatus("⚠️ " + msg);
            }
        })
        .fail(function() { setStatus("Network error."); })
        .always(function() { $btn.prop("disabled", false); });
    });

    /* ---- Apply schema ---- */
    $(document).on("click", "#siloq-ep-apply-schema-btn", function() {
        var $btn = $(this);
        $btn.prop("disabled", true);
        setStatus("Applying schema…");

        $.post(siloqEP.ajaxUrl, {
            action:  "siloq_apply_schema",
            post_id: siloqEP.postId,
            nonce:   siloqEP.nonce
        })
        .done(function(res) {
            if (res.success) {
                setStatus("✅ Schema applied successfully.");
            } else {
                var msg = (res.data && res.data.message) ? res.data.message : "Failed to apply schema.";
                setStatus("⚠️ " + msg);
            }
        })
        .fail(function() { setStatus("Network error."); })
        .always(function() { $btn.prop("disabled", false); });
    });

    /* ---- Generate content ---- */
    $(document).on("click", "#siloq-ep-generate-btn", function() {
        var $btn         = $(this);
        var contentType  = $("input[name=siloq_content_type]:checked").val() || "faq";
        $btn.prop("disabled", true).text("Generating…");
        setStatus("Generating content snippet…");

        $.post(siloqEP.ajaxUrl, {
            action:       "siloq_generate_content_snippet",
            post_id:      siloqEP.postId,
            content_type: contentType,
            nonce:        siloqEP.nonce
        })
        .done(function(res) {
            if (res.success && res.data && res.data.content) {
                $("#siloq-ep-content-output").val(res.data.content);
                $("#siloq-ep-generated-content").show();
                setStatus("✅ Content generated.");
            } else {
                var msg = (res.data && res.data.message) ? res.data.message : "Could not generate content.";
                setStatus("⚠️ " + msg);
            }
        })
        .fail(function() { setStatus("Network error."); })
        .always(function() {
            $btn.prop("disabled", false).text("✨ Generate Content");
        });
    });

    /* ---- Copy to clipboard ---- */
    $(document).on("click", "#siloq-ep-copy-btn", function() {
        var text = $("#siloq-ep-content-output").val();
        if (!text) return;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                setStatus("✅ Copied to clipboard!");
            }).catch(function() {
                fallbackCopy(text);
            });
        } else {
            fallbackCopy(text);
        }
    });

    function fallbackCopy(text) {
        var $ta = $("<textarea>").val(text).css({position:"fixed",top:0,left:0,opacity:0}).appendTo("body");
        $ta[0].select();
        try { document.execCommand("copy"); setStatus("✅ Copied to clipboard!"); }
        catch(e) { setStatus("Could not copy — please select and copy manually."); }
        $ta.remove();
    }

    /* ---- Structure tab: load supporting pages ---- */
    function loadSupportingPages() {
        var $list = $("#siloq-ep-supporting-list");
        $list.html(\'<p class="siloq-ep-hint">Loading…</p>\');

        $.post(siloqEP.ajaxUrl, {
            action:  "siloq_get_supporting_pages",
            post_id: siloqEP.postId,
            nonce:   siloqEP.nonce
        })
        .done(function(res) {
            if (res.success && res.data && res.data.pages && res.data.pages.length) {
                var html = "";
                res.data.pages.forEach(function(page, idx) {
                    var badgeClass = page.type === "blog" ? "siloq-ep-badge-blog" : "siloq-ep-badge-subpage";
                    var badgeLabel = page.type === "blog" ? "Blog" : "Sub-page";
                    var doneHtml   = page.created
                        ? \'<span style="font-size:11px;color:#065f46;">✅ Created</span>\'
                        : \'<button class="siloq-ep-btn siloq-ep-create-one-btn" data-index="\' + idx + \'">➕ Draft</button>\';
                    html += \'<div class="siloq-ep-supporting-item">\' +
                        \'<span class="siloq-ep-topic-title">\' + $("<div>").text(page.title).html() + \'</span>\' +
                        \'<span class="siloq-ep-badge \' + badgeClass + \'">\' + badgeLabel + \'</span>\' +
                        doneHtml +
                    \'</div>\';
                });
                $list.html(html);
            } else {
                $list.html(\'<p class="siloq-ep-hint">No supporting pages found — run an analysis first.</p>\');
            }
        })
        .fail(function() {
            $list.html(\'<p class="siloq-ep-hint">Could not load supporting pages.</p>\');
        });
    }

    /* ---- Create supporting draft (per-item) ---- */
    $(document).on("click", ".siloq-ep-create-one-btn", function() {
        var $btn  = $(this);
        var index = $btn.data("index");
        $btn.prop("disabled", true).text("Creating…");
        setStatus("Creating draft…");

        $.post(siloqEP.ajaxUrl, {
            action:      "siloq_create_supporting_draft",
            post_id:     siloqEP.postId,
            topic_index: index,
            nonce:       siloqEP.nonce
        })
        .done(function(res) {
            if (res.success && res.data && res.data.edit_url) {
                $btn.replaceWith(\'<a href="\' + res.data.edit_url + \'" target="_blank" style="font-size:11px;color:#4f46e5;">✅ Edit Draft</a>\');
                setStatus("✅ Draft created.");
            } else {
                var msg = (res.data && res.data.message) ? res.data.message : "Failed to create draft.";
                setStatus("⚠️ " + msg);
                $btn.prop("disabled", false).text("➕ Draft");
            }
        })
        .fail(function() {
            setStatus("Network error.");
            $btn.prop("disabled", false).text("➕ Draft");
        });
    });

    /* ---- Create draft (global button, picks first un-created topic) ---- */
    $(document).on("click", "#siloq-ep-create-draft-btn", function() {
        var $first = $(".siloq-ep-create-one-btn:first");
        if ($first.length) {
            $first.trigger("click");
        } else {
            setStatus("ℹ️ Load supporting pages above first.");
            loadSupportingPages();
        }
    });

})(jQuery);
';
    }

    /* -----------------------------------------------------------------------
     * AJAX: Generate content snippet
     * -------------------------------------------------------------------- */

    public static function ajax_generate_content_snippet() {
        check_ajax_referer( 'siloq_ai_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [ 'message' => 'Insufficient permissions.' ] );
        }

        $post_id      = absint( $_POST['post_id'] ?? 0 );
        $content_type = sanitize_key( $_POST['content_type'] ?? 'faq' );

        $api_url = get_option( 'siloq_api_url', 'https://api.siloq.ai/api/v1' );
        $api_key = get_option( 'siloq_api_key', '' );
        $site_id = get_option( 'siloq_site_id', '' );

        if ( ! $site_id || ! $api_key ) {
            wp_send_json_success( [
                'content' => "Connect your Siloq account to generate content.\n\nGo to Siloq → Settings and enter your API key and Site ID.",
            ] );
        }

        // Map WP post to Siloq page_id
        $page_id = get_post_meta( $post_id, '_siloq_page_id', true );
        if ( ! $page_id ) {
            wp_send_json_success( [
                'content' => "This page hasn't been synced with Siloq yet.\n\nSave the page first, then use Siloq → Sync to connect it.",
            ] );
        }

        $endpoint = trailingslashit( $api_url ) . "sites/{$site_id}/pages/{$page_id}/generate-snippet/";
        $response = wp_remote_post( $endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode( [ 'content_type' => $content_type ] ),
            'timeout' => 60,
        ] );

        if ( is_wp_error( $response ) ) {
            wp_send_json_success( [
                'content' => "Could not reach Siloq API: " . $response->get_error_message() . "\n\nCheck your connection and try again.",
            ] );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code >= 200 && $code < 300 && isset( $body['content'] ) ) {
            wp_send_json_success( [ 'content' => $body['content'] ] );
        }

        // Fallback
        $msg = isset( $body['detail'] ) ? $body['detail'] : "API returned status {$code}.";
        wp_send_json_success( [
            'content' => "Content generation unavailable: {$msg}\n\nMake sure your Siloq account is connected and this page is synced.",
        ] );
    }

    /* -----------------------------------------------------------------------
     * AJAX: Get supporting pages
     * -------------------------------------------------------------------- */

    public static function ajax_get_supporting_pages() {
        check_ajax_referer( 'siloq_ai_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [ 'message' => 'Insufficient permissions.' ] );
        }

        $post_id = absint( $_POST['post_id'] ?? 0 );
        $api_url = get_option( 'siloq_api_url', 'https://api.siloq.ai/api/v1' );
        $api_key = get_option( 'siloq_api_key', '' );
        $site_id = get_option( 'siloq_site_id', '' );

        if ( ! $site_id || ! $api_key ) {
            wp_send_json_error( [ 'message' => 'Siloq not connected.' ] );
        }

        $page_id = get_post_meta( $post_id, '_siloq_page_id', true );
        if ( ! $page_id ) {
            wp_send_json_error( [ 'message' => 'Page not synced with Siloq.' ] );
        }

        $endpoint = trailingslashit( $api_url ) . "sites/{$site_id}/pages/{$page_id}/supporting-content/";
        $response = wp_remote_get( $endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 30,
        ] );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( [ 'message' => 'API error: ' . $response->get_error_message() ] );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code >= 200 && $code < 300 ) {
            $pages = [];

            // Normalise whatever structure the API returns
            $raw = isset( $body['pages'] ) ? $body['pages']
                 : ( isset( $body['results'] ) ? $body['results']
                 : ( is_array( $body ) ? $body : [] ) );

            foreach ( $raw as $item ) {
                $pages[] = [
                    'title'          => sanitize_text_field( $item['title'] ?? $item['topic'] ?? 'Untitled' ),
                    'type'           => sanitize_key( $item['type'] ?? 'sub-page' ),
                    'target_keyword' => sanitize_text_field( $item['target_keyword'] ?? '' ),
                    'created'        => ! empty( $item['created'] ),
                ];
            }

            // Cache topics in post meta for draft creation
            update_post_meta( $post_id, '_siloq_supporting_topics', $pages );

            wp_send_json_success( [ 'pages' => $pages ] );
        }

        wp_send_json_error( [ 'message' => 'API returned status ' . $code ] );
    }

    /* -----------------------------------------------------------------------
     * AJAX: Create supporting draft
     * -------------------------------------------------------------------- */

    public static function ajax_create_supporting_draft() {
        check_ajax_referer( 'siloq_ai_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [ 'message' => 'Insufficient permissions.' ] );
        }

        $post_id     = absint( $_POST['post_id'] ?? 0 );
        $topic_index = absint( $_POST['topic_index'] ?? 0 );

        $topics = get_post_meta( $post_id, '_siloq_supporting_topics', true );

        if ( empty( $topics ) || ! isset( $topics[ $topic_index ] ) ) {
            wp_send_json_error( [ 'message' => 'Topic not found. Load supporting pages first.' ] );
        }

        $topic = $topics[ $topic_index ];

        $new_post_id = wp_insert_post( [
            'post_title'   => wp_strip_all_tags( $topic['title'] ),
            'post_status'  => 'draft',
            'post_type'    => 'page',
            'post_parent'  => $post_id,
            'meta_input'   => [
                '_siloq_target_keyword' => $topic['target_keyword'] ?? '',
                '_siloq_content_type'   => $topic['type'] ?? '',
            ],
        ], true );

        if ( is_wp_error( $new_post_id ) ) {
            wp_send_json_error( [ 'message' => $new_post_id->get_error_message() ] );
        }

        // Mark as created in cached topics
        $topics[ $topic_index ]['created'] = true;
        update_post_meta( $post_id, '_siloq_supporting_topics', $topics );

        wp_send_json_success( [
            'edit_url' => get_edit_post_link( $new_post_id, 'raw' ),
            'post_id'  => $new_post_id,
        ] );
    }
}
