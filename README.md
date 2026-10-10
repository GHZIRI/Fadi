# Modern Clothing Store (E-Commerce Web Application)

A beginner-friendly, clean, and secure e-commerce web application built using native web technologies and PHP 8.

---

## 1. Project Specifications
- **Type:** Online Clothing Store (Men, Women, Kids, Accessories).
- **Features:** Sizes (S, M, L, XL), Colors, Stock Management, Cash on Delivery (COD).
- **Tech Stack:**
  - **Frontend:** HTML5, Modern CSS3 (Responsive Design), Vanilla JavaScript.
  - **Backend:** Native PHP 8 (No frameworks).
  - **Database:** MySQL via PDO (Prepared Statements).
  - **Environment:** Localhost with XAMPP (Apache & MySQL).

---

## 2. Directory Structure
```text
fadi/
├── assets/
│   ├── css/
│   │   ├── style.css             # Main storefront styling (responsive, modern)
│   │   └── admin.css             # Admin dashboard styling
│   ├── js/
│   │   ├── main.js              # Storefront interactions (cart, filters)
│   │   └── admin.js             # Admin UI helpers
│   └── images/
│       └── products/            # Stored clothing item pictures
├── config/
│   └── database.php             # PDO connection configuration
├── includes/
│   ├── header.php               # Customer header & navigation bar
│   ├── footer.php               # Customer footer & scripts
│   ├── functions.php            # Helper functions (sanitization, auth, helpers)
│   └── admin_header.php         # Admin header & navigation
├── admin/
│   ├── index.php                # Admin dashboard overview & statistics
│   ├── login.php                # Admin authentication page
│   ├── logout.php               # Admin logout handler
│   ├── products.php             # Product catalog management
│   ├── product_add.php          # Add new clothing item
│   ├── product_edit.php         # Edit existing clothing item
│   ├── categories.php           # Category management
│   └── orders.php               # Order fulfillment & status updates
├── database/
│   └── schema.sql               # Database schema & sample clothing data
├── index.php                    # Storefront homepage (Hero, featured items)
├── shop.php                     # Catalog & filtering (Category, size, price)
├── product_details.php          # Single product view (Size/color selection)
├── cart.php                     # Shopping cart overview
├── checkout.php                 # Cash on Delivery checkout form
├── order_success.php            # Order confirmation receipt
├── login.php                    # Customer login
├── register.php                 # Customer registration
├── logout.php                   # Customer logout
└── README.md                    # Project roadmap and documentation
```

---

## 3. Database Architecture (clothing_store)
1. **users:** Customer and administrator accounts with hashed passwords (`password_hash`).
2. **categories:** Clothing collections (e.g., T-Shirts, Shirts, Jeans, Jackets, Hoodies).
3. **products:** Clothes with titles, descriptions, prices, sizes (`S, M, L, XL`), colors, stock quantities, and images.
4. **orders:** Order master table (Customer details, shipping address, total price, payment method: `COD`, status: `pending`, `completed`, `cancelled`).
5. **order_items:** Line items linking ordered products, selected sizes, quantities, and unit prices.

---

## 4. Development Phases
- **Phase 1:** Planning, Workspace Inspection & Architectural Blueprint *(Current)*.
- **Phase 2:** Database Design & Initial SQL Data (`schema.sql`).
- **Phase 3:** Core Configuration & Shared Helpers (`config/database.php`, `includes/functions.php`).
- **Phase 4:** Storefront Layout & Base Styling (`assets/css/style.css`, `header.php`, `footer.php`).
- **Phase 5:** Customer Pages (Catalog, Product View, Cart, Cash-on-Delivery Checkout).
- **Phase 6:** Authentication & Admin Management Panel.
- **Phase 7:** End-to-end Local Verification & Beginner Setup Guide for XAMPP.
