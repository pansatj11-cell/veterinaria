<?php
// auth_check.php
require_once __DIR__ . '/config.php';
session_start();

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
