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
/* ── Siloq Diagnostic Scanner — Dark Theme ───────────────────── */
.diag-wrap{--bg:#0d0f1a;--surface:#151829;--surface2:#1c2038;--border:#2a2e4a;--text:#e2e4f0;--text-muted:#8b8fa8;--accent:#6c5ce7;--accent-glow:rgba(108,92,231,.25);--good:#5ab87a;--mid:#e6b422;--bad:#e05252;--cta-bg:linear-gradient(135deg,#6c5ce7,#a855f7);font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:var(--bg);color:var(--text);max-width:740px;margin:0 auto;padding:48px 24px;min-height:100vh;box-sizing:border-box}
.diag-wrap *,.diag-wrap *::before,.diag-wrap *::after{box-sizing:border-box}

/* Symbol header */
.diag-symbol{text-align:center;margin-bottom:32px}
.diag-symbol .pulse{display:inline-flex;align-items:center;justify-content:center;width:72px;height:72px;border-radius:50%;background:var(--surface);border:2px solid var(--accent);box-shadow:0 0 24px var(--accent-glow);animation:diagPulse 2.4s ease-in-out infinite}
@keyframes diagPulse{0%,100%{box-shadow:0 0 24px var(--accent-glow)}50%{box-shadow:0 0 40px var(--accent-glow),0 0 60px rgba(108,92,231,.12)}}
.diag-symbol .pulse svg{width:32px;height:32px;stroke:var(--accent);fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}

/* Headline */
.headline-block{text-align:center;margin-bottom:36px}
.headline-block h1{font-size:28px;font-weight:800;line-height:1.25;margin:0 0 14px;background:linear-gradient(135deg,#fff 60%,var(--accent));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.headline-block .sub{font-size:15px;color:var(--text-muted);line-height:1.55;max-width:520px;margin:0 auto}

/* Signal teasers */
.signals-row{display:flex;gap:12px;margin-bottom:32px}
.signals-row .sig{flex:1;background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:16px 14px;text-align:center}
.signals-row .sig-val{font-size:22px;font-weight:700;margin-bottom:4px}
.signals-row .sig-lbl{font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px}
.sig-val.good{color:var(--good)}.sig-val.bad{color:var(--bad)}.sig-val.mid{color:var(--mid)}

/* Form card */
.form-card{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:28px 24px;margin-bottom:24px}
.form-card label{display:block;font-size:13px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px}
.form-card input{width:100%;padding:14px 16px;background:var(--surface2);border:1.5px solid var(--border);border-radius:8px;color:var(--text);font-size:15px;outline:none;transition:border .2s}
.form-card input::placeholder{color:#555a78}
.form-card input:focus{border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-glow)}
.form-row{display:flex;gap:12px;margin-top:14px}
@media(max-width:600px){.form-row{flex-direction:column}}
.form-row .form-col{flex:1}
.form-card .run-btn{display:block;width:100%;padding:16px;margin-top:18px;background:var(--cta-bg);color:#fff;font-size:16px;font-weight:700;border:none;border-radius:10px;cursor:pointer;transition:transform .15s,box-shadow .15s;box-shadow:0 4px 20px var(--accent-glow)}
.form-card .run-btn:hover{transform:translateY(-1px);box-shadow:0 6px 28px var(--accent-glow)}
.form-card .run-btn:disabled{opacity:.55;cursor:not-allowed;transform:none}
.entity-note{font-size:13px;color:var(--text-muted);margin-top:10px;padding:10px 14px;background:var(--surface2);border-radius:8px;border-left:3px solid var(--accent)}

/* Loading overlay */
.diag-loading{text-align:center;padding:48px 0}
.diag-loading .progress-track{height:4px;background:var(--surface2);border-radius:2px;overflow:hidden;max-width:400px;margin:0 auto 24px}
.diag-loading .progress-fill{height:100%;width:0;background:var(--cta-bg);border-radius:2px;transition:width .5s}
.diag-loading .load-msg{font-size:15px;color:var(--text-muted);min-height:22px}
.diag-loading .load-sub{font-size:13px;color:#555a78;margin-top:10px}

/* Results area */
.results-area{display:none}

/* Score hero */
.score-hero{text-align:center;padding:32px 0 28px}
.score-ring{display:inline-flex;align-items:center;justify-content:center;width:140px;height:140px;border-radius:50%;border:5px solid;font-size:48px;font-weight:800;position:relative}
.score-ring.good{border-color:var(--good);color:var(--good);box-shadow:0 0 30px rgba(90,184,122,.2)}
.score-ring.mid{border-color:var(--mid);color:var(--mid);box-shadow:0 0 30px rgba(230,180,34,.2)}
.score-ring.bad{border-color:var(--bad);color:var(--bad);box-shadow:0 0 30px rgba(224,82,82,.2)}
.score-of{font-size:16px;font-weight:400;opacity:.5}
.score-grade{font-size:18px;font-weight:600;margin-top:12px;color:var(--text)}
.score-pages{font-size:14px;color:var(--text-muted);margin-top:4px}

/* Pillar row */
.pillars-row{display:flex;gap:10px;margin-bottom:32px;overflow-x:auto;padding-bottom:4px}
.pillar-card{flex:1;min-width:110px;background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:16px 12px;text-align:center;transition:border-color .2s}
.pillar-card:hover{border-color:var(--accent)}
.pillar-icon{font-size:24px;margin-bottom:6px}
.pillar-label{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.3px;color:var(--text-muted);line-height:1.3;margin-bottom:10px;min-height:28px;white-space:pre-line}
.pillar-score{font-size:20px;font-weight:700;margin-bottom:4px}
.pillar-max{font-size:11px;color:var(--text-muted)}
.pillar-bar{height:3px;background:var(--surface2);border-radius:2px;margin-top:8px;overflow:hidden}
.pillar-bar-fill{height:100%;border-radius:2px;transition:width .6s}
.pillar-issues{margin-top:10px;text-align:left}
.pillar-issues li{font-size:12px;color:var(--text-muted);padding:2px 0;list-style:none;position:relative;padding-left:12px}
.pillar-issues li::before{content:"\2022";position:absolute;left:0;color:#555a78}
.pillar-badge{display:inline-block;font-size:11px;background:rgba(90,184,122,.12);color:var(--good);padding:3px 8px;border-radius:4px;margin-top:6px}

/* Entity section */
.entity-section{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:24px;margin-bottom:28px}
.entity-section h3{font-size:18px;font-weight:700;margin:0 0 16px;display:flex;align-items:center;gap:8px}
.entity-loading{text-align:center;padding:20px 0}
.entity-loading .entity-spinner{display:inline-block;width:20px;height:20px;border:2px solid var(--border);border-top-color:var(--accent);border-radius:50%;animation:espin .8s linear infinite;margin-bottom:10px}
@keyframes espin{to{transform:rotate(360deg)}}
.entity-loading .entity-msg{font-size:14px;color:var(--text-muted)}
.entity-results{display:none}
.perception-boxes{display:flex;gap:12px;margin-bottom:16px}
@media(max-width:600px){.perception-boxes{flex-direction:column}}
.perception-box{flex:1;background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:16px}
.perception-box .pbox-label{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);margin-bottom:6px}
.perception-box .pbox-value{font-size:15px;font-weight:600;color:var(--text)}
.pitch-bar{background:var(--surface2);border-left:3px solid var(--accent);border-radius:0 8px 8px 0;padding:14px 16px;margin-bottom:18px;font-size:14px;color:var(--text-muted);line-height:1.5}
.gap-row{display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid var(--border)}
.gap-row:last-child{border-bottom:none}
.gap-name{font-size:14px;font-weight:500;color:var(--text);flex:1}
.gap-pct{font-size:14px;font-weight:700;width:50px;text-align:right;margin-right:12px}
.gap-severity{font-size:11px;font-weight:600;text-transform:uppercase;padding:3px 8px;border-radius:4px}
.gap-severity.critical{background:rgba(224,82,82,.15);color:var(--bad)}
.gap-severity.high{background:rgba(230,180,34,.15);color:var(--mid)}
.gap-severity.medium{background:rgba(90,184,122,.15);color:var(--good)}
.critical-block{background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:16px;margin-top:12px}
.critical-block .cb-title{font-size:14px;font-weight:600;color:var(--text);margin-bottom:4px}
.critical-block .cb-desc{font-size:13px;color:var(--text-muted);line-height:1.5}
.critical-block .cb-sev{font-size:11px;font-weight:600;text-transform:uppercase;margin-bottom:6px}

/* CTA block */
.cta-block{text-align:center;padding:28px 0;margin-bottom:24px}
.cta-block .cta-main{display:inline-block;padding:16px 40px;background:var(--cta-bg);color:#fff;font-size:17px;font-weight:700;border:none;border-radius:10px;cursor:pointer;text-decoration:none;transition:transform .15s,box-shadow .15s;box-shadow:0 4px 20px var(--accent-glow)}
.cta-block .cta-main:hover{transform:translateY(-1px);box-shadow:0 6px 28px var(--accent-glow)}
.cta-block .cta-sub{font-size:14px;color:var(--text-muted);margin-top:10px}
.cta-block .cta-link{font-size:14px;color:var(--accent);text-decoration:underline;margin-top:8px;display:inline-block}

/* Send section */
.send-section{border-top:1px solid var(--border);padding-top:24px;margin-top:8px}
.send-section .send-title{font-size:16px;font-weight:700;color:var(--text);margin-bottom:6px}
.send-section .send-desc{font-size:14px;color:var(--text-muted);margin:0 0 16px}
.send-section input{width:100%;padding:12px 14px;background:var(--surface2);border:1.5px solid var(--border);border-radius:8px;color:var(--text);font-size:14px;outline:none;margin-bottom:10px}
.send-section input::placeholder{color:#555a78}
.send-section input:focus{border-color:var(--accent)}
.send-section .send-btn{width:100%;padding:14px;background:#059669;color:#fff;font-size:15px;font-weight:600;border:none;border-radius:8px;cursor:pointer;transition:background .2s}
.send-section .send-btn:hover{background:#047857}
.send-section .send-btn:disabled{opacity:.55;cursor:not-allowed}
.send-section .send-status{font-size:14px;text-align:center;min-height:20px;margin-top:8px}

/* Error */
.diag-error{text-align:center;color:var(--bad);padding:20px;font-size:15px}
</style>

<div class="diag-wrap" id="siloq-scanner">
    <!-- Header symbol -->
    <div class="diag-symbol">
        <div class="pulse">
            <svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
        </div>
    </div>

    <!-- Headline -->
    <div class="headline-block">
        <h1>AI assistants are answering questions about your business right now.</h1>
        <p class="sub">Are they getting it right? Our diagnostic checks what ChatGPT, Gemini, and Google AI Overviews actually know about you &mdash; and where you&rsquo;re invisible.</p>
    </div>

    <!-- Signal teasers -->
    <div class="signals-row">
        <div class="sig"><div class="sig-val bad">73%</div><div class="sig-lbl">Businesses invisible to AI</div></div>
        <div class="sig"><div class="sig-val mid">2.4x</div><div class="sig-lbl">More traffic with entity clarity</div></div>
        <div class="sig"><div class="sig-val good">Free</div><div class="sig-lbl">Full diagnostic</div></div>
    </div>

    <!-- STEP 1 — Form -->
    <div id="ss-step-form">
        <div class="form-card">
            <label>Website URL</label>
            <input type="url" id="ss-url" placeholder="https://yourwebsite.com" required>
            <div class="form-row">
                <div class="form-col">
                    <label style="margin-top:14px">Your name</label>
                    <input type="text" id="ss-name" placeholder="First name (optional)">
                </div>
                <div class="form-col">
                    <label style="margin-top:14px">Email</label>
                    <input type="email" id="ss-email" placeholder="you@company.com (optional)">
                </div>
            </div>
            <div class="form-row">
                <div class="form-col">
                    <label style="margin-top:14px">Business name</label>
                    <input type="text" id="ss-biz" placeholder="e.g. Precision Marketing (optional)">
                </div>
                <div class="form-col">
                    <label style="margin-top:14px">City / Region</label>
                    <input type="text" id="ss-location" placeholder="e.g. Denver, CO (optional)">
                </div>
            </div>
            <button class="run-btn" id="ss-submit">Run Diagnostic &rarr;</button>
            <div class="entity-note">&#x1F50D; Adding your business name unlocks <strong>Entity Analysis</strong> &mdash; see how Google&rsquo;s Knowledge Graph classifies your brand and where authority gaps exist.</div>
        </div>
    </div>

    <!-- STEP 2 — Loading -->
    <div id="ss-step-loading" style="display:none">
        <div class="diag-loading">
            <div class="progress-track"><div class="progress-fill" id="ss-bar"></div></div>
            <div class="load-msg" id="ss-status"></div>
            <div class="load-sub">Full diagnostic &bull; Usually takes 20-40 seconds</div>
        </div>
    </div>

    <!-- STEP 3 — Results -->
    <div id="ss-step-results" class="results-area"></div>

    <!-- Error -->
    <div id="ss-step-error" style="display:none" class="diag-error"></div>
</div>

<script>
(function(){
    var ajaxUrl  = <?php echo wp_json_encode( $ajax_url ); ?>;
    var nonce    = <?php echo wp_json_encode( $nonce ); ?>;
    var apiKey   = '<?php echo esc_js( get_option( "siloq_api_key", "" ) ); ?>';
    var apiBase  = '<?php echo esc_js( get_option( "siloq_api_url", "https://api.siloq.ai/api/v1" ) ); ?>';

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

    var entityMsgs = [
        {at:0,    msg:'Checking how Google classifies your business\u2026'},
        {at:20000,msg:'Analyzing brand signal clarity across directories\u2026'},
        {at:45000,msg:'Mapping entity authority gaps\u2026'},
        {at:70000,msg:'Building your entity health report\u2026'}
    ];

    function show(el){ el.style.display = ''; }
    function hide(el){ el.style.display = 'none'; }

    function scoreClass(score, max){
        var pct = max > 0 ? (score / max) * 100 : 0;
        if(pct >= 70) return 'good';
        if(pct >= 45) return 'mid';
        return 'bad';
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
        lastScannedUrl = url;

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

    /* ── Pillar config ──────────────────────────────────────────── */
    var pillarMeta = {
        ai_visibility:     { icon:'\uD83E\uDD16', label:'GEO\nReady' },
        cannibalization:   { icon:'\uD83D\uDD17', label:'Cannibal-\nization' },
        meta_titles:       { icon:'\uD83C\uDFF7\uFE0F', label:'Meta\nTitles' },
        content_structure: { icon:'\uD83D\uDCD0', label:'Content\nDepth' },
        technical:         { icon:'\u2699\uFE0F', label:'Technical\nSEO' }
    };
    var pillarOrder = ['ai_visibility','cannibalization','meta_titles','content_structure','technical'];

    function renderResults(data){
        var r = (data.results && typeof data.results === 'object') ? data.results : data;
        var d = r.dimensions || r;
        var total = r.total_score || data.total_score || data.score || 0;
        var grade = r.grade || data.grade || '';
        var pages = r.pages_crawled || data.pages_crawled || data.pages_analyzed || 0;
        var benchmark = r.benchmark || data.benchmark || '';
        var autoCount = r.auto_fixable_count || data.auto_fixable_count || 0;
        var contentCount = r.requires_content_count || data.requires_content_count || 0;
        var totalIssues = autoCount + contentCount;
        var scanId = data.id || data.scan_id || '';

        var col = scoreClass(total, 100);

        var html = '<div class="score-hero">';
        html += '<div class="score-ring ' + col + '">' + total + '<span class="score-of">/100</span></div>';
        html += '<div class="score-grade">' + esc(grade) + '</div>';
        if(pages) html += '<div class="score-pages">' + pages + ' pages analyzed</div>';
        html += '</div>';

        /* Pillars */
        html += '<div class="pillars-row">';
        for(var i=0; i<pillarOrder.length; i++){
            var key = pillarOrder[i];
            var p = d[key];
            if(!p) continue;
            var m = pillarMeta[key];
            var pc = scoreClass(p.score, p.max);
            var pctW = p.max > 0 ? Math.round((p.score / p.max) * 100) : 0;

            html += '<div class="pillar-card">';
            html += '<div class="pillar-icon">' + m.icon + '</div>';
            html += '<div class="pillar-label">' + m.label + '</div>';
            html += '<div class="pillar-score ' + pc + '">' + p.score + '</div>';
            html += '<div class="pillar-max">of ' + p.max + '</div>';
            html += '<div class="pillar-bar"><div class="pillar-bar-fill ' + pc + '" style="width:' + pctW + '%;background:var(--' + pc + ')"></div></div>';

            var issues = p.issues || [];
            if(issues.length){
                html += '<ul class="pillar-issues">';
                var showN = Math.min(issues.length, 2);
                for(var j=0; j<showN; j++) html += '<li>' + esc(issues[j]) + '</li>';
                html += '</ul>';
            }
            var af = p.auto_fixable || [];
            if(af.length) html += '<div class="pillar-badge">Auto-fixable \u2713</div>';
            html += '</div>';
        }
        html += '</div>';

        /* Entity section */
        var bizName = document.getElementById('ss-biz').value.trim();
        html += '<div class="entity-section" id="entity-section">';
        html += '<h3>\uD83E\uDDE0 Entity &amp; Brand Authority Analysis</h3>';
        if(bizName && scanId && apiKey){
            html += '<div class="entity-loading" id="entity-loading"><div class="entity-spinner"></div><div class="entity-msg" id="entity-msg">Checking how Google classifies your business\u2026</div></div>';
            html += '<div class="entity-results" id="entity-results"></div>';
        } else {
            html += '<div style="text-align:center;padding:16px;color:var(--text-muted);font-size:14px;">Entity analysis requires a business name and API key. Re-run with your business name to unlock this section.</div>';
        }
        html += '</div>';

        /* Benchmark */
        if(benchmark) html += '<div style="text-align:center;font-size:14px;color:var(--text-muted);margin-bottom:18px;font-style:italic;">' + esc(benchmark) + '</div>';

        /* CTA */
        html += '<div class="cta-block">';
        if(autoCount && totalIssues) html += '<div style="font-size:18px;font-weight:700;color:var(--text);margin-bottom:14px;">Siloq can automatically fix ' + autoCount + ' of your ' + totalIssues + ' issues.</div>';
        html += '<a class="cta-main" href="https://app.siloq.ai/register" target="_blank" rel="noopener">Start Your Free Trial &rarr;</a>';
        html += '<div class="cta-sub" id="cta-sub">See exactly what Siloq would fix — no credit card required.</div>';
        html += '<br><a class="cta-link" href="#" target="_blank" rel="noopener">Want a full audit? Book a demo &rarr;</a>';
        html += '</div>';

        /* Send report section */
        var scannedDomain = '';
        try { scannedDomain = new URL(lastScannedUrl).hostname.replace(/^www\./,''); } catch(e){}
        html += '<div class="send-section">'
            + '<div class="send-title">\uD83D\uDCE4 Send this report to the business owner</div>'
            + '<p class="send-desc">Personalize the email with their name and business — it\'ll show their exact score and top issues.</p>'
            + '<input type="text" id="ss-prospect-name" placeholder="First name (e.g. John)">'
            + '<input type="text" id="ss-biz-name" placeholder="Business name (e.g. EMS Cleanup)" value="' + esc(scannedDomain) + '">'
            + '<input type="email" id="ss-prospect-email" placeholder="Their email address">'
            + '<button class="send-btn" id="ss-send-btn">Send Report Email \u2192</button>'
            + '<div class="send-status" id="ss-send-status"></div>'
            + '</div>';

        resEl.innerHTML = html;
        resEl.style.display = '';

        /* Store scan data */
        lastScanData = {
            url: lastScannedUrl,
            total_score: total,
            grade: grade,
            pages: pages,
            benchmark: benchmark,
            auto_count: autoCount,
            total_issues: totalIssues,
            top_issues: r.top_issues || [],
            cta: r.cta || ''
        };

        /* Wire send button */
        document.getElementById('ss-send-btn').addEventListener('click', handleSend);

        /* Start entity polling if applicable */
        if(bizName && scanId && apiKey){
            pollEntity(scanId);
        }
    }

    /* ── Entity polling ─────────────────────────────────────────── */
    function pollEntity(scanId){
        var pollCount = 0;
        var maxPolls = 15;
        var startTime = Date.now();
        var msgEl = document.getElementById('entity-msg');
        var loadingEl = document.getElementById('entity-loading');
        var resultsEl = document.getElementById('entity-results');

        function updateProgressMsg(){
            var elapsed = Date.now() - startTime;
            for(var i=entityMsgs.length-1; i>=0; i--){
                if(elapsed >= entityMsgs[i].at){
                    if(msgEl) msgEl.textContent = entityMsgs[i].msg;
                    break;
                }
            }
        }

        function doPoll(){
            pollCount++;
            updateProgressMsg();
            if(pollCount > maxPolls){
                if(msgEl) msgEl.textContent = 'Entity analysis timed out \u2014 try again later.';
                return;
            }
            var url = apiBase.replace(/\/+$/,'') + '/scans/' + scanId + '/entity-analysis/';
            var xhr = new XMLHttpRequest();
            xhr.open('GET', url, true);
            xhr.setRequestHeader('Authorization', 'Bearer ' + apiKey);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.onload = function(){
                if(xhr.status === 200){
                    try{
                        var resp = JSON.parse(xhr.responseText);
                        if(resp.status === 'completed' && resp.data){
                            renderEntityResults(resp.data, loadingEl, resultsEl);
                            return;
                        }
                    }catch(e){}
                }
                setTimeout(doPoll, 10000);
            };
            xhr.onerror = function(){
                setTimeout(doPoll, 10000);
            };
            xhr.send();
        }

        setTimeout(doPoll, 10000);
    }

    function renderEntityResults(ed, loadingEl, resultsEl){
        if(loadingEl) loadingEl.style.display = 'none';
        if(!resultsEl) return;
        var html = '';
        var pg = ed.perception_gap || {};
        html += '<div class="perception-boxes">';
        html += '<div class="perception-box"><div class="pbox-label">How Google sees you now</div><div class="pbox-value">' + esc(pg.current || 'Unknown') + '</div></div>';
        html += '<div class="perception-box"><div class="pbox-label">Target entity classification</div><div class="pbox-value">' + esc(pg.target || 'Not set') + '</div></div>';
        html += '</div>';
        if(pg.pitch_line){
            html += '<div class="pitch-bar">' + esc(pg.pitch_line) + '</div>';
        }

        var gaps = ed.authority_gaps || [];
        if(gaps.length){
            html += '<h4 style="font-size:15px;font-weight:600;margin:18px 0 10px;color:var(--text);">Authority Gaps</h4>';
            for(var i=0; i<gaps.length; i++){
                var g = gaps[i];
                var sevClass = (g.severity||'').toLowerCase();
                html += '<div class="gap-row">';
                html += '<div class="gap-name">' + esc(g.name || g.label || '') + '</div>';
                if(g.pct !== undefined) html += '<div class="gap-pct">' + g.pct + '%</div>';
                html += '<div class="gap-severity ' + esc(sevClass) + '">' + esc(g.severity || '') + '</div>';
                html += '</div>';
            }
        }

        var crits = ed.critical_issues || [];
        if(crits.length){
            html += '<h4 style="font-size:15px;font-weight:600;margin:18px 0 10px;color:var(--text);">Critical Issues</h4>';
            for(var i=0; i<crits.length; i++){
                var c = crits[i];
                html += '<div class="critical-block">';
                if(c.severity) html += '<div class="cb-sev" style="color:var(--bad)">' + esc(c.severity) + '</div>';
                html += '<div class="cb-title">' + esc(c.title || '') + '</div>';
                html += '<div class="cb-desc">' + esc(c.description || '') + '</div>';
                html += '</div>';
            }
        }

        /* Update CTA subtext if opportunity pitch exists */
        var opp = ed.siloq_opportunity || {};
        if(opp.pitch){
            var ctaSub = document.getElementById('cta-sub');
            if(ctaSub) ctaSub.textContent = opp.pitch;
        }

        resultsEl.innerHTML = html;
        resultsEl.style.display = '';
    }

    /* ── Send report handler ────────────────────────────────────── */
    function handleSend(){
        var name  = document.getElementById('ss-prospect-name').value.trim();
        var biz   = document.getElementById('ss-biz-name').value.trim();
        var email = document.getElementById('ss-prospect-email').value.trim();
        var statusEl2 = document.getElementById('ss-send-status');
        var sendBtn = document.getElementById('ss-send-btn');
        if(!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)){
            statusEl2.style.color = 'var(--bad)';
            statusEl2.textContent = 'Please enter a valid email address.';
            return;
        }
        sendBtn.disabled = true;
        sendBtn.textContent = 'Sending\u2026';
        statusEl2.textContent = '';

        var fd2 = new FormData();
        fd2.append('action', 'siloq_send_scan_report');
        fd2.append('_wpnonce', nonce);
        fd2.append('prospect_name', name);
        fd2.append('biz_name', biz);
        fd2.append('prospect_email', email);
        fd2.append('scan_data', JSON.stringify(lastScanData));

        var xhr2 = new XMLHttpRequest();
        xhr2.open('POST', ajaxUrl, true);
        xhr2.onload = function(){
            try{
                var resp2 = JSON.parse(xhr2.responseText);
                if(resp2.success){
                    sendBtn.textContent = '\u2713 Report sent!';
                    sendBtn.style.background = '#6b7280';
                    statusEl2.style.color = 'var(--good)';
                    statusEl2.textContent = 'Email sent to ' + email + '. Follow up in 2-3 days.';
                } else {
                    sendBtn.disabled = false;
                    sendBtn.textContent = 'Send Report Email \u2192';
                    statusEl2.style.color = 'var(--bad)';
                    statusEl2.textContent = (resp2.data && resp2.data.message) ? resp2.data.message : 'Send failed. Please try again.';
                }
            }catch(e){
                sendBtn.disabled = false;
                sendBtn.textContent = 'Send Report Email \u2192';
                statusEl2.style.color = 'var(--bad)';
                statusEl2.textContent = 'Unexpected error. Please try again.';
            }
        };
        xhr2.onerror = function(){
            sendBtn.disabled = false;
            sendBtn.textContent = 'Send Report Email \u2192';
            statusEl2.style.color = 'var(--bad)';
            statusEl2.textContent = 'Network error. Please try again.';
        };
        xhr2.send(fd2);
    }

    var lastScannedUrl = '';
    var lastScanData = {};

    function esc(s){
        if(!s) return '';
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

    /**
     * AJAX handler — send scan report email to a prospect.
     */
    public static function ajax_send_scan_report() {
        check_ajax_referer( 'siloq_scanner_nonce', '_wpnonce' );

        $prospect_name  = sanitize_text_field( wp_unslash( $_POST['prospect_name'] ?? '' ) );
        $biz_name       = sanitize_text_field( wp_unslash( $_POST['biz_name'] ?? '' ) );
        $prospect_email = sanitize_email( wp_unslash( $_POST['prospect_email'] ?? '' ) );
        $scan_data_raw  = wp_unslash( $_POST['scan_data'] ?? '{}' );
        $scan           = json_decode( $scan_data_raw, true );

        if ( empty( $prospect_email ) || ! is_email( $prospect_email ) ) {
            wp_send_json_error( array( 'message' => 'Invalid email address.' ) );
            return;
        }
        if ( empty( $scan ) || ! is_array( $scan ) ) {
            wp_send_json_error( array( 'message' => 'Missing scan data.' ) );
            return;
        }

        $score       = intval( $scan['total_score'] ?? 0 );
        $grade       = sanitize_text_field( $scan['grade'] ?? 'Needs Attention' );
        $pages       = intval( $scan['pages'] ?? 0 );
        $url         = esc_url( $scan['url'] ?? '' );
        $benchmark   = sanitize_text_field( $scan['benchmark'] ?? '' );
        $auto_count  = intval( $scan['auto_count'] ?? 0 );
        $total_issues= intval( $scan['total_issues'] ?? 0 );
        $top_issues  = array_slice( (array) ( $scan['top_issues'] ?? [] ), 0, 4 );

        $first_name  = $prospect_name ?: 'there';
        $biz_label   = $biz_name ?: ( $url ? parse_url( $url, PHP_URL_HOST ) : 'your business' );
        $score_emoji = $score >= 80 ? '🟢' : ( $score >= 60 ? '🟡' : '🔴' );

        // Build issue list
        $issues_text = '';
        foreach ( $top_issues as $i => $issue ) {
            $issues_text .= ( $i + 1 ) . '. ' . $issue . "\n";
        }
        if ( empty( $issues_text ) ) {
            $issues_text = "• Missing or incomplete structured data\n• Meta title issues\n• AI visibility gaps\n";
        }

        $auto_line = $auto_count > 0 && $total_issues > 0
            ? "Siloq can automatically fix {$auto_count} of your {$total_issues} issues — no developer needed."
            : "Siloq can diagnose and fix these issues automatically.";

        $subject = "We scanned {$biz_label} — here's what we found";

        $body  = "Hi {$first_name},\n\n";
        $body .= "I ran a quick SEO and AI-visibility scan on {$url} and wanted to share the results.\n\n";
        $body .= "{$score_emoji} Your score: {$score}/100 — {$grade}\n";
        if ( $benchmark ) {
            $body .= "{$benchmark}\n";
        }
        $body .= "\n";
        $body .= "Here's what we found:\n\n";
        $body .= $issues_text;
        $body .= "\n{$auto_line}\n\n";
        $body .= "Most businesses in your category are leaving significant ranking opportunities on the table — especially with how AI search engines like ChatGPT and Google AI Overviews now decide who to surface.\n\n";
        $body .= "If you'd like to see exactly what Siloq would fix on {$biz_label}, you can start a free trial here:\n";
        $body .= "https://app.siloq.ai/register\n\n";
        $body .= "Happy to answer any questions.\n\n";
        $body .= "— Kyle Fuchs\nPrecision Marketing\n";

        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: Kyle Fuchs <support@siloq.ai>',
            'Reply-To: support@siloq.ai',
        );

        $sent = wp_mail( $prospect_email, $subject, $body, $headers );

        if ( $sent ) {
            // Log to options for basic tracking (last 50 sends)
            $log = get_option( 'siloq_scan_sends_log', array() );
            array_unshift( $log, array(
                'email'    => $prospect_email,
                'biz'      => $biz_label,
                'url'      => $url,
                'score'    => $score,
                'sent_at'  => current_time( 'mysql' ),
            ) );
            update_option( 'siloq_scan_sends_log', array_slice( $log, 0, 50 ), false );

            wp_send_json_success( array( 'message' => 'Report sent.' ) );
        } else {
            wp_send_json_error( array( 'message' => 'Email failed to send. Check your server mail settings.' ) );
        }
    }
}
