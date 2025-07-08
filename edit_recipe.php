<?php
session_start();
require_once 'connect.php';

$user = $_SESSION['user'] ?? null;

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: view_all.php");
    exit();
}

$recipe_id = intval($_GET['id']);
$error = '';

// Fetch recipe
$stmt = $pdo->prepare("SELECT * FROM recipes WHERE id = :id");
$stmt->execute([':id' => $recipe_id]);
$recipeData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$recipeData) {
    header("Location: view_all.php");
    exit();
}

// Fetch categories and tags
$categories = $pdo->query("SELECT * FROM categories")->fetchAll(PDO::FETCH_ASSOC);
$tags = $pdo->query("SELECT * FROM tags")->fetchAll(PDO::FETCH_ASSOC);

// Get current tag IDs
$tag_stmt = $pdo->prepare("SELECT tag_id FROM recipe_tags WHERE recipe_id = :recipe_id");
$tag_stmt->execute([':recipe_id' => $recipe_id]);
$selectedTagIds = $tag_stmt->fetchAll(PDO::FETCH_COLUMN);

// Handle update only if logged in
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $ingredients = trim($_POST['ingredients']);
    $instructions = trim($_POST['instructions']);
    $prep_time = intval($_POST['prep_time']);
    $cook_time = intval($_POST['cook_time']);
    $servings = intval($_POST['servings']);
    $difficulty = trim($_POST['difficulty']);
    $image_url = trim($_POST['image_url']);
    $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : null;
    $tag_ids = isset($_POST['tags']) ? array_map('intval', $_POST['tags']) : [];

    // Validate required fields
    if (empty($title) || empty($ingredients) || empty($instructions)) {
        $error = "Title, ingredients, and instructions are required.";
    } elseif ($prep_time < 0 || $cook_time < 0) {
        $error = "Time values must be positive numbers.";
    } elseif ($servings <= 0) {
        $error = "Servings must be at least 1.";
    } elseif (!in_array($difficulty, ['easy', 'medium', 'hard'])) {
        $error = "Invalid difficulty level selected.";
    } else {
        try {
            // Check if another recipe with the same title exists (excluding current recipe)
            $check_stmt = $pdo->prepare("
                SELECT COUNT(*) FROM recipes 
                WHERE LOWER(title) = LOWER(:title) 
                AND id != :id
            ");
            $check_stmt->execute([
                ':title' => $title,
                ':id' => $recipe_id
            ]);
            
            $duplicateCount = $check_stmt->fetchColumn();
            
            if ($duplicateCount > 0) {
                $error = "A different recipe with this title already exists. Please choose a unique title.";
            } else {
                // Begin transaction
                $pdo->beginTransaction();

                // Update recipe details with new fields
                $update_stmt = $pdo->prepare("
                    UPDATE recipes SET 
                        title = :title, 
                        description = :description, 
                        ingredients = :ingredients, 
                        instructions = :instructions,
                        prep_time = :prep_time,
                        cook_time = :cook_time,
                        servings = :servings,
                        difficulty = :difficulty,
                        image_url = :image_url, 
                        category_id = :category_id
                    WHERE id = :id
                ");

                $updated = $update_stmt->execute([
                    ':title' => $title,
                    ':description' => $description,
                    ':ingredients' => $ingredients,
                    ':instructions' => $instructions,
                    ':prep_time' => $prep_time,
                    ':cook_time' => $cook_time,
                    ':servings' => $servings,
                    ':difficulty' => $difficulty,
                    ':image_url' => $image_url,
                    ':category_id' => $category_id,
                    ':id' => $recipe_id
                ]);

                if ($updated) {
                    // Update tags
                    $pdo->prepare("DELETE FROM recipe_tags WHERE recipe_id = :id")->execute([':id' => $recipe_id]);

                    if (!empty($tag_ids)) {
                        $tag_insert_stmt = $pdo->prepare("INSERT INTO recipe_tags (recipe_id, tag_id) VALUES (:rid, :tid)");
                        foreach ($tag_ids as $tag_id) {
                            $tag_insert_stmt->execute([':rid' => $recipe_id, ':tid' => $tag_id]);
                        }
                    }

                    // Commit transaction
                    $pdo->commit();

                    $_SESSION['flash'] = "Recipe updated successfully!";
                    header("Location: view_all.php?id=$recipe_id");
                    exit();
                } else {
                    $error = "Failed to update recipe. Please try again.";
                    $pdo->rollBack();
                }
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Recipe</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        #edit-recipe-page .bg-orange { background-color: #fd7e14 !important; }
        #edit-recipe-page .text-orange { color:rgb(0, 0, 0) !important; }
        #edit-recipe-page .border-orange { border-color: #fd7e14 !important; }
        #edit-recipe-page .btn-orange {
            background-color: #fd7e14;
            color: white;
        }
        #edit-recipe-page .btn-orange:hover {
            background-color: #e76c00;
            color: white;
        }
        #edit-recipe-page .card-header {
            border-bottom: none;
        }
    </style>
</head>
<body class="bg-light">
<div id="edit-recipe-page" class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-orange shadow-sm rounded-4">
                <div class="card-header bg-orange text-white rounded-top-4">
                    <h3 class="mb-0">✏️ Edit Recipe</h3>
                </div>

                <div class="card-body p-4">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form method="POST" action="edit_recipe.php?id=<?= $recipeData['id'] ?>" onsubmit="return confirm('Update this recipe?');" novalidate>
                        <input type="hidden" name="id" value="<?= htmlspecialchars($recipeData['id']) ?>">

                        <div class="form-floating mb-3">
                            <input type="text" name="title" id="title" class="form-control" placeholder="Title" value="<?= htmlspecialchars($_POST['title'] ?? $recipeData['title']) ?>" required>
                            <label for="title">Recipe Title</label>
                        </div>

                        <div class="form-floating mb-3">
                            <textarea name="description" id="description" class="form-control" placeholder="Description" style="height: 100px;" required><?= htmlspecialchars($_POST['description'] ?? $recipeData['description']) ?></textarea>
                            <label for="description">Description</label>
                        </div>

                        <div class="form-floating mb-3">
                            <textarea name="ingredients" id="ingredients" class="form-control" placeholder="Ingredients" style="height: 100px;" required><?= htmlspecialchars($_POST['ingredients'] ?? $recipeData['ingredients']) ?></textarea>
                            <label for="ingredients">Ingredients</label>
                        </div>

                        <div class="form-floating mb-3">
                            <textarea name="instructions" id="instructions" class="form-control" placeholder="Instructions" style="height: 120px;" required><?= htmlspecialchars($_POST['instructions'] ?? $recipeData['instructions']) ?></textarea>
                            <label for="instructions">Instructions</label>
                        </div>

                        <!-- New fields for time and servings -->
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <div class="form-floating">
                                    <input type="number" name="prep_time" id="prep_time" class="form-control" placeholder="Prep Time" min="0" value="<?= htmlspecialchars($_POST['prep_time'] ?? $recipeData['prep_time']) ?>" required>
                                    <label for="prep_time">Prep Time (min)</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-floating">
                                    <input type="number" name="cook_time" id="cook_time" class="form-control" placeholder="Cook Time" min="0" value="<?= htmlspecialchars($_POST['cook_time'] ?? $recipeData['cook_time']) ?>" required>
                                    <label for="cook_time">Cook Time (min)</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-floating">
                                    <input type="number" name="servings" id="servings" class="form-control" placeholder="Servings" min="1" value="<?= htmlspecialchars($_POST['servings'] ?? $recipeData['servings']) ?>" required>
                                    <label for="servings">Servings</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-floating">
                                    <select name="difficulty" id="difficulty" class="form-select" required>
                                        <option value="easy" <?= (($_POST['difficulty'] ?? $recipeData['difficulty']) === 'easy') ? 'selected' : '' ?>>Easy</option>
                                        <option value="medium" <?= (($_POST['difficulty'] ?? $recipeData['difficulty']) === 'medium') ? 'selected' : '' ?>>Medium</option>
                                        <option value="hard" <?= (($_POST['difficulty'] ?? $recipeData['difficulty']) === 'hard') ? 'selected' : '' ?>>Hard</option>
                                    </select>
                                    <label for="difficulty">Difficulty</label>
                                </div>
                            </div>
                        </div>

                        <div class="form-floating mb-3">
                            <input type="url" name="image_url" id="image_url" class="form-control" placeholder="Image URL" value="<?= htmlspecialchars($_POST['image_url'] ?? $recipeData['image_url']) ?>">
                            <label for="image_url">Image URL (optional)</label>
                        </div>

                        <div class="mb-3">
                            <label for="category_id" class="form-label fw-semibold text-orange">Category</label>
                            <select name="category_id" id="category_id" class="form-select border-orange" required>
                                <option value="">-- Select Category --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= (($recipeData['category_id'] == $cat['id']) || ($_POST['category_id'] ?? '') == $cat['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="tags" class="form-label fw-semibold text-orange">Tags (Ctrl/Cmd for multiple)</label>
                            <select name="tags[]" id="tags" class="form-select border-orange" multiple>
                                <?php foreach ($tags as $tag): ?>
                                    <option value="<?= $tag['id'] ?>" <?= in_array($tag['id'], $_POST['tags'] ?? $selectedTagIds) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($tag['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                         <?php if ($user): ?>
                            <button type="submit" class="btn btn-orange w-100 mt-3">Update Recipe</button>
                        <?php else: ?>
                            <a href="login.php" class="btn btn-outline-secondary w-100 mt-3" disabled>
                                🔒 Login to Edit
                            </a>
                            <p class="text-center text-muted mt-2">You must be logged in to update this recipe.</p>
                        <?php endif; ?>
                        
                    </form>
                </div>
            </div>

            <div class="text-center mt-3">
                <a href="view_all.php" class="text-decoration-none text-orange fw-semibold">&larr; Back to Dashboard</a>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>