<?php
/**
 * THREAD&CO - Admin Products Management (admin/products.php)
 * 
 * Full clothing catalog management page for administrators:
 * - Searchable and filterable product listing table
 * - Quick single-product deletion with CSRF protection
 * - Stock level indicators (In Stock / Low Stock / Out of Stock)
 * - Direct links to Add New and Edit individual products
 */

$pageTitle   = 'Products | THREAD&CO Admin';
$pageHeading = 'Manage Products';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin('login.php');

// -------------------------------------------------------------
// Handle: Delete Product (GET action with CSRF via token)
// -------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id']) && isset($_GET['csrf'])) {
    if (!validateCsrfToken($_GET['csrf'])) {
        setFlashMessage('error', 'Invalid security token. Please try again.');
        header('Location: products.php');
        exit;
    }

    $deleteId = (int)$_GET['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = :id");
        $stmt->execute(['id' => $deleteId]);
        setFlashMessage('success', 'Product has been deleted successfully.');
    } catch (PDOException $e) {
        setFlashMessage('error', 'Cannot delete: product may be linked to existing orders.');
    }
    header('Location: products.php');
    exit;
}

// -------------------------------------------------------------
// Build Product Query with Optional Search + Category Filter
// -------------------------------------------------------------
$search     = trim($_GET['search'] ?? '');
$filterCat  = (int)($_GET['category'] ?? 0);
$sortBy     = $_GET['sort'] ?? 'newest';

// Sort mapping
$sortMap = [
    'newest'     => 'p.created_at DESC',
    'oldest'     => 'p.created_at ASC',
    'price_asc'  => 'p.price ASC',
    'price_desc' => 'p.price DESC',
    'stock_asc'  => 'p.stock_quantity ASC',
    'name_asc'   => 'p.name ASC',
];
$orderSQL = $sortMap[$sortBy] ?? 'p.created_at DESC';

$conditions = [];
$params     = [];

if ($search !== '') {
    $conditions[] = "(p.name LIKE :search OR p.description LIKE :search2)";
    $params[':search']  = '%' . $search . '%';
    $params[':search2'] = '%' . $search . '%';
}
if ($filterCat > 0) {
    $conditions[] = "p.category_id = :cat_id";
    $params[':cat_id'] = $filterCat;
}

$whereSQL = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

try {
    $products = $pdo->prepare("
        SELECT p.*, c.name AS category_name
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        {$whereSQL}
        ORDER BY {$orderSQL}
    ");
    $products->execute($params);
    $products = $products->fetchAll();

    // Fetch all categories for the filter dropdown
    $allCategories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();

} catch (PDOException $e) {
    $products      = [];
    $allCategories = [];
}

$csrfToken = generateCsrfToken();

// Stock level label helper
function stockLabel(int $qty): string {
    if ($qty === 0) {
        return '<span style="color:#991b1b;font-weight:700;">Out of Stock</span>';
    } elseif ($qty <= 5) {
        return '<span style="color:#92400e;font-weight:700;">Low (' . $qty . ')</span>';
    }
    return '<span style="color:#166534;font-weight:600;">' . $qty . '</span>';
}

require_once __DIR__ . '/header.php';
?>

<!-- =========================================================
     PAGE ACTION BAR
     ========================================================= -->
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; flex-wrap:wrap; gap:12px;">
    <p style="color:var(--admin-text-muted); font-size:0.92rem; margin:0;">
        <?= count($products); ?> product(s) found
    </p>
    <a href="product_add.php" class="btn-admin btn-admin-primary">
        + Add New Product
    </a>
</div>

<!-- =========================================================
     SEARCH & FILTER TOOLBAR
     ========================================================= -->
<form method="GET" action="products.php" class="admin-card" style="padding:20px 24px; margin-bottom:24px;">
    <div style="display:grid; grid-template-columns:1fr auto auto auto; gap:12px; align-items:end; flex-wrap:wrap;">

        <!-- Search Input -->
        <div class="admin-form-group" style="margin:0;">
            <label class="admin-form-label">Search Products</label>
            <input type="text"
                   name="search"
                   class="admin-form-control"
                   placeholder="Search by name or description..."
                   value="<?= htmlspecialchars($search); ?>">
        </div>

        <!-- Category Filter -->
        <div class="admin-form-group" style="margin:0;">
            <label class="admin-form-label">Category</label>
            <select name="category" class="admin-form-control" style="min-width:160px;">
                <option value="0">All Categories</option>
                <?php foreach ($allCategories as $cat): ?>
                    <option value="<?= (int)$cat['id']; ?>" <?= $filterCat === (int)$cat['id'] ? 'selected' : ''; ?>>
                        <?= htmlspecialchars($cat['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Sort By -->
        <div class="admin-form-group" style="margin:0;">
            <label class="admin-form-label">Sort By</label>
            <select name="sort" class="admin-form-control" style="min-width:140px;">
                <option value="newest"     <?= $sortBy==='newest'     ? 'selected' : ''; ?>>Newest First</option>
                <option value="oldest"     <?= $sortBy==='oldest'     ? 'selected' : ''; ?>>Oldest First</option>
                <option value="price_asc"  <?= $sortBy==='price_asc'  ? 'selected' : ''; ?>>Price ↑</option>
                <option value="price_desc" <?= $sortBy==='price_desc' ? 'selected' : ''; ?>>Price ↓</option>
                <option value="stock_asc"  <?= $sortBy==='stock_asc'  ? 'selected' : ''; ?>>Stock ↑</option>
                <option value="name_asc"   <?= $sortBy==='name_asc'   ? 'selected' : ''; ?>>Name A–Z</option>
            </select>
        </div>

        <!-- Submit + Reset -->
        <div class="admin-form-group" style="margin:0; display:flex; gap:8px; align-items:flex-end;">
            <button type="submit" class="btn-admin btn-admin-primary" style="white-space:nowrap;">Filter</button>
            <a href="products.php" class="btn-admin btn-admin-secondary" style="white-space:nowrap;">Reset</a>
        </div>

    </div>
</form>

<!-- =========================================================
     PRODUCTS TABLE
     ========================================================= -->
<div class="admin-card">
    <div class="admin-card-header">
        <span class="admin-card-title">All Clothing Items</span>
    </div>

    <?php if (empty($products)): ?>
        <div style="padding:60px; text-align:center; color:var(--admin-text-muted);">
            <div style="font-size:2.5rem; margin-bottom:12px;">👕</div>
            <p>No products found. <a href="product_add.php" style="color:var(--admin-primary);">Add your first product →</a></p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="width:48px;">#</th>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Sizes</th>
                        <th>Stock</th>
                        <th>Featured</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <?php
                            $imagePath = '../assets/images/products/' . $product['image'];
                            $imgSrc    = (file_exists(__DIR__ . '/../assets/images/products/' . $product['image']))
                                         ? $imagePath
                                         : 'https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=80&auto=format&fit=crop&q=60';
                        ?>
                        <tr>
                            <!-- ID -->
                            <td style="color:var(--admin-text-muted); font-size:0.85rem;"><?= (int)$product['id']; ?></td>

                            <!-- Thumbnail + Name -->
                            <td>
                                <div style="display:flex; align-items:center; gap:12px;">
                                    <img src="<?= htmlspecialchars($imgSrc); ?>"
                                         alt="<?= htmlspecialchars($product['name']); ?>"
                                         class="admin-table-thumb">
                                    <div>
                                        <div style="font-weight:700; font-size:0.92rem; max-width:220px;">
                                            <?= htmlspecialchars($product['name']); ?>
                                        </div>
                                        <div style="font-size:0.78rem; color:var(--admin-text-muted); margin-top:2px;">
                                            <?= htmlspecialchars($product['available_colors']); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Category -->
                            <td><?= htmlspecialchars($product['category_name'] ?? '—'); ?></td>

                            <!-- Price -->
                            <td><strong style="color:var(--admin-primary);"><?= number_format((float)$product['price'], 2); ?> DH</strong></td>

                            <!-- Sizes -->
                            <td style="font-size:0.82rem; color:var(--admin-text-muted);"><?= htmlspecialchars($product['available_sizes']); ?></td>

                            <!-- Stock Level -->
                            <td><?= stockLabel((int)$product['stock_quantity']); ?></td>

                            <!-- Featured Badge -->
                            <td>
                                <?php if ($product['is_featured']): ?>
                                    <span class="status-badge completed">★ Featured</span>
                                <?php else: ?>
                                    <span style="color:var(--admin-text-muted); font-size:0.82rem;">—</span>
                                <?php endif; ?>
                            </td>

                            <!-- Action Buttons -->
                            <td style="text-align:right; white-space:nowrap;">
                                <a href="product_edit.php?id=<?= (int)$product['id']; ?>"
                                   class="btn-admin btn-admin-secondary btn-admin-sm">
                                    Edit
                                </a>
                                <a href="products.php?action=delete&id=<?= (int)$product['id']; ?>&csrf=<?= $csrfToken; ?>"
                                   class="btn-admin btn-admin-danger btn-admin-sm btn-confirm-action"
                                   data-confirm="Permanently delete &quot;<?= htmlspecialchars($product['name']); ?>&quot;? This cannot be undone."
                                   style="margin-left:6px;">
                                    Delete
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
