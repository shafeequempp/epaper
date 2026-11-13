/*
 * Light deterrent against casual copying: blocks the right-click menu and the common
 * dev-tools / view-source / save shortcuts. This is NOT real security (anyone determined
 * can still read the page), but it stops casual right-click "Save image as" and F12.
 */
(function () {
  'use strict';

  document.addEventListener('contextmenu', function (e) { e.preventDefault(); }, { capture: true });

  // stop dragging images out of the page
  document.addEventListener('dragstart', function (e) {
    if (e.target && e.target.tagName === 'IMG') e.preventDefault();
  }, { capture: true });

  document.addEventListener('keydown', function (e) {
    var k = (e.key || '').toLowerCase();
    if (e.key === 'F12') { e.preventDefault(); return false; }
    // Ctrl/Cmd + Shift + I/J/C  (dev tools / inspector / console)
    if ((e.ctrlKey || e.metaKey) && e.shiftKey && (k === 'i' || k === 'j' || k === 'c')) {
      e.preventDefault(); return false;
    }
    // Ctrl/Cmd + U (view source), Ctrl/Cmd + S (save page)
    if ((e.ctrlKey || e.metaKey) && (k === 'u' || k === 's')) {
      e.preventDefault(); return false;
    }
  }, { capture: true });
})();
