<?php
require_once 'connect.php'; // Include database connection

try {
    // Prepare SQL to fetch all recipes with their category name, author username, and tags
    $stmt = $pdo->prepare("
        SELECT 
            r.*, 
            c.name AS category_name, 
            u.username,
            u.picture,
            GROUP_CONCAT(t.name SEPARATOR ', ') AS tags
        FROM recipes r
        LEFT JOIN categories c ON r.category_id = c.id
        LEFT JOIN users u ON r.user_id = u.id
        LEFT JOIN recipe_tags rt ON r.id = rt.recipe_id
        LEFT JOIN tags t ON rt.tag_id = t.id
        GROUP BY r.id
        ORDER BY r.created_at DESC
    ");

    // Retrieve filter parameters from GET request (for search, category filter, and favorites)
    $filters = [
        'search' => $_GET['search'] ?? '',        // Search query
        'category' => $_GET['category'] ?? '',    // Category filter
        'favorited' => $_GET['favorited'] ?? null // Favorited-only filter
    ];

    // Execute the query and fetch all matching recipes
    $stmt->execute();
    $recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare and execute query for chart: get count of recipes per category
    $chartStmt = $pdo->prepare("
        SELECT c.name AS category, COUNT(r.id) AS total
        FROM recipes r
        LEFT JOIN categories c ON r.category_id = c.id
        GROUP BY c.name
    ");
    $chartStmt->execute();
    $chartData = $chartStmt->fetchAll(PDO::FETCH_ASSOC); // For use in category chart

    // Fetch all categories to populate filter options
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Handle any PDO-related errors gracefully
    die("Database error: " . $e->getMessage());
}
?>
