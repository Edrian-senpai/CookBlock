<?php
require 'connect.php';
session_start();
$user = $_SESSION['user'] ?? null;
if (!$user || ($user['role'] !== 'admin' && $user['role'] !== 'superadmin')) {
    header('Location: login.php');
    exit;
}

// Handle add category
$addMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $catName = trim($_POST['category_name'] ?? '');
    if ($catName !== '') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE name = ?");
        $stmt->execute([$catName]);
        if ($stmt->fetchColumn() == 0) {
            $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
            $stmt->execute([$catName]);
            // Log the action
            if ($user['role'] === 'superadmin') {
                $logStmt = $pdo->prepare("INSERT INTO admin_logs (admin_id, action, target, details) VALUES (?, ?, ?, ?)");
                $logStmt->execute([$user['id'], 'add_category', $catName, 'Superadmin added category: ' . $catName]);
            } else {
                $logStmt = $pdo->prepare("INSERT INTO user_logs (user_id, action, details) VALUES (?, ?, ?)");
                $logStmt->execute([$user['id'], 'add_category', 'Admin added category: ' . $catName]);
            }
            $addMsg = '<div class="alert alert-success mb-2">Category added!</div>';
        } else {
            $addMsg = '<div class="alert alert-warning mb-2">Category already exists.</div>';
        }
    } else {
        $addMsg = '<div class="alert alert-danger mb-2">Category name cannot be empty.</div>';
    }
}

// Handle delete category
if (isset($_POST['delete_category']) && isset($_POST['category_id'])) {
    $catId = (int)$_POST['category_id'];
    // Optionally: Check if any recipes use this category before deleting
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM recipes WHERE category_id = ?");
    $stmt->execute([$catId]);
    if ($stmt->fetchColumn() > 0) {
        $addMsg = '<div class="alert alert-danger mb-2">Cannot delete: Category is in use by recipes.</div>';
    } else {
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$catId]);
        // Log the action
        if ($user['role'] === 'superadmin') {
            $logStmt = $pdo->prepare("INSERT INTO admin_logs (admin_id, action, target, details) VALUES (?, ?, ?, ?)");
            $logStmt->execute([$user['id'], 'delete_category', $catId, 'Superadmin deleted category ID: ' . $catId]);
        } else {
            $logStmt = $pdo->prepare("INSERT INTO user_logs (user_id, action, details) VALUES (?, ?, ?)");
            $logStmt->execute([$user['id'], 'delete_category', 'Admin deleted category ID: ' . $catId]);
        }
        $addMsg = '<div class="alert alert-success mb-2">Category deleted.</div>';
    }
}

// Handle edit category
if (isset($_POST['edit_category']) && isset($_POST['category_id']) && isset($_POST['new_name'])) {
    $catId = (int)$_POST['category_id'];
    $newName = trim($_POST['new_name']);
    if ($newName !== '') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE name = ? AND id != ?");
        $stmt->execute([$newName, $catId]);
        if ($stmt->fetchColumn() == 0) {
            $stmt = $pdo->prepare("UPDATE categories SET name = ? WHERE id = ?");
            $stmt->execute([$newName, $catId]);
            // Log the action
            if ($user['role'] === 'superadmin') {
                $logStmt = $pdo->prepare("INSERT INTO admin_logs (admin_id, action, target, details) VALUES (?, ?, ?, ?)");
                $logStmt->execute([$user['id'], 'edit_category', $catId, 'Superadmin updated category ID: ' . $catId . ' to ' . $newName]);
            } else {
                $logStmt = $pdo->prepare("INSERT INTO user_logs (user_id, action, details) VALUES (?, ?, ?)");
                $logStmt->execute([$user['id'], 'edit_category', 'Admin updated category ID: ' . $catId . ' to ' . $newName]);
            }
            $addMsg = '<div class="alert alert-success mb-2">Category updated.</div>';
        } else {
            $addMsg = '<div class="alert alert-warning mb-2">Another category with this name already exists.</div>';
        }
    } else {
        $addMsg = '<div class="alert alert-danger mb-2">Category name cannot be empty.</div>';
    }
}

// Fetch all categories
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Categories - CookBlock Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="text-orange fw-bold">
            <i class="bi bi-tags me-2"></i>Manage Categories
        </h2>
        <a href="view_all.php" class="btn btn-outline-orange">
            <i class="bi bi-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <div class="card shadow-sm border-orange mb-4">
        <div class="card-body">
            <h5 class="mb-3 text-orange"><i class="bi bi-plus-circle me-2"></i>Add New Category</h5>
            <?= $addMsg ?>
            <form method="post" class="row g-2 align-items-center">
                <div class="col-md-8">
                    <input type="text" name="category_name" class="form-control" placeholder="Category name" required>
                </div>
                <div class="col-md-4">
                    <button type="submit" name="add_category" class="btn btn-orange w-100">
                        <i class="bi bi-plus-lg"></i> Add Category
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-orange">
        <div class="card-body">
            <h5 class="mb-3 text-orange"><i class="bi bi-list-ul me-2"></i>All Categories</h5>
            <?php if (count($categories)): ?>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 60%;">Category Name</th>
                                <th style="width: 40%;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($categories as $cat): ?>
                            <tr>
                                <td>
                                    <form method="post" class="d-flex align-items-center gap-2 mb-0">
                                        <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                                        <input type="text" name="new_name" value="<?= htmlspecialchars($cat['name']) ?>" class="form-control form-control-sm" style="max-width: 250px;" required>
                                        <button type="submit" name="edit_category" class="btn btn-outline-orange btn-sm" title="Save">
                                            <i class="bi bi-save"></i>
                                        </button>
                                    </form>
                                </td>
                                <td>
                                    <form method="post" onsubmit="return confirm('Delete this category?');" class="d-inline">
                                        <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                                        <button type="submit" name="delete_category" class="btn btn-danger btn-sm">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-warning text-center mb-0">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    No categories found.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>