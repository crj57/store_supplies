'use strict';

// ── Constants ──────────────────────────────────────────────────────────────
const FAV_KEY = 'supply_favorites_v1';

// ── Favorites helpers ──────────────────────────────────────────────────────
function getFavs() {
  try { return JSON.parse(localStorage.getItem(FAV_KEY)) || []; }
  catch { return []; }
}

function saveFavs(favs) {
  localStorage.setItem(FAV_KEY, JSON.stringify(favs));
}

function isFav(id) {
  return getFavs().includes(String(id));
}

function toggleFav(id) {
  const strId = String(id);
  let favs = getFavs();
  if (favs.includes(strId)) {
    favs = favs.filter(f => f !== strId);
  } else {
    favs.push(strId);
  }
  saveFavs(favs);
  return favs.includes(strId);
}

// ── XSS-safe string ───────────────────────────────────────────────────────
function esc(str) {
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

// ── Build an item row (used for favorites & search results) ───────────────
function buildItemRow(item, context) {
  const starred = isFav(item.id);
  const catLabel = context === 'search'
    ? `<span class="search-cat-label">${esc(item.category)}</span>`
    : '';

  return `
    <div class="item-row" data-id="${esc(item.id)}">
      <div class="item-main">
        <div class="item-info">
          <span class="item-number">${esc(item.item_number)}</span>
          <span class="item-name">${esc(item.name)}</span>
          ${catLabel}
        </div>
        <div class="item-controls">
          <button class="star-btn" data-id="${esc(item.id)}"
                  aria-label="${starred ? 'Remove from' : 'Add to'} favorites"
                  aria-pressed="${starred}">
            <svg class="star-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                 stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
            </svg>
          </button>
          <button class="expand-btn" data-id="${esc(item.id)}"
                  aria-label="Show details" aria-expanded="false">
            <svg class="chevron-right" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2.5"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <polyline points="9 18 15 12 9 6"/>
            </svg>
          </button>
        </div>
      </div>
      <div class="item-detail" hidden>
        <span class="detail-chip">Pkg size: ${esc(item.package_size)}</span>
        <span class="detail-chip">Max order: ${esc(item.max_order_number)}</span>
      </div>
    </div>`;
}

// ── Render favorites section ───────────────────────────────────────────────
function renderFavorites() {
  const favIds  = getFavs();
  const list    = document.getElementById('favorites-list');
  const empty   = document.getElementById('favorites-empty');
  const countEl = document.getElementById('favorites-count');

  const favItems = ITEMS.filter(item => favIds.includes(String(item.id)));

  if (favItems.length === 0) {
    empty.hidden = false;
    list.innerHTML = '';
    countEl.textContent = '';
  } else {
    empty.hidden = true;
    countEl.textContent = `(${favItems.length})`;
    list.innerHTML = favItems.map(item => buildItemRow(item, 'favorites')).join('');
  }
}

// ── Sync all star buttons for a given item ID ──────────────────────────────
function syncStarButtons(id, isStarred) {
  document.querySelectorAll(`.star-btn[data-id="${id}"]`).forEach(btn => {
    btn.setAttribute('aria-pressed', isStarred);
    btn.setAttribute('aria-label', `${isStarred ? 'Remove from' : 'Add to'} favorites`);
  });
}

// ── Init: apply starred state to server-rendered rows ─────────────────────
function initStarStates() {
  const favIds = getFavs();
  favIds.forEach(id => {
    document.querySelectorAll(`.star-btn[data-id="${id}"]`).forEach(btn => {
      btn.setAttribute('aria-pressed', 'true');
      btn.setAttribute('aria-label', 'Remove from favorites');
    });
  });
}

// ── Handlers ──────────────────────────────────────────────────────────────
function onStarClick(btn) {
  const id       = btn.dataset.id;
  const isNowFav = toggleFav(id);
  syncStarButtons(id, isNowFav);
  renderFavorites();
}

function onExpandClick(btn) {
  const row      = btn.closest('.item-row');
  const detail   = row.querySelector('.item-detail');
  const expanded = btn.getAttribute('aria-expanded') === 'true';
  btn.setAttribute('aria-expanded', String(!expanded));
  detail.hidden = expanded;
}

function onCategoryClick(header) {
  const body     = header.nextElementSibling;
  const expanded = header.getAttribute('aria-expanded') === 'true';
  header.setAttribute('aria-expanded', String(!expanded));
  body.classList.toggle('open', !expanded);
}

// ── Search ────────────────────────────────────────────────────────────────
const searchInput    = document.getElementById('search-input');
const searchClear    = document.getElementById('search-clear');
const mainContent    = document.getElementById('main-content');
const searchResults  = document.getElementById('search-results');
const searchList     = document.getElementById('search-results-list');
const searchNoResult = document.getElementById('search-no-results');

function runSearch(query) {
  const q = query.trim().toLowerCase();

  if (!q) {
    mainContent.hidden   = false;
    searchResults.hidden = true;
    searchClear.hidden   = true;
    return;
  }

  mainContent.hidden   = true;
  searchResults.hidden = false;
  searchClear.hidden   = false;

  const matches = ITEMS.filter(item =>
    item.name.toLowerCase().includes(q) ||
    item.item_number.toLowerCase().includes(q)
  );

  if (matches.length === 0) {
    searchList.innerHTML    = '';
    searchNoResult.hidden   = false;
  } else {
    searchNoResult.hidden   = true;
    searchList.innerHTML    = matches.map(item => buildItemRow(item, 'search')).join('');
    // Apply star states to newly rendered rows
    const favIds = getFavs();
    favIds.forEach(id => {
      searchList.querySelectorAll(`.star-btn[data-id="${id}"]`).forEach(btn => {
        btn.setAttribute('aria-pressed', 'true');
        btn.setAttribute('aria-label', 'Remove from favorites');
      });
    });
  }
}

searchInput.addEventListener('input', () => runSearch(searchInput.value));

searchClear.addEventListener('click', () => {
  searchInput.value = '';
  runSearch('');
  searchInput.focus();
});

// ── Event delegation (single listener for all clicks) ─────────────────────
document.addEventListener('click', e => {
  const star   = e.target.closest('.star-btn');
  if (star)   { onStarClick(star);   return; }

  const expand = e.target.closest('.expand-btn');
  if (expand) { onExpandClick(expand); return; }

  const catHdr = e.target.closest('.category-header');
  if (catHdr) { onCategoryClick(catHdr); return; }
});

// ── Boot ──────────────────────────────────────────────────────────────────
initStarStates();
renderFavorites();
