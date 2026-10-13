<?php
$pageTitle = 'Add Product | THREAD&CO Admin';
$pageHeading = 'Add New Product';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin('login.php');

$errors = [];
$name = '';
$description = '';
$price = '';
$categoryId = '';
$availableSizes = '';
$availableColors = '';
$stockQuantity = '10';
$isFeatured = false;
$categories = [];

try {
    $categories = $pdo->query('SELECT id, name FROM categories ORDER BY name ASC')->fetchAll();
} catch (PDOException $e) {
    $errors[] = 'Categories could not be loaded. Please try again later.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $categoryId = trim($_POST['category_id'] ?? '');
    $availableSizes = trim($_POST['available_sizes'] ?? '');
    $availableColors = trim($_POST['available_colors'] ?? '');
    $stockQuantity = trim($_POST['stock_quantity'] ?? '');
    $isFeatured = isset($_POST['is_featured']);

    if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session token is invalid. Refresh the page and try again.';
    }

    if ($name === '' || mb_strlen($name) > 200) {
        $errors[] = 'Enter a product name of 1 to 200 characters.';
    }
    if ($description === '') {
        $errors[] = 'Enter a product description.';
    }
    if (!is_numeric($price) || (float)$price <= 0 || (float)$price > 99999999.99) {
        $errors[] = 'Enter a valid price greater than zero.';
    }
    if (filter_var($categoryId, FILTER_VALIDATE_INT) === false || !in_array((int)$categoryId, array_map('intval', array_column($categories, 'id')), true)) {
        $errors[] = 'Choose a valid category.';
    }
    if ($availableSizes === '' || mb_strlen($availableSizes) > 100) {
        $errors[] = 'Enter available sizes, separated by commas (up to 100 characters).';
    }
    if ($availableColors === '' || mb_strlen($availableColors) > 100) {
        $errors[] = 'Enter available colors, separated by commas (up to 100 characters).';
    }
    if (filter_var($stockQuantity, FILTER_VALIDATE_INT) === false || (int)$stockQuantity < 0) {
        $errors[] = 'Stock quantity must be a whole number of zero or more.';
    }

    $image = $_FILES['image'] ?? null;
    $mimeType = '';
    $allowedImageTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    $imageDirectory = __DIR__ . '/../assets/images/products';

    if (!$image || $image['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'Select a product image to upload.';
    } elseif ($image['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'The image upload failed. Please select the image again.';
    } elseif ($image['size'] > 5 * 1024 * 1024) {
        $errors[] = 'The image must be 5 MB or smaller.';
    } else {
        $imageInfo = @getimagesize($image['tmp_name']);
        $mimeType = $imageInfo['mime'] ?? '';
        if (!isset($allowedImageTypes[$mimeType])) {
            $errors[] = 'Use a valid JPG, PNG, or WebP image.';
        }
    }

    if (!$errors) {
        if (!is_dir($imageDirectory) && !mkdir($imageDirectory, 0755, true) && !is_dir($imageDirectory)) {
            $errors[] = 'The product image directory could not be created.';
        }
    }

    if (!$errors) {
        $extension = $allowedImageTypes[$mimeType];
        $imageFileName = bin2hex(random_bytes(16)) . '.' . $extension;
        $imagePath = $imageDirectory . '/' . $imageFileName;

        if (!move_uploaded_file($image['tmp_name'], $imagePath)) {
            $errors[] = 'The image could not be saved. Check folder permissions and try again.';
        } else {
            $asciiName = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
            $slugBase = trim((string)preg_replace('/[^a-z0-9]+/', '-', strtolower($asciiName ?: '')), '-');
            if ($slugBase === '') {
                $slugBase = 'product';
            }

            try {
                $slug = $slugBase;
                $suffix = 2;
                $slugQuery = $pdo->prepare('SELECT id FROM products WHERE slug = :slug LIMIT 1');
                while (true) {
                    $slugQuery->execute([':slug' => $slug]);
                    if (!$slugQuery->fetch()) {
                        break;
                    }
                    $slug = $slugBase . '-' . $suffix;
                    $suffix++;
                }

                $insertProduct = $pdo->prepare('
                    INSERT INTO products (
                        category_id, name, slug, description, price, available_sizes,
                        available_colors, stock_quantity, image, is_featured
                    ) VALUES (
                        :category_id, :name, :slug, :description, :price, :available_sizes,
                        :available_colors, :stock_quantity, :image, :is_featured
                    )
                ');
                $insertProduct->execute([
                    ':category_id' => (int)$categoryId,
                    ':name' => $name,
                    ':slug' => $slug,
                    ':description' => $description,
                    ':price' => number_format((float)$price, 2, '.', ''),
                    ':available_sizes' => $availableSizes,
                    ':available_colors' => $availableColors,
                    ':stock_quantity' => (int)$stockQuantity,
                    ':image' => $imageFileName,
                    ':is_featured' => $isFeatured ? 1 : 0,
                ]);

                setFlashMessage('success', 'Product added successfully.');
                header('Location: products.php');
                exit;
            } catch (PDOException $e) {
                if (is_file($imagePath)) {
                    unlink($imagePath);
                }
                $errors[] = 'The product could not be saved. Check the details and try again.';
            }
        }
    }
}

$csrfToken = generateCsrfToken();
require_once __DIR__ . '/header.php';
?>

<div class="admin-card">
    <div class="admin-card-header">
        <span class="admin-card-title">Product Details</span>
    </div>

    <?php if ($errors): ?>
        <div class="admin-alert admin-alert-error" role="alert">
            <ul style="margin:0; padding-left:20px;">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!$categories): ?>
        <p style="padding:0 24px;">Create a category before adding a product.</p>
    <?php else: ?>
        <form method="POST" enctype="multipart/form-data" class="admin-form" style="padding:24px;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

            <div class="admin-form-group">
                <label for="name" class="admin-form-label">Product Name</label>
                <input id="name" name="name" type="text" class="admin-form-control" maxlength="200" value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>

            <div class="admin-form-group">
                <label for="category_id" class="admin-form-label">Category</label>
                <select id="category_id" name="category_id" class="admin-form-control" required>
                    <option value="">Select a category</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int)$category['id']; ?>" <?= (string)$categoryId === (string)$category['id'] ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="admin-form-group">
                <label for="description" class="admin-form-label">Description</label>
                <textarea id="description" name="description" class="admin-form-control" rows="5" required><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>

            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:16px;">
                <div class="admin-form-group">
                    <label for="price" class="admin-form-label">Price (DH)</label>
                    <input id="price" name="price" type="number" class="admin-form-control" min="0.01" max="99999999.99" step="0.01" value="<?= htmlspecialchars($price, ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>

                <div class="admin-form-group">
                    <label for="stock_quantity" class="admin-form-label">Stock Quantity</label>
                    <input id="stock_quantity" name="stock_quantity" type="number" class="admin-form-control" min="0" step="1" value="<?= htmlspecialchars($stockQuantity, ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
            </div>

            <div class="admin-form-group">
                <label for="available_sizes" class="admin-form-label">Available Sizes</label>
                <input id="available_sizes" name="available_sizes" type="text" class="admin-form-control" maxlength="100" placeholder="S, M, L, XL" value="<?= htmlspecialchars($availableSizes, ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>

            <div class="admin-form-group">
                <label for="available_colors" class="admin-form-label">Available Colors</label>
                <input id="available_colors" name="available_colors" type="text" class="admin-form-control" maxlength="100" placeholder="Black, White, Navy" value="<?= htmlspecialchars($availableColors, ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>

            <div class="admin-form-group">
                <label for="image" class="admin-form-label">Product Image (JPG, PNG, or WebP; max 5 MB)</label>
                <input id="image" name="image" type="file" class="admin-form-control" accept="image/jpeg,image/png,image/webp" required>
            </div>

            <div class="admin-form-group" style="display:flex; align-items:center; gap:10px;">
                <input id="is_featured" name="is_featured" type="checkbox" value="1" <?= $isFeatured ? 'checked' : ''; ?>>
                <label for="is_featured" class="admin-form-label" style="margin:0;">Feature this product on the storefront</label>
            </div>

            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <button type="submit" class="btn-admin btn-admin-primary">Add Product</button>
                <a href="products.php" class="btn-admin btn-admin-secondary">Cancel</a>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
