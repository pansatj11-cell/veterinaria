<?php
// config.php
// Configuración general del sistema

// Token del Bot de Telegram (Obtenido de @BotFather)
define('TELEGRAM_BOT_TOKEN', '8541502378:AAHzlqZlpHEnntJoCB8AqZFDlLiudTZNYiE');

// Usuario y Contraseña del Panel Administrativo
define('ADMIN_USER', 'admin');
define('ADMIN_PASSWORD', 'TomaSjimeneZ123459');

// Ruta a la base de datos
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' && file_exists('C:/xampp/htdocs/veterinaria/bdatos.sqlite')) {
    define('DB_PATH', 'C:/xampp/htdocs/veterinaria/bdatos.sqlite');
} else {
    // En hosting o si no está en la ruta de XAMPP, usar ruta relativa
    define('DB_PATH', __DIR__ . '/bdatos.sqlite');
}

// Zona horaria para la validación de citas
date_default_timezone_set('America/Bogota'); // Ajusta a la zona deseada
?>
