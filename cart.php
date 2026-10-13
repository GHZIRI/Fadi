<?php
/**
 * Shopping Cart Management Page (cart.php)
 * 
 * Allows customers to:
 * - View clothes currently in their shopping bag
 * - Modify item quantities (with real-time stock checks)
 * - Remove single items or empty the cart
 * - Review price subtotal and free shipping thresholds
 * - Proceed directly to Cash-on-Delivery checkout
 */

// Initialize config and helpers
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Ensure cart array exists in session
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// -------------------------------------------------------------
// Handle Actions: Remove Item, Update Quantities, Clear Cart
// -------------------------------------------------------------

// 1. Remove Single Item
if (isset($_GET['action']) && $_GET['action'] === 'remove' && isset($_GET['key'])) {
    $itemKey = $_GET['key'];
    if (isset($_SESSION['cart'][$itemKey])) {
        $removedName = $_SESSION['cart'][$itemKey]['name'];
        unset($_SESSION['cart'][$itemKey]);
        setFlashMessage('info', "Removed \"{$removedName}\" from your shopping bag.");
    }
    header('Location: cart.php');
    exit;
}

// 2. Clear Whole Cart
if (isset($_GET['action']) && $_GET['action'] === 'clear') {
    $_SESSION['cart'] = [];
    setFlashMessage('info', 'Your shopping bag has been cleared.');
    header('Location: cart.php');
    exit;
}

// 3. Update Quantities (POST Form)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_cart') {
    
    // CSRF check
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'Session expired. Please try again.');
        header('Location: cart.php');
        exit;
    }

    $quantities = $_POST['quantities'] ?? [];
    
    foreach ($quantities as $key => $qty) {
        $qty = (int)$qty;
        if (isset($_SESSION['cart'][$key])) {
            if ($qty <= 0) {
                // Remove item if quantity set to 0
                unset($_SESSION['cart'][$key]);
            } else {
                // Verify against current database stock
                $stmt = $pdo->prepare("SELECT stock_quantity FROM products WHERE id = :id");
                $stmt->execute(['id' => $_SESSION['cart'][$key]['product_id']]);
                $currentStock = (int)$stmt->fetchColumn();

                if ($qty > $currentStock) {
                    $qty = $currentStock;
                    setFlashMessage('warning', "Quantity for \"{$_SESSION['cart'][$key]['name']}\" adjusted to maximum available stock ({$currentStock}).");
                }
                
                $_SESSION['cart'][$key]['quantity'] = $qty;
            }
        }
    }

    setFlashMessage('success', 'Your shopping bag has been updated.');
    header('Location: cart.php');
    exit;
}

// -------------------------------------------------------------
// Calculate Financial Totals
// -------------------------------------------------------------
$subtotal = 0.0;
foreach ($_SESSION['cart'] as $item) {
    $subtotal += ((float)$item['price'] * (int)$item['quantity']);
}

// Delivery calculation using global settings (30 DH standard, or Free on orders >= 300 DH)
$freeShippingThreshold = FREE_SHIPPING_THRESHOLD;
$shippingCost = calculateShippingFee($subtotal);
$grandTotal = $subtotal + $shippingCost;

/**
 * Image helper
 */
function getCartImageUrl(?string $imageName): string {
    $localPath = 'assets/images/products/' . ($imageName ?? '');
    if (!empty($imageName) && file_exists(__DIR__ . '/' . $localPath)) {
        return $localPath;
    }
    return 'https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=200&auto=format&fit=crop&q=80';
}

$pageTitle = 'Shopping Bag (' . getCartItemCount() . ') | THREAD&CO';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container" style="padding-top: 30px; padding-bottom: 70px;">

    <!-- Heading -->
    <div style="margin-bottom: 30px;">
        <span style="font-size: 0.88rem; color: var(--text-muted);">
            <a href="index.php">Home</a> &rsaquo; <strong>Shopping Bag</strong>
        </span>
        <h1 style="font-size: 2.2rem; font-weight: 800; letter-spacing: -0.02em; margin-top: 8px;">
            Your Shopping Bag
        </h1>
    </div>

    <?php if (empty($_SESSION['cart'])): ?>
        
        <!-- Empty Cart Display -->
        <div style="background: var(--bg-surface); border: 1px solid var(--border-light); border-radius: var(--radius-lg); padding: 70px 20px; text-align: center; max-width: 650px; margin: 40px auto; box-shadow: var(--shadow-sm);">
            <div style="font-size: 3.5rem; margin-bottom: 16px;">🛍️</div>
            <h2 style="font-size: 1.6rem; font-weight: 800; margin-bottom: 10px;">Your bag is currently empty</h2>
            <p style="color: var(--text-muted); font-size: 1rem; margin-bottom: 28px; line-height: 1.6;">
                Looks like you haven't added any clothing pieces yet. Explore our spring drop and discover timeless apparel.
            </p>
            <a href="shop.php" class="btn btn-primary" style="padding: 14px 32px; font-size: 1rem;">
                Browse Clothing Catalog &rarr;
            </a>
        </div>

    <?php else: ?>

        <!-- Cart Grid Layout -->
        <div class="cart-layout">
            
            <!-- Left: Items Table & Update Controls -->
            <div>
                <form method="POST" action="cart.php">
                    <input type="hidden" name="action" value="update_cart">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken(); ?>">

                    <div style="overflow-x: auto;">
                        <table class="cart-table">
                            <thead>
                                <tr>
                                    <th style="width: 45%;">Item Details</th>
                                    <th>Price</th>
                                    <th>Quantity</th>
                                    <th>Total</th>
                                    <th style="text-align: right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($_SESSION['cart'] as $key => $item): ?>
                                    <?php 
                                        $itemTotal = (float)$item['price'] * (int)$item['quantity'];
                                        $imgUrl = getCartImageUrl($item['image']);
                                    ?>
                                    <tr>
                                        <!-- Item Image & Info -->
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 16px;">
                                                <img src="<?= htmlspecialchars($imgUrl); ?>" 
                                                     alt="<?= htmlspecialchars($item['name']); ?>" 
                                                     style="width: 70px; height: 85px; object-fit: cover; border-radius: var(--radius-sm); border: 1px solid var(--border-light);">
                                                <div>
                                                    <div class="cart-item-title">
                                                        <a href="product_details.php?id=<?= (int)$item['product_id']; ?>" style="color: var(--text-main); font-weight: 700;">
                                                            <?= htmlspecialchars($item['name']); ?>
                                                        </a>
                                                    </div>
                                                    <div class="cart-item-meta" style="display: flex; gap: 8px; margin-top: 6px;">
                                                        <span style="background: var(--bg-subtle); padding: 2px 8px; border-radius: 4px; font-weight: 600;">
                                                            Size: <?= htmlspecialchars($item['selected_size']); ?>
                                                        </span>
                                                        <span style="background: var(--bg-subtle); padding: 2px 8px; border-radius: 4px;">
                                                            Color: <?= htmlspecialchars($item['selected_color']); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Unit Price -->
                                        <td style="font-weight: 600; color: var(--text-main);">
                                            <?= formatPrice($item['price']); ?>
                                        </td>

                                        <!-- Quantity Input -->
                                        <td>
                                            <input type="number" 
                                                   name="quantities[<?= htmlspecialchars($key); ?>]" 
                                                   value="<?= (int)$item['quantity']; ?>" 
                                                   min="1" 
                                                   max="<?= (int)$item['stock_quantity']; ?>" 
                                                   class="qty-input form-control" 
                                                   style="padding: 6px 8px;">
                                        </td>

                                        <!-- Subtotal per item -->
                                        <td style="font-weight: 800; color: var(--color-primary); font-size: 1.05rem;">
                                            <?= formatPrice($itemTotal); ?>
                                        </td>

                                        <!-- Remove Button -->
                                        <td style="text-align: right;">
                                            <a href="cart.php?action=remove&key=<?= urlencode($key); ?>" 
                                               class="btn-remove-cart" 
                                               style="color: var(--danger); font-size: 0.85rem; font-weight: 600;"
                                               title="Remove item">
                                                ✕ Remove
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Cart Actions Bar -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; flex-wrap: wrap; gap: 12px;">
                        <a href="shop.php" class="btn btn-outline">
                            &larr; Continue Shopping
                        </a>
                        <div style="display: flex; gap: 10px;">
                            <a href="cart.php?action=clear" class="btn btn-outline btn-confirm-delete" style="color: var(--danger); border-color: #fca5a5;">
                                Clear Bag
                            </a>
                            <button type="submit" class="btn btn-dark">
                                ↻ Update Quantities
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Right: Order Summary Card -->
            <div>
                <div class="summary-card">
                    <h3 class="summary-title">Order Summary</h3>

                    <!-- Free shipping indicator -->
                    <?php if ($subtotal < $freeShippingThreshold): ?>
                        <div style="background: var(--warning-bg); border: 1px solid #fde68a; color: #92400e; padding: 10px 14px; border-radius: var(--radius-md); font-size: 0.82rem; margin-bottom: 16px;">
                            Add <strong><?= formatPrice($freeShippingThreshold - $subtotal); ?></strong> more to unlock <strong>FREE Delivery</strong>!
                        </div>
                    <?php else: ?>
                        <div style="background: var(--success-bg); border: 1px solid #a7f3d0; color: #065f46; padding: 10px 14px; border-radius: var(--radius-md); font-size: 0.82rem; margin-bottom: 16px;">
                            🎉 You have qualified for <strong>FREE Delivery</strong>!
                        </div>
                    <?php endif; ?>

                    <div class="summary-row">
                        <span>Items Subtotal:</span>
                        <strong style="color: var(--text-main);"><?= formatPrice($subtotal); ?></strong>
                    </div>

                    <div class="summary-row">
                        <span>Shipping Estimate:</span>
                        <span><?= $shippingCost === 0.0 ? '<span style="color: var(--success); font-weight:700;">FREE</span>' : formatPrice($shippingCost); ?></span>
                    </div>

                    <div class="summary-row">
                        <span>Payment Method:</span>
                        <strong style="color: #166534;">Cash on Delivery (COD)</strong>
                    </div>

                    <div class="summary-row total">
                        <span>Total Due:</span>
                        <span style="color: var(--color-primary);"><?= formatPrice($grandTotal); ?></span>
                    </div>

                    <!-- Checkout Button -->
                    <div style="margin-top: 24px;">
                        <a href="checkout.php" class="btn btn-primary btn-block" style="padding: 15px; font-size: 1.05rem;">
                            Proceed to Checkout &rarr;
                        </a>
                    </div>

                    <!-- Trust Points -->
                    <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--border-light); font-size: 0.82rem; color: var(--text-muted); display: grid; gap: 8px;">
                        <div>✓ No credit card needed (Pay in Cash)</div>
                        <div>✓ Easy exchanges upon receipt</div>
                        <div>✓ Fast dispatch to your doorstep</div>
                    </div>
                </div>
            </div>

        </div>

    <?php endif; ?>

</div>

<?php
// Load global footer
require_once __DIR__ . '/includes/footer.php';
?>
