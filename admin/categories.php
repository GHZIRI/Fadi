<?php
$pageTitle = 'Categories | THREAD&CO Admin';
$pageHeading = 'Manage Categories';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin('login.php');

$errors = [];
$categoryName = '';
$categoryDescription = '';
$editingCategoryId = 0;

if (isset($_GET['edit'])) {
    $editingCategoryId = filter_var($_GET['edit'], FILTER_VALIDATE_INT) ?: 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session token is invalid. Refresh the page and try again.';
    } elseif ($action === 'delete') {
        $categoryId = filter_var($_POST['category_id'] ?? null, FILTER_VALIDATE_INT);

        if ($categoryId === false || $categoryId < 1) {
            $errors[] = 'Choose a valid category.';
        } else {
            try {
                $pdo->beginTransaction();

                $lockCategory = $pdo->prepare('SELECT id FROM categories WHERE id = :id FOR UPDATE');
                $lockCategory->execute([':id' => $categoryId]);

                if (!$lockCategory->fetch()) {
                    $pdo->rollBack();
                    $errors[] = 'The category could not be found.';
                } else {
                    $lockProducts = $pdo->prepare('SELECT id FROM products WHERE category_id = :id LIMIT 1 FOR UPDATE');
                    $lockProducts->execute([':id' => $categoryId]);

                    if ($lockProducts->fetch()) {
                        $pdo->rollBack();
                        $errors[] = 'Move or delete this category\'s products before deleting it.';
                    } else {
                        $deleteCategory = $pdo->prepare('DELETE FROM categories WHERE id = :id');
                        $deleteCategory->execute([':id' => $categoryId]);
                        $pdo->commit();
                        setFlashMessage('success', 'Category deleted successfully.');
                        header('Location: categories.php');
                        exit;
                    }
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = 'The category could not be deleted. Please try again.';
            }
        }
    } elseif ($action === 'add' || $action === 'update') {
        $categoryName = trim($_POST['name'] ?? '');
        $categoryDescription = trim($_POST['description'] ?? '');
        $editingCategoryId = $action === 'update'
            ? (filter_var($_POST['category_id'] ?? null, FILTER_VALIDATE_INT) ?: 0)
            : 0;

        if ($categoryName === '' || mb_strlen($categoryName) > 100) {
            $errors[] = 'Enter a category name of 1 to 100 characters.';
        }
        if ($action === 'update' && $editingCategoryId < 1) {
            $errors[] = 'Choose a valid category to edit.';
        }

        if (!$errors) {
            $asciiName = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $categoryName);
            $slugBase = trim((string)preg_replace('/[^a-z0-9]+/', '-', strtolower($asciiName ?: '')), '-');
            if ($slugBase === '') {
                $slugBase = 'category';
            }

            try {
                if ($action === 'update') {
                    $categoryExists = $pdo->prepare('SELECT id FROM categories WHERE id = :id');
                    $categoryExists->execute([':id' => $editingCategoryId]);
                    if (!$categoryExists->fetch()) {
                        $errors[] = 'The category could not be found.';
                    }
                }

                if (!$errors) {
                    $slug = $slugBase;
                    $suffix = 2;
                    $slugQuery = $pdo->prepare('SELECT id FROM categories WHERE slug = :slug AND id <> :id LIMIT 1');

                    while (true) {
                        $slugQuery->execute([
                            ':slug' => $slug,
                            ':id' => $editingCategoryId,
                        ]);
                        if (!$slugQuery->fetch()) {
                            break;
                        }
                        $slug = $slugBase . '-' . $suffix;
                        $suffix++;
                    }

                    if ($action === 'add') {
                        $saveCategory = $pdo->prepare('INSERT INTO categories (name, slug, description) VALUES (:name, :slug, :description)');
                        $successMessage = 'Category added successfully.';
                    } else {
                        $saveCategory = $pdo->prepare('UPDATE categories SET name = :name, slug = :slug, description = :description WHERE id = :id');
                        $successMessage = 'Category updated successfully.';
                    }

                    $categoryValues = [
                        ':name' => $categoryName,
                        ':slug' => $slug,
                        ':description' => $categoryDescription !== '' ? $categoryDescription : null,
                    ];
                    if ($action === 'update') {
                        $categoryValues[':id'] = $editingCategoryId;
                    }
                    $saveCategory->execute($categoryValues);

                    setFlashMessage('success', $successMessage);
                    header('Location: categories.php');
                    exit;
                }
            } catch (PDOException $e) {
                $errors[] = 'The category could not be saved. Its name may already be in use.';
            }
        }
    } else {
        $errors[] = 'Choose a valid category action.';
    }
}

$editingCategory = null;
if ($editingCategoryId > 0 && !$categoryName) {
    try {
        $editQuery = $pdo->prepare('SELECT id, name, description FROM categories WHERE id = :id');
        $editQuery->execute([':id' => $editingCategoryId]);
        $editingCategory = $editQuery->fetch();

        if ($editingCategory) {
            $categoryName = $editingCategory['name'];
            $categoryDescription = $editingCategory['description'] ?? '';
        } else {
            $errors[] = 'The category could not be found.';
            $editingCategoryId = 0;
        }
    } catch (PDOException $e) {
        $errors[] = 'Category details could not be loaded. Please try again later.';
    }
}

try {
    $categories = $pdo->query('
        SELECT c.id, c.name, c.slug, c.description, c.created_at, COUNT(p.id) AS product_count
        FROM categories c
        LEFT JOIN products p ON p.category_id = c.id
        GROUP BY c.id, c.name, c.slug, c.description, c.created_at
        ORDER BY c.name ASC
    ')->fetchAll();
} catch (PDOException $e) {
    $categories = [];
    $errors[] = 'Categories could not be loaded. Please try again later.';
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

<div class="admin-card" style="margin-bottom:24px;">
    <div class="admin-card-header">
        <span class="admin-card-title"><?= $editingCategoryId > 0 ? 'Edit Category' : 'Add Category'; ?></span>
    </div>
    <form method="POST" class="admin-form" style="padding:24px;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="action" value="<?= $editingCategoryId > 0 ? 'update' : 'add'; ?>">
        <?php if ($editingCategoryId > 0): ?>
            <input type="hidden" name="category_id" value="<?= (int)$editingCategoryId; ?>">
        <?php endif; ?>

        <div class="admin-form-group">
            <label for="name" class="admin-form-label">Category Name</label>
            <input id="name" name="name" type="text" class="admin-form-control" maxlength="100" value="<?= htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8'); ?>" required>
        </div>

        <div class="admin-form-group">
            <label for="description" class="admin-form-label">Description</label>
            <textarea id="description" name="description" class="admin-form-control" rows="3"><?= htmlspecialchars($categoryDescription, ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>

        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <button type="submit" class="btn-admin btn-admin-primary">
                <?= $editingCategoryId > 0 ? 'Save Changes' : 'Add Category'; ?>
            </button>
            <?php if ($editingCategoryId > 0): ?>
                <a href="categories.php" class="btn-admin btn-admin-secondary">Cancel</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="admin-card">
    <div class="admin-card-header">
        <span class="admin-card-title">All Categories (<?= count($categories); ?>)</span>
    </div>

    <?php if (!$categories): ?>
        <p style="padding:24px; color:var(--admin-text-muted);">No categories found.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Slug</th>
                        <th>Products</th>
                        <th>Description</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $category): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                            <td><?= htmlspecialchars($category['slug'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= (int)$category['product_count']; ?></td>
                            <td><?= htmlspecialchars($category['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td style="text-align:right; white-space:nowrap;">
                                <a href="categories.php?edit=<?= (int)$category['id']; ?>" class="btn-admin btn-admin-secondary btn-admin-sm">Edit</a>
                                <form method="POST" style="display:inline-block; margin-left:6px;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="category_id" value="<?= (int)$category['id']; ?>">
                                    <button type="submit" class="btn-admin btn-admin-danger btn-admin-sm btn-confirm-action" data-confirm="Delete the category &quot;<?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?>&quot;? Categories with products cannot be deleted.">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>