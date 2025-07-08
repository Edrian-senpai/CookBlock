<?php
require 'connect.php'; // Connect to the database

// Decide which PDF to generate: site report, selected recipes, or favorites
$python = escapeshellcmd('c:/xampp/htdocs/.venv/Scripts/python.exe');
$output = null;
$retval = null;

if (isset($_POST['print_all_favorites']) && isset($_SESSION['user']['id'])) {
    // Export all favorites for the logged-in user
    $script = escapeshellarg(__DIR__ . '/generate_recipes_pdf.py');
    $userId = (int)$_SESSION['user']['id'];
    $cmd = "$python $script --favorites $userId";
    exec($cmd, $output, $retval);
    $filename = 'favorites.pdf';
} elseif (isset($_POST['selected_recipes']) && is_array($_POST['selected_recipes']) && count($_POST['selected_recipes']) > 0) {
    // Export only selected recipes
    $script = escapeshellarg(__DIR__ . '/generate_recipes_pdf.py');
    $ids = array_map('intval', $_POST['selected_recipes']);
    $idArgs = implode(' ', $ids);
    $cmd = "$python $script --ids $idArgs";
    exec($cmd, $output, $retval);
    $filename = 'selected_recipes.pdf';
} else {
    // Default: site-wide report
    $script = escapeshellarg(__DIR__ . '/generate_report.py');
    $cmd = "$python $script";
    exec($cmd, $output, $retval);
    $filename = 'recipe_report.pdf';
}

if ($retval === 0 && !empty($output)) {
    $pdf_path = trim($output[0]);
    if (file_exists($pdf_path)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        readfile($pdf_path);
        exit;
    } else {
        die('PDF report was not generated.');
    }
} else {
    die('Failed to generate PDF report.');
}
