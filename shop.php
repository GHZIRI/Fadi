<?php
/**
 * Clothing Catalog & Search Page (shop.php)
 * 
 * Allows customers to:
 * - Browse all apparel items
 * - Filter by category
 * - Search by keyword
 * - Filter by clothing size (S, M, L, XL)
 * - Sort by price or date
 */

// Define page title and load header
$pageTitle = 'Catalog & Collections | THREAD&CO';
require_once __DIR__ . '/includes/header.php';

// Get and sanitize query parameters
$selectedCategory = isset($_GET['category']) ? trim($_GET['category']) : '';
$searchQuery      = isset($_GET['search']) ? trim($_GET['search']) : '';
$selectedSize     = isset($_GET['size']) ? trim($_GET['size']) : '';
$sortOrder        = isset($_GET['sort']) ? trim($_GET['sort']) : 'newest';

// Fetch all categories for filter sidebar/pills
try {
    $catStmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
    $categories = $catStmt->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

// Build dynamic SQL query safely with prepared statements
$sql = "
    SELECT p.*, c.name AS category_name, c.slug AS category_slug 
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE 1=1
";
$params = [];

// Filter by category slug
if (!empty($selectedCategory)) {
    $sql .= " AND c.slug = :category_slug";
    $params['category_slug'] = $selectedCategory;
}

// Search by keyword in name or description
if (!empty($searchQuery)) {
    $sql .= " AND (p.name LIKE :search_name OR p.description LIKE :search_desc)";
    $params['search_name'] = '%' . $searchQuery . '%';
    $params['search_desc'] = '%' . $searchQuery . '%';
}

// Filter by size
if (!empty($selectedSize)) {
    $sql .= " AND p.available_sizes LIKE :size";
    $params['size'] = '%' . $selectedSize . '%';
}

// Apply sorting
switch ($sortOrder) {
    case 'price_low':
        $sql .= " ORDER BY p.price ASC";
        break;
    case 'price_high':
        $sql .= " ORDER BY p.price DESC";
        break;
    case 'name_asc':
        $sql .= " ORDER BY p.name ASC";
        break;
    default:
        $sql .= " ORDER BY p.id DESC";
        break;
}

// Execute query
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    $products = [];
}

/**
 * Fallback image helper
 */
function getCatalogImageUrl(?string $imageName, string $categorySlug = ''): string {
    $localPath = 'assets/images/products/' . ($imageName ?? '');
    if (!empty($imageName) && file_exists(__DIR__ . '/' . $localPath)) {
        return $localPath;
    }
    $fallbacks = [
        't-shirts'            => 'https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=600&auto=format&fit=crop&q=80',
        'shirts'              => 'https://images.unsplash.com/photo-1596755094514-f87e34085b2c?w=600&auto=format&fit=crop&q=80',
        'hoodies-sweatshirts' => 'https://images.unsplash.com/photo-1556905055-8f358a7a47b2?w=600&auto=format&fit=crop&q=80',
        'jeans-denim'         => 'https://images.unsplash.com/photo-1542272604-780c96856592?w=600&auto=format&fit=crop&q=80',
        'jackets-coats'       => 'https://images.unsplash.com/photo-1548883354-7622d03aca27?w=600&auto=format&fit=crop&q=80',
    ];
    return $fallbacks[$categorySlug] ?? 'https://images.unsplash.com/photo-1489987707025-afc232f7ea0f?w=600&auto=format&fit=crop&q=80';
}
?>

<div class="container" style="padding-top: 30px; padding-bottom: 60px;">

    <!-- Breadcrumb & Header -->
    <div style="margin-bottom: 24px;">
        <span style="font-size: 0.85rem; color: var(--text-muted);">
            <a href="index.php">Home</a> &rsaquo; 
            <strong>All Collections</strong>
            <?php if (!empty($selectedCategory)): ?>
                &rsaquo; <?= htmlspecialchars(ucwords(str_replace('-', ' ', $selectedCategory))); ?>
            <?php endif; ?>
        </span>
        <h1 style="font-size: 2.2rem; font-weight: 800; letter-spacing: -0.02em; margin-top: 8px;">
            <?php 
                if (!empty($searchQuery)) {
                    echo 'Search results for: "' . htmlspecialchars($searchQuery) . '"';
                } elseif (!empty($selectedCategory)) {
                    echo htmlspecialchars(ucwords(str_replace('-', ' ', $selectedCategory)));
                } else {
                    echo 'All Clothing Collection';
                }
            ?>
        </h1>
    </div>

    <!-- Category Filter Pills -->
    <div class="categories-bar" style="margin-bottom: 30px;">
        <a href="shop.php" class="category-pill <?= empty($selectedCategory) ? 'active' : ''; ?>">
            All (<?= count($products); ?>)
        </a>
        <?php foreach ($categories as $cat): ?>
            <a href="shop.php?category=<?= urlencode($cat['slug']); ?><?= !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : ''; ?>" 
               class="category-pill <?= $selectedCategory === $cat['slug'] ? 'active' : ''; ?>">
                <?= htmlspecialchars($cat['name']); ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Filter Bar (Size & Sort Controls) -->
    <form method="GET" action="shop.php" style="background: var(--bg-surface); border: 1px solid var(--border-light); border-radius: var(--radius-md); padding: 16px 20px; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 36px;">
        
        <!-- Preserve existing category or search filters in hidden fields -->
        <?php if (!empty($selectedCategory)): ?>
            <input type="hidden" name="category" value="<?= htmlspecialchars($selectedCategory); ?>">
        <?php endif; ?>
        <?php if (!empty($searchQuery)): ?>
            <input type="hidden" name="search" value="<?= htmlspecialchars($searchQuery); ?>">
        <?php endif; ?>

        <!-- Left Filters: Size Filter -->
        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
            <label style="font-size: 0.88rem; font-weight: 700; color: var(--text-muted);">FILTER BY SIZE:</label>
            <select name="size" onchange="this.form.submit()" class="form-control" style="width: auto; padding: 6px 12px; font-size: 0.88rem;">
                <option value="">All Sizes</option>
                <?php foreach (['S', 'M', 'L', 'XL'] as $size): ?>
                    <option value="<?= $size; ?>" <?= $selectedSize === $size ? 'selected' : ''; ?>>
                        Size <?= $size; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Right Filters: Sort By -->
        <div style="display: flex; align-items: center; gap: 12px;">
            <label style="font-size: 0.88rem; font-weight: 700; color: var(--text-muted);">SORT BY:</label>
            <select name="sort" onchange="this.form.submit()" class="form-control" style="width: auto; padding: 6px 12px; font-size: 0.88rem;">
                <option value="newest" <?= $sortOrder === 'newest' ? 'selected' : ''; ?>>Newest Arrivals</option>
                <option value="price_low" <?= $sortOrder === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                <option value="price_high" <?= $sortOrder === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                <option value="name_asc" <?= $sortOrder === 'name_asc' ? 'selected' : ''; ?>>Alphabetical (A - Z)</option>
            </select>
            
            <?php if (!empty($selectedCategory) || !empty($searchQuery) || !empty($selectedSize)): ?>
                <a href="shop.php" class="btn btn-outline btn-sm" style="color: var(--danger); border-color: #fca5a5;">
                    Clear Filters
                </a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Product Grid -->
    <?php if (empty($products)): ?>
        <div style="background: var(--bg-surface); padding: 60px 20px; text-align: center; border-radius: var(--radius-lg); border: 1px solid var(--border-light); margin: 30px 0;">
            <div style="font-size: 3rem; margin-bottom: 12px;">🔍</div>
            <h3 style="font-size: 1.4rem; font-weight: 700; margin-bottom: 8px;">No clothing found</h3>
            <p style="color: var(--text-muted); max-width: 420px; margin: 0 auto 20px;">
                We couldn't find any items matching your active search or filters. Try clearing your filters or searching for another term.
            </p>
            <a href="shop.php" class="btn btn-primary">Reset All Filters</a>
        </div>
    <?php else: ?>
        <div class="products-grid">
            <?php foreach ($products as $product): ?>
                <?php 
                    $sizes = array_map('trim', explode(',', $product['available_sizes']));
                    $imageUrl = getCatalogImageUrl($product['image'], $product['category_slug']);
                ?>
                <article class="product-card">
                    <div class="product-image-wrap">
                        <?php if ($product['is_featured']): ?>
                            <span class="product-badge">Featured</span>
                        <?php endif; ?>

                        <?php if ($product['stock_quantity'] <= 15): ?>
                            <span class="stock-badge-low">Only <?= (int)$product['stock_quantity']; ?> left</span>
                        <?php endif; ?>

                        <a href="product_details.php?id=<?= (int)$product['id']; ?>" style="display:block; width:100%; height:100%;">
                            <img src="<?= htmlspecialchars($imageUrl); ?>" 
                                 alt="<?= htmlspecialchars($product['name']); ?>" 
                                 class="product-image" 
                                 loading="lazy">
                        </a>
                    </div>

                    <div class="product-info">
                        <div class="product-category"><?= htmlspecialchars($product['category_name']); ?></div>
                        
                        <h3 class="product-title">
                            <a href="product_details.php?id=<?= (int)$product['id']; ?>">
                                <?= htmlspecialchars($product['name']); ?>
                            </a>
                        </h3>

                        <!-- Sizes available -->
                        <div class="product-meta-sizes">
                            <strong>Sizes:</strong>
                            <?php foreach ($sizes as $size): ?>
                                <span><?= htmlspecialchars($size); ?></span>
                            <?php endforeach; ?>
                        </div>

                        <div class="product-bottom">
                            <div class="product-price"><?= formatPrice($product['price']); ?></div>
                            <a href="product_details.php?id=<?= (int)$product['id']; ?>" class="btn btn-dark btn-sm">
                                View Details
                            </a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<?php
// Load footer
require_once __DIR__ . '/includes/footer.php';
?>
