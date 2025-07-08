<?php
session_start();
require 'connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

$userId = $_SESSION['user']['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['recipe_id'])) {
    $recipeId = (int)$_POST['recipe_id'];

    try {
        // Check if recipe is already favorited
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE user_id = ? AND recipe_id = ?");
        $stmt->execute([$userId, $recipeId]);
        $exists = $stmt->fetchColumn();

        if ($exists) {
            // Remove from favorites
            $stmt = $pdo->prepare("DELETE FROM favorites WHERE user_id = ? AND recipe_id = ?");
            $stmt->execute([$userId, $recipeId]);
            $favorited = false;
            $message = 'Recipe removed from favorites.';
        } else {
            // Add to favorites
            $stmt = $pdo->prepare("INSERT INTO favorites (user_id, recipe_id) VALUES (?, ?)");
            $stmt->execute([$userId, $recipeId]);
            $favorited = true;
            $message = 'Recipe added to favorites!';
        }

        // Get updated total count of favorites
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE user_id = ?");
        $stmt->execute([$userId]);
        $totalFavorites = (int)$stmt->fetchColumn();

        echo json_encode([
            'success' => true,
            'favorited' => $favorited,
            'message' => $message,
            'totalFavorites' => $totalFavorites
        ]);

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
}
exit;