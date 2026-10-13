<?php
/**
 * THREAD&CO - Admin Header & Navigation Component (admin/header.php)
 * 
 * Provides unified layout foundation for all admin management pages:
 * - Session verification and administrator access guard (requireAdmin)
 * - HTML5 document structure with assets/css/admin.css inclusion
 * - Sidebar navigation with active page highlights and admin user profile
 * - Sticky topbar with storefront shortcut and logout action
 * - Flash notification alert renderer for admin feedback
 */

// Load core configuration and helper functions from root
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Enforce admin privileges; redirects to admin/login.php if not authenticated
requireAdmin('login.php');

// Identify active page for sidebar menu highlighting
$currentAdminPage = basename($_SERVER['PHP_SELF']);
$adminPageTitle = isset($pageTitle) ? $pageTitle : 'Admin Dashboard | THREAD&CO';
$adminUserDisplayName = $_SESSION['user_name'] ?? 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($adminPageTitle); ?></title>
    
    <!-- Admin Dashboard Styling -->
    <link rel="stylesheet" href="../assets/css/admin.css">
    
    <!-- Google Fonts for Clean Dashboard Typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body.admin-body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
    </style>
</head>
<body class="admin-body">

<div class="admin-layout">

    <!-- 1. Admin Sidebar Navigation -->
    <aside class="admin-sidebar" id="adminSidebar">
        
        <!-- Brand / Logo -->
        <div class="admin-sidebar-brand">
            THREAD&CO <span>Admin</span>
        </div>

        <!-- Navigation Menu -->
        <ul class="admin-sidebar-nav">
            
            <!-- Dashboard Overview -->
            <li class="admin-nav-item <?= $currentAdminPage === 'index.php' ? 'active' : ''; ?>">
                <a href="index.php">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="9" rx="1"></rect>
                        <rect x="14" y="3" width="7" height="5" rx="1"></rect>
                        <rect x="14" y="12" width="7" height="9" rx="1"></rect>
                        <rect x="3" y="16" width="7" height="5" rx="1"></rect>
                    </svg>
                    <span>Dashboard</span>
                </a>
            </li>

            <!-- All Products -->
            <li class="admin-nav-item <?= in_array($currentAdminPage, ['products.php', 'product_edit.php']) ? 'active' : ''; ?>">
                <a href="products.php">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20.38 3.46L16 2a4 4 0 0 1-8 0L3.62 3.46a2 2 0 0 0-1.34 2.23l.58 3.47a1 1 0 0 0 .99.84H6v10c0 1.1.9 2 2 2h8a2 2 0 0 0 2-2V10h2.15a1 1 0 0 0 .99-.84l.58-3.47a2 2 0 0 0-1.34-2.23z"></path>
                    </svg>
                    <span>Products</span>
                </a>
            </li>

            <!-- Add New Product -->
            <li class="admin-nav-item <?= $currentAdminPage === 'product_add.php' ? 'active' : ''; ?>">
                <a href="product_add.php">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="16"></line>
                        <line x1="8" y1="12" x2="16" y2="12"></line>
                    </svg>
                    <span>Add Product</span>
                </a>
            </li>

            <!-- Categories -->
            <li class="admin-nav-item <?= $currentAdminPage === 'categories.php' ? 'active' : ''; ?>">
                <a href="categories.php">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
                        <line x1="7" y1="7" x2="7.01" y2="7"></line>
                    </svg>
                    <span>Categories</span>
                </a>
            </li>

            <!-- Orders Management -->
            <li class="admin-nav-item <?= $currentAdminPage === 'orders.php' ? 'active' : ''; ?>">
                <a href="orders.php">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <path d="M16 10a4 4 0 0 1-8 0"></path>
                    </svg>
                    <span>Customer Orders</span>
                </a>
            </li>

        </ul>

        <!-- Sidebar Footer with Current Admin Profile -->
        <div class="admin-sidebar-footer">
            <div class="admin-user-info">
                <span class="admin-user-name"><?= htmlspecialchars($adminUserDisplayName); ?></span>
                <span class="admin-user-role">Administrator</span>
            </div>
            <a href="logout.php" title="Sign Out" style="color: #ef4444; display: flex; align-items: center;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
            </a>
        </div>

    </aside>

    <!-- 2. Main Workspace Layout -->
    <div class="admin-main">
        
        <!-- Topbar Header -->
        <header class="admin-topbar">
            
            <div class="admin-topbar-left">
                <!-- Mobile Menu Toggle Button -->
                <button type="button" 
                        onclick="document.getElementById('adminSidebar').classList.toggle('open')"
                        class="btn-admin btn-admin-secondary btn-admin-sm"
                        style="display: none;" 
                        id="btnToggleSidebar">
                    ☰ Menu
                </button>
                <div class="admin-topbar-title">
                    <?= htmlspecialchars($pageHeading ?? 'Dashboard'); ?>
                </div>
            </div>

            <div class="admin-topbar-right">
                <!-- Jump to Storefront link -->
                <a href="../index.php" target="_blank" class="btn-storefront-link">
                    <span>View Storefront</span>
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                        <polyline points="15 3 21 3 21 9"></polyline>
                        <line x1="10" y1="14" x2="21" y2="3"></line>
                    </svg>
                </a>

                <!-- Quick Logout Button -->
                <a href="logout.php" class="btn-admin btn-admin-danger btn-admin-sm">
                    Logout
                </a>
            </div>

        </header>

        <!-- Admin Alerts Area -->
        <div style="padding: 24px 32px 0 32px;">
            <?php $flash = getFlashMessage(); ?>
            <?php if ($flash): ?>
                <div class="admin-alert admin-alert-<?= $flash['type'] === 'error' ? 'error' : 'success'; ?>">
                    <span><?= htmlspecialchars($flash['message']); ?></span>
                    <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; cursor:pointer; font-size:1.2rem; font-weight:bold; color:inherit;">&times;</button>
                </div>
            <?php endif; ?>
        </div>

        <!-- Begin Main Page Content -->
        <main class="admin-content">
