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
