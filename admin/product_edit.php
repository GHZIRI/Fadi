<?php
$pageTitle = 'Edit Product | THREAD&CO Admin';
$pageHeading = 'Edit Product';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin('login.php');

$productId = filter_var($_POST['id'] ?? $_GET['id'] ?? '', FILTER_VALIDATE_INT);
if ($productId === false || $productId < 1) {
    setFlashMessage('error', 'Invalid product.');
    header('Location: products.php');
    exit;
}

$productQuery = $pdo->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');
$productQuery->execute([':id' => $productId]);
$product = $productQuery->fetch();
if (!$product) {
    setFlashMessage('error', 'Product not found.');
    header('Location: products.php');
    exit;
}

$errors = [];
$name = $product['name'];
$description = $product['description'];
$price = (string)$product['price'];
$categoryId = (string)$product['category_id'];
$availableSizes = $product['available_sizes'];
$availableColors = $product['available_colors'];
$stockQuantity = (string)$product['stock_quantity'];
$isFeatured = (bool)$product['is_featured'];
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
    $imageFileName = $product['image'];
    $newImagePath = null;
    $mimeType = '';
    $allowedImageTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if ($image && $image['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($image['error'] !== UPLOAD_ERR_OK) {
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
    }

    if (!$errors) {
        $asciiName = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        $slugBase = trim((string)preg_replace('/[^a-z0-9]+/', '-', strtolower($asciiName ?: '')), '-');
        if ($slugBase === '') {
            $slugBase = 'product';
        }

        $slug = $slugBase;
        $suffix = 2;
        $slugQuery = $pdo->prepare('SELECT id FROM products WHERE slug = :slug AND id <> :id LIMIT 1');
        while (true) {
            $slugQuery->execute([':slug' => $slug, ':id' => $productId]);
            if (!$slugQuery->fetch()) {
                break;
            }
            $slug = $slugBase . '-' . $suffix;
            $suffix++;
        }

        if ($image && $image['error'] === UPLOAD_ERR_OK) {
            $imageDirectory = __DIR__ . '/../assets/images/products';
            if (!is_dir($imageDirectory) && !mkdir($imageDirectory, 0755, true) && !is_dir($imageDirectory)) {
                $errors[] = 'The product image directory could not be created.';
            } else {
                $imageFileName = bin2hex(random_bytes(16)) . '.' . $allowedImageTypes[$mimeType];
                $newImagePath = $imageDirectory . '/' . $imageFileName;
                if (!move_uploaded_file($image['tmp_name'], $newImagePath)) {
                    $errors[] = 'The image could not be saved. Check folder permissions and try again.';
                    $newImagePath = null;
                }
            }
        }

        if (!$errors) {
            try {
                $updateProduct = $pdo->prepare('
                    UPDATE products SET
                        category_id = :category_id,
                        name = :name,
                        slug = :slug,
                        description = :description,
                        price = :price,
                        available_sizes = :available_sizes,
                        available_colors = :available_colors,
                        stock_quantity = :stock_quantity,
                        image = :image,
                        is_featured = :is_featured
                    WHERE id = :id
                ');
                $updateProduct->execute([
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
                    ':id' => $productId,
                ]);

                setFlashMessage('success', 'Product updated successfully.');
                header('Location: products.php');
                exit;
            } catch (PDOException $e) {
                if ($newImagePath !== null && is_file($newImagePath)) {
                    unlink($newImagePath);
                }
                $errors[] = 'The product could not be updated. Check the details and try again.';
            }
        }
    }
}

$csrfToken = generateCsrfToken();
$currentImagePath = __DIR__ . '/../assets/images/products/' . basename($product['image']);
$currentImageUrl = '../assets/images/products/' . rawurlencode(basename($product['image']));
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
        <p style="padding:0 24px;">A category is required to edit this product.</p>
    <?php else: ?>
        <form method="POST" enctype="multipart/form-data" class="admin-form" style="padding:24px;">
            <input type="hidden" name="id" value="<?= (int)$productId; ?>">
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
                <input id="available_sizes" name="available_sizes" type="text" class="admin-form-control" maxlength="100" value="<?= htmlspecialchars($availableSizes, ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>

            <div class="admin-form-group">
                <label for="available_colors" class="admin-form-label">Available Colors</label>
                <input id="available_colors" name="available_colors" type="text" class="admin-form-control" maxlength="100" value="<?= htmlspecialchars($availableColors, ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>

            <div class="admin-form-group">
                <label for="image" class="admin-form-label">Replace Product Image (JPG, PNG, or WebP; max 5 MB)</label>
                <?php if (is_file($currentImagePath)): ?>
                    <div style="margin-bottom:10px;">
                        <img src="<?= htmlspecialchars($currentImageUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Current product image" style="width:120px; height:120px; object-fit:cover; border-radius:4px;">
                    </div>
                <?php endif; ?>
                <input id="image" name="image" type="file" class="admin-form-control" accept="image/jpeg,image/png,image/webp">
                <small style="color:var(--admin-text-muted);">Leave empty to keep the current image.</small>
            </div>

            <div class="admin-form-group" style="display:flex; align-items:center; gap:10px;">
                <input id="is_featured" name="is_featured" type="checkbox" value="1" <?= $isFeatured ? 'checked' : ''; ?>>
                <label for="is_featured" class="admin-form-label" style="margin:0;">Feature this product on the storefront</label>
            </div>

            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <button type="submit" class="btn-admin btn-admin-primary">Save Changes</button>
                <a href="products.php" class="btn-admin btn-admin-secondary">Cancel</a>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
