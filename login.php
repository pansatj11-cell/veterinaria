<?php
// login.php
require_once __DIR__ . '/config.php';
session_start();

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    if ($password === ADMIN_PASSWORD) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: index.php');
        exit;
    } else {
        $error = 'Contraseña incorrecta';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Admin - Veterinaria</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body { display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; background: var(--bg-color); }
        .login-card { background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); width: 100%; max-width: 350px; text-align: center; }
        input { width: 100%; padding: 12px; margin: 15px 0; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; }
        button { width: 100%; padding: 12px; background: var(--primary-color); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; }
        .error { color: #e74c3c; font-size: 0.9rem; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="login-card">
        <h2>🐾 Admin Panel</h2>
        <p>Ingresa la contraseña para continuar</p>
        <form method="POST">
            <input type="password" name="password" placeholder="Contraseña" required autofocus>
            <button type="submit">Entrar</button>
        </form>
        <?php if ($error): ?>
            <p class="error"><?php echo $error; ?></p>
        <?php endif; ?>
    </div>
</body>
</html>
