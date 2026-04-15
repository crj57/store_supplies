# Store Supplies App — Technical Specification

## Overview

A mobile-first web application for pharmacy employees to look up store supply item numbers, browse by category, and maintain a personal favorites list. Hosted on Bluehost alongside an existing WordPress site.

**Primary user:** Pharmacy employees submitting supply orders on their mobile phones.

---

## Tech Stack

| Layer | Choice |
|---|---|
| Backend | PHP |
| Database | MySQL (Bluehost shared hosting) |
| Frontend | HTML, CSS, vanilla JavaScript |
| Data source | `store_supplies.csv` (imported into MySQL at launch) |
| PWA | Web app manifest for home screen install |

No WordPress integration required — this is a standalone PHP app hosted in a subdirectory (e.g., `yourdomain.com/supplies/`).

---

## Database Schema

### Table: `items`

| Column | Type | Description |
|---|---|---|
| `id` | INT AUTO_INCREMENT PK | Internal ID |
| `item_number` | VARCHAR(20) | Supplier item number (e.g., `145610`) |
| `name` | VARCHAR(255) | Item name (e.g., `Amber vials - 20 dram`) |
| `package_size` | INT | Units per package |
| `max_order_number` | INT | Maximum orderable quantity |
| `category` | VARCHAR(100) | One of the 5 categories (see below) |

Initial data imported from `store_supplies.csv` (78 items).

---

## Categories

Displayed in the following fixed order:

1. Rx Dispensing
2. Inventory
3. Immunization
4. Trash
5. Miscellaneous

Favorites (if any) appear as a collapsible accordion **above** all categories.

---

## Core Features

### 1. Sticky Search Bar

- Fixed to the top of the screen at all times, even while scrolling.
- Searches against both **item name** and **item number** simultaneously.
- On input, collapses all categories and shows a **flat, ranked list** of all matching items across every category.
- Clearing the search field restores the normal category accordion view.
- Match is case-insensitive and supports partial strings (e.g., `"vial"` matches `"Amber vials - 20 dram"`).

### 2. Category Accordion

- Below the search bar, each category is a collapsible accordion row.
- Each row shows the category name and an arrow/chevron indicating open/closed state.
- Default state: all categories collapsed.
- Tapping a category header expands it to reveal all items in that category.
- Multiple categories can be open simultaneously.

### 3. Item Rows

Each item row displays:
- **Item number** (prominent, easy to read at a glance)
- **Item name**
- A **chevron icon** on the right to expand the row
- A **star icon** on the right to toggle favoriting

When the chevron is tapped, the row expands inline to reveal:
- `Package size: [value]`
- `Max order: [value]`

Tapping the chevron again collapses the detail back.

### 4. Favorites

- Tapping the star icon on any item adds it to the Favorites list and highlights the star.
- Tapping the star again removes it from Favorites and returns the star to its default (unfilled) state.
- Favorites are stored in **`localStorage`** — they persist on the same device/browser but do not sync across devices.
- The **Favorites category** appears as a collapsible accordion at the top of the list (above Rx Dispensing).
- Favorites items are duplicated in this section — they still also appear in their original category.
- **Empty state:** When no items are starred, the Favorites accordion is visible but collapsed, with the text: _"Star items to save them here."_

---

## Admin Panel

### Access

- A **gear icon** in the app header opens the admin panel.
- Protected by a **single shared password** (stored as a hashed value in a config file or the database).
- No user accounts or login sessions beyond the password gate.

### Capabilities

- **Add item:** Form with fields for item number, name, package size, max order number, and category (dropdown).
- **Edit item:** Tap any existing item to edit all fields inline.
- **Delete item:** Delete button per item with a confirmation prompt.
- Changes are reflected immediately in the main app view.

---

## PWA / Home Screen Install

- Include a `manifest.json` with:
  - App name: `Store Supplies`
  - Short name: `Supplies`
  - Theme color: matching app primary color
  - Display: `standalone`
  - Icons: at least 192×192 and 512×512 PNG
- No service worker / offline caching required — the app requires an active internet connection.
- On supported browsers (iOS Safari, Chrome on Android), the browser will prompt or allow the employee to add the app to their home screen.

---

## Visual Design

**Style:** Clean and minimal — neutral background, strong typography for item numbers, ample touch targets for mobile use.

**Key design rules:**
- Minimum tap target size: 44×44px (Apple HIG / Material guidelines)
- Item numbers displayed in a **monospace or semi-bold font** — they must be easy to read at a glance
- Star icon: outlined (not favorited) / filled yellow (favorited)
- Chevron icon: points right (collapsed) / points down (expanded)
- Color palette: white/light gray backgrounds, dark text, one accent color for interactive elements
- No CVS branding required — neutral professional appearance

---

## Hosting & Deployment

- Hosted on Bluehost shared hosting in a subdirectory (e.g., `/supplies/`)
- MySQL database created via Bluehost cPanel
- Database credentials stored in a `config.php` file (excluded from version control)
- Initial data load: PHP migration script reads `store_supplies.csv` and inserts all 78 rows into the `items` table
- No build step required — plain PHP/HTML/CSS/JS files deployed via FTP or Git

---

## Out of Scope

- User accounts or per-user favorites sync
- Offline / service worker caching
- Copy-to-clipboard for item numbers
- Integration with any external ordering system
- Barcode scanning
- Order quantity input or order cart functionality
