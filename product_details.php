<?php
/**
 * Single Product Details & Add to Cart Page (product_details.php)
 * 
 * Displays:
 * - High-resolution clothing image & stock status
 * - Interactive size selector (S, M, L, XL)
 * - Color selector
 * - Quantity selector
 * - Add to Cart processing logic with CSRF & session
 * - Cash on Delivery reassurance badge
 * - Related products from the same collection
 */

// Include database & helpers first to process POST before header rendering
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Validate and fetch product ID
$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($productId <= 0) {
    setFlashMessage('error', 'Invalid product requested.');
    header('Location: shop.php');
    exit;
}

// Fetch product with its category
try {
    $stmt = $pdo->prepare("
        SELECT p.*, c.name AS category_name, c.slug AS category_slug 
        FROM products p
        JOIN categories c ON p.category_id = c.id
        WHERE p.id = :id
        LIMIT 1
    ");
    $stmt->execute(['id' => $productId]);
    $product = $stmt->fetch();
} catch (PDOException $e) {
    $product = false;
}

if (!$product) {
    setFlashMessage('error', 'Product not found.');
    header('Location: shop.php');
    exit;
}

// Parse available sizes and colors
$availableSizes  = array_map('trim', explode(',', $product['available_sizes']));
$availableColors = array_map('trim', explode(',', $product['available_colors']));

// Handle "Add to Cart" POST Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_to_cart') {
    
    // Verify CSRF token
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'Security token expired. Please try again.');
        header("Location: product_details.php?id={$productId}");
        exit;
    }

    $selectedSize  = sanitize($_POST['selected_size'] ?? '');
    $selectedColor = sanitize($_POST['selected_color'] ?? '');
    $quantity      = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

    // Validation
    $errors = [];
    if (empty($selectedSize) || !in_array($selectedSize, $availableSizes)) {
        $errors[] = 'Please select a valid clothing size.';
    }
    if (empty($selectedColor) || !in_array($selectedColor, $availableColors)) {
        $errors[] = 'Please select a valid color.';
    }
    if ($quantity < 1) {
        $quantity = 1;
    }
    if ($quantity > $product['stock_quantity']) {
        $errors[] = "Only {$product['stock_quantity']} units available in stock.";
    }

    if (!empty($errors)) {
        setFlashMessage('error', implode(' ', $errors));
        header("Location: product_details.php?id={$productId}");
        exit;
    }

    // Initialize cart in session if not set
    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    // Create unique key per item variation (Product ID + Size + Color)
    $cartKey = $productId . '_' . $selectedSize . '_' . $selectedColor;

    if (isset($_SESSION['cart'][$cartKey])) {
        // Increment quantity if already in cart
        $newQty = $_SESSION['cart'][$cartKey]['quantity'] + $quantity;
        if ($newQty > $product['stock_quantity']) {
            $newQty = $product['stock_quantity'];
        }
        $_SESSION['cart'][$cartKey]['quantity'] = $newQty;
    } else {
        // Add new item to cart
        $_SESSION['cart'][$cartKey] = [
            'product_id'     => (int)$product['id'],
            'name'           => $product['name'],
            'slug'           => $product['slug'],
            'price'          => (float)$product['price'],
            'image'          => $product['image'],
            'category_name'  => $product['category_name'],
            'selected_size'  => $selectedSize,
            'selected_color' => $selectedColor,
            'quantity'       => $quantity,
            'stock_quantity' => (int)$product['stock_quantity'],
        ];
    }

    setFlashMessage('success', "Added \"{$product['name']} ({$selectedSize}, {$selectedColor})\" to your cart!");
    header('Location: cart.php');
    exit;
}

// Fetch related clothing items from same category
try {
    $relatedStmt = $pdo->prepare("
        SELECT p.*, c.name AS category_name, c.slug AS category_slug 
        FROM products p
        JOIN categories c ON p.category_id = c.id
        WHERE p.category_id = :cat_id AND p.id != :curr_id
        ORDER BY p.id DESC
        LIMIT 4
    ");
    $relatedStmt->execute([
        'cat_id'  => $product['category_id'],
        'curr_id' => $productId,
    ]);
    $relatedProducts = $relatedStmt->fetchAll();
} catch (PDOException $e) {
    $relatedProducts = [];
}

/**
 * Image URL helper
 */
function getProductDetailImageUrl(?string $imageName, string $categorySlug = ''): string {
    $localPath = 'assets/images/products/' . ($imageName ?? '');
    if (!empty($imageName) && file_exists(__DIR__ . '/' . $localPath)) {
        return $localPath;
    }
    $fallbacks = [
        't-shirts'            => 'https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=800&auto=format&fit=crop&q=80',
        'shirts'              => 'https://images.unsplash.com/photo-1596755094514-f87e34085b2c?w=800&auto=format&fit=crop&q=80',
        'hoodies-sweatshirts' => 'https://images.unsplash.com/photo-1556905055-8f358a7a47b2?w=800&auto=format&fit=crop&q=80',
        'jeans-denim'         => 'https://images.unsplash.com/photo-1542272604-780c96856592?w=800&auto=format&fit=crop&q=80',
        'jackets-coats'       => 'https://images.unsplash.com/photo-1548883354-7622d03aca27?w=800&auto=format&fit=crop&q=80',
    ];
    return $fallbacks[$categorySlug] ?? 'https://images.unsplash.com/photo-1489987707025-afc232f7ea0f?w=800&auto=format&fit=crop&q=80';
}

$pageTitle = htmlspecialchars($product['name']) . ' | THREAD&CO';
require_once __DIR__ . '/includes/header.php';
$mainImageUrl = getProductDetailImageUrl($product['image'], $product['category_slug']);
?>

<div class="container" style="padding-top: 24px; padding-bottom: 70px;">

    <!-- Breadcrumb -->
    <nav style="margin-bottom: 24px; font-size: 0.88rem; color: var(--text-muted);">
        <a href="index.php">Home</a> &rsaquo; 
        <a href="shop.php">Catalog</a> &rsaquo; 
        <a href="shop.php?category=<?= urlencode($product['category_slug']); ?>">
            <?= htmlspecialchars($product['category_name']); ?>
        </a> &rsaquo; 
        <strong style="color: var(--text-main);"><?= htmlspecialchars($product['name']); ?></strong>
    </nav>

    <!-- Main Product Detail Layout -->
    <div class="product-detail-layout">
        
        <!-- Left: Product Image & Badges -->
        <div class="product-detail-media">
            <img src="<?= htmlspecialchars($mainImageUrl); ?>" 
                 alt="<?= htmlspecialchars($product['name']); ?>">
            
            <?php if ($product['is_featured']): ?>
                <span class="product-badge" style="top: 20px; left: 20px;">Featured Selection</span>
            <?php endif; ?>
        </div>

        <!-- Right: Purchasing Controls & Information -->
        <div class="product-detail-info">
            
            <span class="product-category"><?= htmlspecialchars($product['category_name']); ?></span>
            
            <h1 style="font-size: 2.2rem; font-weight: 800; line-height: 1.25; margin: 8px 0 12px; color: var(--color-primary);">
                <?= htmlspecialchars($product['name']); ?>
            </h1>

            <div class="product-detail-price">
                <?= formatPrice($product['price']); ?>
            </div>

            <!-- Stock status indicator -->
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 20px;">
                <?php if ($product['stock_quantity'] > 0): ?>
                    <span style="display:inline-block; width:10px; height:10px; border-radius:50%; background: var(--success);"></span>
                    <span style="font-size: 0.9rem; font-weight: 600; color: #065f46;">
                        In Stock (<?= (int)$product['stock_quantity']; ?> units available)
                    </span>
                <?php else: ?>
                    <span style="display:inline-block; width:10px; height:10px; border-radius:50%; background: var(--danger);"></span>
                    <span style="font-size: 0.9rem; font-weight: 600; color: #991b1b;">
                        Out of Stock
                    </span>
                <?php endif; ?>
            </div>

            <!-- Description -->
            <p class="product-detail-desc">
                <?= nl2br(htmlspecialchars($product['description'])); ?>
            </p>

            <!-- Purchasing Form -->
            <?php if ($product['stock_quantity'] > 0): ?>
                <form id="add-to-cart-form" method="POST" action="product_details.php?id=<?= (int)$product['id']; ?>">
                    <input type="hidden" name="action" value="add_to_cart">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken(); ?>">

                    <!-- Size Selection -->
                    <label class="option-label">
                        Select Size: <strong id="display-selected-size" style="color: var(--color-accent); font-weight: 700;">(Choose)</strong>
                    </label>
                    <div class="option-group">
                        <?php foreach ($availableSizes as $idx => $size): ?>
                            <input type="radio" 
                                   name="selected_size" 
                                   id="size_<?= $idx; ?>" 
                                   value="<?= htmlspecialchars($size); ?>" 
                                   class="size-pill-radio" 
                                   required>
                            <label for="size_<?= $idx; ?>" class="size-pill-label">
                                <?= htmlspecialchars($size); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <!-- Color Selection -->
                    <label class="option-label">
                        Select Color: <strong id="display-selected-color" style="color: var(--color-primary); font-weight: 700;">(Choose)</strong>
                    </label>
                    <div class="option-group">
                        <?php foreach ($availableColors as $cIdx => $color): ?>
                            <input type="radio" 
                                   name="selected_color" 
                                   id="color_<?= $cIdx; ?>" 
                                   value="<?= htmlspecialchars($color); ?>" 
                                   class="color-pill-radio" 
                                   required>
                            <label for="color_<?= $cIdx; ?>" class="color-pill-label">
                                <?= htmlspecialchars($color); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <!-- Quantity & Add to Cart Button -->
                    <div style="display: flex; gap: 16px; align-items: center; margin-top: 10px;">
                        <div>
                            <label class="form-label" style="font-size: 0.8rem; text-transform: uppercase;">Quantity</label>
                            <input type="number" 
                                   name="quantity" 
                                   class="qty-input form-control" 
                                   value="1" 
                                   min="1" 
                                   max="<?= (int)$product['stock_quantity']; ?>">
                        </div>
                        <div style="flex-grow: 1; padding-top: 24px;">
                            <button type="submit" class="btn btn-primary btn-block" style="padding: 14px 28px; font-size: 1.05rem;">
                                🛍️ Add to Shopping Bag
                            </button>
                        </div>
                    </div>
                </form>
            <?php else: ?>
                <div style="background: var(--bg-subtle); padding: 18px; border-radius: var(--radius-md); color: var(--text-muted); font-size: 0.95rem;">
                    This clothing item is currently sold out. Check back soon for our restock!
                </div>
            <?php endif; ?>

            <!-- Cash on Delivery Reassurance Box -->
            <div class="cod-badge">
                <span>💵</span>
                <span><strong>Cash on Delivery (COD) Available:</strong> Inspect your package and pay upon arrival at your doorstep.</span>
            </div>

            <!-- Features list -->
            <div style="margin-top: 30px; border-top: 1px solid var(--border-light); padding-top: 20px;">
                <ul style="list-style: none; font-size: 0.88rem; color: var(--text-muted); display: grid; gap: 10px;">
                    <li>✓ 100% Breathable Combed Fabric</li>
                    <li>✓ Pre-shrunk &amp; color-fast materials</li>
                    <li>✓ Free size exchanges within 14 days of delivery</li>
                </ul>
            </div>

        </div>

    </div>

    <!-- Related Clothes Section -->
    <?php if (!empty($relatedProducts)): ?>
        <section style="margin-top: 40px;">
            <div class="section-header">
                <div>
                    <h2 class="section-title">You Might Also Like</h2>
                    <p class="section-subtitle">More popular pieces from the <?= htmlspecialchars($product['category_name']); ?> collection</p>
                </div>
            </div>

            <div class="products-grid">
                <?php foreach ($relatedProducts as $relItem): ?>
                    <?php 
                        $relSizes = array_map('trim', explode(',', $relItem['available_sizes']));
                        $relImg = getProductDetailImageUrl($relItem['image'], $relItem['category_slug']);
                    ?>
                    <article class="product-card">
                        <div class="product-image-wrap">
                            <a href="product_details.php?id=<?= (int)$relItem['id']; ?>" style="display:block; width:100%; height:100%;">
                                <img src="<?= htmlspecialchars($relImg); ?>" 
                                     alt="<?= htmlspecialchars($relItem['name']); ?>" 
                                     class="product-image" 
                                     loading="lazy">
                            </a>
                        </div>
                        <div class="product-info">
                            <div class="product-category"><?= htmlspecialchars($relItem['category_name']); ?></div>
                            <h3 class="product-title">
                                <a href="product_details.php?id=<?= (int)$relItem['id']; ?>">
                                    <?= htmlspecialchars($relItem['name']); ?>
                                </a>
                            </h3>
                            <div class="product-meta-sizes">
                                <strong>Sizes:</strong>
                                <?php foreach ($relSizes as $s): ?>
                                    <span><?= htmlspecialchars($s); ?></span>
                                <?php endforeach; ?>
                            </div>
                            <div class="product-bottom">
                                <div class="product-price"><?= formatPrice($relItem['price']); ?></div>
                                <a href="product_details.php?id=<?= (int)$relItem['id']; ?>" class="btn btn-dark btn-sm">
                                    View Details
                                </a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

</div>

<?php
// Load global footer
require_once __DIR__ . '/includes/footer.php';
?>
