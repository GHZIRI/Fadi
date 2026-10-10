<?php
/**
 * Global Storefront Header & Navigation
 * 
 * Shared header included across customer-facing pages.
 * Handles database connection, security helpers, top navbar,
 * search form, cart counters, user menu, and flash alerts.
 */

// Include core database and helper functions
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

// Fetch all categories for navigation bar
try {
    $navCatStmt = $pdo->query("SELECT id, name, slug FROM categories ORDER BY name ASC");
    $navCategories = $navCatStmt->fetchAll();
} catch (PDOException $e) {
    $navCategories = [];
}

// Current page title (can be overridden by individual pages)
$pageTitle = $pageTitle ?? 'Modern Clothing Store | Premium Apparel';

// Current active page name for navigation highlighting
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle); ?></title>
    
    <!-- Meta SEO -->
    <meta name="description" content="Discover modern streetwear, casual shirts, denim jeans and jackets. High quality apparel with cash on delivery.">
    
    <!-- Core Stylesheet -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <!-- Top Promo / Announcement Bar -->
    <div class="top-bar">
        <div class="container">
            <span>✨ Spring Collection Now Live | <strong>Cash on Delivery (COD)</strong> Available Across All Orders</span>
        </div>
    </div>

    <!-- Main Navigation Header -->
    <header class="site-header">
        <div class="container">
            <nav class="navbar">
                
                <!-- Brand Logo -->
                <a href="index.php" class="nav-logo">
                    THREAD<span>&amp;</span>CO
                </a>

                <!-- Navigation Links -->
                <ul class="nav-menu">
                    <li>
                        <a href="index.php" class="<?= $currentPage === 'index.php' ? 'active' : ''; ?>">Home</a>
                    </li>
                    <li>
                        <a href="shop.php" class="<?= $currentPage === 'shop.php' && empty($_GET['category']) ? 'active' : ''; ?>">All Clothes</a>
                    </li>
                    <?php foreach (array_slice($navCategories, 0, 4) as $cat): ?>
                        <li>
                            <a href="shop.php?category=<?= urlencode($cat['slug']); ?>" 
                               class="<?= (isset($_GET['category']) && $_GET['category'] === $cat['slug']) ? 'active' : ''; ?>">
                                <?= htmlspecialchars($cat['name']); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <!-- Search, Cart & Account Actions -->
                <div class="nav-actions">
                    
                    <!-- Search Form -->
                    <form action="shop.php" method="GET" class="nav-search-form">
                        <svg class="nav-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                        <input type="text" 
                               name="search" 
                               class="nav-search-input" 
                               placeholder="Search clothes..." 
                               value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                    </form>

                    <!-- Cart Icon with Dynamic Count -->
                    <a href="cart.php" class="cart-link" title="View Shopping Cart">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                            <line x1="3" y1="6" x2="21" y2="6"></line>
                            <path d="M16 10a4 4 0 0 1-8 0"></path>
                        </svg>
                        <span class="cart-badge" id="cart-item-count"><?= getCartItemCount(); ?></span>
                    </a>

                    <!-- User Account / Admin Links -->
                    <?php if (isLoggedIn()): ?>
                        <?php if (isAdmin()): ?>
                            <a href="admin/index.php" class="user-btn" style="background: #1e293b; color: #ffffff;">Admin Panel</a>
                        <?php else: ?>
                            <span class="user-btn">Hi, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Customer'); ?></span>
                        <?php endif; ?>
                        <a href="logout.php" class="user-btn" style="color: #ef4444;" title="Sign Out">Logout</a>
                    <?php else: ?>
                        <a href="login.php" class="user-btn">Sign In</a>
                    <?php endif; ?>

                </div>
            </nav>
        </div>
    </header>

    <!-- Flash Alert System -->
    <div class="container">
        <?php $flash = getFlashMessage(); ?>
        <?php if ($flash): ?>
            <div class="flash-container">
                <div class="flash-alert flash-<?= htmlspecialchars($flash['type']); ?>">
                    <span><?= htmlspecialchars($flash['message']); ?></span>
                    <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; cursor:pointer; font-weight:bold; font-size:1.1rem; color:inherit;">&times;</button>
                </div>
            </div>
        <?php endif; ?>
    </div>
