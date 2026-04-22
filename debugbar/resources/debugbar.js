(function () {
  var root = document.getElementById('fdbg-root');
  if (!root) return;

  var toggle = root.querySelector('[data-fdbg-toggle]');
  var close = root.querySelector('[data-fdbg-close]');
  var tabs = root.querySelectorAll('[data-fdbg-tab]');
  var panes = root.querySelectorAll('[data-fdbg-pane]');
  var queryInput = root.querySelector('[data-fdbg-query-filter]');
  var queryRows = root.querySelectorAll('[data-fdbg-query-row]');
  var copyButtons = root.querySelectorAll('[data-fdbg-copy]');
  var storageOpenKey = 'Fnlla.debugbar.open';
  var storageTabKey = 'Fnlla.debugbar.tab';

  function setOpen(next) {
    root.setAttribute('data-open', next ? '1' : '0');
    try {
      window.localStorage.setItem(storageOpenKey, next ? '1' : '0');
    } catch (e) {}
  }

  function setTab(name) {
    tabs.forEach(function (btn) {
      var active = btn.getAttribute('data-fdbg-tab') === name;
      btn.setAttribute('aria-selected', active ? 'true' : 'false');
    });

    panes.forEach(function (pane) {
      pane.classList.toggle('is-active', pane.getAttribute('data-fdbg-pane') === name);
    });

    try {
      window.localStorage.setItem(storageTabKey, name);
    } catch (e) {}
  }

  try {
    var storedOpen = window.localStorage.getItem(storageOpenKey);
    if (storedOpen === '1') {
      setOpen(true);
    }

    var storedTab = window.localStorage.getItem(storageTabKey);
    if (storedTab) {
      setTab(storedTab);
    }
  } catch (e) {}

  if (toggle) {
    toggle.addEventListener('click', function () {
      setOpen(root.getAttribute('data-open') !== '1');
    });
  }

  if (close) {
    close.addEventListener('click', function () {
      setOpen(false);
    });
  }

  tabs.forEach(function (btn) {
    btn.addEventListener('click', function () {
      setTab(btn.getAttribute('data-fdbg-tab'));
    });
  });

  copyButtons.forEach(function (button) {
    button.addEventListener('click', function () {
      var payload = button.getAttribute('data-fdbg-copy') || '';
      if (payload === '') {
        return;
      }

      function markCopied() {
        button.textContent = 'Copied';
        setTimeout(function () {
          button.textContent = 'Copy SQL';
        }, 1200);
      }

      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard
          .writeText(payload)
          .then(function () {
            markCopied();
          })
          .catch(function () {});
      } else {
        var area = document.createElement('textarea');
        area.value = payload;
        area.setAttribute('readonly', '');
        area.style.position = 'absolute';
        area.style.left = '-9999px';
        document.body.appendChild(area);
        area.select();
        try {
          document.execCommand('copy');
          markCopied();
        } catch (e) {}
        document.body.removeChild(area);
      }
    });
  });

  if (queryInput) {
    queryInput.addEventListener('input', function () {
      var term = queryInput.value.trim().toLowerCase();
      queryRows.forEach(function (row) {
        var hay = (row.getAttribute('data-fdbg-query-text') || '').toLowerCase();
        row.style.display = term === '' || hay.indexOf(term) !== -1 ? '' : 'none';
      });
    });
  }

  document.addEventListener('keydown', function (event) {
    if (event.ctrlKey && event.shiftKey && (event.key === 'D' || event.key === 'd')) {
      event.preventDefault();
      setOpen(root.getAttribute('data-open') !== '1');
    }

    if (event.key === 'Escape') {
      setOpen(false);
    }
  });
})();
