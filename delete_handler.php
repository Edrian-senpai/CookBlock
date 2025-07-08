<?php
require_once 'recipe.php';
$recipe = new Recipe($pdo);

$message = '';
$success = false;

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // MULTIPLE DELETE
        if (!empty($_POST['selected_recipes']) && is_array($_POST['selected_recipes'])) {
            $ids = array_map('intval', $_POST['selected_recipes']);
            $recipe->deleteMultiple($ids);
            $success = true;
            $message = count($ids) . ' recipe(s) was deleted successfully.';

        // SINGLE DELETE
        } elseif (isset($_POST['id'])) {
            $id = intval($_POST['id']);
            $recipeData = $recipe->getById($id);
            if ($recipeData) {
                $recipe->deleteMultiple([$id]);
                $success = true;
                $message = 'Recipe "' . htmlspecialchars($recipeData['title']) . '" was deleted successfully.';
            } else {
                $message = "Recipe not found or already deleted.";
            }
        } else {
            $message = "No valid recipe ID(s) provided for deletion.";
        }

    } else {
        $message = "Invalid request method.";
    }

} catch (PDOException $e) {
    $message = "Error deleting recipe(s): " . htmlspecialchars($e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Delete Recipes</title>
    <link rel="stylesheet" href="style.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
    <div class="container text-center" style="max-width: 600px; padding: 60px 30px; margin-top: 200px;">
        <h2 class="<?= $success ? 'text-success' : 'text-danger' ?>">
            <?= $success ? 'Success' : 'Error' ?>
        </h2>
        <p style="font-size: 18px; margin: 20px 0;">
            <?= $message ?>
        </p>
        <a href="view_all.php" class="btn btn-danger px-4 py-2">
            ← Back to Dashboard
        </a>
    </div>
</body>
</html>
