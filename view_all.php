<?php
require 'connect.php';
// Fetch site-wide settings FIRST so variables are always defined before use
$pdo->exec("CREATE TABLE IF NOT EXISTS site_settings (name VARCHAR(64) PRIMARY KEY, value TEXT)");
function getSetting($name, $default = '') {
    global $pdo;
    $stmt = $pdo->prepare("SELECT value FROM site_settings WHERE name = ?");
    $stmt->execute([$name]);
    $val = $stmt->fetchColumn();
    return $val !== false ? $val : $default;
}

$siteLogo = getSetting('logo_url', '');
$announcement = getSetting('announcement', '');
$maintenance = getSetting('maintenance_mode', '0');
$contactEmail = getSetting('contact_email', '');

// Include the file responsible for retrieving and preparing recipe data
require 'fetch_view.php';

// Include filtering logic (e.g., search, category/tag filtering)
require 'filters.php';

// Retrieve the currently logged-in user from the session (or null if not logged in)
$user = $_SESSION['user'] ?? null;
$isSuperAdmin = $user && ($user['role'] ?? 'user') === 'superadmin';
$isAdmin = $user && (($user['role'] ?? 'user') === 'admin' || $isSuperAdmin);

// Check if the user is verified
if ($user && isset($user['is_verified']) && !$user['is_verified']) {
    $_SESSION['error'] = "Please verify your email before accessing your account.";
    header("Location: login.php");
    exit;
}

// --- Manage Users Actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin) {
    // Remove user
    if (isset($_POST['delete_user'], $_POST['user_id'])) {
        $userId = (int)$_POST['user_id'];
        // Fetch target user's role
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $targetRole = $stmt->fetchColumn();

        // Only allow delete if:
        // - Superadmin can delete anyone except themselves
        // - Admin can only delete users with lower role (not admin or superadmin)
        $canDelete = false;
        if ($isSuperAdmin && $userId !== (int)$user['id']) {
            $canDelete = true;
        } elseif (($user['role'] ?? 'user') === 'admin' && $targetRole === 'user') {
            $canDelete = true;
        }

        if ($canDelete) {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            // Log to admin_logs
            $pdo->exec("CREATE TABLE IF NOT EXISTS admin_logs (id INT AUTO_INCREMENT PRIMARY KEY, admin_id INT, action VARCHAR(255), details TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
            $adminId = $user['id'] ?? 0;
            $logAction = 'User deleted';
            $logDetails = "Deleted user with ID {$userId}";
            $logStmt = $pdo->prepare("INSERT INTO admin_logs (admin_id, action, details) VALUES (?, ?, ?)");
            $logStmt->execute([$adminId, $logAction, $logDetails]);
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    showFlashMessage('User deleted successfully.');
                });
            </script>";
        }
    }
    // Make admin
    if (isset($_POST['make_admin'], $_POST['user_id'])) {
        $userId = (int)$_POST['user_id'];
        // Only superadmin can promote to admin
        if ($isSuperAdmin) {
            $stmt = $pdo->prepare("UPDATE users SET role = 'admin' WHERE id = ?");
            $stmt->execute([$userId]);
            // Log to admin_logs
            $pdo->exec("CREATE TABLE IF NOT EXISTS admin_logs (id INT AUTO_INCREMENT PRIMARY KEY, admin_id INT, action VARCHAR(255), details TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
            $adminId = $user['id'] ?? 0;
            $logAction = 'User promoted to admin';
            $logDetails = "Promoted user with ID {$userId} to admin.";
            $logStmt = $pdo->prepare("INSERT INTO admin_logs (admin_id, action, details) VALUES (?, ?, ?)");
            $logStmt->execute([$adminId, $logAction, $logDetails]);
            // Set a flash message (optional)
            $_SESSION['flash_message'] = 'User promoted to admin.';
            // Refresh the page to reflect changes
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        }
    }
    // Demote admin to user
    if (isset($_POST['make_user'], $_POST['user_id'])) {
        $userId = (int)$_POST['user_id'];
        // Fetch target user's role
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $targetRole = $stmt->fetchColumn();

        // Only superadmin can demote admin
        if ($isSuperAdmin && $targetRole === 'admin' && $userId !== (int)$user['id']) {
            $stmt = $pdo->prepare("UPDATE users SET role = 'user' WHERE id = ?");
            $stmt->execute([$userId]);
            // Log to admin_logs
            $pdo->exec("CREATE TABLE IF NOT EXISTS admin_logs (id INT AUTO_INCREMENT PRIMARY KEY, admin_id INT, action VARCHAR(255), details TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
            $adminId = $user['id'] ?? 0;
            $logAction = 'Admin demoted to user';
            $logDetails = "Demoted admin with ID {$userId} to user.";
            $logStmt = $pdo->prepare("INSERT INTO admin_logs (admin_id, action, details) VALUES (?, ?, ?)");
            $logStmt->execute([$adminId, $logAction, $logDetails]);
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    showFlashMessage('Admin demoted to user.');
                });
            </script>";
        }
    }
}

// Handle profile update form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $newUsername = trim($_POST['username']);
    $newEmail = trim($_POST['email']);

    // Only allow profile picture update via URL if not Google user
    if (($user['auth_provider'] ?? 'local') === 'google') {
        // For Google users, always use their Google profile picture
        $picture = $user['picture'];
    } else {
        $picture = $user['picture'] ?? null;
        if (isset($_POST['picture_url'])) {
            $picture = trim($_POST['picture_url']);
            // If blank, use default avatar
            if ($picture === '') {
                $avatarName = urlencode($newUsername ?: ($user['name'] ?? 'User'));
                $picture = "https://ui-avatars.com/api/?name={$avatarName}&background=ff6f00&color=fff&size=30";
            }
        }
    }

    // Handle password change if fields are filled (optional, basic)
    if (
        isset($_POST['old_password'], $_POST['new_password'], $_POST['confirm_password']) &&
        $_POST['old_password'] !== '' && $_POST['new_password'] !== '' && $_POST['confirm_password'] !== ''
    ) {
        // Fetch current password from DB
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $currentPassword = $stmt->fetchColumn();

        // Check old password (plain text for demo, use password_hash in production)
        if ($_POST['old_password'] === $currentPassword) {
            if ($_POST['new_password'] === $_POST['confirm_password']) {
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$_POST['new_password'], $user['id']]);
            } else {
                echo "<script>document.addEventListener('DOMContentLoaded',function(){showFlashMessage('New passwords do not match.');});</script>";
            }
        } else {
            echo "<script>document.addEventListener('DOMContentLoaded',function(){showFlashMessage('Old password is incorrect.');});</script>";
        }
    }

    // Update the database
    $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, picture = ? WHERE id = ?");
    $stmt->execute([$newUsername, $newEmail, $picture, $user['id']]);

    // Fetch the updated user data from the database
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $updatedUser = $stmt->fetch(PDO::FETCH_ASSOC);

    // Update $_SESSION['user'] so the new info is reflected immediately
    $_SESSION['user'] = $updatedUser;

    // Show a flash message and reload the page to apply changes
    $_SESSION['flash_message'] = 'Profile updated successfully.';
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

// Recipes added today
$recipesToday = $pdo->query("SELECT COUNT(*) FROM recipes WHERE DATE(created_at) = CURDATE()")->fetchColumn();
// Most active user
$mostActiveUser = $pdo->query("
    SELECT u.username, COUNT(r.id) AS total
    FROM users u
    JOIN recipes r ON r.user_id = u.id
    GROUP BY u.id
    ORDER BY total DESC
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);
// Most favorited recipe
$mostFavorited = $pdo->query("
    SELECT r.title, COUNT(*) AS total
    FROM recipes r
    JOIN favorites f ON f.recipe_id = r.id
    GROUP BY r.id
    ORDER BY total DESC
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CookBlock Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

    <?php if ($maintenance === '1'): ?>
    <div class="alert alert-warning text-center mb-0" style="border-radius:0;">
        <i class="bi bi-tools me-2"></i>
        <strong>Maintenance Mode:</strong> The site is currently under maintenance. Some features may be unavailable.
    </div>
    <?php endif; ?>

    <?php if ($announcement): ?>
    <div class="alert alert-info text-center mb-0" style="border-radius:0;">
        <i class="bi bi-megaphone me-2"></i>
        <?= htmlspecialchars($announcement) ?>
    </div>
    <?php endif; ?>

    <!--User Top Navigation-->
<div class="container py-5">

    <!--User Profile -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="text-orange fw-bold">🍲 CookBlock Dashboard</h2>
        <?php if ($user): ?>
            <div class="d-flex align-items-center justify-content-end flex-wrap gap-2 text-end">
                <div class="text-nowrap fw-semibold me-3">
                    Welcome,
                </div>
                <!-- Profile Dropdown -->
                <div class="flex-shrink-0 dropdown">
                    <button class="btn btn-outline-orange btn-sm dropdown-toggle d-flex align-items-center text-dark" type="button" id="profileDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <?php
$avatarName = urlencode($user['username'] ?? $user['name'] ?? 'User');
$avatarUrl = "https://ui-avatars.com/api/?name={$avatarName}&background=ff6f00&color=fff&size=30";
?>
<img src="<?= !empty($user['picture']) ? htmlspecialchars($user['picture']) : $avatarUrl ?>"
     alt="Profile Picture"
     class="rounded-circle me-2"
     style="width: 30px; height: 30px; object-fit: cover;"
     onerror="this.onerror=null;this.src='<?= $avatarUrl ?>';">
                        <span><?= htmlspecialchars($user['username'] ?? $user['name'] ?? 'User') ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileDropdown">
                        <li>
                            <button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#profileModal">
                                <i class="bi bi-person-circle me-2"></i>Profile
                            </button>
                        </li>
                        <?php if ($isAdmin): ?>
                        <li>
                            <button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#manageUsersModal">
                                <i class="bi bi-people-fill me-2"></i>Manage Users
                            </button>
                        </li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a href="logout.php" class="dropdown-item text-danger" onclick="return confirm('Are you sure you want to log out?');">
                                <i class="bi bi-box-arrow-right me-2"></i>Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

            <!-- Profile Modal -->
            <div class="modal fade" id="profileModal" tabindex="-1" aria-labelledby="profileModalLabel" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                  <form id="profileForm" method="POST" enctype="multipart/form-data">
                    <div class="modal-header bg-orange text-white">
                      <h5 class="modal-title" id="profileModalLabel">My Profile</h5>
                      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                      <!-- Profile Picture -->
                      <div class="text-center mb-3">
                        <img id="profilePicPreview"
                             src="<?= !empty($user['picture']) 
                                    ? htmlspecialchars($user['picture']) 
                                    : 'https://ui-avatars.com/api/?name=' . urlencode($user['username'] ?? $user['name'] ?? 'User') . '&background=ff6f00&color=fff&size=80' ?>"
                             alt="Profile Picture"
                             class="rounded-circle"
                             style="width:80px;height:80px;object-fit:cover;"
                             onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=<?= urlencode($user['username'] ?? $user['name'] ?? 'User') ?>&background=ff6f00&color=fff&size=80';">
                      </div>
                      <div class="mb-3">
                        <label class="form-label">Change Profile Picture</label>
                        <input type="url" name="picture_url" class="form-control" id="profilePicUrlInput"
                            placeholder="Paste image URL here"
                            <?php if (($user['auth_provider'] ?? 'local') === 'google') echo 'disabled value="Google profile picture cannot be changed"'; ?>>
                        <?php if (($user['auth_provider'] ?? 'local') === 'google'): ?>
                            <div class="form-text text-danger">Google users cannot change their profile picture here.</div>
                        <?php endif; ?>
                      </div>
                      <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user['username'] ?? $user['name'] ?? '') ?>" required>
                      </div>
                      <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                      </div>
                      <?php if (($user['auth_provider'] ?? 'local') === 'local'): ?>
                      <hr>
                      <div class="mb-3">
                        <label class="form-label">Change Password</label>
                        <input type="password" name="old_password" class="form-control mb-2" placeholder="Current Password">
                        <input type="password" name="new_password" class="form-control mb-2" placeholder="New Password">
                        <input type="password" name="confirm_password" class="form-control" placeholder="Confirm New Password">
                      </div>
                      <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                      <button type="submit" name="update_profile" class="btn btn-orange">Save Changes</button>
                    </div>
                  </form>
                </div>
              </div>
            </div>

            <!-- Admin Manage Users Modal -->
            <?php if ($isAdmin): ?>
            <div class="modal fade" id="manageUsersModal" tabindex="-1" aria-labelledby="manageUsersModalLabel" aria-hidden="true">
              <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                  <div class="modal-header bg-orange text-white">
                    <h5 class="modal-title" id="manageUsersModalLabel">User Management</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                  </div>
                  <div class="modal-body">
                    <?php $users = $pdo->query("SELECT * FROM users")->fetchAll(); ?>
                    <div class="table-responsive">
                      <table class="table table-bordered align-middle">
                        <thead>
                          <tr>
                            <th>Profile</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Provider</th>
                            <th>Role</th>
                            <th>Actions</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach ($users as $u): ?>
                          <tr>
                            <td>
                              <img src="<?= htmlspecialchars($u['picture'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($u['username'] ?? $u['name'] ?? 'User')) ?>"
                                   alt="Profile" class="rounded-circle" style="width:40px;height:40px;object-fit:cover;">
                            </td>
                            <td><?= htmlspecialchars($u['username'] ?? $u['name'] ?? 'User') ?></td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td><?= htmlspecialchars($u['auth_provider'] ?? 'local') ?></td>
                            <td>
                              <span class="badge <?= $u['role'] === 'superadmin' ? 'bg-danger' : ($u['role'] === 'admin' ? 'bg-warning text-dark' : 'bg-secondary') ?>">
                                <?= ucfirst($u['role']) ?>
                              </span>
                            </td>
                            <td>
                                <?php
                                // Disable delete if:
                                // - Not superadmin and target is admin or superadmin
                                // - Superadmin cannot delete themselves
                                $disableDelete = false;
                                if ($u['id'] == $user['id']) {
                                    $disableDelete = true;
                                } elseif (($user['role'] ?? 'user') === 'admin' && ($u['role'] === 'admin' || $u['role'] === 'superadmin')) {
                                    $disableDelete = true;
                                } elseif (($user['role'] ?? 'user') === 'superadmin' && $u['role'] === 'superadmin') {
                                    $disableDelete = true;
                                }
                                ?>
                                <?php if (!$disableDelete): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <button type="submit" name="delete_user" class="btn btn-sm btn-danger" onclick="return confirm('Delete this user?')">Delete</button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted">Not allowed</span>
                                <?php endif; ?>

                                <?php if ($isSuperAdmin && $u['role'] === 'user'): ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirmGrantAdmin();">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <button type="submit" name="make_admin" class="btn btn-sm btn-warning">Make Admin</button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($isSuperAdmin && $u['role'] === 'admin' && $u['id'] != $user['id']): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <button type="submit" name="make_user" class="btn btn-sm btn-secondary">Demote to User</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                          </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                  </div>
                </div>
              </div>
            </div>
            <?php endif; ?>

    </div>

    <!-- Recipes & User Statistics Section -->
<div class="row mb-4 g-4">
    <!-- Recipes per Category Chart -->
    <div class="col-lg-6">
        <div class="card p-4 shadow-sm border-orange h-100">
            <h5 class="text-center text-orange mb-3"><i class="bi bi-bar-chart-line-fill me-3"></i>Recipes per Category</h5>
            <canvas id="categoryChart" height="200"></canvas>
        </div>
    </div>

    <!-- Additional Statistics -->
    <div class="col-lg-6">
        <div class="card p-4 shadow-sm border-orange h-100">
            <h5 class="text-center text-orange mb-3"><i class="bi bi-graph-up-arrow me-2"></i>Site Statistics</h5>
            <?php
            // Total recipes
            $totalRecipes = $pdo->query("SELECT COUNT(*) FROM recipes")->fetchColumn();
            // Total users
            $totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
            // Total verified users
            $totalVerified = $pdo->query("SELECT COUNT(*) FROM users WHERE is_verified = 1")->fetchColumn();
            // Most popular category
            $popularCategory = $pdo->query("
                SELECT c.name, COUNT(r.id) AS total
                FROM categories c
                LEFT JOIN recipes r ON r.category_id = c.id
                GROUP BY c.id
                ORDER BY total DESC
                LIMIT 1
            ")->fetch(PDO::FETCH_ASSOC);
            ?>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-journal-text text-orange me-2"></i>Total Recipes</span>
                    <span class="fw-bold"><?= $totalRecipes ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-people text-orange me-2"></i>Total Users</span>
                    <span class="fw-bold"><?= $totalUsers ?></span>
                </li>
                <?php if ($isAdmin): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-person-check text-success me-2"></i>Verified Users</span>
                    <span class="fw-bold"><?= $totalVerified ?></span>
                </li>
                <?php endif; ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-star-fill text-orange me-2"></i>Most Popular Category</span>
                    <span class="fw-bold"><?= htmlspecialchars($popularCategory['name'] ?? 'N/A') ?> (<?= $popularCategory['total'] ?? 0 ?>)</span>
                </li>
            </ul>
            <?php if ($isAdmin): ?>
                <hr>
                <div class="mt-3">
                    <h6 class="text-orange fw-bold mb-2"><i class="bi bi-tools me-2"></i>Admin Tools</h6>
                    <div class="d-grid gap-2">
                        <a href="user_logs.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-clock-history me-1"></i>View User Logs</a>
                        <a href="manage_categories.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-tags me-1"></i>Manage Categories</a>
                        <a href="site_settings.php" class="btn btn-outline-warning btn-sm"><i class="bi bi-gear me-1"></i>Site Settings</a>
                        <?php if ($isSuperAdmin): ?>
                            <a href="admin_logs.php" class="btn btn-outline-danger btn-sm"><i class="bi bi-shield-shaded me-1"></i>Admin logs</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Search, Filters & Add Recipe Button -->
<div class="row justify-content-between mb-4">
    <form method="GET" class="row g-3 align-items-end">

        <!-- Search Bar with Clear (×) -->
        <div class="col-md-4">
            <div class="input-group shadow-sm">
                <input type="text" id="searchInput" name="search" class="form-control" placeholder="🔍 Search by title" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('searchInput').value='';"><i class="bi bi-x-lg"></i></button>
            </div>
        </div>

        <!-- Filter Dropdown -->
        <div class="col-md-2">
            <div class="dropdown w-100">
                <button class="btn btn-secondary dropdown-toggle w-100" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-funnel"></i> Filters
                </button>
                <div class="dropdown-menu p-3 w-100" style="min-width: 240px;">
                    <div class="mb-3">
                        <label for="category" class="form-label mb-1">Category</label>
                        <select name="category" id="category" class="form-select form-select-sm">
                            <option value="">All</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= ($_GET['category'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="favorited" class="form-label mb-1">Favorite</label>
                        <select name="favorited" id="favorited" class="form-select form-select-sm">
                            <option value="">All</option>
                            <option value="1" <?= ($_GET['favorited'] ?? '') === '1' ? 'selected' : '' ?>>Yes</option>
                            <option value="0" <?= ($_GET['favorited'] ?? '') === '0' ? 'selected' : '' ?>>No</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search & Filter Submit Button -->
        <div class="col-md-1">
        <button class="btn btn-primary w-100 d-flex align-items-center justify-content-center" type="submit">
        <i class="bi bi-search me-1"></i>
        <span class="text-truncate">Search</span>
        </button>

        </div>

        <!-- Spacer Column -->
        <div class="col-md-2 d-none d-md-block"></div>

        <!-- Add Recipe Button -->
        <div class="col-md-3 text-end">
            <?php if ($user): ?>
                <a href="add_recipe.php" class="btn btn-success w-100"><i class="bi bi-plus-circle"></i> Add Recipe</a>
            <?php else: ?>
                <a href="login.php" class="btn btn-outline-secondary w-100">Login to Add Recipe</a>
            <?php endif; ?>
        </div>

    </form>
</div>

<!-- Toast Container -->
<div id="flash-toast" class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 9999;"></div>


<!-- Database Table -->
<div id="recipe-list">
    <form method="POST" action="delete_handler.php">
        <div class="table-responsive mb-4">
            <p class="text-muted mb-2 text-center">Click on row(s) to select. Selected rows will highlight.</p>
            <table class="table table-striped table-bordered table-hover align-middle shadow-sm text-center">
                <thead class="table-orange text-white">
                    <tr>
                        <th>Image</th>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Posted By</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($filteredRecipes) > 0): ?>
                        <?php foreach ($filteredRecipes as $row): ?>
                            <?php
                            // Author information handling
                            $authorName = $row['username'] ?? $row['author'] ?? $row['name'] ?? 'Unknown';
                            ?>
                            <tr class="recipe-row" data-id="<?= $row['id'] ?>">
                                <td class="d-none">
                                    <input type="checkbox" name="selected_recipes[]" value="<?= $row['id'] ?>" class="row-checkbox">
                                </td>
                                <td>
                        <!--recipe picture -->
                                    <?php
                                    $imageSrc = !empty($row['image_url']) 
                                        ? htmlspecialchars($row['image_url']) 
                                        : 'https://scontent-mnl1-2.xx.fbcdn.net/v/t1.15752-9/506770443_1104849738173938_2940013142766161190_n.png?stp=dst-png_s552x414&_nc_cat=101&ccb=1-7&_nc_sid=0024fc&_nc_eui2=AeG-BT8cMWRuR7qIbtL_b07CpjR-tzbyGTmmNH63NvIZOVFbt-IgoiC5FzP7KxFh3ueecl9chWHhnviDqrOOAKBt&_nc_ohc=rqEhM0Ennp4Q7kNvwH_sL3c&_nc_oc=Adl_5d6BfB7tiY13jxZjpOSv7bQWkRhHpe7gNl0yUCXT5mcfNl1I7SuAbMTzdlGO3pI&_nc_ad=z-m&_nc_cid=0&_nc_zt=23&_nc_ht=scontent-mnl1-2.xx&oh=03_Q7cD2gEIhZggwfTCuM0CHYA6ZHo21tGxP-rRqmiC7I_EMmqxZg&oe=68784412';
                                    ?>
                                    <img src="<?= $imageSrc ?>" 
                                        alt="Recipe Image"
                                        style="width: 100px; height: 70px; object-fit: cover;" 
                                        class="rounded"
                                        onerror="this.onerror=null;this.src='https://scontent-mnl1-2.xx.fbcdn.net/v/t1.15752-9/506770443_1104849738173938_2940013142766161190_n.png?stp=dst-png_s552x414&_nc_cat=101&ccb=1-7&_nc_sid=0024fc&_nc_eui2=AeG-BT8cMWRuR7qIbtL_b07CpjR-tzbyGTmmNH63NvIZOVFbt-IgoiC5FzP7KxFh3ueecl9chWHhnviDqrOOAKBt&_nc_ohc=rqEhM0Ennp4Q7kNvwH_sL3c&_nc_oc=Adl_5d6BfB7tiY13jxZjpOSv7bQWkRhHpe7gNl0yUCXT5mcfNl1I7SuAbMTzdlGO3pI&_nc_ad=z-m&_nc_cid=0&_nc_zt=23&_nc_ht=scontent-mnl1-2.xx&oh=03_Q7cD2gEIhZggwfTCuM0CHYA6ZHo21tGxP-rRqmiC7I_EMmqxZg&oe=68784412';">
                                </td>
                                <td><?= htmlspecialchars($row['title']) ?></td>
                                <td><?= htmlspecialchars($row['category'] ?? $row['category_name'] ?? 'Uncategorized') ?></td>
                                <td>
                                    <div class="d-flex align-items-center justify-content-center">
                        <!--profile picture -->
                                    <?php
                                    $pictureSrc = !empty($row['picture']) 
                                        ? htmlspecialchars($row['picture']) 
                                        : 'https://ui-avatars.com/api/?name=<?= urlencode($authorName) ?>&background=ff6f00&color=fff&size=30';
                                    ?>
                                    <img src="<?= $pictureSrc ?>" 
                                        alt="Default picture"
                                        style="width: 30px; height: 30px; object-fit: cover; background-color: #ff6f00; color: white;"
                                        class="rounded-circle me-2"
                                        onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=<?= urlencode($authorName) ?>&background=ff6f00&color=fff&size=30';">
                                        <span><?= htmlspecialchars($authorName) ?></span>
                                    </div>
                                </td>
                                <td><?= date("M d, Y", strtotime($row['created_at'])) ?></td>
                                <td>
                                    <div class="d-flex flex-wrap justify-content-center gap-2">
                                        <!-- View Button -->
                                        <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#viewModal<?= $row['id'] ?>">
                                            <i class="bi bi-eye"></i> View
                                        </button>

                                        <?php if ($user && ($user['id'] == $row['user_id'] || $isAdmin)): ?>
                                            <!-- Edit Button -->
                                            <a href="edit_recipe.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-primary">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a>

                                            <!-- Delete Form -->
                                            <form method="POST" action="delete_handler.php" class="d-inline">
                                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger delete-btn">
                                                    <i class="bi bi-trash"></i> Delete
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <?php
                                            // Check if the recipe is in the user's favorites
                                            $favoriteStatus = false;
                                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE user_id = ? AND recipe_id = ?");
                                            $stmt->execute([$user['id'], $row['id']]);
                                            $favoriteStatus = $stmt->fetchColumn() > 0;
                                            ?>

                                            <!-- Favorite Form - Updated with AJAX support -->
                                        
                                            <input type="hidden" name="recipe_id" value="<?= $row['id'] ?>">
                                            <button type="button"
                                                    class="btn btn-sm <?= $favoriteStatus ? 'btn-warning' : 'btn-outline-warning' ?> favorite-btn text-dark"
                                                    data-recipe-id="<?= $row['id'] ?>"
                                                    data-favorited="<?= $favoriteStatus ? '1' : '0' ?>">
                                                <i class="bi bi-star<?= $favoriteStatus ? '-fill' : '' ?>"></i>
                                                <span class="favorite-text"><?= $favoriteStatus ? 'Saved' : 'Save' ?></span>
                                            </button>

                                        
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                <i class="bi bi-emoji-frown" style="font-size: 1.5rem;"></i>
                                <p class="mt-2">No recipes found. Try adjusting your filters.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($user): ?>
            <div class="d-flex flex-wrap align-items-stretch gap-2 justify-content-center">
                <div>
                    <button type="submit" class="btn btn-danger h-10" id="deleteSelectedBtn" disabled onclick="return confirm('Delete selected recipe(s)?');">
                        🗑️ Delete Selected
                    </button>
                </div>

                <div>
                    <button type="button" class="btn btn-secondary h-10" id="printSelectedBtn" disabled>
                        🖨️ Print Selected
                    </button>
                </div>

                <?php
                // At the top of your file (where you have other PHP code)
                $totalFavorites = 0;
                if (isset($user) && isset($user['id'])) {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE user_id = ?");
                    $stmt->execute([$user['id']]);
                    $totalFavorites = (int)$stmt->fetchColumn();
                }
                ?>

                <form method="POST" action="export_recipe.php">
                    <button
                        type="button"
                        id="downloadFavoritesBtn"
                        name="print_all_favorites"
                        class="btn btn-info"
                        style="color: white;"
                        data-favorites="<?= $totalFavorites ?>"
                        <?= $totalFavorites === 0 ? 'disabled' : '' ?>
                    >
                        📥 Print All Favorite Recipes
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </form>
</div>

<!-- View Modals -->
<?php foreach ($filteredRecipes as $row): ?>
<div class="modal fade" id="viewModal<?= $row['id'] ?>" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content rounded-4 border border-2 border-orange shadow-sm">
            
            <!-- Modal Header -->
            <div class="modal-header bg-orange text-white rounded-top-4">
                <h5 class="modal-title fw-semibold">
                    <?= htmlspecialchars($row['title']) ?> - Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <!-- Modal Body -->
            <div class="modal-body">
                <!-- Recipe Meta -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="mb-2">
                            <strong class="text-orange">Category:</strong>
                            <?= htmlspecialchars($row['category'] ?? $row['category_name'] ?? 'Uncategorized') ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-2">
                            <strong class="text-orange">Author:</strong>

                            <!--profile picture -->
                                    <?php
                                    $pictureSrc = !empty($row['picture']) 
                                        ? htmlspecialchars($row['picture']) 
                                        : 'https://ui-avatars.com/api/?name=<?= urlencode($authorName) ?>&background=ff6f00&color=fff&size=30';
                                    ?>
                                    <img src="<?= $pictureSrc ?>" 
                                        alt="Default picture"
                                        style="width: 30px; height: 30px; object-fit: cover; background-color: #ff6f00; color: white;"
                                        class="rounded-circle me-2"
                                        onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=<?= urlencode($row['username']) ?>&background=ff6f00&color=fff&size=30';">
                            <span><?= htmlspecialchars($row['username']) ?></span>
                        </div>
                    </div>
                    <?php if (isset($row['difficulty'])): ?>
                        <div class="col-md-6">
                            <div class="mb-2">
                                <strong class="text-orange">Difficulty:</strong>
                                <span class="badge 
                                    <?= $row['difficulty'] == 'easy' ? 'bg-success' : '' ?>
                                    <?= $row['difficulty'] == 'medium' ? 'bg-warning text-dark' : '' ?>
                                    <?= $row['difficulty'] == 'hard' ? 'bg-danger' : '' ?>">
                                    <?= ucfirst($row['difficulty'] ?? 'Not specified') ?>
                                </span>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if (isset($row['prep_time']) || isset($row['cook_time'])): ?>
                        <div class="col-md-6">
                            <div class="mb-2">
                                <strong class="text-orange">Time:</strong>
                                <?= ($row['prep_time'] ?? '?') ?> min prep + 
                                <?= ($row['cook_time'] ?? '?') ?> min cook
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if (isset($row['servings'])): ?>
                        <div class="col-md-6">
                            <div class="mb-2">
                                <strong class="text-orange">Servings:</strong>
                                <?= $row['servings'] ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Image Section -->
                <div class="d-flex justify-content-center mb-4">
                    <div class="border border-orange rounded-4 overflow-hidden shadow-sm" style="width: 100%; max-width: 500px; aspect-ratio: 4/3;">
                        <?php
                        $image = !empty($row['image_url']) ? htmlspecialchars($row['image_url']) : 'https://via.placeholder.com/500x375?text=No+Image+Available';
                    ?>
                    <img src="<?= $image ?>"
                        class="w-100 h-100 object-fit-cover"
                        alt="Recipe Image"
                        onerror="this.onerror=null;this.src='https://scontent-mnl1-2.fbcdn.net/v/t1.15752-9/506770443_1104849738173938_2940013142766161190_n.png?stp=dst-png_s552x414&_nc_cat=101&ccb=1-7&_nc_sid=0024fc&_nc_eui2=AeG-BT8cMWRuR7qIbtL_b07CpjR-tzbyGTmmNH63NvIZOVFbt-IgoiC5FzP7KxFh3ueecl9chWHhnviDqrOOAKBt&_nc_ohc=rqEhM0Ennp4Q7kNvwH_sL3c&_nc_oc=Adl_5d6BfB7tiY13jxZjpOSv7bQWkRhHpe7gNl0yUCXT5mcfNl1I7SuAbMTzdlGO3pI&_nc_ad=z-m&_nc_cid=0&_nc_zt=23&_nc_ht=scontent-mnl1-2.xx&oh=03_Q7cD2gEIhZggwfTCuM0CHYA6ZHo21tGxP-rRqmiC7I_EMmqxZg&oe=68784412';">
                    </div>
                </div>

                <!-- Description -->
                <div class="border-top pt-3 mb-3">
                    <h6 class="fw-bold text-orange">Description:</h6>
                    <p><?= nl2br(htmlspecialchars($row['description'])) ?></p>
                </div>

                <!-- Ingredients -->
                <div class="border-top pt-3 mb-3">
                    <h6 class="fw-bold text-orange">Ingredients:</h6>
                    <p><?= nl2br(htmlspecialchars($row['ingredients'])) ?></p>
                </div>

                <!-- Instructions -->
                <div class="border-top pt-3 mb-3">
                    <h6 class="fw-bold text-orange">Instructions:</h6>
                    <p><?= nl2br(htmlspecialchars($row['instructions'])) ?></p>
                </div>

                <!-- Tags -->
                <div class="border-top pt-3">
                    <h6 class="fw-bold text-orange">Tags:</h6>
                    <?php if (!empty($row['tags'])): ?>
                        <?php foreach (explode(',', $row['tags']) as $tag): ?>
                            <span class="badge bg-orange text-white me-1 mb-1">
                                <?= htmlspecialchars(trim($tag)) ?>
                            </span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="text-muted">No tags</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="modal-footer border-top">
                <button class="btn btn-outline-orange" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<!-- Hidden form for POST submission -->
<form id="pdfForm" action="export_recipe.php" method="POST" class="d-none" onclick="return confirm('Print selected recipe(s)?');">
    <input type="hidden" name="selected_ids" id="pdfSelectedIds">
</form>

<?php if (!empty($_SESSION['flash_message'])): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            showFlashMessage('<?= addslashes($_SESSION['flash_message']) ?>');
        });
    </script>
    <?php unset($_SESSION['flash_message']); ?>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
const ctx = document.getElementById('categoryChart').getContext('2d');
const chart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($chartData, 'category')) ?>,
        datasets: [{
            label: 'Number of Recipes',
            data: <?= json_encode(array_column($chartData, 'total')) ?>,
            backgroundColor: '#ff6f00',
            borderColor: '#e65c00',
            borderWidth: 1
        }]
    },
    options: {
        scales: { y: { beginAtZero: true } }
    }
});

document.querySelectorAll('.recipe-row').forEach(row => {
    row.addEventListener('click', (e) => {
        if (e.target.closest('button') || e.target.closest('a')) return;

        const checkbox = row.querySelector('.row-checkbox');
        checkbox.checked = !checkbox.checked;
        row.classList.toggle('table-primary', checkbox.checked);

        const anyChecked = document.querySelectorAll('.row-checkbox:checked').length > 0;
        document.getElementById('deleteSelectedBtn').disabled = !anyChecked;
        document.getElementById('printSelectedBtn').disabled = !anyChecked;
    });
});

document.querySelectorAll('.delete-btn').forEach(btn => {
    btn.addEventListener('click', function (e) {
        e.preventDefault();
        const confirmed = confirm('Are you sure you want to delete this recipe?');
        if (confirmed) {
            btn.closest('form').submit();
        }
    });
});

document.getElementById('printSelectedBtn').addEventListener('click', () => {
    // Maintenance mode check
    const maintenance = '<?= $maintenance ?>';
    if (maintenance === '1') {
        showFlashMessage('Maintenance mode is active. Printing/exporting recipes is temporarily disabled.');
        return;
    }
    const selectedCheckboxes = document.querySelectorAll('.row-checkbox:checked');
    if (selectedCheckboxes.length === 0) {
        showFlashMessage('Please select at least one recipe.');
        return;
    }
    const selectedIds = Array.from(selectedCheckboxes).map(cb => cb.value);
    const form = document.getElementById('pdfForm');
    form.innerHTML = '';
    selectedIds.forEach(id => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'selected_recipes[]';
        input.value = id;
        form.appendChild(input);
    });
    form.submit();
});

document.addEventListener('DOMContentLoaded', function () {
    // Existing flash message fade-out
    const flash = document.getElementById('flash-message');
    if (flash) {
        setTimeout(function () {
            flash.style.transition = 'opacity 0.5s ease';
            flash.style.opacity = '0';
            setTimeout(() => flash.remove(), 500);
        }, 3000);
    }

    // ✅ Enable Download Favorites if data-user-favorites-count > 0
    const downloadBtn = document.getElementById('downloadFavoritesBtn');
    const hasFavorites = parseInt(downloadBtn?.getAttribute('data-favorites') || '0', 10);
    if (hasFavorites > 0) {
        downloadBtn.removeAttribute('disabled');
    }
    // Add maintenance mode check for downloadFavoritesBtn
    if (downloadBtn) {
        downloadBtn.addEventListener('click', function (e) {
            const maintenance = '<?= $maintenance ?>';
            if (maintenance === '1') {
                showFlashMessage('Maintenance mode is active. Printing/exporting recipes is temporarily disabled.');
                return;
            }
            if (hasFavorites > 0) {
                // Submit the form programmatically
                downloadBtn.closest('form').submit();
            } else {
                showFlashMessage('No favorite recipes to print.');
            }
        });
    }
});



// Add this function to update the favorites button state
function updateFavoritesButton(totalFavorites) {
    const downloadBtn = document.getElementById('downloadFavoritesBtn');
    if (!downloadBtn) return;
    
    // Update the data attribute
    downloadBtn.setAttribute('data-favorites', totalFavorites);
    
    // Enable/disable based on count
    downloadBtn.disabled = totalFavorites === 0;
    
    // Optional: Visual feedback
    if (totalFavorites === 0) {
        downloadBtn.classList.add('disabled');
    } else {
        downloadBtn.classList.remove('disabled');
    }
}

// Modify your favorite button click handler
document.querySelectorAll('.favorite-btn').forEach(button => {
    button.addEventListener('click', async function() {
        const recipeId = button.getAttribute('data-recipe-id');
        const isFavorited = button.getAttribute('data-favorited') === '1';

        try {
            const response = await fetch('favorite_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `recipe_id=${recipeId}`
            });

            if (response.ok) {
                const result = await response.json();

                if (result.success) {
                    const icon = button.querySelector('i');
                    const text = button.querySelector('.favorite-text');

                    // Update button appearance
                    if (result.favorited) {
                        button.classList.remove('btn-outline-warning');
                        button.classList.add('btn-warning');
                        icon.classList.remove('bi-star');
                        icon.classList.add('bi-star-fill');
                        text.textContent = 'Saved';
                        button.setAttribute('data-favorited', '1');
                    } else {
                        button.classList.remove('btn-warning');
                        button.classList.add('btn-outline-warning');
                        icon.classList.remove('bi-star-fill');
                        icon.classList.add('bi-star');
                        text.textContent = 'Save';
                        button.setAttribute('data-favorited', '0');
                    }

                    // Update download button if we have the total count
                    if (typeof result.totalFavorites !== 'undefined') {
                        updateFavoritesButton(result.totalFavorites);
                    }

                    // Show success message
                    showFlashMessage(result.message);
                } else {
                    showFlashMessage(result.message);
                }
            } else {
                showFlashMessage('Failed to update favorite. Please try again.');
            }
        } catch (err) {
            console.error('Error:', err);
            showFlashMessage('An error occurred. Please try again later.');
        }
    });
});

// Initialize the download button on page load
document.addEventListener('DOMContentLoaded', function() {
    const downloadBtn = document.getElementById('downloadFavoritesBtn');
    if (downloadBtn) {
        const totalFavorites = parseInt(downloadBtn.getAttribute('data-favorites')) || 0;
        updateFavoritesButton(totalFavorites);
    }
});

// 🔔 Flash message handler
function showFlashMessage(message) {
    const toastContainer = document.getElementById('flash-toast');

    const toast = document.createElement('div');
    toast.className = 'toast align-items-center text-white bg-info border-0 show';
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');

    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    `;

    toastContainer.appendChild(toast);

    // Auto-remove after 3 seconds
    setTimeout(() => {
        toast.classList.remove('show');
        toast.classList.add('hide');
        setTimeout(() => toast.remove(), 500);
    }, 3000);
}

document.getElementById('profilePicInput')?.addEventListener('change', function(e) {
    const [file] = e.target.files;
    if (file) {
        document.getElementById('profilePicPreview').src = URL.createObjectURL(file);
    }
});

</script>
<!-- Add this JS at the end of your file, before </body> -->
<script>
function confirmGrantAdmin() {
    return confirm('Are you sure you want to grant admin permissions to this user?');
}
</script>
</body>
</html>

