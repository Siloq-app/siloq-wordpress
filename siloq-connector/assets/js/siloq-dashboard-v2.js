/**
 * Siloq Dashboard v2 — Tab switching, score ring, plan AJAX, roadmap persistence, pages tab
 * Version: 1.5.130
 */
(function ($) {
  'use strict';

  var cfg = window.siloqDash || {};
  // Seed Quick Wins completed state from PHP (persisted across sessions)
  window.siloqQwCompleted = cfg.qwCompleted || {};

  /* ─── Tab Switching (aria) ───────────────────── */
  function initTabs() {
    var $btns = $('.siloq-tab-btn');
    var $panels = $('.siloq-tab-panel');

    $btns.on('click', function (e) {
      e.preventDefault();
      var target = $(this).attr('aria-controls');

      $btns.attr('aria-selected', 'false');
      $(this).attr('aria-selected', 'true');

      $panels.attr('aria-hidden', 'true').removeClass('active');
      $('#' + target).attr('aria-hidden', 'false').addClass('active');

      // Update URL hash for bookmarking
      if (history.replaceState) {
        history.replaceState(null, null, '#' + target);
      }
    });

    // Restore from hash
    var hash = window.location.hash.replace('#', '');
    if (hash && $('#' + hash).length) {
      $btns.filter('[aria-controls="' + hash + '"]').trigger('click');
    }
  }

  /* ─── Score Ring Animation ───────────────────── */
  function initScoreRing() {
    var $fg = $('.siloq-score-ring__fg');
    if (!$fg.length) return;

    var score = parseInt(cfg.siteScore, 10) || 0;
    var radius = parseFloat($fg.attr('r')) || 72;
    var circumference = 2 * Math.PI * radius;

    // Set initial state (empty)
    $fg.css({
      'stroke-dasharray': '0 ' + circumference,
      'stroke-dashoffset': '0'
    });

    // Determine color
    var color;
    if (score >= 90) color = getComputedStyle(document.documentElement).getPropertyValue('--siloq-teal').trim();
    else if (score >= 75) color = getComputedStyle(document.documentElement).getPropertyValue('--siloq-success').trim();
    else if (score >= 50) color = getComputedStyle(document.documentElement).getPropertyValue('--siloq-warning').trim();
    else color = getComputedStyle(document.documentElement).getPropertyValue('--siloq-danger').trim();

    $fg.css('stroke', color);

    // Animate after short delay
    setTimeout(function () {
      var filled = (score / 100) * circumference;
      $fg.css('stroke-dasharray', filled + ' ' + circumference);
    }, 200);

    // Animate number count-up
    var $val = $('.siloq-score-ring__value');
    if ($val.length) {
      $({ v: 0 }).animate({ v: score }, {
        duration: 1200,
        easing: 'swing',
        step: function () { $val.text(Math.round(this.v)); },
        complete: function () { $val.text(score); }
      });
    }
  }

  /* ─── Accordion open/close ───────────────────── */
  function initAccordions() {
    $(document).on('click', '.siloq-accordion__trigger', function () {
      var $acc = $(this).closest('.siloq-accordion');
      $acc.toggleClass('is-open');
      var isOpen = $acc.hasClass('is-open');
      $(this).attr('aria-expanded', isOpen ? 'true' : 'false');
    });
  }

  /* ─── Plan Generation AJAX ───────────────────── */
  var planLoaded = false;

  function loadPlanData($btn) {
    if ($btn) { $btn.prop('disabled', true).text('Generating...'); }
    $.post(cfg.ajaxUrl, {
      action: 'siloq_get_plan_data',
      nonce: cfg.nonce
    }, function (resp) {
      if (resp.success && resp.data) {
        planLoaded = true;
        renderPlanData(resp.data);
        if ($btn) { $btn.text('Refresh Plan').addClass('siloq-btn--success').prop('disabled', false); }
      } else {
        if ($btn) { $btn.prop('disabled', false).text('Generate Your SEO Plan →'); }
        var msg = resp.data && resp.data.message ? resp.data.message : 'Failed to generate plan. Please try again.';
        $('#siloq-architecture-content').html('<p class="siloq-empty" style="color:#c0392b">' + msg + '</p>');
      }
    }).fail(function () {
      if ($btn) { $btn.prop('disabled', false).text('Generate Your SEO Plan →'); }
    });
  }

  function initPlanGeneration() {
    // Manual generate button
    $(document).on('click', '.siloq-generate-plan-btn', function (e) {
      e.preventDefault();
      loadPlanData($(this));
    });

    // Auto-load when plan tab becomes active (covers "View Priority Actions" click too)
    $(document).on('click', '[aria-controls="siloq-tab-plan"], .siloq-tab-btn[aria-controls="siloq-tab-plan"]', function () {
      if (!planLoaded) {
        setTimeout(function () { loadPlanData(null); }, 100);
      }
    });

    // Auto-load if plan tab is already active on page load (URL hash)
    if (window.location.hash === '#siloq-tab-plan' || $('#siloq-tab-plan').hasClass('active')) {
      setTimeout(function () { loadPlanData(null); }, 300);
    }
  }

  // Quick Wins completed state (loaded once, managed in memory)
  var qwCompleted = window.siloqQwCompleted || {};

  function renderPlanData(data) {

    // Architecture tree
    var $archContent = $('#siloq-architecture-content');
    if (data.architecture && data.architecture.length) {
      // Group nodes: hubs first, then nest spokes/supporting under their hub
      var hubs   = {}; // id → node
      var spokes = {}; // hub_id → [nodes]
      var top    = []; // nodes with no parent (apex_hub, hub, orphan, pending, flat)

      data.architecture.forEach(function (node) {
        if (node.type === 'hub' || node.type === 'apex_hub') {
          hubs[node.id] = node;
          hubs[node.id]._children = [];
        }
      });

      data.architecture.forEach(function (node) {
        if (node.type === 'hub' || node.type === 'apex_hub') {
          top.push(node);
        } else if (node.hub_id && hubs[node.hub_id]) {
          hubs[node.hub_id]._children.push(node);
        } else {
          top.push(node);
        }
      });

      function nodeHtml(node, depth) {
        var indent = depth * 18;
        var typeColors = {
          'apex_hub':  '#7c3aed',
          'hub':       '#0ea5e9',
          'spoke':     '#059669',
          'supporting':'#6b7280',
          'orphan':    '#f59e0b',
          'pending':   '#9ca3af',
        };
        var color = typeColors[node.type] || '#6b7280';
        var badge = '';
        if (node.type === 'apex_hub') badge = '<span style="font-size:10px;font-weight:700;color:#7c3aed;background:#ede9fe;border-radius:3px;padding:1px 5px;margin-left:5px;">APEX HUB</span>';
        else if (node.type === 'hub')  badge = '<span style="font-size:10px;font-weight:700;color:#0ea5e9;background:#e0f2fe;border-radius:3px;padding:1px 5px;margin-left:5px;">HUB</span>';
        else if (node.type === 'orphan') badge = '<span style="font-size:10px;color:#f59e0b;margin-left:5px;">(no structure assigned)</span>';
        else if (node.type === 'pending') badge = '<span style="font-size:10px;color:#9ca3af;margin-left:5px;">(not yet analyzed)</span>';
        var link = node.el_url
          ? '<a href="' + escAttr(node.el_url) + '" target="_blank" style="color:' + color + ';text-decoration:none;font-size:13px;font-weight:' + (depth === 0 ? '600' : '400') + ';">' + escHtml(node.title) + '</a>'
          : '<span style="color:' + color + ';font-size:13px;">' + escHtml(node.title) + '</span>';
        var connector = depth > 0 ? '<span style="color:#d1d5db;margin-right:4px;">└─</span>' : '';
        var html = '<div style="display:flex;align-items:center;padding:5px 0;padding-left:' + indent + 'px;border-bottom:1px solid #f9fafb;">'
          + connector + link + badge + '</div>';
        if (node._children && node._children.length) {
          node._children.forEach(function (child) {
            html += nodeHtml(child, depth + 1);
          });
        }
        return html;
      }

      var html = '';
      top.forEach(function (node) { html += nodeHtml(node, 0); });
      $archContent.html('<div style="border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">' + html + '</div>');
    } else {
      $archContent.html('<p class="siloq-empty-hint">All pages are orphans &mdash; sync pages and assign page types to build your structure.</p>');
    }

    // ── Site Health Score ring ────────────────────────────────────────
    var score = data.site_score || 0;
    var arc   = document.getElementById('siloq-health-arc');
    var numEl = document.getElementById('siloq-health-score-num');
    var lblEl = document.getElementById('siloq-health-label');
    if (arc && numEl) {
      var circumference = 2 * Math.PI * 34; // 213.6
      var offset = circumference - (score / 100) * circumference;
      arc.style.strokeDashoffset = offset;
      arc.style.stroke = score >= 80 ? '#0d9488' : score >= 60 ? '#4f46e5' : score >= 40 ? '#f59e0b' : '#dc2626';
      numEl.textContent = score;
      if (lblEl) {
        var label = score >= 80 ? 'Excellent' : score >= 60 ? 'Good' : score >= 40 ? 'Needs work' : 'Critical issues';
        lblEl.textContent = 'Site Health: ' + label;
        lblEl.style.color = arc.style.stroke;
      }
    }

    // Score breakdown
    var breakdown = data.score_breakdown || {};
    var bdHtml = '';
    if (breakdown.missing_titles > 0)   bdHtml += '<div style="font-size:11px;color:#dc2626;">● ' + breakdown.missing_titles + ' missing SEO titles</div>';
    if (breakdown.missing_descs > 0)    bdHtml += '<div style="font-size:11px;color:#dc2626;">● ' + breakdown.missing_descs + ' missing descriptions</div>';
    if (breakdown.missing_schema > 0)   bdHtml += '<div style="font-size:11px;color:#f59e0b;">● ' + breakdown.missing_schema + ' pages without schema</div>';
    if (breakdown.thin_content > 0)     bdHtml += '<div style="font-size:11px;color:#f59e0b;">● ' + breakdown.thin_content + ' thin content pages</div>';
    if (breakdown.orphan_count > 0)     bdHtml += '<div style="font-size:11px;color:#9ca3af;">● ' + breakdown.orphan_count + ' orphan pages</div>';
    if (!bdHtml) bdHtml = '<div style="font-size:12px;color:#059669;">No major issues found.</div>';
    $('#siloq-score-breakdown').html(bdHtml);

    // Fix All banner
    var missingCount = (breakdown.missing_titles || 0) + (breakdown.missing_descs || 0);
    var fixAllPages  = data.fix_all_pages || []; // array of post_ids with missing title or desc
    if (missingCount > 0) {
      $('#siloq-fix-all-bar').show();
      $('#siloq-fix-all-desc').text(missingCount + ' pages are missing titles or descriptions.');
    }

    // ── Priority Actions — grouped by fix_category ────────────────────────
    if (data.actions && data.actions.length) {
      var autoActs    = data.actions.filter(function(a) { return a.fix_category === 'auto'; });
      var quickActs   = data.actions.filter(function(a) { return a.fix_category === 'quick'; });
      var contentActs = data.actions.filter(function(a) { return !a.fix_category || a.fix_category === 'content'; });

      $('#siloq-actions-count').text(data.actions.length + ' action' + (data.actions.length !== 1 ? 's' : ''));
      var actHtml = '';
      var globalIdx = 0;

      function renderActionGroup(acts, groupLabel, groupColor, groupBg, groupBadgeStyle) {
        if (!acts.length) return '';
        var h = '<div style="margin-bottom:16px;">'
          + '<div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">'
          + '<span style="font-size:11px;font-weight:700;padding:2px 10px;border-radius:999px;' + groupBadgeStyle + '">' + groupLabel + '</span>'
          + '</div>';
        acts.forEach(function(act) {
          globalIdx++;
          var isAutoFix = act.fix_category === 'auto' && (act.fix_type === 'meta_title' || act.fix_type === 'meta_description');
          var isSchema  = act.fix_category === 'auto' && act.fix_type === 'schema';
          var actionBtn = '';

          if (isAutoFix) {
            // Inline fix flow — never opens Elementor
            actionBtn = '<button class="siloq-action-apply-btn siloq-btn siloq-btn--sm siloq-btn--primary" '
              + 'data-post-id="' + escAttr(String(act.post_id || '')) + '" '
              + 'data-fix-type="' + escAttr(act.fix_type || '') + '" '
              + 'data-formula="' + escAttr(act.formula || '') + '" '
              + 'style="background:#059669;border-color:#059669;">Apply Fix &rarr;</button>';
          } else if (isSchema) {
            actionBtn = '<button class="siloq-schema-fix-btn siloq-btn siloq-btn--sm siloq-btn--primary" '
              + 'data-post-id="' + escAttr(String(act.post_id || '')) + '" '
              + 'style="background:#4f46e5;">Generate Schema &rarr;</button>';
          } else if (act.fix_category === 'content' && act.elementor_url) {
            actionBtn = '<a href="' + escAttr(act.elementor_url) + '" target="_blank" class="siloq-btn siloq-btn--sm siloq-btn--outline">'
              + 'Open in Elementor &rarr;</a>';
            if (act.instructions) {
              actionBtn += '<p style="font-size:11px;color:#6b7280;margin:6px 0 0;font-style:italic;">' + escHtml(act.instructions) + '</p>';
            }
          } else if (act.edit_url) {
            actionBtn = '<a href="' + escAttr(act.edit_url) + '" target="_blank" class="siloq-btn siloq-btn--sm siloq-btn--outline">Edit in WordPress &rarr;</a>';
          }

          h += '<div class="siloq-action-card" style="border:1px solid ' + groupColor + ';background:' + groupBg + ';border-radius:8px;padding:14px 16px;margin-bottom:8px;">'
            + '<div style="display:flex;align-items:flex-start;gap:10px;">'
            + '<span style="font-size:13px;font-weight:800;color:' + groupColor + ';min-width:22px;padding-top:1px;">' + globalIdx + '.</span>'
            + '<div style="flex:1;">'
            + '<p style="font-size:13px;font-weight:600;color:#111827;margin:0 0 4px;">' + escHtml(act.headline) + '</p>'
            + (act.detail ? '<p style="font-size:12px;color:#6b7280;margin:0 0 8px;line-height:1.5;">' + escHtml(act.detail) + '</p>' : '')
            + '<div style="display:flex;gap:8px;align-items:flex-start;flex-wrap:wrap;">' + actionBtn
            + '</div>'
            // Inline fix panel (hidden until "Apply Fix" clicked)
            + (isAutoFix ? '<div class="siloq-inline-fix-panel" style="display:none;margin-top:10px;background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:12px;"></div>' : '')
            + '</div></div></div>';
        });
        return h + '</div>';
      }

      actHtml += renderActionGroup(autoActs,    'Siloq fixes this automatically', '#059669', '#f0fdf4', 'background:#dcfce7;color:#166534;');
      actHtml += renderActionGroup(quickActs,   'Takes 2 minutes',                '#d97706', '#fffbeb', 'background:#fef3c7;color:#92400e;');
      actHtml += renderActionGroup(contentActs, 'Requires content work',          '#6b7280', '#f9fafb', 'background:#f3f4f6;color:#374151;');

      $('#siloq-actions-content').html(actHtml || '<p style="color:#9ca3af;font-size:13px;">No priority actions found.</p>');
    } else {
      $('#siloq-actions-content').html('<p style="color:#9ca3af;font-size:13px;padding:12px 0;">✅ No priority actions — your pages look good. Sync more pages to analyze them.</p>');
    }

    // ── Quick Wins checklist ──────────────────────────────────────────────
    if (data.issues) {
      var allIssues = [];
      ['critical', 'important', 'opportunity'].forEach(function(level) {
        (data.issues[level] || []).forEach(function(iss) {
          iss._level = level;
          allIssues.push(iss);
        });
      });
      renderQuickWins(allIssues);
    }

    // Roadmap
    if (data.roadmap) {
      renderRoadmap(data.roadmap);
    }
  }

  function renderQuickWins(issues) {
    if (!issues.length) {
      $('#siloq-issues-content').html('<div style="text-align:center;padding:24px;color:#9ca3af;font-size:13px;">✅ No issues found. Sync more pages to check them.</div>');
      return;
    }

    var completed = [];
    var pending   = [];

    issues.forEach(function(iss) {
      var key = (iss.post_id || '') + '_' + (iss.fix_type || sanitizeKey(iss.issue));
      var isDone = !!qwCompleted[key];
      if (isDone) completed.push(iss);
      else        pending.push(iss);
    });

    var total = issues.length;
    var doneCount = completed.length;
    $('#siloq-qw-progress').text(doneCount + ' of ' + total + ' completed');

    var html = '';

    function issueRow(iss, isDone) {
      var key      = (iss.post_id || '') + '_' + (iss.fix_type || sanitizeKey(iss.issue));
      var isAutoFix = iss.fix_category === 'auto' && (iss.fix_type === 'meta_title' || iss.fix_type === 'meta_description');
      var levelColors = { critical: '#dc2626', important: '#d97706', opportunity: '#4f46e5' };
      var color = levelColors[iss._level] || '#6b7280';

      var actionBtn = '';
      if (isAutoFix && !isDone) {
        actionBtn = '<button class="siloq-qw-apply-btn siloq-btn siloq-btn--sm siloq-btn--primary" '
          + 'data-post-id="' + escAttr(String(iss.post_id || '')) + '" '
          + 'data-fix-type="' + escAttr(iss.fix_type || '') + '" '
          + 'data-formula="' + escAttr(iss.formula || '') + '" '
          + 'data-qw-key="' + escAttr(key) + '" '
          + 'style="background:#059669;border-color:#059669;font-size:11px;padding:3px 10px;">Apply Fix</button>';
      } else if (iss.fix_category === 'auto' && iss.fix_type === 'schema' && !isDone) {
        actionBtn = '<button class="siloq-schema-fix-btn siloq-btn siloq-btn--sm siloq-btn--primary" '
          + 'data-post-id="' + escAttr(String(iss.post_id || '')) + '" '
          + 'style="font-size:11px;padding:3px 10px;">Generate Schema</button>';
      } else if (iss.fix_category === 'content' && iss.elementor_url && !isDone) {
        actionBtn = '<a href="' + escAttr(iss.elementor_url) + '" target="_blank" '
          + 'class="siloq-btn siloq-btn--sm siloq-btn--outline" style="font-size:11px;padding:3px 10px;">Open in Elementor</a>';
      }

      return '<div class="siloq-qw-row" data-key="' + escAttr(key) + '" style="display:flex;align-items:flex-start;gap:10px;padding:10px 0;border-bottom:1px solid #f3f4f6;opacity:' + (isDone ? '0.6' : '1') + ';">'
        + '<input type="checkbox" class="siloq-qw-checkbox" data-key="' + escAttr(key) + '" '
        + 'data-post-id="' + escAttr(String(iss.post_id || '')) + '" '
        + 'data-issue-type="' + escAttr(iss.fix_type || sanitizeKey(iss.issue)) + '" '
        + (isDone ? 'checked ' : '')
        + 'style="margin-top:3px;accent-color:#4f46e5;width:15px;height:15px;flex-shrink:0;">'
        + '<div style="flex:1;">'
        + '<span style="font-size:13px;font-weight:' + (isDone ? '400' : '600') + ';color:' + (isDone ? '#9ca3af' : '#111827') + ';">'
        + escHtml(iss.title) + '</span>'
        + ' <span style="font-size:11px;color:' + color + ';font-weight:500;">&mdash; ' + escHtml(iss.issue) + '</span>'
        + (isDone ? ' <span style="font-size:11px;color:#059669;margin-left:4px;">✓ fixed</span>' : '')
        + (!isDone && actionBtn ? '<div style="margin-top:6px;">' + actionBtn + '<div class="siloq-qw-fix-panel" style="display:none;margin-top:8px;"></div></div>' : '')
        + '</div>'
        + '</div>';
    }

    pending.forEach(function(iss)   { html += issueRow(iss, false); });
    completed.forEach(function(iss) { html += issueRow(iss, true);  });

    $('#siloq-issues-content').html(html);
  }

  function sanitizeKey(s) {
    return String(s || '').toLowerCase().replace(/[^a-z0-9]/g, '_').substring(0, 40);
  }

  // ── Quick Win checkbox persistence ───────────────────────────────────
  $(document).on('change', '.siloq-qw-checkbox', function() {
    var $cb   = $(this);
    var key   = $cb.data('key');
    var postId = $cb.data('post-id');
    var issType = $cb.data('issue-type');
    var checked = $cb.is(':checked');

    qwCompleted[key] = checked ? true : false;

    $.post(cfg.ajaxUrl, {
      action:     'siloq_save_quick_win',
      nonce:      cfg.nonce,
      post_id:    postId,
      issue_type: issType,
      checked:    checked ? 1 : 0
    });

    // Move row to bottom (completed) or top (uncompleted) after a short delay
    var $row = $cb.closest('.siloq-qw-row');
    $row.css('opacity', checked ? '0.6' : '1');
    setTimeout(function() {
      if (checked) {
        $('#siloq-issues-content').append($row);
      } else {
        var $firstDone = $('#siloq-issues-content .siloq-qw-row').filter(function() {
          return $(this).find('.siloq-qw-checkbox').is(':checked');
        }).first();
        if ($firstDone.length) $firstDone.before($row);
        else $('#siloq-issues-content').prepend($row);
      }

      // Update progress counter
      var total = $('#siloq-issues-content .siloq-qw-row').length;
      var done  = $('#siloq-issues-content .siloq-qw-checkbox:checked').length;
      $('#siloq-qw-progress').text(done + ' of ' + total + ' completed');
    }, 300);
  });

  // ── Inline Fix Panel (Priority Actions + Quick Wins) ─────────────────
  // Shared handler for both .siloq-action-apply-btn and .siloq-qw-apply-btn
  $(document).on('click', '.siloq-action-apply-btn, .siloq-qw-apply-btn', function() {
    var $btn      = $(this);
    var postId    = $btn.data('post-id');
    var fixType   = $btn.data('fix-type');  // 'meta_title' | 'meta_description'
    var formula   = $btn.data('formula');
    var qwKey     = $btn.data('qw-key');
    var $panel    = $btn.closest('.siloq-action-card, .siloq-qw-row').find('.siloq-inline-fix-panel, .siloq-qw-fix-panel').first();

    if (!$panel.length || $panel.is(':visible')) return; // Already open

    var fieldLabel = fixType === 'meta_title' ? 'SEO Title' : 'Meta Description';
    var maxLen     = fixType === 'meta_title' ? 60 : 160;
    var hasAI      = !!cfg.hasAnthropicKey;

    $panel.html(
      '<div style="font-size:12px;font-weight:600;color:#374151;margin-bottom:6px;">' + fieldLabel + ' suggestion:</div>'
      + '<textarea class="siloq-fix-textarea" style="width:100%;font-size:12px;padding:7px 10px;border:1px solid #d1d5db;border-radius:6px;resize:vertical;min-height:52px;font-family:inherit;" maxlength="' + maxLen + '">' + escHtml(formula || '') + '</textarea>'
      + '<div style="display:flex;align-items:center;justify-content:space-between;margin-top:6px;flex-wrap:wrap;gap:6px;">'
      + '<span class="siloq-char-count" style="font-size:11px;color:#9ca3af;">' + (formula ? formula.length : 0) + ' / ' + maxLen + ' chars</span>'
      + '<div style="display:flex;gap:6px;">'
      + (hasAI ? '<button class="siloq-fix-ai-btn siloq-btn siloq-btn--sm siloq-btn--outline" style="font-size:11px;" data-post-id="' + postId + '" data-field="' + (fixType === 'meta_title' ? 'title' : 'description') + '">✨ Improve with AI</button>' : '')
      + '<button class="siloq-fix-confirm-btn siloq-btn siloq-btn--sm siloq-btn--primary" style="font-size:11px;background:#059669;border-color:#059669;" data-post-id="' + postId + '" data-fix-type="' + fixType + '" data-qw-key="' + (qwKey || '') + '">Confirm &amp; Apply</button>'
      + '<button class="siloq-fix-cancel-btn siloq-btn siloq-btn--sm siloq-btn--outline" style="font-size:11px;">Cancel</button>'
      + '</div></div>'
    ).show();

    // Live char count
    $panel.find('.siloq-fix-textarea').on('input', function() {
      $panel.find('.siloq-char-count').text($(this).val().length + ' / ' + maxLen + ' chars');
    });

    $btn.prop('disabled', true);
  });

  // Cancel inline fix
  $(document).on('click', '.siloq-fix-cancel-btn', function() {
    var $panel = $(this).closest('.siloq-inline-fix-panel, .siloq-qw-fix-panel');
    $panel.hide().empty();
    $panel.closest('.siloq-action-card, .siloq-qw-row').find('.siloq-action-apply-btn, .siloq-qw-apply-btn').prop('disabled', false);
  });

  // AI improve button
  $(document).on('click', '.siloq-fix-ai-btn', function() {
    var $btn   = $(this);
    var postId = $btn.data('post-id');
    var field  = $btn.data('field');
    var $ta    = $btn.closest('.siloq-inline-fix-panel, .siloq-qw-fix-panel').find('.siloq-fix-textarea');
    $btn.text('Generating...').prop('disabled', true);
    $.post(cfg.ajaxUrl, { action: 'siloq_generate_meta_suggestion', nonce: cfg.nonce, post_id: postId, field: field, use_ai: 1 }, function(res) {
      if (res.success && res.data.suggestion) {
        $ta.val(res.data.suggestion).trigger('input');
      }
      $btn.text('✨ Improve with AI').prop('disabled', false);
    }).fail(function() { $btn.text('✨ Improve with AI').prop('disabled', false); });
  });

  // Confirm & Apply — writes directly via ajax_dashboard_fix
  $(document).on('click', '.siloq-fix-confirm-btn', function() {
    var $btn    = $(this);
    var postId  = $btn.data('post-id');
    var fixType = $btn.data('fix-type'); // 'meta_title' | 'meta_description'
    var qwKey   = $btn.data('qw-key');
    var $panel  = $btn.closest('.siloq-inline-fix-panel, .siloq-qw-fix-panel');
    var value   = $panel.find('.siloq-fix-textarea').val().trim();

    if (!value) { alert('Please enter a value before applying.'); return; }

    $btn.text('Applying...').prop('disabled', true);

    var fixMode = fixType === 'meta_title' ? 'fix_meta' : 'fix_meta';
    var fixSubtype = fixType === 'meta_title' ? 'title' : 'description';

    $.post(cfg.ajaxUrl, {
      action:       'siloq_dashboard_fix',
      nonce:        cfg.nonce,
      post_id:      postId,
      fix_action:   fixMode,
      fix_type:     fixSubtype,
      custom_value: value  // Pass user's edited value directly
    }, function(res) {
      if (res.success) {
        $panel.html('<div style="font-size:12px;color:#059669;font-weight:600;padding:6px 0;">✅ Applied: "' + escHtml(value.substring(0, 60)) + (value.length > 60 ? '...' : '') + '"</div>');
        // Auto-check the Quick Win if this came from QW
        if (qwKey) {
          var $cb = $('#siloq-issues-content .siloq-qw-checkbox[data-key="' + qwKey + '"]');
          if ($cb.length && !$cb.is(':checked')) {
            $cb.prop('checked', true).trigger('change');
          }
        }
      } else {
        var msg = res.data && res.data.message ? res.data.message : 'Apply failed.';
        $panel.html('<div style="font-size:12px;color:#dc2626;padding:6px 0;">⚠ ' + escHtml(msg) + '</div>');
        $btn.prop('disabled', false).text('Confirm & Apply');
      }
    });
  });

  // ── Fix All — bulk apply titles + descriptions ────────────────────────
  $(document).on('click', '#siloq-fix-all-btn', function() {
    var $btn = $(this);
    // Collect all pages with missing titles or descriptions from Priority Actions
    var postIds = [];
    $('#siloq-actions-content .siloq-action-apply-btn').each(function() {
      var id = $(this).data('post-id');
      if (id && postIds.indexOf(id) === -1) postIds.push(id);
    });
    // Also collect from Quick Wins
    $('#siloq-issues-content .siloq-qw-apply-btn').each(function() {
      var id = $(this).data('post-id');
      if (id && postIds.indexOf(id) === -1) postIds.push(id);
    });

    if (!postIds.length) {
      $('#siloq-fix-all-bar').hide();
      return;
    }

    $btn.prop('disabled', true).text('Fixing...');
    $('#siloq-fix-all-progress').show();
    var applied = [], failed = [], total = postIds.length, done = 0;

    // Fetch page titles for progress display
    var pageTitles = {};
    $('#siloq-actions-content .siloq-action-apply-btn').each(function() {
      var id = $(this).data('post-id');
      var headline = $(this).closest('.siloq-action-card').find('p').first().text();
      if (id) pageTitles[id] = headline || ('Page ' + id);
    });

    function applyNext() {
      if (!postIds.length) {
        $('#siloq-fix-all-progress').hide();
        $btn.prop('disabled', false).text('Fix All Missing Titles & Descriptions');
        var summaryHtml = '<strong>' + applied.length + ' pages updated</strong>';
        if (applied.length) {
          summaryHtml += '<ul style="margin:8px 0 0;padding:0 0 0 16px;font-size:12px;">';
          applied.forEach(function(a) { summaryHtml += '<li>' + escHtml(a.title) + (a.seo_title ? ' — Title: "' + escHtml(a.seo_title.substring(0, 40)) + '..."' : '') + '</li>'; });
          summaryHtml += '</ul>';
        }
        if (failed.length) summaryHtml += '<div style="color:#dc2626;margin-top:6px;font-size:12px;">' + failed.length + ' failed: ' + failed.map(function(f){ return escHtml(f.title); }).join(', ') + '</div>';

        $('#siloq-fix-all-summary')
          .html(summaryHtml)
          .css({'background': failed.length ? '#fef2f2' : '#f0fdf4', 'color': failed.length ? '#991b1b' : '#166534', 'border': '1px solid ' + (failed.length ? '#fca5a5' : '#86efac'), 'border-radius': '8px', 'padding': '12px 16px'})
          .show();
        if (!failed.length) $('#siloq-fix-all-bar').hide();
        return;
      }

      var postId = postIds.shift();
      done++;
      var pct = Math.round((done / total) * 100);
      $('#siloq-fix-all-pbar').css('width', pct + '%');
      $('#siloq-fix-all-msg').text('Applying to ' + (pageTitles[postId] || 'Page ' + postId) + '... (' + done + ' of ' + total + ')');

      $.post(cfg.ajaxUrl, { action: 'siloq_fix_all_seo', nonce: cfg.nonce, post_id: postId }, function(res) {
        if (res.success) {
          applied.push({ title: res.data.title || ('Page ' + postId), seo_title: res.data.applied && res.data.applied.title });
        } else {
          failed.push({ title: pageTitles[postId] || ('Page ' + postId) });
        }
        setTimeout(applyNext, 600);
      }).fail(function() {
        failed.push({ title: pageTitles[postId] || ('Page ' + postId) });
        setTimeout(applyNext, 600);
      });
    }
    applyNext();
  });

  /* ─── Roadmap Checkbox Persistence ───────────── */
  function initRoadmap() {
    $(document).on('change', '.siloq-roadmap__item input[type="checkbox"]', function () {
      var $item = $(this).closest('.siloq-roadmap__item');
      var key = $(this).data('key');
      var checked = $(this).is(':checked');

      $item.toggleClass('is-done', checked);

      // Save to server
      $.post(cfg.ajaxUrl, {
        action: 'siloq_save_roadmap_progress',
        nonce: cfg.nonce,
        key: key,
        checked: checked ? 1 : 0
      });
    });
  }

  function renderRoadmap(roadmap) {
    var saved = window.siloqRoadmapProgress || {};
    var months = ['month1', 'month2', 'month3'];
    var labels = ['Month 1', 'Month 2', 'Month 3'];
    var html = '<div class="siloq-roadmap">';

    months.forEach(function (m, idx) {
      var items = roadmap[m] || [];
      html += '<div class="siloq-roadmap__col">'
        + '<h4 class="siloq-roadmap__col-title">' + labels[idx] + '</h4>';
      items.forEach(function (item, i) {
        var k = m + '_' + i;
        var done = saved[k] ? true : false;
        html += '<div class="siloq-roadmap__item' + (done ? ' is-done' : '') + '">'
          + '<input type="checkbox" data-key="' + k + '"' + (done ? ' checked' : '') + '>'
          + '<span>' + escHtml(item) + '</span></div>';
      });
      html += '</div>';
    });
    html += '</div>';
    $('#siloq-roadmap-content').html(html);
  }

  /* ─── Pages Tab ─────────────────────────────────── */
  var pagesLoaded = false;
  var pagesOffset = 0;
  var pagesFilter = 'all';

  function initPagesTab() {
    // Auto-load pages when tab is clicked
    $(document).on('click', '.siloq-tab-btn[aria-controls="siloq-tab-pages"]', function () {
      if (!pagesLoaded) {
        loadPages(false);
      }
    });

    // If pages tab is active on load (via hash), load immediately
    if ($('#siloq-tab-pages').hasClass('active') || window.location.hash === '#siloq-tab-pages') {
      loadPages(false);
    }

    // Filter pills
    $(document).on('click', '.siloq-filter-pill', function () {
      $('.siloq-filter-pill').removeClass('is-active');
      $(this).addClass('is-active');
      pagesFilter = $(this).data('filter');
      pagesOffset = 0;
      pagesLoaded = false;
      loadPages(false);
    });

    // Search (client-side filter)
    var searchTimer;
    $(document).on('keyup', '#siloq-pages-search', function () {
      var query = $(this).val().toLowerCase();
      clearTimeout(searchTimer);
      searchTimer = setTimeout(function () {
        $('.siloq-page-card').each(function () {
          var title = $(this).data('title') || '';
          $(this).toggle(title.toLowerCase().indexOf(query) !== -1);
        });
      }, 200);
    });

    // Load More
    $(document).on('click', '#siloq-pages-load-more button', function () {
      loadPages(true);
    });

    // Sync All — paginated batch loop so large sites (700+ pages) don't timeout
    $(document).on('click', '#siloq-pages-sync-all', function () {
      var $btn = $(this);
      var totalSynced = 0;
      var batchOffset = 0;
      var grandTotal  = 0;

      function runBatch(offset) {
        $btn.prop('disabled', true).html('<span class="siloq-spinner"></span> Syncing ' + (grandTotal ? totalSynced + '/' + grandTotal : '...') + ' pages');

        $.post(cfg.ajaxUrl, {
          action: 'siloq_sync_all_pages',
          nonce:  cfg.nonce,
          offset: offset
        }, function (resp) {
          // Use resp.data regardless of resp.success — a batch may have API errors
          // for some pages but still return has_more for the next batch.
          var d = resp.data || {};
          totalSynced += (d.synced || 0);
          if (d.total) grandTotal = d.total;

          if (d.has_more && d.next_offset !== undefined) {
            // More batches to process — keep going
            runBatch(d.next_offset);
          } else {
            // All done (or no data returned at all)
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Sync All');
            pagesOffset = 0;
            pagesLoaded = false;
            loadPages(false);
          }
        }).fail(function () {
          // Network/PHP fatal — stop and show what synced so far
          $btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Sync All (' + totalSynced + ' synced)');
          pagesOffset = 0;
          pagesLoaded = false;
          loadPages(false);
        });
      }

      runBatch(0);
    });

    // View Issues toggle
    $(document).on('click', '.siloq-view-issues-btn', function () {
      var $list = $(this).closest('.siloq-page-card').find('.siloq-page-card__issue-list');
      $list.toggleClass('is-open');
      $(this).text($list.hasClass('is-open') ? 'Hide Issues' : 'View Issues');
    });

    // Schema button on page cards (Pages tab)
    $(document).on('click', '.siloq-page-schema-btn', function () {
      var $btn = $(this);
      var postId = $btn.data('post-id');
      var $feedback = $('.siloq-page-schema-feedback-' + postId);
      $btn.prop('disabled', true).text('Generating...');
      $feedback.hide().removeClass('siloq-schema-fb--ok siloq-schema-fb--err');
      $.post(cfg.ajaxUrl, {
        action: 'siloq_generate_schema',
        post_id: postId,
        nonce: cfg.nonce
      }, function (res) {
        if (res.success) {
          $btn.text('✅ Schema').prop('disabled', false);
          var warn = res.data && res.data.profile_warnings && res.data.profile_warnings.length
            ? '⚠️ ' + res.data.profile_warnings[0]
            : '✅ Schema generated — apply it in the editor or Schema tab.';
          $feedback.text(warn)
            .css({'background': warn.indexOf('⚠️') === 0 ? '#fef3c7' : '#f0fdf4',
                  'color': warn.indexOf('⚠️') === 0 ? '#92400e' : '#166534',
                  'border': '1px solid ' + (warn.indexOf('⚠️') === 0 ? '#fcd34d' : '#86efac')})
            .show();
        } else {
          var msg = (res.data && res.data.message) ? res.data.message : 'Schema generation failed.';
          $btn.text('⚡ Schema').prop('disabled', false);
          $feedback.text('⚠️ ' + msg)
            .css({'background': '#fef2f2', 'color': '#991b1b', 'border': '1px solid #fca5a5'})
            .show();
        }
        setTimeout(function () { $feedback.hide(); }, 6000);
      }).fail(function (xhr) {
        $btn.text('⚡ Schema').prop('disabled', false);
        $feedback.text('⚠️ Server error (HTTP ' + xhr.status + '). Check WP error log.')
          .css({'background': '#fef2f2', 'color': '#991b1b', 'border': '1px solid #fca5a5'})
          .show();
        setTimeout(function () { $feedback.hide(); }, 6000);
      });
    });

    // Role dropdown change
    $(document).on('change', '.siloq-role-select', function () {
      var $sel = $(this);
      var pageId = $sel.data('page-id');
      var role = $sel.val();
      $sel.prop('disabled', true);
      $.post(cfg.ajaxUrl, {
        action: 'siloq_set_page_role',
        nonce: cfg.nonce,
        page_id: pageId,
        role: role
      }, function (res) {
        $sel.prop('disabled', false);
        if (res.success) {
          // Update the badge on the card
          var $card = $sel.closest('.siloq-page-card');
          var displayType = role || $card.data('type');
          $card.data('type', displayType);
          var $badge = $card.find('.siloq-badge').first();
          $badge.attr('class', 'siloq-badge siloq-badge--' + (displayType || 'gray'))
                .text(displayType ? displayType.toUpperCase() : 'AUTO');
        } else {
          alert(res.data && res.data.message ? res.data.message : 'Failed to update role.');
        }
      }).fail(function () {
        $sel.prop('disabled', false);
      });
    });
  }

  function loadPages(append) {
    var $grid = $('#siloq-pages-grid');
    var $loadMore = $('#siloq-pages-load-more');

    if (!append) {
      pagesOffset = 0;
      $grid.html('<div class="siloq-pages-loading"><span class="siloq-spinner"></span><span>Loading pages...</span></div>');
      $loadMore.hide();
    }

    $.post(cfg.ajaxUrl, {
      action: 'siloq_get_pages_list',
      nonce: cfg.nonce,
      offset: pagesOffset,
      filter: pagesFilter
    }, function (resp) {
      if (!resp.success) {
        if (!append) {
          $grid.html('<div class="siloq-pages-empty"><div class="siloq-pages-empty__icon">&#9888;</div><p class="siloq-pages-empty__title">Failed to load pages</p></div>');
        }
        return;
      }

      var pages = resp.data.pages;
      pagesOffset = resp.data.offset;
      pagesLoaded = true;

      if (!append) $grid.empty();

      if (pages.length === 0 && !append) {
        $grid.html(
          '<div class="siloq-pages-empty">'
          + '<div class="siloq-pages-empty__icon">&#128196;</div>'
          + '<p class="siloq-pages-empty__title">No pages synced yet</p>'
          + '<p class="siloq-pages-empty__desc">Click <strong>Sync All</strong> to get started.</p>'
          + '</div>'
        );
        $('#siloq-pages-count').text('0 pages synced');
        $loadMore.hide();
        return;
      }

      pages.forEach(function (page) {
        $grid.append(renderPageCard(page));
      });

      var total = $grid.find('.siloq-page-card').length;
      var orphanCount = $grid.find('.siloq-page-card[data-type="orphan"]').length;
      $('#siloq-pages-count').text(total + ' page' + (total !== 1 ? 's' : '') + ' synced');

      // Show orphan hint banner
      $('#siloq-orphan-banner').remove();
      if (orphanCount > 0) {
        $grid.before('<div id="siloq-orphan-banner" class="siloq-orphan-banner">' + orphanCount + ' page' + (orphanCount !== 1 ? 's need' : ' needs') + ' content structure assignment. Open each page in Elementor and run <strong>Analyze</strong> to get started.</div>');
      }

      $loadMore.toggle(pages.length >= 20);
    }).fail(function () {
      if (!append) {
        $grid.html('<div class="siloq-pages-empty"><div class="siloq-pages-empty__icon">&#9888;</div><p class="siloq-pages-empty__title">Failed to load pages</p></div>');
      }
    });
  }

  function renderPageCard(page) {
    var scoreColor;
    if (page.score >= 90) scoreColor = '#14b8a6';
    else if (page.score >= 75) scoreColor = '#22c55e';
    else if (page.score >= 50) scoreColor = '#f59e0b';
    else scoreColor = '#ef4444';

    var r = 18;
    var circ = 2 * Math.PI * r;
    var filled = (page.score / 100) * circ;

    var typeBadgeClass = 'gray';
    if (page.page_type === 'apex_hub') typeBadgeClass = 'apex_hub';
    else if (page.page_type === 'hub') typeBadgeClass = 'hub';
    else if (page.page_type === 'spoke') typeBadgeClass = 'spoke';
    else if (page.page_type === 'supporting') typeBadgeClass = 'supporting';
    else if (page.page_type === 'orphan') typeBadgeClass = 'orphan';
    else if (page.page_type === 'pending') typeBadgeClass = 'pending';

    // Top 3 issues as pills
    var pillsHtml = '';
    var issues = page.issues || [];
    var topIssues = issues.slice(0, 3);
    topIssues.forEach(function (iss) {
      var sev = (iss.severity || iss.level || 'opportunity').toLowerCase();
      if (sev === 'high' || sev === 'error') sev = 'critical';
      else if (sev === 'medium' || sev === 'warning') sev = 'important';
      else sev = 'opportunity';
      var label = iss.title || iss.description || iss.message || '';
      if (label.length > 30) label = label.substring(0, 28) + '...';
      pillsHtml += '<span class="siloq-issue-pill siloq-issue-pill--' + sev + '">' + escHtml(label) + '</span>';
    });

    // Full issue list (expanded view)
    var issueListHtml = '';
    issues.forEach(function (iss) {
      var sev = (iss.severity || iss.level || 'opportunity').toLowerCase();
      var icon = '&#128309;'; // blue
      if (sev === 'high' || sev === 'error' || sev === 'critical') icon = '&#128308;';
      else if (sev === 'medium' || sev === 'warning' || sev === 'important') icon = '&#128993;';
      var desc = iss.description || iss.message || iss.title || '';
      issueListHtml += '<div class="siloq-issue-item">'
        + '<span class="siloq-issue-icon">' + icon + '</span>'
        + '<span>' + escHtml(desc) + '</span>'
        + '<a href="' + escAttr(page.elementor_url) + '" class="siloq-btn siloq-btn--sm siloq-btn--outline siloq-issue-item__fix">Fix in Editor</a>'
        + '</div>';
    });

    // Role dropdown
    var currentRole = page.page_role || '';
    var roleOpts = [
      {v: '', l: 'Auto'},
      {v: 'apex_hub', l: 'Apex Hub'},
      {v: 'hub', l: 'Hub'},
      {v: 'spoke', l: 'Spoke'},
      {v: 'supporting', l: 'Supporting'},
      {v: 'unclassified', l: 'Unclassified'}
    ];
    var roleSelect = '<select class="siloq-role-select" data-page-id="' + page.id + '" style="font-size:12px;padding:2px 4px;margin-left:8px;">';
    roleOpts.forEach(function (o) {
      roleSelect += '<option value="' + o.v + '"' + (currentRole === o.v ? ' selected' : '') + '>' + o.l + '</option>';
    });
    roleSelect += '</select>';

    return '<div class="siloq-page-card" data-title="' + escAttr(page.title) + '" data-type="' + escAttr(page.page_type) + '">'
      + '<div class="siloq-page-card__top">'
      + '<div class="siloq-page-card__score-ring">'
      + '<svg viewBox="0 0 44 44"><circle class="ring-bg" cx="22" cy="22" r="' + r + '"/>'
      + '<circle class="ring-fg" cx="22" cy="22" r="' + r + '" stroke="' + scoreColor + '" stroke-dasharray="' + filled + ' ' + circ + '"/></svg>'
      + '<span class="siloq-page-card__score-num">' + page.score + '</span>'
      + '</div>'
      + '<div class="siloq-page-card__info">'
      + '<a href="' + escAttr(page.edit_url) + '" class="siloq-page-card__title">' + escHtml(page.title) + '</a>'
      + '<div class="siloq-page-card__meta">'
      + '<span class="siloq-badge siloq-badge--' + typeBadgeClass + '"' + (page.page_type === 'orphan' ? ' title="No content structure assigned. Open in Elementor and run Analyze."' : '') + (page.page_type === 'pending' ? ' title="Open in Elementor and run Analyze to get recommendations."' : '') + '>' + (page.page_type === 'pending' ? 'NOT ANALYZED' : (page.page_type === 'apex_hub' ? 'APEX HUB' : escHtml(page.page_type.toUpperCase()))) + '</span>'
      + roleSelect
      + (page.primary_keyword ? '<span class="siloq-page-card__keyword">' + escHtml(page.primary_keyword) + '</span>' : '')
      + '</div>'
      + (pillsHtml ? '<div class="siloq-page-card__issues-pills">' + pillsHtml + '</div>' : '')
      + '</div>'
      + '</div>'
      + '<div class="siloq-page-card__actions">'
      + '<a href="' + escAttr(page.elementor_url) + '" class="siloq-btn siloq-btn--sm siloq-btn--primary">Analyze</a>'
      + (issues.length > 0 ? '<button type="button" class="siloq-btn siloq-btn--sm siloq-btn--outline siloq-view-issues-btn">View Issues</button>' : '')
      + '<button type="button" class="siloq-btn siloq-btn--sm siloq-btn--outline siloq-page-schema-btn" data-post-id="' + page.id + '" title="Generate schema markup for this page">'
      + (page.has_schema ? '✅ Schema' : '⚡ Schema') + '</button>'
      + '</div>'
      + (issues.length > 0 ? '<div class="siloq-page-card__issue-list">' + issueListHtml + '</div>' : '')
      + '<div class="siloq-page-schema-feedback-' + page.id + '" style="display:none;font-size:11px;padding:4px 8px;border-radius:4px;margin-top:4px;"></div>'
      + '</div>';
  }

  /* ─── Utility ────────────────────────────────── */
  function escHtml(str) {
    if (!str) return '';
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
  }

  function escAttr(str) {
    if (!str) return '';
    return str.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  /* ─── Schema Tab ──────────────────────────────── */
  var schemaLoaded = false;
  var schemaGraphLoaded = false;

  function initSchemaTab() {
    // Lazy-load schema data when tab is clicked
    $(document).on('click', '.siloq-tab-btn[aria-controls="siloq-tab-schema"]', function () {
      if (!schemaLoaded) loadSchemaStatus();
      if (!schemaGraphLoaded) loadSchemaGraph();
      animateEntityRing();
    });

    // If schema tab is active on load (via hash)
    if ($('#siloq-tab-schema').hasClass('active') || window.location.hash === '#siloq-tab-schema') {
      loadSchemaStatus();
      loadSchemaGraph();
      animateEntityRing();
    }

    // Refresh button
    $(document).on('click', '#siloq-schema-refresh', function () {
      schemaLoaded = false;
      loadSchemaStatus();
    });

    // Apply Schema to All — sequential bulk processor
    $(document).on('click', '#siloq-schema-apply-all-btn', function () {
      var $btn      = $(this);
      var $progress = $('#siloq-schema-bulk-progress');
      var $bar      = $('#siloq-schema-bulk-bar');
      var $msg      = $('#siloq-schema-bulk-msg');
      var $summary  = $('#siloq-schema-bulk-summary');

      // Collect all page IDs that have no schema (class siloq-schema-none-badge or data attribute)
      var pageIds = [];
      $('#siloq-schema-pages-list tr[data-post-id]').each(function () {
        var hasSchema = $(this).data('has-schema');
        if (!hasSchema || hasSchema === 'false' || hasSchema === false || hasSchema === '0') {
          pageIds.push($(this).data('post-id'));
        }
      });
      // Fallback: if no schema list rendered yet, ask user to refresh first
      if (!pageIds.length) {
        $summary.text('No pages without schema found. Click Refresh first to load the list, then try again.')
                .css({'background':'#fef3c7','color':'#92400e','border':'1px solid #fcd34d'}).show();
        return;
      }

      $btn.prop('disabled', true).text('Running...');
      $progress.show();
      $summary.hide();
      var applied = [], failed = [], total = pageIds.length, done = 0;

      function applyNext() {
        if (!pageIds.length) {
          // All done
          $progress.hide();
          $bar.css('width', '100%');
          $btn.prop('disabled', false).text('⚡ Apply Schema to All');
          var summaryHtml = '<strong>✅ Done!</strong> Applied: ' + applied.length + ', Failed: ' + failed.length + ' of ' + total + '<br>';
          if (failed.length) {
            summaryHtml += '<span style="color:#991b1b">Failed: ' + failed.map(function(f){return f.title;}).join(', ') + '</span>';
          }
          $summary.html(summaryHtml)
                  .css({'background': failed.length ? '#fef2f2' : '#f0fdf4',
                        'color': failed.length ? '#991b1b' : '#166534',
                        'border': '1px solid ' + (failed.length ? '#fca5a5' : '#86efac')})
                  .show();
          // Reload schema list to show updated status
          schemaLoaded = false;
          loadSchemaStatus();
          return;
        }

        var postId = pageIds.shift();
        done++;
        var pct = Math.round((done / total) * 100);
        $bar.css('width', pct + '%');
        $msg.text('Applying schema to page ' + done + ' of ' + total + '...');

        $.post(cfg.ajaxUrl, {
          action:  'siloq_bulk_apply_schema',
          nonce:   cfg.nonce,
          post_id: postId
        }, function (res) {
          if (res.success) {
            applied.push({id: postId, title: res.data.title});
          } else {
            failed.push({id: postId, title: res.data.title || ('Page ' + postId), error: res.data.message});
          }
          // 1-second delay between pages to avoid server overload
          setTimeout(applyNext, 1000);
        }).fail(function () {
          failed.push({id: postId, title: 'Page ' + postId, error: 'Network error'});
          setTimeout(applyNext, 1000);
        });
      }

      applyNext();
    });

    // Repair schema panels (fix missing _elementor_edit_mode on existing pages)
    $(document).on('click', '#siloq-schema-repair-btn', function () {
      var $btn = $(this);
      var $msg = $('#siloq-schema-repair-msg');
      $btn.prop('disabled', true).text('Repairing...');
      $msg.hide();
      $.post(cfg.ajaxUrl, {
        action: 'siloq_repair_elementor_meta',
        nonce: cfg.nonce
      }, function (res) {
        $btn.prop('disabled', false).text('🔧 Repair Schema Panels');
        var ok = res.success;
        var text = (res.data && res.data.message) ? res.data.message : (ok ? 'Repair complete.' : 'Repair failed.');
        $msg.text(text)
            .css({
              'background': ok ? '#f0fdf4' : '#fef2f2',
              'color': ok ? '#166534' : '#991b1b',
              'border': '1px solid ' + (ok ? '#86efac' : '#fca5a5')
            })
            .show();
        setTimeout(function () { $msg.hide(); }, 8000);
      }).fail(function () {
        $btn.prop('disabled', false).text('🔧 Repair Schema Panels');
        $msg.text('Request failed — check WP error log.')
            .css({'background': '#fef2f2', 'color': '#991b1b', 'border': '1px solid #fca5a5'})
            .show();
        setTimeout(function () { $msg.hide(); }, 6000);
      });
    });

    // Generate schema button
    $(document).on('click', '.siloq-schema-generate-btn', function () {
      var $btn = $(this);
      var $row = $btn.closest('.siloq-schema-page-row');
      var postId = $btn.data('post-id');
      var nonce = cfg.nonce || (window.siloqAjax && window.siloqAjax.nonce) || '';
      var ajaxUrl = cfg.ajaxUrl || (window.siloqAjax && window.siloqAjax.ajaxurl) || ajaxurl || '';
      $btn.prop('disabled', true).text('Generating...');
      // Clear any previous inline error for this row
      $row.find('.siloq-schema-inline-error').remove();
      $.post(ajaxUrl, {
        action: 'siloq_generate_schema',
        post_id: postId,
        nonce: nonce
      }, function (res) {
        if (res.success) {
          $btn.text('✓ Generated').addClass('siloq-btn--success');
          // Show profile warnings inline (non-blocking)
          if (res.data && res.data.profile_warnings && res.data.profile_warnings.length) {
            var warnHtml = '<div class="siloq-schema-inline-error" style="font-size:11px;color:#92400e;background:#fef3c7;border:1px solid #fcd34d;border-radius:4px;padding:4px 8px;margin-top:4px;">'
              + '⚠️ ' + escHtml(res.data.profile_warnings[0]) + '</div>';
            $btn.after(warnHtml);
          }
          schemaLoaded = false;
          setTimeout(function () { loadSchemaStatus(); }, 800);
        } else {
          var msg = (res.data && res.data.message) ? res.data.message : 'Schema generation failed.';
          $btn.text('Error').addClass('siloq-btn--danger');
          // Show error inline in the row, not as an alert
          var errHtml = '<div class="siloq-schema-inline-error" style="font-size:11px;color:#991b1b;background:#fef2f2;border:1px solid #fca5a5;border-radius:4px;padding:4px 8px;margin-top:4px;">'
            + '⚠️ ' + escHtml(msg);
          if (res.data && res.data.fix_url) {
            errHtml += ' <a href="' + escAttr(res.data.fix_url) + '" target="_blank" style="color:#dc2626;font-weight:600;">Fix now →</a>';
          }
          errHtml += '</div>';
          $btn.after(errHtml);
        }
        setTimeout(function () {
          $btn.prop('disabled', false).text('Generate Schema').removeClass('siloq-btn--success siloq-btn--danger');
        }, 3000);
      }).fail(function (xhr) {
        var rawMsg = 'Server error (HTTP ' + xhr.status + '). Check WP error log for details.';
        $btn.prop('disabled', false).text('Generate Schema');
        var errHtml = '<div class="siloq-schema-inline-error" style="font-size:11px;color:#991b1b;background:#fef2f2;border:1px solid #fca5a5;border-radius:4px;padding:4px 8px;margin-top:4px;">'
          + '⚠️ ' + escHtml(rawMsg) + '</div>';
        $btn.after(errHtml);
      });
    });

    // View schema toggle
    $(document).on('click', '.siloq-schema-view-btn', function () {
      var postId = $(this).data('post-id');
      var $preview = $('#siloq-schema-json-' + postId);
      $preview.toggleClass('active');
      $(this).text($preview.hasClass('active') ? 'Hide Schema' : 'View Schema');
    });
  }

  function animateEntityRing() {
    var $fg = $('.siloq-entity-ring-fg');
    if (!$fg.length) return;
    var score = parseInt($fg.data('score'), 10) || 0;
    var radius = parseFloat($fg.data('radius')) || 48;
    var circumference = 2 * Math.PI * radius;
    $fg.css({ 'stroke-dasharray': '0 ' + circumference, 'stroke-dashoffset': '0' });
    setTimeout(function () {
      var filled = (score / 100) * circumference;
      $fg.css('stroke-dasharray', filled + ' ' + circumference);
    }, 200);
    var $val = $('.siloq-entity-ring-value');
    if ($val.length) {
      $({ v: 0 }).animate({ v: score }, {
        duration: 800,
        easing: 'swing',
        step: function () { $val.text(Math.round(this.v)); },
        complete: function () { $val.text(score); }
      });
    }
  }

  function loadSchemaStatus() {
    var $list = $('#siloq-schema-pages-list');
    $list.html('<div class="siloq-pages-loading"><span class="siloq-spinner"></span><span>Loading schema status...</span></div>');
    $.post(cfg.ajaxUrl || (window.siloqAjax && window.siloqAjax.ajaxurl) || ajaxurl, {
      action: 'siloq_get_schema_status',
      nonce: (window.siloqAjax && window.siloqAjax.nonce) || cfg.nonce
    }, function (res) {
      schemaLoaded = true;
      if (!res.success || !res.data || !res.data.pages) {
        $list.html('<div class="siloq-empty"><p>No synced pages found. Sync pages first from the Pages tab.</p></div>');
        return;
      }
      renderSchemaPages(res.data.pages);
    }).fail(function () {
      $list.html('<div class="siloq-empty"><p>Failed to load schema status.</p></div>');
    });
  }

  function renderSchemaPages(pages) {
    var $list = $('#siloq-schema-pages-list');
    if (!pages.length) {
      $list.html('<div class="siloq-empty"><p>No synced pages found. Sync pages first from the Pages tab.</p></div>');
      return;
    }
    var html = '';
    for (var i = 0; i < pages.length; i++) {
      var p = pages[i];
      var statusLabel = '';
      if (p.status === 'applied') statusLabel = '<span style="color:var(--siloq-success)">Applied &#10003;</span>';
      else if (p.status === 'partial') statusLabel = '<span style="color:var(--siloq-warning)">Partial &#9888;</span>';
      else statusLabel = '<span style="color:var(--siloq-danger)">None &#10007;</span>';

      var appliedBadges = '';
      if (p.applied_types && p.applied_types.length) {
        for (var a = 0; a < p.applied_types.length; a++) {
          appliedBadges += '<span class="siloq-schema-badge siloq-schema-badge--applied">' + escHtml(p.applied_types[a]) + '</span>';
        }
      }

      var recBadges = '';
      if (p.recommended_types && p.recommended_types.length) {
        for (var r = 0; r < p.recommended_types.length; r++) {
          if (!p.applied_types || p.applied_types.indexOf(p.recommended_types[r]) === -1) {
            recBadges += '<span class="siloq-schema-badge siloq-schema-badge--recommended">' + escHtml(p.recommended_types[r]) + '</span>';
          }
        }
      }

      var hasSchemaData = (p.applied_types && p.applied_types.length) || p.status === 'applied';
      html += '<div class="siloq-schema-page-row" data-post-id="' + p.id + '" data-has-schema="' + (hasSchemaData ? 'true' : 'false') + '">';
      html += '<div class="siloq-schema-page-row__title"><a href="' + escAttr(p.edit_url || '#') + '" target="_blank">' + escHtml(p.title) + '</a></div>';
      html += '<div class="siloq-schema-page-row__types">' + appliedBadges + recBadges + '</div>';
      html += '<div class="siloq-schema-page-row__status">' + statusLabel + '</div>';
      html += '<div class="siloq-schema-page-row__actions">';
      html += '<button type="button" class="siloq-btn siloq-btn--outline siloq-btn--xs siloq-schema-generate-btn" data-post-id="' + p.id + '">Generate Schema</button>';
      if (p.schema_json) {
        html += ' <button type="button" class="siloq-btn siloq-btn--outline siloq-btn--xs siloq-schema-view-btn" data-post-id="' + p.id + '">View Schema</button>';
      }
      html += '</div>';
      if (p.schema_json) {
        html += '<pre id="siloq-schema-json-' + p.id + '" class="siloq-schema-json-preview">' + escHtml(p.schema_json) + '</pre>';
      }
      html += '</div>';
    }
    $list.html(html);
  }

  function loadSchemaGraph() {
    var siteId = (window.siloqDash && window.siloqDash.siteId) || '';
    if (!siteId) {
      schemaGraphLoaded = true;
      return; // placeholder already shows message
    }
    var $content = $('#siloq-schema-graph-content');
    $content.html('<div class="siloq-pages-loading"><span class="siloq-spinner"></span><span>Loading schema graph...</span></div>');
    $.post(cfg.ajaxUrl || (window.siloqAjax && window.siloqAjax.ajaxurl) || ajaxurl, {
      action: 'siloq_get_schema_graph',
      nonce: (window.siloqAjax && window.siloqAjax.nonce) || cfg.nonce
    }, function (res) {
      schemaGraphLoaded = true;
      if (!res.success) {
        var msg = (res.data && res.data.message) ? res.data.message : 'Schema graph available after site analysis.';
        $content.html('<div class="siloq-empty"><p>' + escHtml(msg) + '</p></div>');
        return;
      }
      renderSchemaGraph(res.data);
    }).fail(function () {
      schemaGraphLoaded = true;
      $content.html('<div class="siloq-empty"><p>Schema graph available after site analysis.</p></div>');
    });
  }

  function renderSchemaGraph(data) {
    var $content = $('#siloq-schema-graph-content');
    var entities = data.entities || data.nodes || [];
    if (!entities.length) {
      $content.html('<div class="siloq-empty"><p>No schema entities found yet. Generate schema for pages to build the graph.</p></div>');
      return;
    }
    var html = '';
    for (var i = 0; i < entities.length; i++) {
      var e = entities[i];
      var connections = '';
      if (e.connections && e.connections.length) {
        connections = e.connections.map(function (c) { return escHtml(c); }).join(', ');
      } else if (e.related && e.related.length) {
        connections = e.related.map(function (c) { return escHtml(c); }).join(', ');
      }
      html += '<div class="siloq-schema-graph-entity">';
      html += '<span class="siloq-schema-graph-entity__type">' + escHtml(e.type || e['@type'] || 'Entity') + '</span>';
      html += '<span class="siloq-schema-graph-entity__connections">' + (connections || 'No connections') + '</span>';
      html += '</div>';
    }
    $content.html(html);
  }

  /* ─── Init ───────────────────────────────────── */
  $(document).ready(function () {
    if (!$('.siloq-dash-wrap').length) return;
    initTabs();
    initScoreRing();
    initAccordions();
    initPlanGeneration();
    initRoadmap();
    initPagesTab();
    initSchemaTab();
    initRedirectsTab();
  });

  /* ─── Redirects Tab ──────────────────────────── */
  function initRedirectsTab() {

    var redirectsLoaded = false;
    var allRedirects = []; // cached for filtering/search

    // Lazy-load when tab is clicked
    $(document).on('click', '.siloq-tab-btn[aria-controls="siloq-tab-redirects"]', function () {
      if (!redirectsLoaded) loadRedirects();
    });

    // Refresh button
    $(document).on('click', '#siloq-redir-refresh-btn', function () {
      loadRedirects();
    });

    // ── Load & render redirect list ──────────────────────────────────────

    function loadRedirects() {
      var $list = $('#siloq-redir-list');
      $list.html('<div class="siloq-pages-loading"><span class="siloq-spinner"></span><span>Loading redirects...</span></div>');
      $.post(cfg.ajaxUrl, { action: 'siloq_get_redirects', nonce: cfg.nonce }, function (res) {
        redirectsLoaded = true;
        if (!res.success) {
          $list.html('<p style="font-size:13px;color:#991b1b;padding:12px;">Failed to load redirects. The redirects table may not exist yet — try deactivating and reactivating the Siloq plugin.</p>');
          return;
        }
        allRedirects = res.data.redirects || [];
        updateRedirectCounts();
        renderRedirectList(allRedirects);
      }).fail(function () {
        $list.html('<p style="font-size:13px;color:#991b1b;padding:12px;">Request failed — check your connection and try again.</p>');
      });
    }

    function updateRedirectCounts() {
      var enabled  = allRedirects.filter(function(r){ return r.enabled == 1; }).length;
      var disabled = allRedirects.length - enabled;
      $('#siloq-redir-count-all').text(allRedirects.length + ' redirect' + (allRedirects.length !== 1 ? 's' : ''));
      $('#siloq-redir-count-pill-all').text(allRedirects.length);
      $('#siloq-redir-count-pill-enabled').text(enabled);
      $('#siloq-redir-count-pill-disabled').text(disabled);
    }

    function renderRedirectList(rows) {
      var $list = $('#siloq-redir-list');
      if (!rows.length) {
        $list.html('<div style="text-align:center;padding:32px 16px;color:#9ca3af;">'
          + '<div style="font-size:28px;margin-bottom:8px;">🔀</div>'
          + '<p style="font-size:13px;font-weight:600;color:#6b7280;margin:0 0 4px;">No redirects yet</p>'
          + '<p style="font-size:12px;color:#9ca3af;margin:0;">Add your first redirect above to start managing URL changes.</p>'
          + '</div>');
        return;
      }
      var html = '<table style="width:100%;border-collapse:collapse;font-size:12px;">'
        + '<thead><tr style="border-bottom:2px solid #e5e7eb;">'
        + '<th style="text-align:left;padding:8px 10px;font-weight:700;color:#374151;">Source URL</th>'
        + '<th style="text-align:left;padding:8px 10px;font-weight:700;color:#374151;">Target URL</th>'
        + '<th style="padding:8px 10px;font-weight:700;text-align:center;color:#374151;">Hits</th>'
        + '<th style="padding:8px 10px;font-weight:700;text-align:center;color:#374151;">Type</th>'
        + '<th style="padding:8px 10px;font-weight:700;text-align:center;color:#374151;">Enabled</th>'
        + '<th style="padding:8px 10px;"></th>'
        + '</tr></thead><tbody>';
      rows.forEach(function (r) {
        var isEnabled = r.enabled == 1;
        var rowOpacity = isEnabled ? '1' : '0.55';
        var codeColor = r.status_code == 301 ? '#166534' : (r.status_code == 302 || r.status_code == 307 ? '#92400e' : '#991b1b');
        var codeBg    = r.status_code == 301 ? '#dcfce7' : (r.status_code == 302 || r.status_code == 307 ? '#fef3c7' : '#fef2f2');
        html += '<tr style="border-bottom:1px solid #f3f4f6;opacity:' + rowOpacity + ';" data-redir-id="' + r.id + '">'
          + '<td style="padding:8px 10px;color:#1d4ed8;word-break:break-all;font-family:monospace;font-size:11px;">' + escHtml(r.source_url) + '</td>'
          + '<td style="padding:8px 10px;color:#4b5563;word-break:break-all;font-family:monospace;font-size:11px;">' + escHtml(r.target_url) + '</td>'
          + '<td style="padding:8px 10px;text-align:center;font-weight:600;color:#6b7280;">' + (r.hits || 0) + '</td>'
          + '<td style="padding:8px 10px;text-align:center;"><span style="background:' + codeBg + ';color:' + codeColor + ';border-radius:4px;padding:2px 8px;font-weight:700;font-size:11px;">' + r.status_code + '</span></td>'
          + '<td style="padding:8px 10px;text-align:center;">'
          + '<label style="position:relative;display:inline-block;width:36px;height:20px;cursor:pointer;">'
          + '<input type="checkbox" class="siloq-redir-toggle" data-id="' + r.id + '" ' + (isEnabled ? 'checked' : '') + ' style="opacity:0;width:0;height:0;">'
          + '<span style="position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:' + (isEnabled ? '#4f46e5' : '#d1d5db') + ';border-radius:20px;transition:0.2s;"></span>'
          + '<span style="position:absolute;height:16px;width:16px;left:' + (isEnabled ? '18px' : '2px') + ';bottom:2px;background:#fff;border-radius:50%;transition:0.2s;"></span>'
          + '</label>'
          + '</td>'
          + '<td style="padding:8px 10px;text-align:right;">'
          + '<button type="button" class="siloq-redir-delete-btn" data-id="' + r.id + '" style="font-size:11px;color:#dc2626;background:none;border:none;cursor:pointer;padding:4px 8px;" title="Delete redirect">🗑️</button>'
          + '</td></tr>';
      });
      html += '</tbody></table>';
      $list.html(html);
    }

    // ── Filter pills ─────────────────────────────────────────────────────

    $(document).on('click', '.siloq-redir-filter', function () {
      $('.siloq-redir-filter').removeClass('siloq-redir-filter--active').css({'background':'#fff','color':'#374151','font-weight':'400'});
      $(this).addClass('siloq-redir-filter--active').css({'background':'#4f46e5','color':'#fff','font-weight':'600'});
      var filter = $(this).data('filter');
      var filtered = allRedirects;
      if (filter === 'enabled')  filtered = allRedirects.filter(function(r){ return r.enabled == 1; });
      if (filter === 'disabled') filtered = allRedirects.filter(function(r){ return r.enabled != 1; });
      renderRedirectList(filtered);
    });

    // ── Search ───────────────────────────────────────────────────────────

    $(document).on('input', '#siloq-redir-search', function () {
      var q = $(this).val().toLowerCase().trim();
      if (!q) { renderRedirectList(allRedirects); return; }
      var filtered = allRedirects.filter(function(r) {
        return (r.source_url && r.source_url.toLowerCase().indexOf(q) !== -1)
            || (r.target_url && r.target_url.toLowerCase().indexOf(q) !== -1);
      });
      renderRedirectList(filtered);
    });

    // ── Toggle redirect enabled/disabled ─────────────────────────────────

    $(document).on('change', '.siloq-redir-toggle', function () {
      var $cb = $(this);
      var id  = $cb.data('id');
      $.post(cfg.ajaxUrl, { action: 'siloq_toggle_redirect', nonce: cfg.nonce, redirect_id: id }, function (res) {
        if (res.success) {
          // Refresh the list to show updated state
          redirectsLoaded = false;
          loadRedirects();
        }
      });
    });

    // ── Delete redirect ──────────────────────────────────────────────────

    $(document).on('click', '.siloq-redir-delete-btn', function () {
      var $btn = $(this);
      var id   = $btn.data('id');
      if (!confirm('Delete this redirect? This cannot be undone.')) return;
      $btn.text('...').prop('disabled', true);
      $.post(cfg.ajaxUrl, { action: 'siloq_delete_redirect', nonce: cfg.nonce, redirect_id: id }, function (res) {
        if (res.success) {
          $btn.closest('tr').fadeOut(300, function () { $(this).remove(); });
          allRedirects = allRedirects.filter(function(r){ return r.id != id; });
          updateRedirectCounts();
        } else {
          $btn.text('🗑️').prop('disabled', false);
          alert(res.data && res.data.message ? res.data.message : 'Delete failed.');
        }
      });
    });

    // ── Add single redirect ──────────────────────────────────────────────

    $(document).on('click', '#siloq-redir-add-btn', function () {
      var $btn  = $(this);
      var from  = $('#siloq-redir-from').val().trim();
      var to    = $('#siloq-redir-to').val().trim();
      var type  = $('#siloq-redir-type').val() || '301';
      var $msg  = $('#siloq-redir-add-msg');

      if (!from || !to) {
        $msg.text('Both Source URL and Target URL are required.').css({'background':'#fef2f2','color':'#991b1b','border':'1px solid #fca5a5'}).show();
        return;
      }

      $btn.prop('disabled', true).text('Adding...');
      $.post(cfg.ajaxUrl, {
        action: 'siloq_add_redirect',
        nonce: cfg.nonce,
        from: from,
        to: to,
        status_code: type
      }, function (res) {
        $btn.prop('disabled', false).text('Add Redirect');
        if (res.success) {
          $('#siloq-redir-from').val('');
          $('#siloq-redir-to').val('');
          $msg.text('✅ ' + (res.data.message || 'Redirect added.')).css({'background':'#f0fdf4','color':'#166534','border':'1px solid #86efac'}).show();
          redirectsLoaded = false;
          loadRedirects();
        } else {
          $msg.text('⚠️ ' + (res.data && res.data.message ? res.data.message : 'Failed.')).css({'background':'#fef2f2','color':'#991b1b','border':'1px solid #fca5a5'}).show();
        }
        setTimeout(function () { $msg.fadeOut(); }, 5000);
      });
    });

  } // end initRedirectsTab

})(jQuery);
