<?php
/**
 * Storefront Homepage (index.php)
 * 
 * The main landing page for THREAD&CO clothing store.
 * Displays:
 * - Hero lifestyle banner with Call to Action
 * - Category showcase cards
 * - Featured clothing collection
 * - Cash on Delivery assurance highlights
 */

// Define page title and load global header
$pageTitle = 'THREAD&CO | Modern Apparel & Everyday Essentials';
require_once __DIR__ . '/includes/header.php';

// Fetch featured products from database
try {
    $featuredStmt = $pdo->prepare("
        SELECT p.*, c.name AS category_name, c.slug AS category_slug 
        FROM products p
        JOIN categories c ON p.category_id = c.id
        WHERE p.is_featured = 1
        ORDER BY p.id DESC
        LIMIT 8
    ");
    $featuredStmt->execute();
    $featuredProducts = $featuredStmt->fetchAll();
} catch (PDOException $e) {
    $featuredProducts = [];
}

// Fetch all categories for quick filter bar
try {
    $categoriesStmt = $pdo->query("SELECT * FROM categories ORDER BY id ASC");
    $allCategories = $categoriesStmt->fetchAll();
} catch (PDOException $e) {
    $allCategories = [];
}

/**
 * Helper to get a product image URL or fallback placeholder
 */
function getProductImageUrl(?string $imageName, string $categorySlug = ''): string {
    $localPath = 'assets/images/products/' . ($imageName ?? '');
    if (!empty($imageName) && file_exists(__DIR__ . '/' . $localPath)) {
        return $localPath;
    }
    // High-quality fallback images based on clothing type
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

<!-- Hero Section -->
<section class="hero-section">
    <div class="container">
        <div class="hero-grid">
            <div class="hero-content">
                <span class="hero-tag">✨ Spring Collection 2026</span>
                <h1 class="hero-title">
                    Timeless Fashion for <span>Modern Living</span>
                </h1>
                <p class="hero-description">
                    Premium cotton t-shirts, relaxed hoodies, versatile denim and outerwear. 
                    Crafted with sustainable fabrics and tailored for exceptional everyday comfort.
                </p>
                <div class="hero-cta-group">
                    <a href="shop.php" class="btn btn-primary" style="padding: 14px 30px; font-size: 1rem;">
                        Shop All Clothes &rarr;
                    </a>
                    <a href="#featured" class="btn btn-outline" style="background: rgba(255,255,255,0.08); color:#ffffff; border-color: rgba(255,255,255,0.2);">
                        Featured Styles
                    </a>
                </div>
            </div>
            <div class="hero-image-wrap" style="text-align: center;">
                <img src="https://images.unsplash.com/photo-1441986300917-64674bd600d8?w=700&auto=format&fit=crop&q=80" 
                     alt="THREAD&CO Apparel" 
                     style="border-radius: var(--radius-lg); box-shadow: var(--shadow-xl); max-height: 420px; width: 100%; object-fit: cover;">
            </div>
        </div>
    </div>
</section>

<!-- Main Storefront Content -->
<main class="container">

    <!-- Categories Bar -->
    <div style="margin-bottom: 24px;">
        <div class="section-header">
            <div>
                <h2 class="section-title">Explore by Category</h2>
                <p class="section-subtitle">Find your exact fit across our core apparel lines</p>
            </div>
            <a href="shop.php" style="color: var(--color-accent); font-weight: 600; font-size: 0.92rem;">View Catalog &rarr;</a>
        </div>

        <div class="categories-bar">
            <a href="shop.php" class="category-pill active">All Categories</a>
            <?php foreach ($allCategories as $category): ?>
                <a href="shop.php?category=<?= urlencode($category['slug']); ?>" class="category-pill">
                    <?= htmlspecialchars($category['name']); ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Featured Products Showcase -->
    <section id="featured" style="margin-bottom: 50px;">
        <div class="section-header">
            <div>
                <h2 class="section-title">Featured Clothes</h2>
                <p class="section-subtitle">Handpicked essentials trending this week</p>
            </div>
        </div>

        <?php if (empty($featuredProducts)): ?>
            <div style="background: var(--bg-surface); padding: 50px; text-align: center; border-radius: var(--radius-lg); border: 1px solid var(--border-light);">
                <p style="color: var(--text-muted); font-size: 1.1rem;">No featured products available at this time.</p>
                <a href="shop.php" class="btn btn-primary" style="margin-top: 16px;">Browse All Items</a>
            </div>
        <?php else: ?>
            <div class="products-grid">
                <?php foreach ($featuredProducts as $product): ?>
                    <?php 
                        $sizes = array_map('trim', explode(',', $product['available_sizes']));
                        $imageUrl = getProductImageUrl($product['image'], $product['category_slug']);
                    ?>
                    <article class="product-card">
                        <div class="product-image-wrap">
                            <span class="product-badge">Featured</span>
                            
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

                            <!-- Available Sizes Preview -->
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
    </section>

    <!-- Value Propositions Banner -->
    <section style="background: var(--bg-surface); border: 1px solid var(--border-light); border-radius: var(--radius-lg); padding: 40px; margin-bottom: 60px;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 30px; text-align: center;">
            <div>
                <div style="font-size: 2rem; margin-bottom: 10px;">💵</div>
                <h4 style="font-size: 1.05rem; font-weight: 700; margin-bottom: 6px;">Cash on Delivery</h4>
                <p style="color: var(--text-muted); font-size: 0.88rem;">Pay at your doorstep with zero pre-payment required.</p>
            </div>
            <div>
                <div style="font-size: 2rem; margin-bottom: 10px;">🚚</div>
                <h4 style="font-size: 1.05rem; font-weight: 700; margin-bottom: 6px;">Fast Nationwide Delivery</h4>
                <p style="color: var(--text-muted); font-size: 0.88rem;">Safe tracked dispatch directly to your home address.</p>
            </div>
            <div>
                <div style="font-size: 2rem; margin-bottom: 10px;">🧵</div>
                <h4 style="font-size: 1.05rem; font-weight: 700; margin-bottom: 6px;">100% Combed Cotton</h4>
                <p style="color: var(--text-muted); font-size: 0.88rem;">Breathable, durable textiles tailored for daily wear.</p>
            </div>
            <div>
                <div style="font-size: 2rem; margin-bottom: 10px;">🔄</div>
                <h4 style="font-size: 1.05rem; font-weight: 700; margin-bottom: 6px;">Easy Size Exchange</h4>
                <p style="color: var(--text-muted); font-size: 0.88rem;">Hassle-free size replacement if the fit isn't right.</p>
            </div>
        </div>
    </section>

</main>

<?php
// Load global storefront footer
require_once __DIR__ . '/includes/footer.php';
?>
