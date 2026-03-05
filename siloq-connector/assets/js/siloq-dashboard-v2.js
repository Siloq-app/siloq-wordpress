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
  function initPlanGeneration() {
    $(document).on('click', '.siloq-generate-plan-btn', function (e) {
      e.preventDefault();
      var $btn = $(this);
      $btn.prop('disabled', true).text('Generating...');

      $.post(cfg.ajaxUrl, {
        action: 'siloq_get_plan_data',
        nonce: cfg.nonce
      }, function (resp) {
        if (resp.success && resp.data) {
          renderPlanData(resp.data);
          $btn.text('Plan Generated').addClass('siloq-btn--success');
        } else {
          $btn.prop('disabled', false).text('Generate Your SEO Plan →');
          alert(resp.data && resp.data.message ? resp.data.message : 'Failed to generate plan. Please try again.');
        }
      }).fail(function () {
        $btn.prop('disabled', false).text('Generate Your SEO Plan →');
      });
    });
  }

  function renderPlanData(data) {
    // Architecture tree
    if (data.architecture && data.architecture.length) {
      var html = '<ul class="siloq-tree">';
      data.architecture.forEach(function (node) {
        var cls = 'siloq-tree--' + (node.type || 'spoke');
        html += '<li class="' + cls + '">' + escHtml(node.title);
        if (node.type === 'missing') {
          html += ' <button class="siloq-btn siloq-btn--sm siloq-btn--outline">Create</button>';
        }
        html += '</li>';
      });
      html += '</ul>';
      $('#siloq-architecture-content').html(html);
    }

    // Priority actions
    if (data.actions && data.actions.length) {
      var actHtml = '';
      data.actions.forEach(function (act, i) {
        var prioClass = act.priority === 'high' ? 'red' : (act.priority === 'medium' ? 'amber' : 'blue');
        var effortLabel = act.effort || 'Quick Win';
        actHtml += '<div class="siloq-action-card">'
          + '<span class="siloq-action-card__number">' + (i + 1) + '</span>'
          + '<div class="siloq-action-card__body">'
          + '<p class="siloq-action-card__headline">' + escHtml(act.headline) + '</p>'
          + '<div class="siloq-action-card__meta">'
          + '<span class="siloq-badge siloq-badge--' + prioClass + '">' + escHtml(act.priority) + '</span>'
          + '<span class="siloq-badge siloq-badge--gray">' + escHtml(effortLabel) + '</span>'
          + '</div>'
          + '<div class="siloq-action-card__actions">'
          + '<button class="siloq-btn siloq-btn--sm siloq-btn--primary">Fix It</button>'
          + '</div></div></div>';
      });
      $('#siloq-actions-content').html(actHtml);
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
          issueHtml += '<div class="siloq-issue-row"><span>' + escHtml(iss.title) + ' &mdash; ' + escHtml(iss.issue) + '</span>'
            + '<button class="siloq-btn siloq-btn--sm siloq-btn--outline">Fix It</button></div>';
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
          + '<div class="siloq-action-card__meta">'
          + '<span class="siloq-badge siloq-badge--' + bClass + '">' + escHtml(s.type) + '</span>'
          + '</div>'
          + '<button class="siloq-btn siloq-btn--sm siloq-btn--outline">Create Draft</button>'
          + '</div></div>';
      });
      $('#siloq-supporting-content').html(supHtml);
    }

    // Roadmap
    if (data.roadmap) {
      renderRoadmap(data.roadmap);
    }

    // Open the plan accordion sections
    $('.siloq-plan-section .siloq-accordion').addClass('is-open');
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
      $('#siloq-pages-count').text(total + ' page' + (total !== 1 ? 's' : '') + ' synced');

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
    if (page.page_type === 'hub') typeBadgeClass = 'hub';
    else if (page.page_type === 'spoke') typeBadgeClass = 'spoke';
    else if (page.page_type === 'supporting') typeBadgeClass = 'supporting';
    else if (page.page_type === 'orphan') typeBadgeClass = 'orphan';

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
      + '<span class="siloq-badge siloq-badge--' + typeBadgeClass + '">' + escHtml(page.page_type.toUpperCase()) + '</span>'
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

  /* ─── Init ───────────────────────────────────── */
  $(document).ready(function () {
    if (!$('.siloq-dash-wrap').length) return;
    initTabs();
    initScoreRing();
    initAccordions();
    initPlanGeneration();
    initRoadmap();
    initPagesTab();
  });

})(jQuery);
