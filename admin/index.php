<?php
/**
 * THREAD&CO - Admin Dashboard Overview (admin/index.php)
 * 
 * Main administration landing page displaying key store metrics:
 * - Total revenue generated in Moroccan Dirham (DH)
 * - Total number of orders by status (Pending, Processing, Completed, Cancelled)
 * - Total products and categories count
 * - 10 most recent customer orders with quick status overview
 */

$pageTitle   = 'Dashboard | THREAD&CO Admin';
$pageHeading = 'Dashboard Overview';

// Load admin header (handles auth guard, DB, session)
require_once __DIR__ . '/header.php';

// -------------------------------------------------------------
// Fetch Key Statistics
// -------------------------------------------------------------
try {
    // 1. Total revenue from completed orders only
    $revenueStmt = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE order_status = 'completed'");
    $totalRevenue = (float)$revenueStmt->fetchColumn();

    // 2. Orders count by status
    $ordersStmt = $pdo->query("
        SELECT 
            COUNT(*) AS total_orders,
            SUM(order_status = 'pending')    AS pending_count,
            SUM(order_status = 'processing') AS processing_count,
            SUM(order_status = 'completed')  AS completed_count,
            SUM(order_status = 'cancelled')  AS cancelled_count
        FROM orders
    ");
    $orderStats = $ordersStmt->fetch();

    // 3. Total products and out-of-stock count
    $productsStmt = $pdo->query("
        SELECT 
            COUNT(*) AS total_products,
            SUM(stock_quantity = 0) AS out_of_stock
        FROM products
    ");
    $productStats = $productsStmt->fetch();

    // 4. Total categories
    $catCount = (int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();

    // 5. Latest 10 orders for the recent orders table
    $recentOrdersStmt = $pdo->query("
        SELECT id, customer_name, customer_phone, city, total_amount, order_status, created_at
        FROM orders
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $recentOrders = $recentOrdersStmt->fetchAll();

} catch (PDOException $e) {
    $revenueStmt = null;
    $orderStats  = ['total_orders' => 0, 'pending_count' => 0, 'processing_count' => 0, 'completed_count' => 0, 'cancelled_count' => 0];
    $productStats = ['total_products' => 0, 'out_of_stock' => 0];
    $catCount     = 0;
    $recentOrders = [];
}

// Helper: badge HTML for order status
function statusBadge(string $status): string {
    $map = [
        'pending'    => 'pending',
        'processing' => 'processing',
        'completed'  => 'completed',
        'cancelled'  => 'cancelled',
    ];
    $cls   = $map[$status] ?? 'pending';
    $label = ucfirst($status);
    return "<span class=\"status-badge {$cls}\">{$label}</span>";
}
?>

<!-- =========================================================
     METRIC CARDS ROW
     ========================================================= -->
<div class="stats-grid">

    <!-- Total Revenue -->
    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-title">Total Revenue</div>
            <div class="stat-card-icon" style="background:#dcfce7;">💰</div>
        </div>
        <div class="stat-card-value"><?= number_format($totalRevenue, 2); ?> <span style="font-size:1rem;color:var(--admin-text-muted);">DH</span></div>
        <div class="stat-card-footer">From completed orders only</div>
    </div>

    <!-- Total Orders -->
    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-title">Total Orders</div>
            <div class="stat-card-icon" style="background:#dbeafe;">🛍️</div>
        </div>
        <div class="stat-card-value"><?= (int)$orderStats['total_orders']; ?></div>
        <div class="stat-card-footer">
            <span style="color:#92400e;"><?= (int)$orderStats['pending_count']; ?> pending</span> &bull;
            <span style="color:#166534;"><?= (int)$orderStats['completed_count']; ?> completed</span>
        </div>
    </div>

    <!-- Total Products -->
    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-title">Products</div>
            <div class="stat-card-icon" style="background:#f3e8ff;">👕</div>
        </div>
        <div class="stat-card-value"><?= (int)$productStats['total_products']; ?></div>
        <div class="stat-card-footer">
            <?php if ((int)$productStats['out_of_stock'] > 0): ?>
                <span style="color:#991b1b;"><?= (int)$productStats['out_of_stock']; ?> out of stock</span>
            <?php else: ?>
                All items in stock ✓
            <?php endif; ?>
        </div>
    </div>

    <!-- Categories -->
    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-title">Categories</div>
            <div class="stat-card-icon" style="background:#fef9c3;">🏷️</div>
        </div>
        <div class="stat-card-value"><?= $catCount; ?></div>
        <div class="stat-card-footer">Clothing collections</div>
    </div>

</div>

<!-- =========================================================
     ORDER STATUS MINI BREAKDOWN
     ========================================================= -->
<div class="admin-card" style="margin-bottom: 28px;">
    <div class="admin-card-header">
        <span class="admin-card-title">Order Status Breakdown</span>
        <a href="orders.php" class="btn-admin btn-admin-secondary btn-admin-sm">Manage All Orders</a>
    </div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 1px; background: var(--admin-border);">

        <?php
        $breakdown = [
            ['label' => 'Pending',    'count' => (int)$orderStats['pending_count'],    'cls' => 'pending'],
            ['label' => 'Processing', 'count' => (int)$orderStats['processing_count'], 'cls' => 'processing'],
            ['label' => 'Completed',  'count' => (int)$orderStats['completed_count'],  'cls' => 'completed'],
            ['label' => 'Cancelled',  'count' => (int)$orderStats['cancelled_count'],  'cls' => 'cancelled'],
        ];
        foreach ($breakdown as $b):
        ?>
            <div style="background:var(--admin-card-bg); padding: 20px 24px; text-align:center;">
                <div style="font-size: 1.6rem; font-weight: 800; margin-bottom: 4px;"><?= $b['count']; ?></div>
                <span class="status-badge <?= $b['cls']; ?>"><?= $b['label']; ?></span>
            </div>
        <?php endforeach; ?>

    </div>
</div>

<!-- =========================================================
     RECENT ORDERS TABLE
     ========================================================= -->
<div class="admin-card">
    <div class="admin-card-header">
        <span class="admin-card-title">Recent Customer Orders</span>
        <a href="orders.php" class="btn-admin btn-admin-primary btn-admin-sm">View All</a>
    </div>

    <?php if (empty($recentOrders)): ?>
        <div style="padding: 60px; text-align:center; color: var(--admin-text-muted);">
            <div style="font-size: 2.5rem; margin-bottom: 12px;">📦</div>
            <p>No orders have been placed yet.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Phone</th>
                        <th>City</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentOrders as $order): ?>
                        <tr>
                            <td><strong>#ORD-<?= str_pad((int)$order['id'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                            <td><?= htmlspecialchars($order['customer_name']); ?></td>
                            <td><?= htmlspecialchars($order['customer_phone']); ?></td>
                            <td><?= htmlspecialchars($order['city']); ?></td>
                            <td><strong style="color: var(--admin-primary);"><?= number_format((float)$order['total_amount'], 2); ?> DH</strong></td>
                            <td><?= statusBadge($order['order_status']); ?></td>
                            <td style="color: var(--admin-text-muted); font-size: 0.85rem;">
                                <?= date('d M Y', strtotime($order['created_at'])); ?>
                            </td>
                            <td style="text-align:right;">
                                <a href="orders.php?view=<?= (int)$order['id']; ?>" class="btn-admin btn-admin-secondary btn-admin-sm">
                                    View
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
