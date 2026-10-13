<?php
/**
 * THREAD&CO - Admin Footer Component (admin/footer.php)
 * 
 * Closes the admin layout tags and provides common UI interactions:
 * - Proper closure of main content, admin layout, and body containers
 * - Admin copyright and status branding bar
 * - JavaScript helpers: mobile sidebar toggler, auto-dismissing flash alerts,
 *   and standardized delete confirmation dialogs
 */
?>
        </main> <!-- End .admin-content -->

        <!-- Admin Global Footer Bar -->
        <footer style="padding: 20px 32px; border-top: 1px solid var(--admin-border); background: var(--admin-card-bg); display: flex; justify-content: space-between; align-items: center; font-size: 0.82rem; color: var(--admin-text-muted); margin-top: auto;">
            <div>
                &copy; <?= date('Y'); ?> <strong>THREAD&CO</strong> &mdash; Modern Apparel Management System.
            </div>
            <div>
                System Status: <span style="color: #166534; font-weight: 700;">Online &bull; COD Active (30 DH)</span>
            </div>
        </footer>

    </div> <!-- End .admin-main -->

</div> <!-- End .admin-layout -->

<!-- Admin Dashboard Client-side Helpers -->
<script>
    // 1. Mobile Sidebar Navigation Toggler
    const btnToggleSidebar = document.getElementById('btnToggleSidebar');
    const adminSidebar = document.getElementById('adminSidebar');

    if (btnToggleSidebar && adminSidebar) {
        // Display button on smaller screens
        if (window.innerWidth <= 900) {
            btnToggleSidebar.style.display = 'inline-flex';
        }
        
        window.addEventListener('resize', () => {
            btnToggleSidebar.style.display = (window.innerWidth <= 900) ? 'inline-flex' : 'none';
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 900 && 
                adminSidebar.classList.contains('open') && 
                !adminSidebar.contains(e.target) && 
                e.target !== btnToggleSidebar) {
                adminSidebar.classList.remove('open');
            }
        });
    }

    // 2. Generic Confirmation Handler for Dangerous Admin Actions (Delete Product, Category, etc.)
    document.querySelectorAll('.btn-confirm-action').forEach(element => {
        element.addEventListener('click', function(event) {
            const promptMessage = this.getAttribute('data-confirm') || 'Are you sure you want to perform this action? This cannot be undone.';
            if (!confirm(promptMessage)) {
                event.preventDefault();
            }
        });
    });

    // 3. Auto-dismiss Flash Alerts after 5 seconds
    const adminAlerts = document.querySelectorAll('.admin-alert');
    adminAlerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            setTimeout(() => alert.remove(), 400);
        }, 5000);
    });
</script>

</body>
</html>
