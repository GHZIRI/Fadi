<?php
/**
 * Global Storefront Footer
 * 
 * Shared footer included at the bottom of customer-facing pages.
 * Displays brand info, navigation links, customer service details,
 * and loads the main frontend JavaScript script.
 */
?>
    <!-- Global Footer -->
    <footer class="site-footer">
        <div class="container">
            <div class="footer-grid">
                
                <!-- Brand Overview & Trust Badges -->
                <div class="footer-brand">
                    <h3>THREAD<span>&amp;</span>CO</h3>
                    <p style="color: #94a3b8; font-size: 0.92rem; line-height: 1.6; max-width: 360px; margin-bottom: 20px;">
                        Crafting modern everyday apparel with premium breathable fabrics and contemporary silhouettes. Designed for comfort, styled for confidence.
                    </p>
                    <div style="display: flex; gap: 12px; align-items: center;">
                        <span style="display: inline-flex; align-items: center; gap: 6px; background: rgba(255,255,255,0.08); padding: 6px 12px; border-radius: 6px; font-size: 0.78rem; color: #e2e8f0;">
                            🚚 Fast Delivery
                        </span>
                        <span style="display: inline-flex; align-items: center; gap: 6px; background: rgba(255,255,255,0.08); padding: 6px 12px; border-radius: 6px; font-size: 0.78rem; color: #4ade80;">
                            💵 Cash on Delivery
                        </span>
                    </div>
                </div>

                <!-- Quick Navigation Links -->
                <div class="footer-links">
                    <h4>Collections</h4>
                    <ul>
                        <li><a href="shop.php">All Clothing</a></li>
                        <li><a href="shop.php?category=t-shirts">T-Shirts &amp; Tops</a></li>
                        <li><a href="shop.php?category=shirts">Casual Shirts</a></li>
                        <li><a href="shop.php?category=hoodies-sweatshirts">Hoodies &amp; Fleece</a></li>
                        <li><a href="shop.php?category=jeans-denim">Jeans &amp; Pants</a></li>
                    </ul>
                </div>

                <!-- Customer Support & Information -->
                <div class="footer-links">
                    <h4>Customer Care</h4>
                    <ul>
                        <li><a href="cart.php">My Shopping Bag</a></li>
                        <li><a href="checkout.php">Order Checkout</a></li>
                        <li><a href="login.php">Customer Account</a></li>
                        <li><span style="font-size: 0.88rem; color: #64748b;">Support: support@threadandco.com</span></li>
                        <li><span style="font-size: 0.88rem; color: #64748b;">Phone: +212 600 000 000</span></li>
                    </ul>
                </div>

            </div>

            <!-- Copyright & Credits -->
            <div class="footer-bottom">
                <p>&copy; <?= date('Y'); ?> THREAD&amp;CO Clothing Store. All rights reserved. Built with PHP &amp; MySQL.</p>
            </div>
        </div>
    </footer>

    <!-- Main Client-Side JavaScript -->
    <script src="assets/js/main.js"></script>
</body>
</html>
