/**
 * Siloq Admin Dashboard — v1.5.60
 * Tab switching, AJAX stats, SVG score ring, readiness meter, sync handler.
 */
(function () {
    'use strict';

    /* ── Helpers ── */
    var $ = function (sel, ctx) { return (ctx || document).querySelector(sel); };
    var $$ = function (sel, ctx) { return (ctx || document).querySelectorAll(sel); };

    /* ── Tab Switching ── */
    function initTabs() {
        var tabs = $$('.siloq-dash-tab');
        var panels = $$('.siloq-dash-panel');

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                var target = tab.getAttribute('data-tab');

                tabs.forEach(function (t) {
                    t.classList.remove('is-active');
                    t.setAttribute('aria-selected', 'false');
                });
                panels.forEach(function (p) {
                    p.classList.remove('is-active');
                    // Use class only — don't mix hidden attr with CSS display:none (specificity conflict)
                });

                tab.classList.add('is-active');
                tab.setAttribute('aria-selected', 'true');

                var panel = $('[data-panel="' + target + '"]');
                if (panel) {
                    panel.classList.add('is-active');
                }
            });
        });
    }

    /* ── Score Ring Animation ── */
    function animateScoreRing(score) {
        var fg = $('.siloq-score-ring__fg');
        var label = $('#siloq-health-score');
        if (!fg || !label) return;

        var circumference = 2 * Math.PI * 60; // r=60
        var offset = circumference - (score / 100) * circumference;

        // Pick colour
        var color;
        if (score >= 90)      color = '#14b8a6'; // teal
        else if (score >= 75) color = '#22c55e'; // green
        else if (score >= 50) color = '#f59e0b'; // amber
        else                  color = '#ef4444'; // red

        fg.style.stroke = color;
        fg.style.strokeDashoffset = offset;
        label.textContent = score;
    }

    /* ── Readiness Meter Animation ── */
    function animateReadiness(pct) {
        var fg = $('.siloq-readiness-fg');
        var label = $('#siloq-readiness-pct');
        if (!fg || !label) return;

        var circumference = 2 * Math.PI * 34; // r=34
        var offset = circumference - (pct / 100) * circumference;

        var color;
        if (pct >= 90)      color = '#14b8a6';
        else if (pct >= 75) color = '#22c55e';
        else if (pct >= 50) color = '#f59e0b';
        else                color = '#ef4444';

        fg.style.stroke = color;
        fg.style.strokeDashoffset = offset;
        label.textContent = pct + '%';
    }

    /* ── Hero Sentence ── */
    function heroSentence(score) {
        if (score >= 90) return 'Your site is in excellent shape. Keep it up!';
        if (score >= 75) return 'Looking good — a few tweaks could push you higher.';
        if (score >= 50) return 'There are opportunities to strengthen your content structure.';
        return 'Your site needs attention — let\u2019s improve things together.';
    }

    /* ── Populate Dashboard Cards ── */
    function populateCards(data) {
        // Health score
        var score = (data.health && typeof data.health.overall_score === 'number')
            ? data.health.overall_score
            : null;

        if (score !== null) {
            animateScoreRing(score);
            var sentence = $('#siloq-hero-sentence');
            if (sentence) sentence.textContent = heroSentence(score);
        } else {
            var label = $('#siloq-health-score');
            if (label) label.textContent = '—';
            var sentence2 = $('#siloq-hero-sentence');
            if (sentence2) sentence2.textContent = 'Connect your site to see your health score.';
        }

        // Readiness
        var schemaPct = (data.schema && typeof data.schema.completeness_pct === 'number')
            ? data.schema.completeness_pct : 0;
        animateReadiness(schemaPct);

        var missingEl = $('#siloq-missing-fields');
        if (missingEl) {
            var missing = (data.schema && Array.isArray(data.schema.missing_fields))
                ? data.schema.missing_fields : [];
            if (missing.length) {
                missingEl.innerHTML = missing.map(function (f) {
                    return '<li>' + escHtml(f) + '</li>';
                }).join('');
            } else if (schemaPct >= 100) {
                missingEl.innerHTML = '<li style="color:#22c55e">All fields complete!</li>';
            } else {
                missingEl.innerHTML = '<li class="siloq-placeholder">No data available</li>';
            }
        }

        // Pages needing attention
        var attEl = $('#siloq-attention-list');
        if (attEl) {
            var pages = (data.pages && Array.isArray(data.pages.results))
                ? data.pages.results : [];
            var needsAttention = pages.filter(function (p) {
                return p.seo_score !== undefined && p.seo_score < 70;
            }).slice(0, 6);

            if (needsAttention.length) {
                attEl.innerHTML = needsAttention.map(function (p) {
                    var dotClass = p.seo_score < 40 ? 'red' : (p.seo_score < 70 ? 'amber' : 'green');
                    return '<li>' +
                        '<span class="siloq-attention-dot siloq-attention-dot--' + dotClass + '"></span>' +
                        '<span class="siloq-attention-label">' + escHtml(p.title || p.url || 'Untitled') + '</span>' +
                        '<a href="' + escAttr(safeUrl(p.edit_url)) + '" class="siloq-btn siloq-btn--small siloq-btn--outline">Fix It</a>' +
                        '</li>';
                }).join('');
            } else {
                attEl.innerHTML = '<li class="siloq-placeholder">All pages look healthy</li>';
            }
        }

        // Recent wins
        var winsEl = $('#siloq-wins-list');
        if (winsEl) {
            var wins = (data.wins && Array.isArray(data.wins.results))
                ? data.wins.results : [];
            if (wins.length) {
                winsEl.innerHTML = wins.slice(0, 3).map(function (w) {
                    return '<li>' +
                        '<div class="siloq-win-title">' + escHtml(w.title || w.page_title || 'Content update') + '</div>' +
                        '<div class="siloq-win-meta">' + escHtml(w.completed_at || w.created_at || '') + '</div>' +
                        '</li>';
                }).join('');
            } else {
                winsEl.innerHTML = '<li class="siloq-placeholder">No recent improvements yet</li>';
            }
        }

        // Last synced
        var syncEl = $('#siloq-last-synced');
        if (syncEl) {
            if (data.last_sync) {
                syncEl.textContent = 'Last synced: ' + data.last_sync;
            } else {
                syncEl.textContent = 'Last synced: never';
            }
        }
    }

    /* ── AJAX: Load Dashboard Stats ── */
    function loadDashboardStats() {
        if (typeof siloqAdminData === 'undefined') return;

        var xhr = new XMLHttpRequest();
        xhr.open('POST', siloqAdminData.ajaxUrl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4 && xhr.status === 200) {
                try {
                    var resp = JSON.parse(xhr.responseText);
                    if (resp.success && resp.data) {
                        populateCards(resp.data);
                    }
                } catch (e) {
                    // silent
                }
            }
        };
        xhr.send('action=siloq_get_dashboard_stats&nonce=' + encodeURIComponent(siloqAdminData.nonce));
    }

    /* ── Sync Now Button ── */
    function initSyncButton() {
        var btn = $('#siloq-sync-now-btn');
        if (!btn) return;

        btn.addEventListener('click', function () {
            if (btn.classList.contains('is-syncing')) return;
            btn.classList.add('is-syncing');
            btn.disabled = true;

            if (typeof siloqAdmin === 'undefined' && typeof siloqAdminData === 'undefined') return;

            var ajaxUrl = (typeof siloqAdmin !== 'undefined') ? siloqAdmin.ajaxUrl : siloqAdminData.ajaxUrl;
            var nonce   = (typeof siloqAdmin !== 'undefined') ? siloqAdmin.nonce   : siloqAdminData.nonce;

            var xhr = new XMLHttpRequest();
            xhr.open('POST', ajaxUrl, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4) {
                    btn.classList.remove('is-syncing');
                    btn.disabled = false;
                    // Reload stats after sync
                    loadDashboardStats();
                }
            };
            xhr.send('action=siloq_sync_all_pages&nonce=' + encodeURIComponent(nonce));
        });
    }

    /* ── Utility ── */
    function escHtml(s) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(s || ''));
        return d.innerHTML;
    }

    function escAttr(s) {
        return (s || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;')
                        .replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/`/g, '&#96;');
    }

    // Validate URLs from API responses — reject javascript: and other non-http schemes
    function safeUrl(u) {
        return (u && /^https?:\/\//i.test(u)) ? u : '#';
    }

    /* ── Init ── */
    document.addEventListener('DOMContentLoaded', function () {
        initTabs();
        initSyncButton();
        loadDashboardStats();
    });
})();
