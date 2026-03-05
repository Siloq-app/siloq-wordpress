/**
 * Siloq Dashboard v2 — Tab switching, score ring, plan AJAX, roadmap persistence
 * Version: 1.5.70
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

  /* ─── Utility ────────────────────────────────── */
  function escHtml(str) {
    if (!str) return '';
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
  }

  /* ─── Init ───────────────────────────────────── */
  $(document).ready(function () {
    if (!$('.siloq-dash-wrap').length) return;
    initTabs();
    initScoreRing();
    initAccordions();
    initPlanGeneration();
    initRoadmap();
  });

})(jQuery);
