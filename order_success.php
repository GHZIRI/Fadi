<?php
/**
 * Order Confirmation & Invoice Receipt Page (order_success.php)
 * 
 * Displays full purchase confirmation after checkout:
 * - Order reference number (e.g. #ORD-1001)
 * - Confirmation of Cash on Delivery (COD) payment
 * - Delivery address, customer phone and recipient name
 * - Itemized list of purchased clothes with sizes, colors, and prices
 * - Option to print the official receipt
 */

// Initialize database connection and helper functions
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Validate order_id parameter
$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($orderId <= 0) {
    setFlashMessage('error', 'Invalid order reference specified.');
    header('Location: shop.php');
    exit;
}

// Fetch order details from database
try {
    $orderStmt = $pdo->prepare("SELECT * FROM orders WHERE id = :id");
    $orderStmt->execute(['id' => $orderId]);
    $order = $orderStmt->fetch();

    if (!$order) {
        setFlashMessage('error', 'The requested order could not be found.');
        header('Location: shop.php');
        exit;
    }

    // Fetch order items with associated product image
    $itemsStmt = $pdo->prepare("
        SELECT oi.*, p.image 
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = :order_id
        ORDER BY oi.id ASC
    ");
    $itemsStmt->execute(['order_id' => $orderId]);
    $orderItems = $itemsStmt->fetchAll();

} catch (PDOException $e) {
    setFlashMessage('error', 'Database query failed: ' . $e->getMessage());
    header('Location: shop.php');
    exit;
}

// Helper to resolve product thumbnail image
function getReceiptImageUrl(?string $imageName): string {
    $localPath = 'assets/images/products/' . ($imageName ?? '');
    if (!empty($imageName) && file_exists(__DIR__ . '/' . $localPath)) {
        return $localPath;
    }
    return 'https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=200&auto=format&fit=crop&q=80';
}

// Calculate subtotal for items to determine shipping breakdown
$itemsCalculatedSubtotal = 0.0;
foreach ($orderItems as $item) {
    $itemsCalculatedSubtotal += (float)$item['subtotal'];
}
$shippingFee = max(0.0, (float)$order['total_amount'] - $itemsCalculatedSubtotal);

$pageTitle = 'Order Confirmed #' . str_pad((string)$order['id'], 5, '0', STR_PAD_LEFT) . ' | THREAD&CO';
require_once __DIR__ . '/includes/header.php';
?>

<style>
    @media print {
        .top-bar, .site-header, .site-footer, .no-print {
            display: none !important;
        }
        body {
            background: #ffffff !important;
            color: #000000 !important;
        }
        .receipt-card {
            box-shadow: none !important;
            border: 1px solid #e2e8f0 !important;
            margin: 0 !important;
            padding: 0 !important;
        }
    }
</style>

<div class="container" style="padding-top: 40px; padding-bottom: 80px;">

    <!-- Success Hero Banner -->
    <div style="text-align: center; max-width: 680px; margin: 0 auto 40px;">
        <div style="display: inline-flex; align-items: center; justify-content: center; width: 80px; height: 80px; background: #dcfce7; border-radius: 50%; color: #16a34a; font-size: 2.4rem; margin-bottom: 20px; box-shadow: 0 4px 14px rgba(22, 163, 74, 0.2);">
            ✓
        </div>
        <h1 style="font-size: 2.3rem; font-weight: 800; letter-spacing: -0.02em; color: var(--text-main); margin-bottom: 12px;">
            Thank You! Your Order is Placed
        </h1>
        <p style="color: var(--text-muted); font-size: 1.05rem; line-height: 1.6;">
            We have received your clothing order. Our delivery dispatch team is preparing your package for courier shipment.
        </p>
    </div>

    <!-- Main Receipt Container -->
    <div class="receipt-card" style="max-width: 860px; margin: 0 auto; background: var(--bg-surface); border: 1px solid var(--border-light); border-radius: var(--radius-lg); padding: 40px; box-shadow: var(--shadow-md);">
        
        <!-- Header Info Bar -->
        <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 20px; padding-bottom: 24px; border-bottom: 2px dashed var(--border-light); margin-bottom: 30px;">
            <div>
                <span style="font-size: 0.85rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); letter-spacing: 0.05em;">
                    Order Reference
                </span>
                <div style="font-size: 1.6rem; font-weight: 800; color: var(--color-primary); margin-top: 4px;">
                    #ORD-<?= str_pad((string)$order['id'], 5, '0', STR_PAD_LEFT); ?>
                </div>
            </div>

            <div style="text-align: right;">
                <span style="font-size: 0.85rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); letter-spacing: 0.05em;">
                    Date Placed
                </span>
                <div style="font-weight: 600; color: var(--text-main); margin-top: 4px;">
                    <?= date('F j, Y - H:i', strtotime($order['created_at'])); ?>
                </div>
            </div>
        </div>

        <!-- COD Instruction Box -->
        <div style="background: #f0fdf4; border: 1.5px solid #86efac; border-radius: var(--radius-md); padding: 20px 24px; margin-bottom: 36px; display: flex; align-items: flex-start; gap: 16px;">
            <div style="font-size: 1.8rem; line-height: 1;">💵</div>
            <div>
                <div style="font-weight: 800; color: #166534; font-size: 1.05rem;">
                    Payment on Delivery Notice
                </div>
                <p style="color: #15803d; font-size: 0.92rem; margin-top: 6px; line-height: 1.5;">
                    Your payment method is set to <strong>Cash on Delivery (COD)</strong>. Please prepare the exact amount of <strong><?= formatPrice($order['total_amount']); ?></strong> in cash. Our courier will call you at <strong><?= htmlspecialchars($order['customer_phone']); ?></strong> prior to delivering to your address.
                </p>
            </div>
        </div>

        <!-- 2-Column Delivery & Order Meta Grid -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 28px; margin-bottom: 36px; padding-bottom: 32px; border-bottom: 1px solid var(--border-light);">
            
            <!-- Customer & Delivery Address -->
            <div>
                <h3 style="font-size: 1.05rem; font-weight: 800; margin-bottom: 14px; color: var(--text-main); display: flex; align-items: center; gap: 8px;">
                    <span>📍</span> Shipping Information
                </h3>
                <div style="font-size: 0.93rem; line-height: 1.7; color: var(--text-main);">
                    <div><strong>Recipient:</strong> <?= htmlspecialchars($order['customer_name']); ?></div>
                    <div><strong>Phone:</strong> <?= htmlspecialchars($order['customer_phone']); ?></div>
                    <div><strong>Email:</strong> <?= htmlspecialchars($order['customer_email']); ?></div>
                    <div><strong>City:</strong> <?= htmlspecialchars($order['city']); ?></div>
                    <div><strong>Address:</strong> <?= nl2br(htmlspecialchars($order['shipping_address'])); ?></div>
                </div>
            </div>

            <!-- Order Status & Dispatch Info -->
            <div>
                <h3 style="font-size: 1.05rem; font-weight: 800; margin-bottom: 14px; color: var(--text-main); display: flex; align-items: center; gap: 8px;">
                    <span>🚚</span> Fulfillment &amp; Status
                </h3>
                <div style="font-size: 0.93rem; line-height: 1.7; color: var(--text-main);">
                    <div>
                        <strong>Order Status:</strong> 
                        <span style="display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: 0.82rem; font-weight: 700; background: #e0f2fe; color: #0369a1; text-transform: capitalize;">
                            <?= htmlspecialchars($order['order_status']); ?>
                        </span>
                    </div>
                    <div><strong>Payment Method:</strong> <?= htmlspecialchars($order['payment_method']); ?></div>
                    <div><strong>Estimated Delivery:</strong> 1 &ndash; 3 Business Days</div>
                    <div><strong>Courier Service:</strong> Standard Express Home Delivery</div>
                </div>
            </div>

        </div>

        <!-- Purchased Clothes Table -->
        <div style="margin-bottom: 30px;">
            <h3 style="font-size: 1.15rem; font-weight: 800; margin-bottom: 18px; color: var(--text-main);">
                Items in this Order (<?= count($orderItems); ?>)
            </h3>

            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; text-align: left;">
                    <thead>
                        <tr style="border-bottom: 2px solid var(--border-light); font-size: 0.82rem; color: var(--text-muted); text-transform: uppercase;">
                            <th style="padding: 12px 0;">Item Details</th>
                            <th style="padding: 12px 16px; text-align: center;">Size</th>
                            <th style="padding: 12px 16px; text-align: center;">Qty</th>
                            <th style="padding: 12px 16px; text-align: right;">Unit Price</th>
                            <th style="padding: 12px 0; text-align: right;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orderItems as $item): ?>
                            <?php $thumb = getReceiptImageUrl($item['image']); ?>
                            <tr style="border-bottom: 1px solid var(--border-light);">
                                <td style="padding: 16px 0;">
                                    <div style="display: flex; align-items: center; gap: 14px;">
                                        <img src="<?= htmlspecialchars($thumb); ?>" 
                                             alt="<?= htmlspecialchars($item['product_name']); ?>" 
                                             style="width: 50px; height: 60px; object-fit: cover; border-radius: var(--radius-sm); border: 1px solid var(--border-light);">
                                        <div>
                                            <div style="font-weight: 700; color: var(--text-main); font-size: 0.95rem;">
                                                <?= htmlspecialchars($item['product_name']); ?>
                                            </div>
                                            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 2px;">
                                                Color: <?= htmlspecialchars($item['selected_color']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td style="padding: 16px; text-align: center; font-weight: 600;">
                                    <span style="background: var(--bg-subtle); padding: 4px 10px; border-radius: 4px; font-size: 0.82rem;">
                                        <?= htmlspecialchars($item['selected_size']); ?>
                                    </span>
                                </td>
                                <td style="padding: 16px; text-align: center; font-weight: 600;">
                                    <?= (int)$item['quantity']; ?>
                                </td>
                                <td style="padding: 16px; text-align: right; color: var(--text-muted);">
                                    <?= formatPrice($item['unit_price']); ?>
                                </td>
                                <td style="padding: 16px 0; text-align: right; font-weight: 700; color: var(--text-main);">
                                    <?= formatPrice($item['subtotal']); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Financial Totals Summary -->
        <div style="max-width: 340px; margin-left: auto; margin-bottom: 36px;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 0.92rem; color: var(--text-muted);">
                <span>Items Subtotal:</span>
                <strong style="color: var(--text-main);"><?= formatPrice($itemsCalculatedSubtotal); ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 0.92rem; color: var(--text-muted);">
                <span>Courier Delivery:</span>
                <span>
                    <?= $shippingFee === 0.0 
                        ? '<strong style="color: #16a34a;">FREE</strong>' 
                        : formatPrice($shippingFee); ?>
                </span>
            </div>
            <div style="display: flex; justify-content: space-between; padding-top: 14px; border-top: 2px dashed var(--border-light); font-size: 1.25rem; font-weight: 800; color: var(--color-primary);">
                <span>Total Due on Delivery:</span>
                <span><?= formatPrice($order['total_amount']); ?></span>
            </div>
        </div>

        <!-- Action Buttons (Print & Continue Shopping) -->
        <div class="no-print" style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 16px; padding-top: 24px; border-top: 1px solid var(--border-light);">
            <button type="button" 
                    onclick="window.print()" 
                    class="btn btn-secondary" 
                    style="display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px; font-weight: 700; cursor: pointer;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 6 2 18 2 18 9"></polyline>
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                    <rect x="6" y="14" width="12" height="8"></rect>
                </svg>
                <span>Print Official Receipt</span>
            </button>

            <a href="shop.php" class="btn btn-primary" style="padding: 12px 28px; font-weight: 700;">
                Continue Shopping &rarr;
            </a>
        </div>

    </div>

</div>

<?php
// Include shared footer
require_once __DIR__ . '/includes/footer.php';
?>
