<?php
// Start session to access session variables like user login
session_start();

// Include database connection and recipe class
require_once 'connect.php';
require_once 'recipe.php';

// Get currently logged-in user or null if not logged in
$user = $_SESSION['user'] ?? null;

// Instantiate the Recipe class to access its methods
$recipe = new Recipe($pdo);

// Fetch all categories and tags from the database
$categories = $recipe->getAllCategories();
$tags = $pdo->query("SELECT * FROM tags")->fetchAll(PDO::FETCH_ASSOC);

// Initialize an empty array to hold any form validation errors
$errors = [];

// Handle form submission only if it's a POST request and user is logged in
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    // Sanitize and collect form inputs
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $ingredients = trim($_POST['ingredients']);
    $instructions = trim($_POST['instructions']);
    $prep_time = intval($_POST['prep_time']);
    $cook_time = intval($_POST['cook_time']);
    $servings = intval($_POST['servings']);
    $difficulty = trim($_POST['difficulty']);
    $category_id = intval($_POST['category_id']);
    $image_url = trim($_POST['image_url']);
    $tag_ids = isset($_POST['tags']) ? array_map('intval', $_POST['tags']) : [];

    // Validate required fields
    if (empty($title)) $errors[] = "Title is required.";
    if (empty($description)) $errors[] = "Description is required.";
    if (empty($ingredients)) $errors[] = "Ingredients are required.";
    if (empty($instructions)) $errors[] = "Instructions are required.";
    if ($prep_time < 0) $errors[] = "Prep time must be a positive number.";
    if ($cook_time < 0) $errors[] = "Cook time must be a positive number.";
    if ($servings <= 0) $errors[] = "Servings must be a positive number.";
    if (!in_array($difficulty, ['easy', 'medium', 'hard'])) $errors[] = "Invalid difficulty level.";
    if ($category_id <= 0) $errors[] = "Valid category is required.";

    // Proceed if there are no validation errors
    if (empty($errors)) {
        try {
            // Check if a recipe with the same title already exists (case-insensitive)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM recipes WHERE LOWER(title) = LOWER(?)");
            $stmt->execute([$title]);
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                $errors[] = "A recipe with this title already exists. Please choose a different title.";
            } else {
                // Begin transaction for atomic insert
                $pdo->beginTransaction();

                // Insert recipe details into the database
                $insert = $pdo->prepare("
                    INSERT INTO recipes (
                        title, description, ingredients, instructions, 
                        prep_time, cook_time, servings, difficulty, 
                        image_url, user_id, category_id
                    ) VALUES (
                        :title, :description, :ingredients, :instructions, 
                        :prep_time, :cook_time, :servings, :difficulty, 
                        :image_url, :user_id, :category_id
                    )
                ");
                $insert->execute([
                    ':title' => $title,
                    ':description' => $description,
                    ':ingredients' => $ingredients,
                    ':instructions' => $instructions,
                    ':prep_time' => $prep_time,
                    ':cook_time' => $cook_time,
                    ':servings' => $servings,
                    ':difficulty' => $difficulty,
                    ':image_url' => $image_url,
                    ':user_id' => $user['id'],
                    ':category_id' => $category_id
                ]);

                // Get the last inserted recipe ID
                $recipe_id = $pdo->lastInsertId();

                // Insert selected tags into recipe_tags table
                if (!empty($tag_ids)) {
                    $tag_stmt = $pdo->prepare("INSERT INTO recipe_tags (recipe_id, tag_id) VALUES (:rid, :tid)");
                    foreach ($tag_ids as $tag_id) {
                        $tag_stmt->execute([':rid' => $recipe_id, ':tid' => $tag_id]);
                    }
                }

                // Commit all changes to the database
                $pdo->commit();

                // Log to user_logs (after commit)
                try {
                    $pdo->exec("CREATE TABLE IF NOT EXISTS user_logs (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, action VARCHAR(255), details TEXT, timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
                    $logAction = 'Add recipe';
                    $logDetails = "Added recipe '{$title}' (ID: {$recipe_id})";
                    $logStmt = $pdo->prepare("INSERT INTO user_logs (user_id, action, details) VALUES (?, ?, ?)");
                    $logStmt->execute([$user['id'], $logAction, $logDetails]);
                } catch (PDOException $logEx) {
                    // Optionally handle logging error (do not block recipe creation)
                }

                // Set success message and redirect
                $_SESSION['flash'] = "Recipe '$title' added successfully!";
                header("Location: view_all.php");
                exit;
            }
        } catch (PDOException $e) {
            // Rollback changes in case of error
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!-- HTML for the Add Recipe Page -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Recipe</title>
    <!-- Bootstrap CSS and custom style -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- Card container for the form -->
            <div class="card border-orange shadow-sm rounded-4">
                <div class="card-header bg-orange text-white rounded-top-4">
                    <h3 class="mb-0">🍳 Add New Recipe</h3>
                </div>

                <div class="card-body p-4">
                    <!-- Display validation errors if any -->
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $err): ?>
                                    <li><?= htmlspecialchars($err) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <!-- Recipe submission form -->
                    <form method="POST" action="add_recipe.php" novalidate>
                        <!-- Title input -->
                        <div class="form-floating mb-3">
                            <input type="text" name="title" id="title" class="form-control" placeholder="Title" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" required>
                            <label for="title">Recipe Title</label>
                        </div>

                        <!-- Description input -->
                        <div class="form-floating mb-3">
                            <textarea name="description" id="description" class="form-control" placeholder="Description" style="height: 100px;" required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                            <label for="description">Description</label>
                        </div>

                        <!-- Ingredients input -->
                        <div class="form-floating mb-3">
                            <textarea name="ingredients" id="ingredients" class="form-control" placeholder="Ingredients" style="height: 100px;" required><?= htmlspecialchars($_POST['ingredients'] ?? '') ?></textarea>
                            <label for="ingredients">Ingredients</label>
                        </div>

                        <!-- Instructions input -->
                        <div class="form-floating mb-3">
                            <textarea name="instructions" id="instructions" class="form-control" placeholder="Instructions" style="height: 120px;" required><?= htmlspecialchars($_POST['instructions'] ?? '') ?></textarea>
                            <label for="instructions">Instructions</label>
                        </div>

                        <!-- Time and servings inputs -->
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <div class="form-floating">
                                    <input type="number" name="prep_time" id="prep_time" class="form-control" placeholder="Prep Time" min="0" value="<?= htmlspecialchars($_POST['prep_time'] ?? '') ?>" required>
                                    <label for="prep_time">Prep Time (min)</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-floating">
                                    <input type="number" name="cook_time" id="cook_time" class="form-control" placeholder="Cook Time" min="0" value="<?= htmlspecialchars($_POST['cook_time'] ?? '') ?>" required>
                                    <label for="cook_time">Cook Time (min)</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-floating">
                                    <input type="number" name="servings" id="servings" class="form-control" placeholder="Servings" min="1" value="<?= htmlspecialchars($_POST['servings'] ?? '') ?>" required>
                                    <label for="servings">Servings</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-floating">
                                    <select name="difficulty" id="difficulty" class="form-select" required>
                                        <option value="easy" <?= (isset($_POST['difficulty']) && $_POST['difficulty'] === 'easy') ? 'selected' : '' ?>>Easy</option>
                                        <option value="medium" <?= (isset($_POST['difficulty']) && $_POST['difficulty'] === 'medium') ? 'selected' : '' ?>>Medium</option>
                                        <option value="hard" <?= (isset($_POST['difficulty']) && $_POST['difficulty'] === 'hard') ? 'selected' : '' ?>>Hard</option>
                                    </select>
                                    <label for="difficulty">Difficulty</label>
                                </div>
                            </div>
                        </div>

                        <!-- Optional image URL input -->
                        <div class="form-floating mb-3">
                            <input type="url" name="image_url" id="image_url" class="form-control" placeholder="Image URL" value="<?= htmlspecialchars($_POST['image_url'] ?? '') ?>">
                            <label for="image_url">Image URL (optional)</label>
                        </div>
                        
                        <!-- Category dropdown -->
                        <div class="mb-3">
                            <label for="category_id" class="form-label fw-semibold text-orange">Category</label>
                            <select name="category_id" id="category_id" class="form-select border-orange" required>
                                <option value="">-- Select Category --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= (isset($_POST['category_id']) && $_POST['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Tags multiple select -->
                        <div class="mb-3">
                            <label for="tags" class="form-label fw-semibold text-orange">Tags (Ctrl/Cmd for multiple)</label>
                            <select name="tags[]" id="tags" class="form-select border-orange" multiple>
                                <?php foreach ($tags as $tag): ?>
                                    <option value="<?= $tag['id'] ?>" <?= in_array($tag['id'], $_POST['tags'] ?? []) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($tag['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Submit button for logged-in users / login prompt for guests -->
                        <?php if ($user): ?>
                            <button type="submit" class="btn btn-orange w-100 mt-3" onclick="return confirm('Are you sure you want to add this recipe?');">Save Recipe</button>
                        <?php else: ?>
                            <a href="login.php" class="btn btn-outline-secondary w-100 mt-3" onclick="return confirm('You must log in to add a recipe. Continue to login?');">🔒 Login to Add</a>
                            <p class="text-center text-muted mt-2">You must be logged in to add new recipe.</p>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Link back to dashboard -->
            <div class="text-center mt-3">
                <a href="view_all.php" class="text-decoration-none text-orange fw-semibold">&larr; Back to Dashboard</a>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS for UI components -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>