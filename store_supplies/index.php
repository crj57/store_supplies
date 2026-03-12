<?php
require_once __DIR__ . '/db.php';

$category_order = ['Rx dispensing', 'Inventory', 'Immunization', 'Trash', 'Miscellaneous'];

$pdo  = get_db();
$rows = $pdo->query(
    "SELECT id, item_number, name, package_size, max_order_number, category FROM items ORDER BY name"
)->fetchAll();

// Group by category (preserving defined order)
$by_category = array_fill_keys($category_order, []);
foreach ($rows as $row) {
    $cat = $row['category'];
    if (!array_key_exists($cat, $by_category)) {
        $by_category[$cat] = [];
    }
    $by_category[$cat][] = $row;
}

// All items as JSON for client-side search
$items_json = json_encode(array_values($rows), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$chevron_svg = '<svg class="chevron" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="theme-color" content="#1d4ed8">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="Supplies">
  <title>Store Supplies</title>
  <link rel="manifest" href="manifest.json">
  <link rel="apple-touch-icon" href="icons/icon-192.png">
  <link rel="stylesheet" href="css/style.css">
</head>
<body>

<header class="app-header">
  <span class="app-title">Store Supplies</span>
  <a href="admin.php" class="admin-btn" aria-label="Admin">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <circle cx="12" cy="12" r="3"/>
      <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06-.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
    </svg>
  </a>
</header>

<div class="search-bar-wrap">
  <div class="search-inner">
    <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <circle cx="11" cy="11" r="8"/>
      <line x1="21" y1="21" x2="16.65" y2="16.65"/>
    </svg>
    <input
      type="search"
      id="search-input"
      class="search-input"
      placeholder="Search by name or item number…"
      autocomplete="off"
      autocorrect="off"
      autocapitalize="off"
      spellcheck="false"
      aria-label="Search items"
    >
    <button class="search-clear" id="search-clear" aria-label="Clear search" hidden>
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
  </div>
</div>

<!-- ── Search results (shown when searching) ── -->
<div id="search-results" hidden>
  <div id="search-results-list"></div>
  <p id="search-no-results" class="empty-state" hidden>No items found.</p>
</div>

<!-- ── Category list (shown when not searching) ── -->
<main id="main-content">

  <!-- Favorites -->
  <section class="category-section" id="favorites-section">
    <button class="category-header" aria-expanded="false" aria-controls="favorites-body">
      <span class="category-title">
        <span class="fav-star" aria-hidden="true">★</span>
        Favorites
        <span class="item-count" id="favorites-count"></span>
      </span>
      <?= $chevron_svg ?>
    </button>
    <div class="category-body" id="favorites-body" role="region">
      <p class="empty-state" id="favorites-empty">Star items to save them here.</p>
      <div id="favorites-list"></div>
    </div>
  </section>

  <!-- Categories (server-rendered) -->
  <?php foreach ($by_category as $cat => $items): ?>
  <?php if (empty($items)) continue; ?>
  <section class="category-section">
    <button class="category-header" aria-expanded="false">
      <span class="category-title">
        <?= h($cat) ?>
        <span class="item-count">(<?= count($items) ?>)</span>
      </span>
      <?= $chevron_svg ?>
    </button>
    <div class="category-body">
      <?php foreach ($items as $item): ?>
      <?php $id = (int) $item['id']; ?>
      <div class="item-row" data-id="<?= $id ?>">
        <div class="item-main">
          <div class="item-info">
            <span class="item-number"><?= h($item['item_number']) ?></span>
            <span class="item-name"><?= h($item['name']) ?></span>
          </div>
          <div class="item-controls">
            <button class="star-btn" data-id="<?= $id ?>" aria-label="Add to favorites" aria-pressed="false">
              <svg class="star-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            </button>
            <button class="expand-btn" data-id="<?= $id ?>" aria-label="Show details" aria-expanded="false">
              <svg class="chevron-right" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
          </div>
        </div>
        <div class="item-detail" hidden>
          <span class="detail-chip">Pkg size: <?= (int) $item['package_size'] ?></span>
          <span class="detail-chip">Max order: <?= (int) $item['max_order_number'] ?></span>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endforeach; ?>

</main>

<script>const ITEMS = <?= $items_json ?>;</script>
<script src="js/app.js"></script>
</body>
</html>
