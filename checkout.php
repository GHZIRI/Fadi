<?php
/**
 * Order Checkout Page (checkout.php)
 * 
 * Allows customers to finalize their clothing purchase via Cash on Delivery (COD).
 * Features:
 * - Guest checkout enabled (no login mandatory).
 * - Real-time stock verification before order placement.
 * - Atomic database transaction (writes to 'orders' and 'order_items', decrements 'products' stock).
 * - Automatic cart clearing upon success.
 * - Redirect to order confirmation receipt.
 */

// Initialize core configuration and helper functions
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Verify that the cart contains items; redirect if empty
if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    setFlashMessage('warning', 'Your shopping bag is empty. Please add clothing items before checking out.');
    header('Location: shop.php');
    exit;
}

$cartItems = $_SESSION['cart'];

// -------------------------------------------------------------
// Calculate Financial Totals
// -------------------------------------------------------------
$subtotal = 0.0;
foreach ($cartItems as $item) {
    $subtotal += ((float)$item['price'] * (int)$item['quantity']);
}

// Delivery calculation using global settings (30 DH standard, or Free on orders >= 300 DH)
$freeShippingThreshold = FREE_SHIPPING_THRESHOLD;
$shippingCost = calculateShippingFee($subtotal);
$grandTotal = $subtotal + $shippingCost;

// Helper function to resolve product image paths
function getCheckoutImageUrl(?string $imageName): string {
    $localPath = 'assets/images/products/' . ($imageName ?? '');
    if (!empty($imageName) && file_exists(__DIR__ . '/' . $localPath)) {
        return $localPath;
    }
    return 'https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=200&auto=format&fit=crop&q=80';
}

// Initialize form variables and error tracking
$errors = [];
$customerName    = '';
$customerEmail   = '';
$customerPhone   = '';
$shippingAddress = '';
$city            = '';
$orderNotes      = '';

// Pre-fill fields if user happens to be logged in
if (isLoggedIn() && isset($_SESSION['user_id'])) {
    $customerName  = $_SESSION['user_name'] ?? '';
    $customerEmail = $_SESSION['user_email'] ?? '';
}

// -------------------------------------------------------------
// Process Form Submission (POST)
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Validate CSRF token
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session expired or the request was invalid. Please try submitting again.';
    }

    // 2. Sanitize and retrieve inputs
    $customerName    = sanitize($_POST['customer_name'] ?? '');
    $customerEmail   = trim($_POST['customer_email'] ?? '');
    $customerPhone   = sanitize($_POST['customer_phone'] ?? '');
    $shippingAddress = sanitize($_POST['shipping_address'] ?? '');
    $city            = sanitize($_POST['city'] ?? '');
    $orderNotes      = sanitize($_POST['order_notes'] ?? '');

    // 3. Validate user fields
    if (empty($customerName) || mb_strlen($customerName) < 3) {
        $errors[] = 'Please enter your full name (at least 3 characters).';
    }

    if (empty($customerPhone) || mb_strlen($customerPhone) < 8) {
        $errors[] = 'Please enter a valid contact phone number so our courier can deliver your order.';
    }

    if (empty($shippingAddress) || mb_strlen($shippingAddress) < 6) {
        $errors[] = 'Please provide a complete shipping address (street, building number, apartment).';
    }

    if (empty($city)) {
        $errors[] = 'Please specify your delivery city.';
    }

    if (empty($customerEmail) || !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address for your order confirmation receipt.';
    }

    // 4. Verify item availability and inventory levels in database
    if (empty($errors)) {
        $stockCheckStmt = $pdo->prepare("SELECT id, name, price, stock_quantity FROM products WHERE id = :id");
        foreach ($cartItems as $item) {
            $stockCheckStmt->execute(['id' => (int)$item['product_id']]);
            $productData = $stockCheckStmt->fetch();

            if (!$productData) {
                $errors[] = "The item \"{$item['name']}\" is no longer available in the store.";
            } elseif ($productData['stock_quantity'] < (int)$item['quantity']) {
                $errors[] = "Insufficient stock for \"{$productData['name']}\". Only {$productData['stock_quantity']} pieces remaining.";
            }
        }
    }

    // 5. Save order inside a safe database transaction
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $userId = isLoggedIn() ? (int)$_SESSION['user_id'] : null;

            // Combine notes with address if provided
            $deliveryNotes = !empty($orderNotes) ? " | Notes: " . $orderNotes : "";
            $finalAddress  = $shippingAddress . $deliveryNotes;

            // A. Insert master order record
            $orderInsertSql = "INSERT INTO orders (
                user_id,
                customer_name,
                customer_email,
                customer_phone,
                shipping_address,
                city,
                total_amount,
                payment_method,
                order_status,
                created_at
            ) VALUES (
                :user_id,
                :customer_name,
                :customer_email,
                :customer_phone,
                :shipping_address,
                :city,
                :total_amount,
                'Cash on Delivery',
                'pending',
                NOW()
            )";

            $orderStmt = $pdo->prepare($orderInsertSql);
            $orderStmt->execute([
                ':user_id'          => $userId,
                ':customer_name'    => $customerName,
                ':customer_email'   => $customerEmail,
                ':customer_phone'   => $customerPhone,
                ':shipping_address' => $finalAddress,
                ':city'             => $city,
                ':total_amount'     => $grandTotal
            ]);

            $createdOrderId = (int)$pdo->lastInsertId();

            // B. Insert order line items and decrement product stock
            $itemInsertSql = "INSERT INTO order_items (
                order_id,
                product_id,
                product_name,
                selected_size,
                selected_color,
                unit_price,
                quantity,
                subtotal
            ) VALUES (
                :order_id,
                :product_id,
                :product_name,
                :selected_size,
                :selected_color,
                :unit_price,
                :quantity,
                :subtotal
            )";
            $itemStmt = $pdo->prepare($itemInsertSql);

            $updateStockSql = "UPDATE products SET stock_quantity = stock_quantity - :qty WHERE id = :product_id";
            $stockStmt = $pdo->prepare($updateStockSql);

            foreach ($cartItems as $item) {
                $lineSubtotal = round((float)$item['price'] * (int)$item['quantity'], 2);

                $itemStmt->execute([
                    ':order_id'       => $createdOrderId,
                    ':product_id'     => (int)$item['product_id'],
                    ':product_name'   => $item['name'],
                    ':selected_size'  => $item['selected_size'] ?? 'M',
                    ':selected_color' => $item['selected_color'] ?? 'Standard',
                    ':unit_price'     => (float)$item['price'],
                    ':quantity'       => (int)$item['quantity'],
                    ':subtotal'       => $lineSubtotal
                ]);

                // Deduct purchased quantity from store stock
                $stockStmt->execute([
                    ':qty'        => (int)$item['quantity'],
                    ':product_id' => (int)$item['product_id']
                ]);
            }

            // Commit transaction
            $pdo->commit();

            // Clear customer cart
            unset($_SESSION['cart']);

            // Save order ID to session for verification
            $_SESSION['recent_order_id'] = $createdOrderId;

            // Redirect to success page
            header("Location: order_success.php?order_id=" . $createdOrderId);
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = "An unexpected error occurred while placing your order. Please try again. (" . $e->getMessage() . ")";
        }
    }
}

// Page title and header
$pageTitle = 'Checkout | Cash on Delivery | THREAD&CO';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container" style="padding-top: 30px; padding-bottom: 70px;">

    <!-- Breadcrumb -->
    <div style="margin-bottom: 24px;">
        <span style="font-size: 0.88rem; color: var(--text-muted);">
            <a href="index.php">Home</a> &rsaquo; 
            <a href="cart.php">Shopping Bag</a> &rsaquo; 
            <strong>Checkout</strong>
        </span>
        <h1 style="font-size: 2.2rem; font-weight: 800; letter-spacing: -0.02em; margin-top: 8px;">
            Order Checkout
        </h1>
        <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: 4px;">
            Complete your delivery information below. You will pay in cash upon receiving your order.
        </p>
    </div>

    <!-- Display Validation Errors -->
    <?php if (!empty($errors)): ?>
        <div style="background: #fef2f2; border: 1.5px solid #fecaca; border-radius: var(--radius-md); padding: 18px 24px; margin-bottom: 30px;">
            <div style="display: flex; align-items: center; gap: 10px; color: #b91c1c; font-weight: 700; margin-bottom: 8px;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <span>Please fix the following issues:</span>
            </div>
            <ul style="margin: 0; padding-left: 24px; color: #991b1b; font-size: 0.92rem; line-height: 1.6;">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Checkout Two-Column Grid -->
    <div class="cart-layout">
        
        <!-- Left Column: Shipping & Contact Details -->
        <div>
            <div style="background: var(--bg-surface); border: 1px solid var(--border-light); border-radius: var(--radius-lg); padding: 32px; box-shadow: var(--shadow-sm);">
                
                <h2 style="font-size: 1.35rem; font-weight: 800; margin-bottom: 24px; padding-bottom: 12px; border-bottom: 1px solid var(--border-light); display: flex; align-items: center; gap: 10px;">
                    <span>📍</span> Shipping &amp; Delivery Details
                </h2>

                <form method="POST" action="checkout.php" id="checkout-form">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken(); ?>">

                    <!-- Row 1: Full Name & Phone -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        
                        <div class="form-group">
                            <label for="customer_name" class="form-label">
                                Full Name <span style="color: #ef4444;">*</span>
                            </label>
                            <input type="text" 
                                   id="customer_name" 
                                   name="customer_name" 
                                   class="form-control" 
                                   placeholder="e.g. John Doe" 
                                   value="<?= htmlspecialchars($customerName); ?>" 
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="customer_phone" class="form-label">
                                Phone Number <span style="color: #ef4444;">*</span>
                            </label>
                            <input type="tel" 
                                   id="customer_phone" 
                                   name="customer_phone" 
                                   class="form-control" 
                                   placeholder="e.g. 0612345678" 
                                   value="<?= htmlspecialchars($customerPhone); ?>" 
                                   required>
                            <small style="color: var(--text-muted); font-size: 0.78rem; display: block; margin-top: 4px;">
                                Essential for the delivery agent to contact you.
                            </small>
                        </div>

                    </div>

                    <!-- Row 2: Email Address & City -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        
                        <div class="form-group">
                            <label for="customer_email" class="form-label">
                                Email Address <span style="color: #ef4444;">*</span>
                            </label>
                            <input type="email" 
                                   id="customer_email" 
                                   name="customer_email" 
                                   class="form-control" 
                                   placeholder="e.g. name@example.com" 
                                   value="<?= htmlspecialchars($customerEmail); ?>" 
                                   required>
                            <small style="color: var(--text-muted); font-size: 0.78rem; display: block; margin-top: 4px;">
                                For your receipt and order status tracking.
                            </small>
                        </div>

                        <div class="form-group">
                            <label for="city" class="form-label">
                                Delivery City <span style="color: #ef4444;">*</span>
                            </label>
                            <input type="text" 
                                   id="city" 
                                   name="city" 
                                   class="form-control" 
                                   placeholder="e.g. Casablanca, Rabat, Marrakech..." 
                                   value="<?= htmlspecialchars($city); ?>" 
                                   required>
                        </div>

                    </div>

                    <!-- Row 3: Shipping Address -->
                    <div class="form-group">
                        <label for="shipping_address" class="form-label">
                            Full Shipping Address <span style="color: #ef4444;">*</span>
                        </label>
                        <textarea id="shipping_address" 
                                  name="shipping_address" 
                                  class="form-control" 
                                  rows="3" 
                                  placeholder="Neighborhood, Street Name, Building/House Number, Floor or Landmark..." 
                                  required><?= htmlspecialchars($shippingAddress); ?></textarea>
                    </div>

                    <!-- Row 4: Optional Delivery Notes -->
                    <div class="form-group">
                        <label for="order_notes" class="form-label">
                            Order Notes <span style="color: var(--text-muted); font-weight: normal;">(Optional)</span>
                        </label>
                        <input type="text" 
                               id="order_notes" 
                               name="order_notes" 
                               class="form-control" 
                               placeholder="Special delivery instructions, preferred contact time, etc." 
                               value="<?= htmlspecialchars($orderNotes); ?>">
                    </div>

                    <!-- Payment Method Section: Locked to COD -->
                    <div style="margin-top: 32px; padding-top: 24px; border-top: 1px solid var(--border-light);">
                        <h3 style="font-size: 1.15rem; font-weight: 800; margin-bottom: 16px;">
                            Payment Method
                        </h3>
                        
                        <div style="background: #f0fdf4; border: 2px solid #86efac; border-radius: var(--radius-md); padding: 18px 20px; display: flex; align-items: flex-start; gap: 14px;">
                            <input type="radio" 
                                   id="cod_payment" 
                                   name="payment_method" 
                                   value="Cash on Delivery" 
                                   checked 
                                   style="margin-top: 4px; accent-color: #16a34a; width: 18px; height: 18px;">
                            <div>
                                <label for="cod_payment" style="font-weight: 800; color: #166534; font-size: 1.02rem; cursor: pointer;">
                                    💵 Cash on Delivery (COD)
                                </label>
                                <p style="color: #15803d; font-size: 0.88rem; margin-top: 4px; line-height: 1.5;">
                                    Pay safely in cash when your parcel is delivered right to your doorstep. No credit card or advance payment required.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Order Button -->
                    <div style="margin-top: 32px;">
                        <button type="submit" class="btn btn-primary btn-block" style="padding: 16px; font-size: 1.1rem; font-weight: 800; letter-spacing: 0.02em; cursor: pointer;">
                            Confirm Order &bull; Pay <?= formatPrice($grandTotal); ?> on Delivery &rarr;
                        </button>
                    </div>

                </form>

            </div>
        </div>

        <!-- Right Column: Order Summary & Review -->
        <div>
            <div class="summary-card">
                
                <h3 class="summary-title" style="display: flex; justify-content: space-between; align-items: center;">
                    <span>Order Summary</span>
                    <a href="cart.php" style="font-size: 0.82rem; font-weight: 600; color: var(--color-accent);">Edit Bag</a>
                </h3>

                <!-- Cart Items Mini-List -->
                <div style="display: grid; gap: 14px; margin-bottom: 24px; max-height: 380px; overflow-y: auto; padding-right: 4px;">
                    <?php foreach ($cartItems as $item): ?>
                        <?php 
                            $itemTotal = (float)$item['price'] * (int)$item['quantity'];
                            $imgUrl = getCheckoutImageUrl($item['image']);
                        ?>
                        <div style="display: flex; align-items: center; gap: 12px; padding-bottom: 12px; border-bottom: 1px solid var(--border-light);">
                            <img src="<?= htmlspecialchars($imgUrl); ?>" 
                                 alt="<?= htmlspecialchars($item['name']); ?>" 
                                 style="width: 52px; height: 64px; object-fit: cover; border-radius: var(--radius-sm); border: 1px solid var(--border-light);">
                            <div style="flex: 1; min-width: 0;">
                                <div style="font-weight: 700; font-size: 0.9rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--text-main);">
                                    <?= htmlspecialchars($item['name']); ?>
                                </div>
                                <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 2px;">
                                    Size: <strong><?= htmlspecialchars($item['selected_size']); ?></strong> | Qty: <strong><?= (int)$item['quantity']; ?></strong>
                                </div>
                            </div>
                            <div style="font-weight: 700; font-size: 0.92rem; color: var(--text-main);">
                                <?= formatPrice($itemTotal); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Financial Breakdown -->
                <div class="summary-row">
                    <span>Items Subtotal:</span>
                    <strong style="color: var(--text-main);"><?= formatPrice($subtotal); ?></strong>
                </div>

                <div class="summary-row">
                    <span>Shipping Fee:</span>
                    <span>
                        <?= $shippingCost === 0.0 
                            ? '<span style="color: #16a34a; font-weight:700;">FREE</span>' 
                            : formatPrice($shippingCost); ?>
                    </span>
                </div>

                <div class="summary-row">
                    <span>Payment Method:</span>
                    <span style="font-weight: 700; color: #166534;">Cash on Delivery</span>
                </div>

                <div class="summary-row total">
                    <span>Total Amount Due:</span>
                    <span style="color: var(--color-primary);"><?= formatPrice($grandTotal); ?></span>
                </div>

                <!-- Guarantee & Assurance Badges -->
                <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--border-light); font-size: 0.82rem; color: var(--text-muted); display: grid; gap: 10px;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="color: #16a34a; font-weight: bold;">✔</span>
                        <span>100% Risk-Free: Inspect before you pay</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="color: #16a34a; font-weight: bold;">✔</span>
                        <span>Fast local courier delivery to your doorstep</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="color: #16a34a; font-weight: bold;">✔</span>
                        <span>Easy 7-day size exchange guarantee</span>
                    </div>
                </div>

            </div>
        </div>

    </div>

</div>

<?php
// Include shared footer
require_once __DIR__ . '/includes/footer.php';
?>
