-- =====================================================================
-- Database Schema for Modern Clothing Store
-- Database Name: clothing_store
-- Charset: utf8mb4 (Full Unicode support for all languages & symbols)
-- =====================================================================

CREATE DATABASE IF NOT EXISTS clothing_store
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE clothing_store;

-- ---------------------------------------------------------------------
-- 1. Table: users
-- Stores customer accounts and administrator credentials
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin', 'customer') NOT NULL DEFAULT 'customer',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. Table: categories
-- Stores clothing categories (e.g. T-Shirts, Hoodies, Jeans)
-- ---------------------------------------------------------------------
CREATE TABLE categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  slug VARCHAR(100) NOT NULL UNIQUE,
  description TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. Table: products
-- Stores clothes details, sizing, colors, pricing and stock count
-- ---------------------------------------------------------------------
CREATE TABLE products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT NOT NULL,
  name VARCHAR(200) NOT NULL,
  slug VARCHAR(200) NOT NULL UNIQUE,
  description TEXT NOT NULL,
  price DECIMAL(10, 2) NOT NULL,
  available_sizes VARCHAR(100) NOT NULL DEFAULT 'S, M, L, XL',
  available_colors VARCHAR(100) NOT NULL DEFAULT 'Black, White, Navy',
  stock_quantity INT NOT NULL DEFAULT 10,
  image VARCHAR(255) NOT NULL DEFAULT 'placeholder.jpg',
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_products_category
    FOREIGN KEY (category_id) REFERENCES categories(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. Table: orders
-- Stores customer orders with Cash on Delivery (COD) payment
-- ---------------------------------------------------------------------
CREATE TABLE orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  customer_name VARCHAR(120) NOT NULL,
  customer_email VARCHAR(150) NOT NULL,
  customer_phone VARCHAR(30) NOT NULL,
  shipping_address TEXT NOT NULL,
  city VARCHAR(80) NOT NULL,
  total_amount DECIMAL(10, 2) NOT NULL,
  payment_method VARCHAR(50) NOT NULL DEFAULT 'Cash on Delivery',
  order_status ENUM('pending', 'processing', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_orders_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. Table: order_items
-- Stores each line item in an order, preserving size, color and price
-- ---------------------------------------------------------------------
CREATE TABLE order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  product_id INT NOT NULL,
  product_name VARCHAR(200) NOT NULL,
  selected_size VARCHAR(20) NOT NULL,
  selected_color VARCHAR(50) NOT NULL,
  unit_price DECIMAL(10, 2) NOT NULL,
  quantity INT NOT NULL,
  subtotal DECIMAL(10, 2) NOT NULL,
  CONSTRAINT fk_items_order
    FOREIGN KEY (order_id) REFERENCES orders(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_items_product
    FOREIGN KEY (product_id) REFERENCES products(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- INITIAL SEED DATA
-- Default password for accounts below is: password
-- Bcrypt Hash: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
-- =====================================================================

-- Insert Default Admin and Customer
INSERT INTO users (full_name, email, password, role) VALUES
('Store Administrator', 'admin@store.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('John Customer', 'customer@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer');

-- Insert Clothing Categories
INSERT INTO categories (id, name, slug, description) VALUES
(1, 'T-Shirts', 't-shirts', 'Casual, graphic and plain everyday cotton t-shirts'),
(2, 'Shirts', 'shirts', 'Formal and casual button-up shirts for men and women'),
(3, 'Hoodies & Sweatshirts', 'hoodies-sweatshirts', 'Warm and cozy streetwear hoodies and pullovers'),
(4, 'Jeans & Denim', 'jeans-denim', 'Classic cut and slim fit premium denim jeans'),
(5, 'Jackets & Coats', 'jackets-coats', 'Outerwear jackets for winter and autumn styling');

-- Insert Demo Clothing Products
INSERT INTO products (category_id, name, slug, description, price, available_sizes, available_colors, stock_quantity, image, is_featured) VALUES
(1, 'Classic Organic Cotton T-Shirt', 'classic-organic-cotton-t-shirt', 'Premium quality 100% organic combed cotton t-shirt with breathable fabric. Perfect for daily casual wear.', 24.99, 'S, M, L, XL', 'Black, White, Navy', 25, 'classic-cotton-tshirt.jpg', 1),
(1, 'Vintage Oversized Graphic Tee', 'vintage-oversized-graphic-tee', 'Trendy boxy fit t-shirt with retro aesthetic print and ribbed crew neckline.', 29.50, 'M, L, XL', 'Washed Black, Olive', 18, 'oversized-graphic-tee.jpg', 1),
(2, 'Slim Fit Oxford Casual Shirt', 'slim-fit-oxford-casual-shirt', 'Smart-casual button-down collar shirt crafted from breathable cotton weave.', 39.99, 'S, M, L, XL', 'Light Blue, White, Charcoal', 15, 'oxford-casual-shirt.jpg', 1),
(2, 'Linen Blend Summer Shirt', 'linen-blend-summer-shirt', 'Lightweight and airy linen-cotton shirt designed for warm summer days.', 34.00, 'M, L, XL', 'Beige, Sage Green', 12, 'linen-summer-shirt.jpg', 0),
(3, 'Heavyweight Fleece Pullover Hoodie', 'heavyweight-fleece-pullover-hoodie', 'Ultra-soft fleece lined heavyweight hoodie with kangaroo pocket and double-layer hood.', 54.99, 'S, M, L, XL', 'Black, Grey Melange, Forest Green', 30, 'heavyweight-fleece-hoodie.jpg', 1),
(3, 'Minimalist Streetwear Sweatshirt', 'minimalist-streetwear-sweatshirt', 'Relaxed silhouette crewneck sweatshirt in modern earth tones.', 44.50, 'S, M, L', 'Dusty Rose, Sand, Black', 20, 'minimalist-sweatshirt.jpg', 0),
(4, 'Slim Straight Stretch Denim Jeans', 'slim-straight-stretch-denim-jeans', 'Durable 5-pocket denim pants with subtle stretch for maximum all-day comfort.', 59.99, '30, 32, 34, 36', 'Dark Indigo, Washed Grey', 22, 'slim-straight-jeans.jpg', 1),
(4, 'Relaxed Fit Cargo Denim Pants', 'relaxed-fit-cargo-denim-pants', 'Utility cargo pockets with comfortable loose fit and sturdy cotton denim.', 64.00, '30, 32, 34', 'Military Green, Jet Black', 14, 'cargo-denim-pants.jpg', 0),
(5, 'Classic Winter Puffer Jacket', 'classic-winter-puffer-jacket', 'Insulated quilted puffer jacket with wind-resistant shell and warm synthetic filling.', 89.99, 'M, L, XL', 'Matte Black, Navy Blue', 10, 'classic-puffer-jacket.jpg', 1),
(5, 'Vintage Washed Denim Trucker Jacket', 'vintage-washed-denim-trucker-jacket', 'Timeless trucker silhouette with metallic button fastening and chest flap pockets.', 69.90, 'S, M, L, XL', 'Vintage Blue, Black Acid', 16, 'denim-trucker-jacket.jpg', 0);
