<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$error   = '';
$success = '';

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_SESSION['admin_auth'])) {
    if (isset($_POST['password']) && hash_equals(ADMIN_PASSWORD, $_POST['password'])) {
        $_SESSION['admin_auth'] = true;
        header('Location: admin.php');
        exit;
    } else {
        $error = 'Incorrect password.';
    }
}

$authed         = isset($_SESSION['admin_auth']);
$category_order = ['Rx dispensing', 'Inventory', 'Immunization', 'Trash', 'Miscellaneous'];

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

if ($authed) {
    $pdo = get_db();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'add') {
            $stmt = $pdo->prepare(
                "INSERT INTO items (item_number, name, package_size, max_order_number, category)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                trim($_POST['item_number']),
                trim($_POST['name']),
                (int) $_POST['package_size'],
                (int) $_POST['max_order_number'],
                $_POST['category'],
            ]);
            $success = 'Item added.';

        } elseif ($action === 'edit') {
            $stmt = $pdo->prepare(
                "UPDATE items SET item_number=?, name=?, package_size=?, max_order_number=?, category=?
                 WHERE id=?"
            );
            $stmt->execute([
                trim($_POST['item_number']),
                trim($_POST['name']),
                (int) $_POST['package_size'],
                (int) $_POST['max_order_number'],
                $_POST['category'],
                (int) $_POST['id'],
            ]);
            $success = 'Item updated.';

        } elseif ($action === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM items WHERE id=?");
            $stmt->execute([(int) $_POST['id']]);
            $success = 'Item deleted.';
        }
    }

    $items = $pdo->query(
        "SELECT * FROM items ORDER BY FIELD(category,'Rx dispensing','Inventory','Immunization','Trash','Miscellaneous'), name"
    )->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin — Store Supplies</title>
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/admin.css">
</head>
<body class="admin-page">

<?php if (!$authed): ?>

<div class="login-wrap">
  <h1 class="login-title">Admin Login</h1>
  <?php if ($error): ?>
    <p class="alert alert-error"><?= h($error) ?></p>
  <?php endif; ?>
  <form method="POST" class="login-form">
    <input type="password" name="password" class="form-input" placeholder="Password" autofocus required>
    <button type="submit" class="btn btn-primary">Log In</button>
  </form>
  <a href="index.php" class="back-link">← Back to app</a>
</div>

<?php else: ?>

<div class="admin-header">
  <h1>Manage Items</h1>
  <div class="admin-header-actions">
    <a href="index.php" class="btn btn-secondary">← App</a>
    <a href="admin.php?logout=1" class="btn btn-secondary">Log out</a>
  </div>
</div>

<?php if ($success): ?>
  <p class="alert alert-success"><?= h($success) ?></p>
<?php endif; ?>

<!-- Add Item -->
<details class="admin-card">
  <summary class="admin-card-summary">+ Add New Item</summary>
  <form method="POST" class="item-form">
    <input type="hidden" name="action" value="add">
    <div class="form-row">
      <label class="form-label">Item Number
        <input type="text" name="item_number" class="form-input" placeholder="e.g. 145610" required>
      </label>
      <label class="form-label">Category
        <select name="category" class="form-input">
          <?php foreach ($category_order as $c): ?>
          <option value="<?= h($c) ?>"><?= h($c) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
    <div class="form-row">
      <label class="form-label" style="flex:1">Name
        <input type="text" name="name" class="form-input" placeholder="e.g. Amber vials - 20 dram" required>
      </label>
    </div>
    <div class="form-row">
      <label class="form-label">Package Size
        <input type="number" name="package_size" class="form-input" min="0" required>
      </label>
      <label class="form-label">Max Order
        <input type="number" name="max_order_number" class="form-input" min="0" required>
      </label>
    </div>
    <button type="submit" class="btn btn-primary">Add Item</button>
  </form>
</details>

<!-- Item List -->
<div class="admin-list">
  <?php foreach ($items as $item): ?>
  <?php $id = (int) $item['id']; ?>
  <div class="admin-item" id="admin-item-<?= $id ?>">
    <div class="admin-item-info">
      <strong class="admin-item-number"><?= h($item['item_number']) ?></strong>
      <span class="admin-item-name"><?= h($item['name']) ?></span>
      <small class="admin-item-meta"><?= h($item['category']) ?> &middot; Pkg: <?= (int)$item['package_size'] ?> &middot; Max: <?= (int)$item['max_order_number'] ?></small>
    </div>
    <div class="admin-item-actions">
      <button class="btn btn-sm btn-secondary" onclick="toggleEdit(<?= $id ?>)">Edit</button>
      <form method="POST" onsubmit="return confirm('Delete this item?')">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= $id ?>">
        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
      </form>
    </div>
    <!-- Edit form -->
    <form method="POST" class="item-form edit-form" id="edit-form-<?= $id ?>" hidden>
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" value="<?= $id ?>">
      <div class="form-row">
        <label class="form-label">Item Number
          <input type="text" name="item_number" class="form-input" value="<?= h($item['item_number']) ?>" required>
        </label>
        <label class="form-label">Category
          <select name="category" class="form-input">
            <?php foreach ($category_order as $c): ?>
            <option value="<?= h($c) ?>" <?= $c === $item['category'] ? 'selected' : '' ?>><?= h($c) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>
      <div class="form-row">
        <label class="form-label" style="flex:1">Name
          <input type="text" name="name" class="form-input" value="<?= h($item['name']) ?>" required>
        </label>
      </div>
      <div class="form-row">
        <label class="form-label">Package Size
          <input type="number" name="package_size" class="form-input" value="<?= (int)$item['package_size'] ?>" min="0" required>
        </label>
        <label class="form-label">Max Order
          <input type="number" name="max_order_number" class="form-input" value="<?= (int)$item['max_order_number'] ?>" min="0" required>
        </label>
      </div>
      <div class="admin-form-actions">
        <button type="submit" class="btn btn-primary">Save</button>
        <button type="button" class="btn btn-secondary" onclick="toggleEdit(<?= $id ?>)">Cancel</button>
      </div>
    </form>
  </div>
  <?php endforeach; ?>
</div>

<script>
function toggleEdit(id) {
  const form = document.getElementById('edit-form-' + id);
  form.hidden = !form.hidden;
  if (!form.hidden) form.querySelector('input[name="item_number"]').focus();
}
</script>

<?php endif; ?>
</body>
</html>
