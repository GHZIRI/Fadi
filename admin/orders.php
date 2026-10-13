<?php
$pageTitle = 'Orders | THREAD&CO Admin';
$pageHeading = 'Manage Orders';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin('login.php');

$errors = [];
$allowedStatuses = ['pending', 'processing', 'completed', 'cancelled'];
$statusFilter = $_GET['status'] ?? '';
if (!is_string($statusFilter) || !in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT);
    $newStatus = $_POST['order_status'] ?? '';

    if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session token is invalid. Refresh the page and try again.';
    } elseif ($orderId === false || $orderId < 1) {
        $errors[] = 'Choose a valid order.';
    } elseif (!is_string($newStatus) || !in_array($newStatus, $allowedStatuses, true)) {
        $errors[] = 'Choose a valid order status.';
    } else {
        try {
            $updateOrder = $pdo->prepare('UPDATE orders SET order_status = :status WHERE id = :id');
            $updateOrder->execute([
                ':status' => $newStatus,
                ':id' => $orderId,
            ]);

            if ($updateOrder->rowCount() === 0) {
                $orderExists = $pdo->prepare('SELECT id FROM orders WHERE id = :id');
                $orderExists->execute([':id' => $orderId]);
                if (!$orderExists->fetch()) {
                    $errors[] = 'The order could not be found.';
                }
            }

            if (!$errors) {
                setFlashMessage('success', 'Order status updated successfully.');
                header('Location: orders.php?view=' . $orderId);
                exit;
            }
        } catch (PDOException $e) {
            $errors[] = 'The order status could not be updated. Please try again.';
        }
    }
}

$viewOrderId = filter_var($_GET['view'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$viewOrder = null;
$orderItems = [];

if ($viewOrderId > 0) {
    try {
        $orderQuery = $pdo->prepare('SELECT * FROM orders WHERE id = :id');
        $orderQuery->execute([':id' => $viewOrderId]);
        $viewOrder = $orderQuery->fetch();

        if ($viewOrder) {
            $itemsQuery = $pdo->prepare('SELECT * FROM order_items WHERE order_id = :order_id ORDER BY id ASC');
            $itemsQuery->execute([':order_id' => $viewOrderId]);
            $orderItems = $itemsQuery->fetchAll();
        } else {
            $errors[] = 'The requested order could not be found.';
        }
    } catch (PDOException $e) {
        $errors[] = 'Order details could not be loaded. Please try again later.';
    }
}

try {
    $ordersSql = 'SELECT id, customer_name, customer_phone, city, total_amount, order_status, created_at FROM orders';
    $ordersParams = [];
    if ($statusFilter !== '') {
        $ordersSql .= ' WHERE order_status = :status';
        $ordersParams[':status'] = $statusFilter;
    }
    $ordersSql .= ' ORDER BY created_at DESC, id DESC';

    $ordersQuery = $pdo->prepare($ordersSql);
    $ordersQuery->execute($ordersParams);
    $orders = $ordersQuery->fetchAll();
} catch (PDOException $e) {
    $orders = [];
    $errors[] = 'Orders could not be loaded. Please try again later.';
}

$csrfToken = generateCsrfToken();
require_once __DIR__ . '/header.php';
?>

<?php if ($errors): ?>
    <div class="admin-alert admin-alert-error" role="alert">
        <ul style="margin:0; padding-left:20px;">
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($viewOrder): ?>
    <div class="admin-card" style="margin-bottom:24px;">
        <div class="admin-card-header">
            <span class="admin-card-title">Order #ORD-<?= str_pad((string)(int)$viewOrder['id'], 4, '0', STR_PAD_LEFT); ?></span>
            <a href="orders.php<?= $statusFilter !== '' ? '?status=' . rawurlencode($statusFilter) : ''; ?>" class="btn-admin btn-admin-secondary btn-admin-sm">Back to Orders</a>
        </div>

        <div style="padding:24px; display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:24px;">
            <section>
                <h3 style="margin:0 0 12px;">Customer</h3>
                <p><strong>Name:</strong> <?= htmlspecialchars($viewOrder['customer_name'], ENT_QUOTES, 'UTF-8'); ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($viewOrder['customer_email'], ENT_QUOTES, 'UTF-8'); ?></p>
                <p><strong>Phone:</strong> <?= htmlspecialchars($viewOrder['customer_phone'], ENT_QUOTES, 'UTF-8'); ?></p>
            </section>

            <section>
                <h3 style="margin:0 0 12px;">Delivery</h3>
                <p><strong>City:</strong> <?= htmlspecialchars($viewOrder['city'], ENT_QUOTES, 'UTF-8'); ?></p>
                <p><strong>Address:</strong><br><?= nl2br(htmlspecialchars($viewOrder['shipping_address'], ENT_QUOTES, 'UTF-8')); ?></p>
            </section>

            <section>
                <h3 style="margin:0 0 12px;">Order</h3>
                <p><strong>Placed:</strong> <?= htmlspecialchars(date('d M Y, H:i', strtotime($viewOrder['created_at'])), ENT_QUOTES, 'UTF-8'); ?></p>
                <p><strong>Payment:</strong> <?= htmlspecialchars($viewOrder['payment_method'], ENT_QUOTES, 'UTF-8'); ?></p>
                <p><strong>Total:</strong> <?= number_format((float)$viewOrder['total_amount'], 2); ?> DH</p>
            </section>
        </div>

        <div style="padding:0 24px 24px;">
            <form method="POST" action="orders.php?view=<?= (int)$viewOrder['id']; ?>" style="display:flex; align-items:end; gap:10px; flex-wrap:wrap;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="order_id" value="<?= (int)$viewOrder['id']; ?>">
                <div class="admin-form-group" style="margin:0; min-width:200px;">
                    <label for="order_status" class="admin-form-label">Order Status</label>
                    <select id="order_status" name="order_status" class="admin-form-control" required>
                        <?php foreach ($allowedStatuses as $status): ?>
                            <option value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>" <?= $viewOrder['order_status'] === $status ? 'selected' : ''; ?>>
                                <?= htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-admin btn-admin-primary">Update Status</button>
            </form>
        </div>

        <div class="admin-card-header">
            <span class="admin-card-title">Items (<?= count($orderItems); ?>)</span>
        </div>
        <?php if (!$orderItems): ?>
            <p style="padding:0 24px 24px; color:var(--admin-text-muted);">No items were found for this order.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Size</th>
                            <th>Color</th>
                            <th>Quantity</th>
                            <th>Unit Price</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orderItems as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['product_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?= htmlspecialchars($item['selected_size'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?= htmlspecialchars($item['selected_color'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?= (int)$item['quantity']; ?></td>
                                <td><?= number_format((float)$item['unit_price'], 2); ?> DH</td>
                                <td><?= number_format((float)$item['subtotal'], 2); ?> DH</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="admin-card" style="margin-bottom:24px;">
    <div class="admin-card-header">
        <span class="admin-card-title">Customer Orders (<?= count($orders); ?>)</span>
    </div>
    <form method="GET" action="orders.php" style="padding:20px 24px; display:flex; align-items:end; gap:10px; flex-wrap:wrap;">
        <div class="admin-form-group" style="margin:0; min-width:200px;">
            <label for="status" class="admin-form-label">Filter by Status</label>
            <select id="status" name="status" class="admin-form-control">
                <option value="">All statuses</option>
                <?php foreach ($allowedStatuses as $status): ?>
                    <option value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>" <?= $statusFilter === $status ? 'selected' : ''; ?>>
                        <?= htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn-admin btn-admin-primary">Filter</button>
        <a href="orders.php" class="btn-admin btn-admin-secondary">Reset</a>
    </form>

    <?php if (!$orders): ?>
        <p style="padding:24px; color:var(--admin-text-muted);">No orders found.</p>
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
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><strong>#ORD-<?= str_pad((string)(int)$order['id'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                            <td><?= htmlspecialchars($order['customer_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars($order['customer_phone'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars($order['city'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><strong><?= number_format((float)$order['total_amount'], 2); ?> DH</strong></td>
                            <td><span class="status-badge <?= htmlspecialchars($order['order_status'], ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars(ucfirst($order['order_status']), ENT_QUOTES, 'UTF-8'); ?></span></td>
                            <td><?= htmlspecialchars(date('d M Y', strtotime($order['created_at'])), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td style="text-align:right;">
                                <a href="orders.php?view=<?= (int)$order['id']; ?><?= $statusFilter !== '' ? '&amp;status=' . rawurlencode($statusFilter) : ''; ?>" class="btn-admin btn-admin-secondary btn-admin-sm">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>