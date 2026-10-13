<?php
/**
 * Global Helper Functions & Security Utilities
 * 
 * Provides common functionality across storefront and admin areas:
 * - Session initiation
 * - Input sanitization (XSS protection)
 * - CSRF token generation and verification
 * - Authentication and authorization guards
 * - Currency formatting and cart counters
 * - Flash messaging for user alerts
 */

// Start session if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Sanitize user input to protect against Cross-Site Scripting (XSS).
 *
 * @param string $data Raw input string
 * @return string Sanitized string safe for HTML output
 */
function sanitize(string $data): string {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate a cryptographically secure CSRF token and store it in session.
 *
 * @return string CSRF token string
 */
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify if submitted CSRF token matches the session token.
 *
 * @param string|null $token Submitted token from POST form
 * @return bool True if valid, false otherwise
 */
function validateCsrfToken(?string $token): bool {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Global Delivery & Shipping Settings
 */
if (!defined('SHIPPING_FEE')) {
    define('SHIPPING_FEE', 30.00); // 30 DH standard delivery
}
if (!defined('FREE_SHIPPING_THRESHOLD')) {
    define('FREE_SHIPPING_THRESHOLD', 300.00); // Free delivery on orders over 300 DH
}

/**
 * Calculate delivery fee based on cart subtotal.
 *
 * @param float|int $subtotal
 * @return float Delivery fee in DH (0.0 if free, or SHIPPING_FEE)
 */
function calculateShippingFee(float|int $subtotal): float {
    if ((float)$subtotal <= 0 || (float)$subtotal >= FREE_SHIPPING_THRESHOLD) {
        return 0.0;
    }
    return SHIPPING_FEE;
}

/**
 * Format numerical amount into standard Moroccan Dirham (DH) currency string.
 *
 * @param float|int|string $amount Price amount
 * @return string Formatted price string (e.g. "250.00 DH")
 */
function formatPrice($amount): string {
    return number_format((float)$amount, 2) . ' DH';
}

/**
 * Check if a customer or admin is currently logged in.
 *
 * @return bool True if user session exists
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

/**
 * Check if the currently logged-in user has admin privileges.
 *
 * @return bool True if role is 'admin'
 */
function isAdmin(): bool {
    return isLoggedIn() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Enforce authentication requirement. Redirects to login if not logged in.
 *
 * @param string $redirectTo URL to redirect unauthenticated visitors
 * @return void
 */
function requireLogin(string $redirectTo = 'login.php'): void {
    if (!isLoggedIn()) {
        setFlashMessage('error', 'Please log in to continue.');
        header("Location: {$redirectTo}");
        exit;
    }
}

/**
 * Enforce administrator requirement. Redirects if user is not admin.
 *
 * @param string $redirectTo URL to redirect non-admin visitors
 * @return void
 */
function requireAdmin(string $redirectTo = '../login.php'): void {
    if (!isAdmin()) {
        setFlashMessage('error', 'Access denied. Administrator privileges required.');
        header("Location: {$redirectTo}");
        exit;
    }
}

/**
 * Get total quantity of items currently in shopping cart.
 *
 * @return int Total number of items in cart
 */
function getCartItemCount(): int {
    if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        return 0;
    }
    $totalCount = 0;
    foreach ($_SESSION['cart'] as $item) {
        $totalCount += (int)($item['quantity'] ?? 0);
    }
    return $totalCount;
}

/**
 * Store a temporary flash message in the session to show on next page load.
 *
 * @param string $type Message category ('success', 'error', 'info', 'warning')
 * @param string $message The message text to display
 * @return void
 */
function setFlashMessage(string $type, string $message): void {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message,
    ];
}

/**
 * Retrieve and clear the current flash message from session.
 *
 * @return array|null Returns ['type' => ..., 'message' => ...] or null
 */
function getFlashMessage(): ?array {
    if (!empty($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $flash;
    }
    return null;
}
