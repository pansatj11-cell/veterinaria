<?php
// auth_check.php
require_once __DIR__ . '/config.php';
session_start();

// Si intentamos acceder al webhook del bot, NO pedimos sesión
$current_page = basename($_SERVER['PHP_SELF']);
if ($current_page === 'bot_webhook.php') {
    return;
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    if (strpos($_SERVER['REQUEST_URI'], 'api_') !== false || strpos($_SERVER['PHP_SELF'], 'api_') !== false) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
        exit;
    }
    header('Location: login.php');
    exit;
}
?>
