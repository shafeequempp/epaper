(function () {
  'use strict';

  // ------------------------------------------------ Block right-click on the reader (deters saving the page image)
  //   The search box keeps its menu so paste/select still work there.
  document.addEventListener('contextmenu', function (e) {
    if (e.target.closest('input, textarea')) return;
    e.preventDefault();
  });

  // ------------------------------------------------ Full page view
  var reader = document.querySelector('.reader');
  if (reader) initReader(reader);

  function initReader(root) {
    var viewport = root.querySelector('.viewport');
    var sheet = root.querySelector('.sheet');
    var panel = root.querySelector('.panel');
    var body = panel.querySelector('.panel__body');
    var openLink = panel.querySelector('.panel__open');
    var api = root.getAttribute('data-api');
    var preview = root.getAttribute('data-preview') === '1';
    var ratio = +sheet.getAttribute('data-h') / +sheet.getAttribute('data-w');
    // open zoomed in for readable text on desktop; phones already fill the page width
    var DEFAULT = window.innerWidth > 760 ? 2 : 1;
    var zoom = DEFAULT, MIN = 1, MAX = 5, current = null, mode = 'image';

    function fitWidth() {
      // whole page visible by default on wide screens, page width on phones
      // the sheet includes the white page margin, so measure its real height-to-width ratio
      var r = sheet.offsetWidth ? sheet.offsetHeight / sheet.offsetWidth : ratio;
      var byHeight = (viewport.clientHeight - 24) / r;
      var byWidth = viewport.clientWidth - 24;
      return window.innerWidth > 760 ? Math.min(byWidth, byHeight) : byWidth;
    }

    function setZoom(z, cx, cy) {
      z = Math.max(MIN, Math.min(MAX, z));
      var before = sheet.getBoundingClientRect();
      var vp = viewport.getBoundingClientRect();
      if (cx === undefined) { cx = vp.left + vp.width / 2; cy = vp.top + vp.height / 2; }
      var fx = before.width ? (cx - before.left) / before.width : 0.5;
      var fy = before.height ? (cy - before.top) / before.height : 0.5;
      zoom = z;
      sheet.style.width = Math.round(fitWidth() * zoom) + 'px';
      root.classList.toggle('is-zoomed', zoom > 1.01);
      var after = sheet.getBoundingClientRect();
      viewport.scrollLeft += (after.left + fx * after.width) - cx;
      viewport.scrollTop += (after.top + fy * after.height) - cy;
    }

    root.querySelectorAll('[data-zoom]').forEach(function (b) {
      b.addEventListener('click', function () {
        var k = b.getAttribute('data-zoom');
        setZoom(k === 'fit' ? 1 : k === 'in' ? zoom * 1.4 : zoom / 1.4);
      });
    });
    viewport.addEventListener('wheel', function (e) {
      if (!e.ctrlKey && !e.metaKey) return;
      e.preventDefault();
      setZoom(zoom * (e.deltaY < 0 ? 1.15 : 1 / 1.15), e.clientX, e.clientY);
    }, { passive: false });
    viewport.addEventListener('dblclick', function (e) {
      setZoom(zoom > 1.5 ? 1 : 2.5, e.clientX, e.clientY);
    });
    window.addEventListener('resize', function () { setZoom(zoom); });
    setZoom(DEFAULT);
    // start at the top of the page (masthead), horizontally centred
    viewport.scrollTop = 0;
    viewport.scrollLeft = (viewport.scrollWidth - viewport.clientWidth) / 2;

    // drag to pan; a drag never counts as a click on a story
    var drag = null, moved = false;
    viewport.addEventListener('pointerdown', function (e) {
      if (e.button !== 0) return;
      drag = { x: e.clientX, y: e.clientY, l: viewport.scrollLeft, t: viewport.scrollTop };
      moved = false;
    });
    window.addEventListener('pointermove', function (e) {
      if (!drag) return;
      var dx = e.clientX - drag.x, dy = e.clientY - drag.y;
      if (Math.abs(dx) + Math.abs(dy) > 6) { moved = true; root.classList.add('is-dragging'); }
      if (moved) { viewport.scrollLeft = drag.l - dx; viewport.scrollTop = drag.t - dy; }
    });
    window.addEventListener('pointerup', function () { drag = null; root.classList.remove('is-dragging'); });

    // stories
    sheet.addEventListener('click', function (e) {
      var spot = e.target.closest('.spot');
      if (!spot) return;
      e.preventDefault();
      if (moved) return;
      openArticle(spot.getAttribute('data-article'));
    });

    function openArticle(id) {
      current = id;
      sheet.querySelectorAll('.spot').forEach(function (s) {
        s.classList.toggle('is-active', s.getAttribute('data-article') === id);
      });
      var wasHidden = panel.hidden;
      panel.hidden = false;
      root.classList.add('has-panel');
      if (wasHidden) requestAnimationFrame(function () { setZoom(zoom); });
      body.innerHTML = '<p class="muted">Loading story…</p>';
      fetch(api + '?id=' + encodeURIComponent(id) + (preview ? '&preview=1' : ''), { credentials: 'same-origin' })
        .then(function (r) { if (!r.ok) throw new Error(); return r.json(); })
        .then(function (a) { if (current === id) render(a); })
        .catch(function () { body.innerHTML = '<p>This story could not be loaded. Check your connection and tap it again.</p>'; });
    }

    function render(a) {
      openLink.href = a.url;
      var html = '';
      if (mode === 'image') {
        a.images.forEach(function (im) {
          html += '<img src="' + esc(im.src) + '" width="' + im.w + '" height="' + im.h + '" alt="' + esc(a.headline) + '">';
        });
      } else {
        html += '<h2>' + esc(a.headline) + '</h2>';
        a.body.split(/\n+/).forEach(function (p) { if (p.trim()) html += '<p>' + esc(p) + '</p>'; });
        (a.captions || []).forEach(function (c) { html += '<p class="caption">' + esc(c) + '</p>'; });
      }
      body.innerHTML = html;
      body.setAttribute('data-mode', mode);
      body.scrollTop = 0;
      panel.lastData = a;
    }

    panel.querySelectorAll('[data-mode]').forEach(function (b) {
      b.addEventListener('click', function () {
        mode = b.getAttribute('data-mode');
        panel.querySelectorAll('[data-mode]').forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
        if (panel.lastData) render(panel.lastData);
      });
    });

    function closePanel() {
      panel.hidden = true;
      root.classList.remove('has-panel');
      current = null;
      sheet.querySelectorAll('.spot.is-active').forEach(function (s) { s.classList.remove('is-active'); });
      requestAnimationFrame(function () { setZoom(zoom); });
    }
    panel.querySelector('.panel__close').addEventListener('click', closePanel);

    document.addEventListener('keydown', function (e) {
      if (e.target.matches('input, select, textarea')) return;
      if (e.key === 'Escape' && !panel.hidden) return closePanel();
      if (e.key === '+' || e.key === '=') setZoom(zoom * 1.4);
      if (e.key === '-') setZoom(zoom / 1.4);
      if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
        var link = root.querySelector('[data-key="' + (e.key === 'ArrowLeft' ? 'prev' : 'next') + '"]');
        if (link && zoom <= DEFAULT * 1.01) location.href = link.href;
      }
    });

    var active = root.querySelector('.strip [aria-current]');
    if (active) active.scrollIntoView({ block: 'center', inline: 'center' });
  }

  // ------------------------------------------------ Page list view: link headlines and boxes
  document.querySelectorAll('.plist').forEach(function (sec) {
    function mark(id, on) {
      sec.querySelectorAll('[data-article="' + id + '"]').forEach(function (el) { el.classList.toggle('is-lit', on); });
    }
    sec.addEventListener('mouseover', function (e) {
      var t = e.target.closest('[data-article]');
      if (t) mark(t.getAttribute('data-article'), true);
    });
    sec.addEventListener('mouseout', function (e) {
      var t = e.target.closest('[data-article]');
      if (t) mark(t.getAttribute('data-article'), false);
    });
    sec.addEventListener('focusin', function (e) {
      var t = e.target.closest('[data-article]');
      if (t) mark(t.getAttribute('data-article'), true);
    });
    sec.addEventListener('focusout', function (e) {
      var t = e.target.closest('[data-article]');
      if (t) mark(t.getAttribute('data-article'), false);
    });
  });

  // ------------------------------------------------ Share
  document.querySelectorAll('.share').forEach(function (b) {
    b.addEventListener('click', function () {
      var data = { title: b.getAttribute('data-title'), url: location.href };
      if (navigator.share) { navigator.share(data).catch(function () {}); return; }
      navigator.clipboard.writeText(location.href).then(function () {
        b.textContent = 'Link copied';
        setTimeout(function () { b.textContent = 'Share'; }, 2000);
      });
    });
  });

  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
})();

// ------------------------------------------------ Date picker: calendar with only paper days clickable
(function () {
  var input = document.querySelector('.pick-date');
  if (!input || input.disabled) return;
  var have = {};
  try { JSON.parse(input.getAttribute('data-dates') || '[]').forEach(function (d) { have[d] = true; }); } catch (e) { return; }
  var months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
  var days = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];
  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function ymd(y, m, d) { return y + '-' + pad(m + 1) + '-' + pad(d); }
  function label(v) { var p = v.split('-'); return +p[2] + ' ' + months[+p[1] - 1] + ' ' + p[0]; }

  var wrap = document.createElement('div');
  wrap.className = 'cal-wrap';
  input.parentNode.insertBefore(wrap, input);
  wrap.appendChild(input);
  input.hidden = true;

  var btn = document.createElement('button');
  btn.type = 'button';
  btn.className = 'cal-btn';
  btn.setAttribute('aria-haspopup', 'dialog');
  btn.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg><span></span>';
  btn.querySelector('span').textContent = input.value ? label(input.value) : 'Choose date';
  wrap.insertBefore(btn, input);

  var pop = document.createElement('div');
  pop.className = 'cal';
  pop.setAttribute('role', 'dialog');
  pop.setAttribute('aria-label', 'Choose a date');
  pop.hidden = true;
  wrap.appendChild(pop);

  var sel = input.value || input.max;
  var view = sel.split('-').map(Number);           // [y, m(1-12)]
  var minYM = input.min ? input.min.slice(0, 7) : '0000-00';
  var maxYM = input.max ? input.max.slice(0, 7) : '9999-99';

  function render() {
    var y = view[0], m = view[1] - 1;
    var ym = y + '-' + pad(m + 1);
    var html = '<div class="cal__head">' +
      '<button type="button" class="cal__nav" data-step="-1" aria-label="Previous month"' + (ym <= minYM ? ' disabled' : '') + '>&#8249;</button>' +
      '<span>' + months[m] + ' ' + y + '</span>' +
      '<button type="button" class="cal__nav" data-step="1" aria-label="Next month"' + (ym >= maxYM ? ' disabled' : '') + '>&#8250;</button></div>' +
      '<div class="cal__grid">';
    days.forEach(function (d) { html += '<span class="cal__dow">' + d + '</span>'; });
    var first = new Date(y, m, 1).getDay(), count = new Date(y, m + 1, 0).getDate();
    for (var i = 0; i < first; i++) html += '<span></span>';
    for (var d = 1; d <= count; d++) {
      var v = ymd(y, m, d);
      if (have[v]) {
        html += '<button type="button" class="cal__day' + (v === sel ? ' is-sel' : '') + '" data-date="' + v + '"' +
                (v === sel ? ' aria-current="date"' : '') + ' aria-label="' + label(v) + '">' + d + '</button>';
      } else {
        html += '<span class="cal__day is-off" aria-hidden="true">' + d + '</span>';
      }
    }
    html += '</div><p class="cal__note">Days in bold have a paper.</p>';
    pop.innerHTML = html;
  }

  function open() {
    render();
    pop.hidden = false;
    btn.setAttribute('aria-expanded', 'true');
    var f = pop.querySelector('.is-sel') || pop.querySelector('button.cal__day');
    if (f) f.focus();
  }
  function close() { pop.hidden = true; btn.setAttribute('aria-expanded', 'false'); }

  btn.addEventListener('click', function () { pop.hidden ? open() : close(); });
  pop.addEventListener('click', function (e) {
    e.stopPropagation();                       // the calendar redraws itself; don't treat that as an outside click
    var nav = e.target.closest('.cal__nav');
    if (nav) {
      view[1] += +nav.getAttribute('data-step');
      if (view[1] < 1) { view[1] = 12; view[0]--; }
      if (view[1] > 12) { view[1] = 1; view[0]++; }
      render();
      return;
    }
    var day = e.target.closest('button.cal__day');
    if (day) {
      input.value = day.getAttribute('data-date');
      btn.querySelector('span').textContent = label(input.value);
      close();
      input.form.submit();
    }
  });
  document.addEventListener('click', function (e) { if (!wrap.contains(e.target)) close(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !pop.hidden) { close(); btn.focus(); } });
})();