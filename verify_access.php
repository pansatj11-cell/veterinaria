<?php
// verify_access.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

echo "=== Verificando Lógica de Autenticación ===\n";

function test_login($user, $pass) {
    global $db;
    echo "Probando login para: '$user' / '$pass'...\n";
    
    // Simulación de login.php
    if ($user === ADMIN_USER && $pass === ADMIN_PASSWORD) {
        return "EXITO: Logueado como ADMIN\n";
    }
    
    $stmt = $db->prepare('SELECT * FROM veterinarios WHERE usuario = :usuario LIMIT 1');
    $stmt->bindParam(':usuario', $user);
    $stmt->execute();
    $vet = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($vet && password_verify($pass, $vet['password'])) {
        return "EXITO: Logueado como VETERINARIO (" . $vet['nombre'] . ")\n";
    }
    
    return "FALLO: Credenciales incorrectas\n";
}

try {
    $db = getDB();
    
    // 1. Probar Admin
    echo test_login(ADMIN_USER, ADMIN_PASSWORD);
    echo test_login(ADMIN_USER, 'password_incorrecta');
    
    // 2. Crear un vet de prueba para verificar
    $test_user = 'vet_test_' . time();
    $test_pass = 'secreto123';
    $hash = password_hash($test_pass, PASSWORD_DEFAULT);
    
    echo "Registrando veterinario de prueba: $test_user...\n";
    $stmt = $db->prepare("INSERT INTO veterinarios (nombre, usuario, password) VALUES ('Dr. Test', :u, :p)");
    $stmt->execute(['u' => $test_user, 'p' => $hash]);
    
    // 3. Probar login del vet
    echo test_login($test_user, $test_pass);
    echo test_login($test_user, 'wrong_pass');
    
    // 4. Limpiar
    $db->exec("DELETE FROM veterinarios WHERE usuario = '$test_user'");
    echo "Prueba completada.\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
