/**
 * Main Client-Side JavaScript for THREAD&CO Clothing Store
 * 
 * Handles interactive storefront behaviors:
 * - Alert dismissal
 * - Product size and color selections
 * - Quantity selector controls
 * - Cart item removal confirmation
 * - Form validation
 */

document.addEventListener('DOMContentLoaded', function () {

    // 1. Auto-dismiss Flash Alerts after 5 seconds
    const flashAlerts = document.querySelectorAll('.flash-alert');
    flashAlerts.forEach(function (alert) {
        setTimeout(function () {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(function () {
                if (alert.parentElement) {
                    alert.parentElement.remove();
                }
            }, 500);
        }, 5000);
    });

    // 2. Quantity Selector Controls (+ and - buttons)
    const qtyInputs = document.querySelectorAll('.qty-input');
    qtyInputs.forEach(function (input) {
        // Enforce numeric bounds
        input.addEventListener('change', function () {
            const min = parseInt(this.getAttribute('min')) || 1;
            const max = parseInt(this.getAttribute('max')) || 99;
            let val = parseInt(this.value) || min;

            if (val < min) val = min;
            if (val > max) val = max;
            this.value = val;
        });
    });

    // 3. Product Details - Selection Feedback
    const sizeRadios = document.querySelectorAll('input[name="selected_size"]');
    const colorRadios = document.querySelectorAll('input[name="selected_color"]');
    const selectedSizeDisplay = document.getElementById('display-selected-size');
    const selectedColorDisplay = document.getElementById('display-selected-color');

    if (sizeRadios.length > 0 && selectedSizeDisplay) {
        sizeRadios.forEach(function (radio) {
            radio.addEventListener('change', function () {
                selectedSizeDisplay.textContent = this.value;
            });
        });
    }

    if (colorRadios.length > 0 && selectedColorDisplay) {
        colorRadios.forEach(function (radio) {
            radio.addEventListener('change', function () {
                selectedColorDisplay.textContent = this.value;
            });
        });
    }

    // 4. Add to Cart Form Validation
    const addToCartForm = document.getElementById('add-to-cart-form');
    if (addToCartForm) {
        addToCartForm.addEventListener('submit', function (e) {
            const sizeChecked = document.querySelector('input[name="selected_size"]:checked');
            const colorChecked = document.querySelector('input[name="selected_color"]:checked');

            if (sizeRadios.length > 0 && !sizeChecked) {
                e.preventDefault();
                alert('Please select your clothing size before adding to cart.');
                return;
            }

            if (colorRadios.length > 0 && !colorChecked) {
                e.preventDefault();
                alert('Please select a color before adding to cart.');
                return;
            }
        });
    }

    // 5. Cart Item Deletion Confirmation
    const deleteButtons = document.querySelectorAll('.btn-remove-cart, .btn-confirm-delete');
    deleteButtons.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            const confirmed = confirm('Are you sure you want to remove this item?');
            if (!confirmed) {
                e.preventDefault();
            }
        });
    });

});
