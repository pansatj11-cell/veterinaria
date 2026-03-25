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
    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';

    // 1. Intentar como Admin (Hardcoded)
    if ($usuario === 'admin' && $password === ADMIN_PASSWORD) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['user_role'] = 'admin';
        $_SESSION['user_name'] = 'Administrador';
        header('Location: index.php');
        exit;
    }

    // 2. Intentar como Veterinario (Base de Datos)
    require_once __DIR__ . '/db.php';
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM veterinarios WHERE usuario = :usuario LIMIT 1');
    $stmt->bindParam(':usuario', $usuario);
    $stmt->execute();
    $vet = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($vet && password_verify($password, $vet['password'])) {
        $_SESSION['admin_logged_in'] = true; // Mantener compatibilidad básica
        $_SESSION['user_role'] = 'vet';
        $_SESSION['user_id'] = $vet['id'];
        $_SESSION['user_name'] = $vet['nombre'];
        header('Location: index.php');
        exit;
    } else {
        $error = 'Usuario o contraseña incorrectos';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso - Sistema Veterinaria</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body { display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; background: var(--bg-color); }
        .login-card { background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); width: 100%; max-width: 350px; text-align: center; }
        .form-group { text-align: left; margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; font-size: 0.9rem; }
        input { width: 100%; padding: 12px; margin-bottom: 10px; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; }
        button { width: 100%; padding: 12px; background: var(--primary-color); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; margin-top: 10px; }
        .error { color: #e74c3c; font-size: 0.9rem; margin-top: 15px; background: #fdf2f2; padding: 10px; border-radius: 8px; }
    </style>
</head>
<body>
    <div class="login-card">
        <h2>🐾 Sistema Veterinario</h2>
        <p>Ingresa tus credenciales</p>
        <form method="POST">
            <div class="form-group">
                <label>Usuario</label>
                <input type="text" name="usuario" placeholder="Tu usuario" required autofocus>
            </div>
            <div class="form-group">
                <label>Contraseña</label>
                <input type="password" name="password" placeholder="Tu contraseña" required>
            </div>
            <button type="submit">Entrar</button>
        </form>
        <?php if ($error): ?>
            <p class="error"><?php echo $error; ?></p>
        <?php endif; ?>
    </div>
</body>
</html>
