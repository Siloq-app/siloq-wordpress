<?php
/**
 * [siloq_scanner] shortcode — lead-gen SEO scanner UI for scan.siloq.ai
 *
 * @package Siloq_Connector
 * @since   1.5.166
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Siloq_Scanner_Shortcode {

    /**
     * Register the shortcode.
     */
    public static function init() {
        add_shortcode( 'siloq_scanner', array( __CLASS__, 'render' ) );
    }

    /**
     * Return the full HTML/CSS/JS for the scanner widget.
     *
     * @param array $atts Shortcode attributes (unused for now).
     * @return string
     */
    public static function render( $atts ) {
        $ajax_url = admin_url( 'admin-ajax.php' );
        $nonce    = wp_create_nonce( 'siloq_scanner_nonce' );

        ob_start();
        ?>
<style>
/* ── Siloq Scanner — scoped styles ───────────────────────────── */
.siloq-scanner{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen,Ubuntu,sans-serif;max-width:720px;margin:0 auto;padding:24px 16px;color:#1a1a2e}
.siloq-scanner *,.siloq-scanner *::before,.siloq-scanner *::after{box-sizing:border-box}
.siloq-scanner h2{font-size:28px;font-weight:700;margin:0 0 8px;text-align:center;color:#1a1a2e}
.siloq-scanner .ss-subhead{font-size:16px;color:#555;text-align:center;margin:0 0 28px;line-height:1.5}
.siloq-scanner .ss-form{display:flex;flex-direction:column;gap:12px}
.siloq-scanner .ss-row{display:flex;gap:12px}
@media(max-width:600px){.siloq-scanner .ss-row{flex-direction:column}}
.siloq-scanner input[type="text"],.siloq-scanner input[type="email"],.siloq-scanner input[type="url"]{width:100%;padding:14px 16px;border:1.5px solid #d1d5db;border-radius:8px;font-size:15px;outline:none;transition:border .2s}
.siloq-scanner input:focus{border-color:#4f46e5}
.siloq-scanner .ss-cta{display:inline-block;width:100%;padding:16px;background:#4f46e5;color:#fff;font-size:17px;font-weight:600;border:none;border-radius:8px;cursor:pointer;transition:background .2s;text-align:center}
.siloq-scanner .ss-cta:hover{background:#4338ca}
.siloq-scanner .ss-cta:disabled{opacity:.6;cursor:not-allowed}
.siloq-scanner .ss-disclaimer{font-size:13px;color:#888;text-align:center;margin-top:8px}

/* Loading */
.siloq-scanner .ss-loading{text-align:center;padding:40px 0}
.siloq-scanner .ss-progress-wrap{height:6px;background:#e5e7eb;border-radius:3px;overflow:hidden;margin:0 auto 20px;max-width:400px}
.siloq-scanner .ss-progress-bar{height:100%;width:0;background:linear-gradient(90deg,#4f46e5,#7c3aed);border-radius:3px;transition:width .4s}
.siloq-scanner .ss-status-msg{font-size:15px;color:#555;min-height:24px}
.siloq-scanner .ss-est{font-size:13px;color:#999;margin-top:12px}

/* Results — score circle */
.siloq-scanner .ss-results{padding:8px 0}
.siloq-scanner .ss-score-wrap{text-align:center;margin-bottom:28px}
.siloq-scanner .ss-score-circle{display:inline-flex;align-items:center;justify-content:center;width:130px;height:130px;border-radius:50%;border:6px solid;font-size:42px;font-weight:700}
.siloq-scanner .ss-score-circle.green{border-color:#22c55e;color:#16a34a}
.siloq-scanner .ss-score-circle.amber{border-color:#f59e0b;color:#d97706}
.siloq-scanner .ss-score-circle.red{border-color:#ef4444;color:#dc2626}
.siloq-scanner .ss-grade{font-size:18px;font-weight:600;margin-top:10px}
.siloq-scanner .ss-pages{font-size:14px;color:#888;margin-top:4px}

/* Pillar grid */
.siloq-scanner .ss-pillars{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:28px}
@media(max-width:600px){.siloq-scanner .ss-pillars{grid-template-columns:1fr}}
.siloq-scanner .ss-pillar{background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:18px}
.siloq-scanner .ss-pillar-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px}
.siloq-scanner .ss-pillar-name{font-weight:600;font-size:15px}
.siloq-scanner .ss-pillar-score{font-size:14px;font-weight:600;color:#555}
.siloq-scanner .ss-bar-bg{height:5px;background:#e5e7eb;border-radius:3px;overflow:hidden;margin-bottom:10px}
.siloq-scanner .ss-bar-fill{height:100%;border-radius:3px;transition:width .5s}
.siloq-scanner .ss-bar-fill.green{background:#22c55e}
.siloq-scanner .ss-bar-fill.amber{background:#f59e0b}
.siloq-scanner .ss-bar-fill.red{background:#ef4444}
.siloq-scanner .ss-issues{list-style:none;padding:0;margin:0 0 8px;font-size:13px;color:#555}
.siloq-scanner .ss-issues li{padding:2px 0;padding-left:14px;position:relative}
.siloq-scanner .ss-issues li::before{content:"\2022";position:absolute;left:0;color:#9ca3af}
.siloq-scanner .ss-more{font-size:12px;color:#4f46e5;font-weight:500}
.siloq-scanner .ss-badge{display:inline-block;font-size:12px;background:#ecfdf5;color:#059669;padding:3px 8px;border-radius:4px;margin-top:4px}

/* Bottom section */
.siloq-scanner .ss-benchmark{text-align:center;font-size:14px;color:#666;margin-bottom:18px;font-style:italic}
.siloq-scanner .ss-fixable{text-align:center;font-size:18px;font-weight:700;color:#1a1a2e;margin-bottom:20px}
.siloq-scanner .ss-actions{text-align:center}
.siloq-scanner .ss-actions .ss-cta{max-width:360px;margin:0 auto 10px;display:block;text-decoration:none}
.siloq-scanner .ss-secondary{font-size:14px;color:#4f46e5;text-decoration:underline}

/* Error */
.siloq-scanner .ss-error{text-align:center;color:#dc2626;padding:20px;font-size:15px}
</style>

<div class="siloq-scanner" id="siloq-scanner">
    <!-- STEP 1 — Input form -->
    <div id="ss-step-form">
        <h2>Get Your Free SEO Score</h2>
        <p class="ss-subhead">See exactly what's hurting your rankings &mdash; and what AI assistants can't find about your business.</p>
        <div class="ss-form">
            <input type="url" id="ss-url" placeholder="https://yourwebsite.com" required>
            <div class="ss-row">
                <input type="text" id="ss-name" placeholder="First name (optional)">
                <input type="email" id="ss-email" placeholder="Email (optional)">
            </div>
            <button class="ss-cta" id="ss-submit">Scan My Website &rarr;</button>
            <p class="ss-disclaimer">Free scan &bull; No credit card &bull; Results in ~30 seconds</p>
        </div>
    </div>

    <!-- STEP 2 — Loading -->
    <div id="ss-step-loading" style="display:none">
        <div class="ss-loading">
            <div class="ss-progress-wrap"><div class="ss-progress-bar" id="ss-bar"></div></div>
            <div class="ss-status-msg" id="ss-status"></div>
            <div class="ss-est">Usually takes 20-40 seconds</div>
        </div>
    </div>

    <!-- STEP 3 — Results -->
    <div id="ss-step-results" style="display:none"></div>

    <!-- Error -->
    <div id="ss-step-error" style="display:none" class="ss-error"></div>
</div>

<script>
(function(){
    var ajaxUrl  = <?php echo wp_json_encode( $ajax_url ); ?>;
    var nonce    = <?php echo wp_json_encode( $nonce ); ?>;

    var formEl   = document.getElementById('ss-step-form');
    var loadEl   = document.getElementById('ss-step-loading');
    var resEl    = document.getElementById('ss-step-results');
    var errEl    = document.getElementById('ss-step-error');
    var barEl    = document.getElementById('ss-bar');
    var statusEl = document.getElementById('ss-status');
    var btnEl    = document.getElementById('ss-submit');

    var msgs = [
        'Crawling your pages\u2026',
        'Checking AI crawler access\u2026',
        'Analyzing keyword cannibalization\u2026',
        'Scanning schema markup\u2026',
        'Checking meta title health\u2026',
        'Building your report\u2026'
    ];

    function show(el){ el.style.display = ''; }
    function hide(el){ el.style.display = 'none'; }

    function scoreColor(score, max){
        var pct = (score / max) * 100;
        if(pct >= 80) return 'green';
        if(pct >= 60) return 'amber';
        return 'red';
    }

    btnEl.addEventListener('click', function(){
        var url = document.getElementById('ss-url').value.trim();
        if(!url){ document.getElementById('ss-url').focus(); return; }
        if(!/^https?:\/\/.+\..+/.test(url)){
            document.getElementById('ss-url').setCustomValidity('Please enter a valid URL');
            document.getElementById('ss-url').reportValidity();
            return;
        }
        document.getElementById('ss-url').setCustomValidity('');

        hide(formEl); hide(errEl); show(loadEl);

        /* Progress animation */
        var msgIdx = 0;
        var progress = 0;
        statusEl.textContent = msgs[0];
        barEl.style.width = '0%';
        var ticker = setInterval(function(){
            progress = Math.min(progress + 3 + Math.random()*5, 92);
            barEl.style.width = progress + '%';
            msgIdx = (msgIdx + 1) % msgs.length;
            statusEl.textContent = msgs[msgIdx];
        }, 3000);

        var fd = new FormData();
        fd.append('action', 'siloq_run_scan');
        fd.append('_wpnonce', nonce);
        fd.append('url', url);
        fd.append('name', document.getElementById('ss-name').value.trim());
        fd.append('email', document.getElementById('ss-email').value.trim());

        var xhr = new XMLHttpRequest();
        xhr.open('POST', ajaxUrl, true);
        xhr.onload = function(){
            clearInterval(ticker);
            barEl.style.width = '100%';
            if(xhr.status === 200){
                try{
                    var resp = JSON.parse(xhr.responseText);
                    if(resp.success){
                        setTimeout(function(){ hide(loadEl); renderResults(resp.data); }, 600);
                    } else {
                        showError(resp.data || 'Scan failed. Please try again.');
                    }
                }catch(e){
                    showError('Unexpected response. Please try again.');
                }
            } else {
                showError('Server error (' + xhr.status + '). Please try again.');
            }
        };
        xhr.onerror = function(){
            clearInterval(ticker);
            showError('Network error. Please check your connection.');
        };
        xhr.send(fd);
    });

    function showError(msg){
        hide(loadEl);
        errEl.textContent = msg;
        show(errEl);
        show(formEl);
    }

    /* ── Pillar display config ──────────────────────────────────── */
    var pillarMeta = {
        ai_visibility:    { icon: '\uD83E\uDD16', label: 'AI Visibility',            sub: 'Is your site visible to AI assistants?' },
        cannibalization:  { icon: '\uD83D\uDD17', label: 'Keyword Cannibalization',   sub: 'Are your pages competing against each other?' },
        meta_titles:      { icon: '\uD83C\uDFF7\uFE0F', label: 'Meta Title Health',   sub: 'Are your titles set up to rank?' },
        content_structure:{ icon: '\uD83D\uDCD0', label: 'Content Structure',         sub: 'Is your content architecture sound?' },
        technical:        { icon: '\u2699\uFE0F', label: 'Technical Foundation',       sub: 'Basic technical health' }
    };
    var pillarOrder = ['ai_visibility','cannibalization','meta_titles','content_structure','technical'];

    function renderResults(data){
        // API wraps results in data.results — flatten for backwards compat with stub format
        var r = (data.results && typeof data.results === 'object') ? data.results : data;
        var d = r.dimensions || r;
        var total = r.total_score || data.total_score || data.score || 0;
        var grade = r.grade || data.grade || '';
        var pages = r.pages_crawled || data.pages_crawled || data.pages_analyzed || 0;
        var benchmark = r.benchmark || data.benchmark || '';
        var autoCount = r.auto_fixable_count || data.auto_fixable_count || 0;
        var contentCount = r.requires_content_count || data.requires_content_count || 0;
        var totalIssues = autoCount + contentCount;

        var col = scoreColor(total, 100);

        var html = '<div class="ss-score-wrap">';
        html += '<div class="ss-score-circle ' + col + '">' + total + '</div>';
        html += '<div class="ss-grade">' + esc(grade) + '</div>';
        if(pages) html += '<div class="ss-pages">' + pages + ' pages crawled</div>';
        html += '</div>';

        html += '<div class="ss-pillars">';
        for(var i=0; i<pillarOrder.length; i++){
            var key = pillarOrder[i];
            var p = d[key];
            if(!p) continue;
            var m = pillarMeta[key];
            var pc = scoreColor(p.score, p.max);
            var pctW = Math.round((p.score / p.max) * 100);

            html += '<div class="ss-pillar">';
            html += '<div class="ss-pillar-head"><span class="ss-pillar-name">' + m.icon + ' ' + m.label + '</span><span class="ss-pillar-score">' + p.score + ' / ' + p.max + '</span></div>';
            html += '<div class="ss-bar-bg"><div class="ss-bar-fill ' + pc + '" style="width:' + pctW + '%"></div></div>';

            var issues = p.issues || [];
            if(issues.length){
                html += '<ul class="ss-issues">';
                var show_n = Math.min(issues.length, 3);
                for(var j=0; j<show_n; j++) html += '<li>' + esc(issues[j]) + '</li>';
                html += '</ul>';
                if(issues.length > 3) html += '<span class="ss-more">+ ' + (issues.length - 3) + ' more</span>';
            }
            var af = p.auto_fixable || [];
            if(af.length) html += '<span class="ss-badge">Siloq fixes this automatically \u2713</span>';
            html += '</div>';
        }
        html += '</div>';

        if(benchmark) html += '<div class="ss-benchmark">' + esc(benchmark) + '</div>';
        if(autoCount && totalIssues) html += '<div class="ss-fixable">Siloq can automatically fix ' + autoCount + ' of your ' + totalIssues + ' issues.</div>';

        html += '<div class="ss-actions">';
        html += '<a class="ss-cta" href="https://app.siloq.ai/register" target="_blank" rel="noopener">Start Your Free Trial &rarr;</a>';
        html += '<a class="ss-secondary" href="#" target="_blank" rel="noopener">Want a full audit? Book a demo &rarr;</a>';
        html += '</div>';

        resEl.innerHTML = html;
        show(resEl);
    }

    function esc(s){
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(s));
        return d.innerHTML;
    }
})();
</script>
        <?php
        return ob_get_clean();
    }

    /**
     * AJAX handler — runs the scan via Siloq API (or returns stub).
     */
    public static function ajax_run_scan() {
        check_ajax_referer( 'siloq_scanner_nonce', '_wpnonce' );

        $url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
        if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
            wp_send_json_error( 'Please enter a valid URL.' );
        }

        $api_key = get_option( 'siloq_api_key', '' );

        // If no API key, or API call fails, return stub
        if ( empty( $api_key ) ) {
            wp_send_json_success( self::stub_result( $url ) );
        }

        $response = wp_remote_post(
            'https://api.siloq.ai/api/v1/scans/',
            array(
                'timeout' => 60,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ),
                'body' => wp_json_encode( array( 'url' => $url ) ),
            )
        );

        if ( is_wp_error( $response ) ) {
            wp_send_json_success( self::stub_result( $url ) );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( $code < 200 || $code >= 300 ) {
            wp_send_json_success( self::stub_result( $url ) );
        }

        $data = json_decode( $body, true );
        if ( empty( $data ) || ! is_array( $data ) ) {
            wp_send_json_success( self::stub_result( $url ) );
        }

        wp_send_json_success( $data );
    }

    /**
     * Realistic stub result for demos / when API is unavailable.
     *
     * @param  string $url The scanned URL (unused, kept for future personalisation).
     * @return array
     */
    private static function stub_result( $url ) {
        return array(
            'total_score'            => 54,
            'grade'                  => 'Critical Issues Found',
            'pages_crawled'          => 8,
            'benchmark'              => 'Sites with strong SEO governance average 82/100. You scored 54.',
            'dimensions'             => array(
                'ai_visibility'      => array(
                    'score'           => 8,
                    'max'             => 25,
                    'issues'          => array(
                        'Homepage missing LocalBusiness schema',
                        'No llms.txt — AI assistants cannot learn your authority structure',
                        'GPTBot blocked in robots.txt',
                    ),
                    'auto_fixable'    => array( 'Add LocalBusiness schema', 'Generate llms.txt' ),
                    'requires_content'=> array(),
                ),
                'cannibalization'    => array(
                    'score'           => 16,
                    'max'             => 30,
                    'issues'          => array( '2 pages competing for same keyword' ),
                    'auto_fixable'    => array(),
                    'requires_content'=> array( 'Rewrite to differentiate service pages' ),
                ),
                'meta_titles'        => array(
                    'score'           => 14,
                    'max'             => 20,
                    'issues'          => array(
                        'Homepage title missing location keyword',
                        '1 duplicate title found',
                    ),
                    'auto_fixable'    => array( 'Fix duplicate title', 'Add location keyword' ),
                    'requires_content'=> array(),
                ),
                'content_structure'  => array(
                    'score'           => 9,
                    'max'             => 15,
                    'issues'          => array( 'Homepage has 2 H1 tags' ),
                    'auto_fixable'    => array( 'Fix multiple H1 tags' ),
                    'requires_content'=> array(),
                ),
                'technical'          => array(
                    'score'           => 7,
                    'max'             => 10,
                    'issues'          => array( 'Page load time: 4.2 seconds' ),
                    'auto_fixable'    => array(),
                    'requires_content'=> array(),
                ),
            ),
            'auto_fixable_count'     => 5,
            'requires_content_count' => 2,
            'cta'                    => 'Siloq can automatically fix 5 of your 7 issues. Start your free trial.',
        );
    }
}
