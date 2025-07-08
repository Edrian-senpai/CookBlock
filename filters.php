<?php
require_once 'connect.php'; // Include the database connection
session_start(); // Start session to access user data

$user = $_SESSION['user'] ?? null; // Get the logged-in user from session, if any
$hasFavorites = false; // Flag to check if the user has any favorite recipes

// Check if the user has any favorites stored in the database
if ($user) {
    $stmt = $pdo->prepare("SELECT 1 FROM favorites WHERE user_id = ? LIMIT 1");
    $stmt->execute([$user['id']]);
    $hasFavorites = $stmt->fetchColumn() !== false; // Set to true if at least one favorite exists
}

// Retrieve filters from the URL query parameters
$category = $_GET['category'] ?? '';     // Filter by category ID
$favorited = $_GET['favorited'] ?? '';   // Filter by favorite status (1 or 0)
$search = trim($_GET['search'] ?? '');   // Filter by search keyword in title

// Helper function: Check if a specific recipe is favorited by the user
function isRecipeFavorited(PDO $pdo, $userId, $recipeId): bool {
    static $cache = []; // Use static variable to cache results and reduce DB queries

    $key = "$userId:$recipeId";
    if (isset($cache[$key])) {
        return $cache[$key]; // Return cached value if available
    }

    // Query to check if a favorite record exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE user_id = ? AND recipe_id = ?");
    $stmt->execute([$userId, $recipeId]);
    $cache[$key] = $stmt->fetchColumn() > 0; // True if count > 0

    return $cache[$key];
}

// Helper function: Determine if a given recipe matches the current filters
function recipeMatchesFilters(array $recipe, string $search, string $category, string $favorited, ?array $user, PDO $pdo): bool {
    $matchesSearch = !$search || stripos($recipe['title'], $search) !== false; // Match title if search is provided
    $matchesCategory = !$category || $recipe['category_id'] == $category; // Match category ID
    $matchesFavorite = true; // Default to true

    // If filtering by favorites and the user is logged in
    if ($favorited !== '' && $user) {
        $isFavorited = isRecipeFavorited($pdo, $user['id'], $recipe['id']);
        $matchesFavorite = $favorited === '1' ? $isFavorited : !$isFavorited; // Match based on selected favorite status
    }

    // Return true only if all filter conditions are met
    return $matchesSearch && $matchesCategory && $matchesFavorite;
}

// Apply filtering to all recipes and return only the ones that match filters
$filteredRecipes = array_filter($recipes, function ($recipe) use ($search, $category, $favorited, $user, $pdo) {
    return recipeMatchesFilters($recipe, $search, $category, $favorited, $user, $pdo);
});
