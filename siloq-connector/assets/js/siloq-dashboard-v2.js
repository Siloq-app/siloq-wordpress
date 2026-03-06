/**
 * Siloq Dashboard v2 — Tab switching, score ring, plan AJAX, roadmap persistence, pages tab
 * Version: 1.5.72
 */
(function ($) {
  'use strict';

  var cfg = window.siloqDash || {};

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

  function renderPlanData(data) {
    console.log('Plan data:', data);

    // Architecture tree
    var $archContent = $('#siloq-architecture-content');
    if (data.architecture && data.architecture.length) {
      var html = '<ul class="siloq-tree">';
      data.architecture.forEach(function (node) {
        var cls = 'siloq-tree--' + (node.type || 'spoke');
        var label = node.title;
        var extra = '';
        if (node.type === 'pending') {
          extra = ' <span style="color:#999;font-size:11px;">(not yet analyzed)</span>';
        } else if (node.type === 'orphan') {
          extra = ' <span style="color:#f59e0b;font-size:11px;">(no structure assigned)</span>';
        } else if (node.type === 'hub') {
          extra = ' <span style="color:#0ea5e9;font-size:11px;">HUB</span>';
        } else if (node.type === 'missing') {
          extra = ' <button class="siloq-btn siloq-btn--sm siloq-btn--outline">Create</button>';
        }
        html += '<li class="' + cls + '">' + escHtml(label) + extra + '</li>';
      });
      html += '</ul>';
      $archContent.html(html);
    } else {
      $archContent.html('<p class="siloq-empty-hint">All pages are orphans &mdash; assign page types to build your structure.</p>');
    }

    // Priority actions
    if (data.actions && data.actions.length) {
      var actHtml = '';
      data.actions.forEach(function (act, i) {
        var prioClass = act.priority === 'high' ? 'red' : (act.priority === 'medium' ? 'amber' : 'blue');
        var fixBtn = act.elementor_url
          ? '<a href="' + escAttr(act.elementor_url) + '" class="siloq-btn siloq-btn--sm siloq-btn--primary">Fix in Elementor &rarr;</a>'
          : (act.edit_url ? '<a href="' + escAttr(act.edit_url) + '" class="siloq-btn siloq-btn--sm siloq-btn--primary">Edit Page &rarr;</a>' : '');
        actHtml += '<div class="siloq-action-card">'
          + '<span class="siloq-action-card__number">' + (i + 1) + '</span>'
          + '<div class="siloq-action-card__body">'
          + '<p class="siloq-action-card__headline">' + escHtml(act.headline) + '</p>'
          + (act.detail ? '<p style="color:#666;font-size:13px;margin:4px 0 8px">' + escHtml(act.detail) + '</p>' : '')
          + '<div class="siloq-action-card__meta">'
          + '<span class="siloq-badge siloq-badge--' + prioClass + '">' + escHtml(act.priority) + ' priority</span>'
          + '</div>'
          + (fixBtn ? '<div class="siloq-action-card__actions" style="margin-top:8px">' + fixBtn + '</div>' : '')
          + '</div></div>';
      });
      $('#siloq-actions-content').html(actHtml);
    } else {
      $('#siloq-actions-content').html('<p class="siloq-empty-hint">No priority actions found. Analyze your pages to get recommendations.</p>');
    }

    // Content issues
    if (data.issues) {
      var issueHtml = '';
      ['critical', 'important', 'opportunity'].forEach(function (level) {
        var items = data.issues[level] || [];
        if (!items.length) return;
        issueHtml += '<div class="siloq-issues-group">'
          + '<h4 class="siloq-issues-group__title siloq-issues-group__title--' + level + '">'
          + level.charAt(0).toUpperCase() + level.slice(1) + '</h4>';
        items.forEach(function (iss) {
          var fixLink = iss.elementor_url
            ? '<a href="' + escAttr(iss.elementor_url) + '" class="siloq-btn siloq-btn--sm siloq-btn--outline">Fix It &rarr;</a>'
            : (iss.edit_url ? '<a href="' + escAttr(iss.edit_url) + '" class="siloq-btn siloq-btn--sm siloq-btn--outline">Edit &rarr;</a>' : '');
          issueHtml += '<div class="siloq-issue-row"><span>' + escHtml(iss.title) + ' &mdash; ' + escHtml(iss.issue) + '</span>'
            + fixLink + '</div>';
        });
        issueHtml += '</div>';
      });
      $('#siloq-issues-content').html(issueHtml);
    }

    // Supporting content
    if (data.supporting && data.supporting.length) {
      var supHtml = '';
      data.supporting.forEach(function (s) {
        var bClass = s.type === 'sub-page' ? 'blue' : 'purple';
        supHtml += '<div class="siloq-action-card"><div class="siloq-action-card__body">'
          + '<p class="siloq-action-card__headline">' + escHtml(s.title) + '</p>'
          + (s.detail ? '<p style="color:#666;font-size:13px;margin:4px 0 8px">' + escHtml(s.detail) + '</p>' : '')
          + (s.parent ? '<p style="color:#999;font-size:12px;margin:0 0 8px">Under: ' + escHtml(s.parent) + '</p>' : '')
          + '</div></div>';
      });
      $('#siloq-supporting-content').html(supHtml);
    }

    // Roadmap
    if (data.roadmap) {
      renderRoadmap(data.roadmap);
    }

    // Open the plan accordion sections and set aria
    $('.siloq-plan-section .siloq-accordion').addClass('is-open')
      .find('.siloq-accordion__trigger').attr('aria-expanded', 'true');
  }

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

    // Sync All (reuse existing AJAX action)
    $(document).on('click', '#siloq-pages-sync-all', function () {
      var $btn = $(this);
      $btn.prop('disabled', true).html('<span class="siloq-spinner"></span> Syncing...');
      $.post(cfg.ajaxUrl, {
        action: 'siloq_sync_all_pages',
        nonce: cfg.nonce
      }, function (resp) {
        $btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Sync All');
        if (resp.success) {
          pagesOffset = 0;
          pagesLoaded = false;
          loadPages(false);
        }
      }).fail(function () {
        $btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Sync All');
      });
    });

    // View Issues toggle
    $(document).on('click', '.siloq-view-issues-btn', function () {
      var $list = $(this).closest('.siloq-page-card').find('.siloq-page-card__issue-list');
      $list.toggleClass('is-open');
      $(this).text($list.hasClass('is-open') ? 'Hide Issues' : 'View Issues');
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
      + '</div>'
      + (issues.length > 0 ? '<div class="siloq-page-card__issue-list">' + issueListHtml + '</div>' : '')
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

    // Generate schema button
    $(document).on('click', '.siloq-schema-generate-btn', function () {
      var $btn = $(this);
      var postId = $btn.data('post-id');
      $btn.prop('disabled', true).text('Generating...');
      $.post(cfg.ajaxUrl || (window.siloqAjax && window.siloqAjax.ajaxurl) || ajaxurl, {
        action: 'siloq_generate_schema',
        post_id: postId,
        nonce: (window.siloqAjax && window.siloqAjax.nonce) || cfg.nonce
      }, function (res) {
        if (res.success) {
          $btn.text('Generated!').addClass('siloq-btn--success');
          // Reload schema status
          schemaLoaded = false;
          setTimeout(function () { loadSchemaStatus(); }, 500);
        } else {
          $btn.text('Error').addClass('siloq-btn--danger');
          alert(res.data && res.data.message ? res.data.message : 'Schema generation failed.');
        }
        setTimeout(function () {
          $btn.prop('disabled', false).text('Generate Schema')
            .removeClass('siloq-btn--success siloq-btn--danger');
        }, 3000);
      }).fail(function () {
        $btn.prop('disabled', false).text('Generate Schema');
        alert('Schema request failed — server returned no response. Check that your Siloq API key is valid and try again. If this persists, contact support.');
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

      html += '<div class="siloq-schema-page-row">';
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
  });

})(jQuery);
