<?php

require 'connect.php';
require_once dirname(__FILE__) . '/vendor/autoload.php'; // Dompdf (robust path)
session_start();

use Dompdf\Dompdf;
use Dompdf\Options;

if (!isset($_SESSION['user'])) {
    die("User not logged in.");
}
$user = $_SESSION['user'];

function fetch_favorites($pdo, $userId) {
    $stmt = $pdo->prepare("
        SELECT r.*, c.name AS category_name, u.username,
               GROUP_CONCAT(t.name SEPARATOR ', ') AS tags
        FROM favorites f
        JOIN recipes r ON f.recipe_id = r.id
        LEFT JOIN categories c ON r.category_id = c.id
        LEFT JOIN users u ON r.user_id = u.id
        LEFT JOIN recipe_tags rt ON r.id = rt.recipe_id
        LEFT JOIN tags t ON rt.tag_id = t.id
        WHERE f.user_id = ?
        GROUP BY r.id
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fetch_selected_recipes($pdo, $ids) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT r.*, c.name AS category_name, u.username,
                   GROUP_CONCAT(t.name SEPARATOR ', ') AS tags
            FROM recipes r
            LEFT JOIN categories c ON r.category_id = c.id
            LEFT JOIN users u ON r.user_id = u.id
            LEFT JOIN recipe_tags rt ON r.id = rt.recipe_id
            LEFT JOIN tags t ON rt.tag_id = t.id
            WHERE r.id IN ($placeholders)
            GROUP BY r.id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($ids);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fetch_site_stats($pdo) {
    $stats = [];
    $stats['total_users'] = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $stats['total_recipes'] = $pdo->query('SELECT COUNT(*) FROM recipes')->fetchColumn();
    $stats['total_categories'] = $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();
    $stats['total_favorites'] = $pdo->query('SELECT COUNT(*) FROM favorites')->fetchColumn();
    $stats['recipes_per_category'] = $pdo->query('SELECT c.name, COUNT(r.id) as count FROM categories c LEFT JOIN recipes r ON c.id = r.category_id GROUP BY c.id')->fetchAll(PDO::FETCH_ASSOC);
    $stats['top_users'] = $pdo->query('SELECT u.username, COUNT(r.id) as count FROM users u LEFT JOIN recipes r ON u.id = r.user_id GROUP BY u.id ORDER BY count DESC LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);
    $stats['top_recipes'] = $pdo->query('SELECT r.title, COUNT(f.id) as count FROM recipes r LEFT JOIN favorites f ON r.id = f.recipe_id GROUP BY r.id ORDER BY count DESC LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);
    return $stats;
}

function render_recipes_html($recipes, $title = 'Recipe Collection') {
    $html = "<h2 style='text-align:center;'>" . htmlspecialchars($title) . "</h2><hr>";
    foreach ($recipes as $i => $r) {
        $tags = htmlspecialchars($r['tags'] ?? 'None');
        $created = htmlspecialchars(date('F j, Y, g:i A', strtotime($r['created_at'])));
        $html .= "<div style='margin-bottom:30px;'>";
        $html .= "<h3>" . htmlspecialchars($r['title']) . "</h3>";
        $html .= "<b>Category:</b> " . htmlspecialchars($r['category_name'] ?? 'Uncategorized') . "<br>";
        $html .= "<b>Tags:</b> $tags<br>";
        $html .= "<b>Created At:</b> $created<br>";
        $html .= "<b>Description:</b><br><div style='margin-left:10px;'>" . nl2br(htmlspecialchars($r['description'])) . "</div><br>";
        $html .= "<b>Ingredients:</b><br><div style='margin-left:10px;'>" . nl2br(htmlspecialchars($r['ingredients'])) . "</div><br>";
        $html .= "<b>Instructions:</b><br><div style='margin-left:10px;'>" . nl2br(htmlspecialchars($r['instructions'])) . "</div>";
        $html .= "</div><hr>";
    }
    return $html;
}

function render_stats_html($stats) {
    $html = "<h2 style='text-align:center;'>Recipe Management System Report</h2><hr>";
    $html .= "<h3>Summary</h3>";
    $html .= "<table border='1' cellpadding='5' cellspacing='0' width='60%'>";
    $html .= "<tr style='background:#eee;'><th>Metric</th><th>Value</th></tr>";
    $html .= "<tr><td>Total Users</td><td>" . $stats['total_users'] . "</td></tr>";
    $html .= "<tr><td>Total Recipes</td><td>" . $stats['total_recipes'] . "</td></tr>";
    $html .= "<tr><td>Total Categories</td><td>" . $stats['total_categories'] . "</td></tr>";
    $html .= "<tr><td>Total Favorites</td><td>" . $stats['total_favorites'] . "</td></tr>";
    $html .= "</table><br>";

    // Recipes per Category
    $html .= "<h4>Recipes per Category</h4>";
    $html .= "<table border='1' cellpadding='5' cellspacing='0' width='60%'>";
    $html .= "<tr style='background:#eee;'><th>Category</th><th>Recipes</th></tr>";
    foreach ($stats['recipes_per_category'] as $row) {
        $html .= "<tr><td>" . htmlspecialchars($row['name']) . "</td><td>" . $row['count'] . "</td></tr>";
    }
    $html .= "</table><br>";

    // Top Users
    $html .= "<h4>Top Users by Recipes</h4>";
    $html .= "<table border='1' cellpadding='5' cellspacing='0' width='60%'>";
    $html .= "<tr style='background:#eee;'><th>Username</th><th>Recipes</th></tr>";
    foreach ($stats['top_users'] as $row) {
        $html .= "<tr><td>" . htmlspecialchars($row['username']) . "</td><td>" . $row['count'] . "</td></tr>";
    }
    $html .= "</table><br>";

    // Top Recipes
    $html .= "<h4>Most Favorited Recipes</h4>";
    $html .= "<table border='1' cellpadding='5' cellspacing='0' width='60%'>";
    $html .= "<tr style='background:#eee;'><th>Recipe Title</th><th>Favorites</th></tr>";
    foreach ($stats['top_recipes'] as $row) {
        $html .= "<tr><td>" . htmlspecialchars($row['title']) . "</td><td>" . $row['count'] . "</td></tr>";
    }
    $html .= "</table><br>";

    // Optionally, you could embed charts as images if you generate them with PHP GD or Chart.js and save as PNG, then embed as <img src="...">, but for now, tables only.
    return $html;
}

$dompdf = new Dompdf();
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$dompdf->setOptions($options);

if (isset($_POST['print_all_favorites']) && isset($_SESSION['user']['id'])) {
    $recipes = fetch_favorites($pdo, $_SESSION['user']['id']);
    if (!$recipes) die('No favorite recipes found.');
    $html = render_recipes_html($recipes, 'Favorite Recipes');
    $filename = 'favorites.pdf';
} elseif (isset($_POST['selected_recipes']) && is_array($_POST['selected_recipes']) && count($_POST['selected_recipes']) > 0) {
    $ids = array_map('intval', $_POST['selected_recipes']);
    $recipes = fetch_selected_recipes($pdo, $ids);
    if (!$recipes) die('No recipes found.');
    $html = render_recipes_html($recipes, 'Selected Recipes');
    $filename = 'selected_recipes.pdf';
} else {
    // Site-wide report
    $stats = fetch_site_stats($pdo);
    $html = render_stats_html($stats);
    $filename = 'recipe_report.pdf';
}

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Output the generated PDF to browser
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo $dompdf->output();
exit;
