<?php
declare(strict_types=1);

ob_start();
require __DIR__ . '/carrier.php';
$html = ob_get_clean();

$html = str_replace(
    ['href="/carrier?', 'href="/carrier#', 'href="/carrier"', 'action="/carrier"', '<title>Carrier | Oligarchy Services</title>'],
    ['href="/carrier-read.php?', 'href="/carrier-read.php#', 'href="/carrier-read.php"', 'action="/carrier-read.php"', '<title>Carrier Auto Read Test | Oligarchy Services</title>'],
    $html
);
$html = str_replace(
    '<h1 data-section-title>Carrier</h1>',
    '<h1 data-section-title>Carrier Auto Read Test</h1>',
    $html
);
$html = str_replace(
    '<main class="carrier-workspace">',
    '<main class="carrier-workspace"><div class="dashboard-alert is-success" role="status">Test page: opening an unread Carrier email marks it read, updates the Unread count, and keeps the opened message visible. Inbox count is the total non-archived mail count.</div>',
    $html
);
$html = str_replace(
    '</body>',
    <<<'HTML'
    <style>
      .dashboard-shell.is-collapsed .sidebar-group[data-settings-group] {
        position: relative;
      }
      .dashboard-shell.is-collapsed .sidebar-group[data-settings-group].is-open .sidebar-subnav {
        position: absolute;
        top: 0;
        left: 62px;
        z-index: 80;
        display: grid;
        width: 230px;
        gap: 4px;
        padding: 8px;
        border: 1px solid rgba(90, 93, 99, 0.48);
        border-radius: 8px;
        background: #17171a;
        box-shadow: 0 18px 44px rgba(0, 0, 0, 0.42);
      }
      .dashboard-shell.is-collapsed .sidebar-group[data-settings-group].is-open .sidebar-subnav::before {
        content: "Settings";
        display: block;
        padding: 6px 8px 8px;
        color: #f4f5f7;
        font-size: 0.78rem;
        font-weight: 900;
        text-transform: uppercase;
      }
      .dashboard-shell.is-collapsed .sidebar-group[data-settings-group].is-open .sidebar-subnav a {
        display: flex;
        min-height: 36px;
        align-items: center;
        gap: 9px;
        padding: 0 10px;
      }
      .dashboard-shell.is-collapsed .sidebar-group[data-settings-group].is-open .sidebar-subnav .nav-label {
        position: static;
        width: auto;
        height: auto;
        overflow: visible;
        clip: auto;
        clip-path: none;
        white-space: nowrap;
      }
      .dashboard-shell.is-collapsed .sidebar-group[data-settings-group].is-open .sidebar-subnav .nav-icon {
        flex: 0 0 22px;
        width: 22px;
        height: 22px;
      }
    </style>
    <script>
      (() => {
        const shell = document.querySelector('[data-dashboard-shell]');
        const settingsGroup = document.querySelector('[data-settings-group]');
        const settingsToggle = document.querySelector('[data-settings-toggle]');
        if (shell && settingsGroup && settingsToggle) {
          settingsToggle.addEventListener('click', (event) => {
            if (!shell.classList.contains('is-collapsed')) return;
            event.preventDefault();
            event.stopImmediatePropagation();
            const isOpen = !settingsGroup.classList.contains('is-open');
            document.querySelectorAll('[data-valley-group], [data-playground-group], [data-settings-group]').forEach((group) => {
              if (group !== settingsGroup) group.classList.remove('is-open');
            });
            settingsGroup.classList.toggle('is-open', isOpen);
            settingsToggle.setAttribute('aria-expanded', String(isOpen));
          }, true);
          document.addEventListener('click', (event) => {
            if (!shell.classList.contains('is-collapsed') || !settingsGroup.classList.contains('is-open')) return;
            if (settingsGroup.contains(event.target)) return;
            settingsGroup.classList.remove('is-open');
            settingsToggle.setAttribute('aria-expanded', 'false');
          });
        }
      })();
    </script>
    <script>
      (() => {
        if (!document.body.classList.contains('carrier-body')) return;
        const params = new URLSearchParams(window.location.search);
        const openedId = params.get('open');
        if (!openedId || window.sessionStorage.getItem(`carrier_auto_read_${openedId}`) === 'done') return;

        const selectedRow = document.querySelector('.carrier-row.is-selected');
        if (!selectedRow || !selectedRow.classList.contains('is-unread')) return;

        const sourceForm = selectedRow.querySelector('.star-form');
        if (!sourceForm) return;

        selectedRow.classList.remove('is-unread');
        selectedRow.dataset.carrierRead = 'read';
        window.sessionStorage.setItem(`carrier_auto_read_${openedId}`, 'done');

        const formData = new FormData(sourceForm);
        formData.set('carrier_id', openedId);
        formData.set('action', 'mark_read');

        window.fetch('/carrier-read.php', {
          method: 'POST',
          body: formData,
          credentials: 'same-origin'
        }).finally(() => {
          const next = new URL(window.location.href);
          if (next.searchParams.get('folder') === 'unread') next.searchParams.set('folder', 'inbox');
          next.hash = 'carrier-preview';
          window.location.replace(next.toString());
        });
      })();
    </script>
  </body>
HTML,
    $html
);

echo $html;
