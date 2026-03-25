<?php require_once 'auth_check.php'; ?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clínica Veterinaria - Panel Admin</title>
    <link rel="stylesheet" href="styles.css">
</head>

<body>

    <header>
        <h1>🐾 Sistema Veterinario</h1>
        <div class="user-info">
            Bienvenido, <b><?php echo $_SESSION['user_name'] ?? 'Usuario'; ?></b> 
            (<?php echo ($_SESSION['user_role'] === 'admin' ? 'Admin' : 'Veterinario'); ?>)
            <a href="logout.php" class="logout-link">Cerrar Sesión</a>
        </div>
        <nav>
            <?php if ($_SESSION['user_role'] === 'admin'): ?>
                <button class="nav-btn active" data-target="clientes">Clientes</button>
                <button class="nav-btn" data-target="veterinarios">Veterinarios</button>
            <?php endif; ?>
            <button class="nav-btn <?php echo $_SESSION['user_role'] === 'vet' ? 'active' : ''; ?>" data-target="citas-admin">Ver Citas</button>
            <button class="nav-btn" data-target="agendar-control">📋 Agendar Control</button>
            <a href="historial.php" class="nav-link-btn">📋 Historial Clínico</a>
        </nav>
    </header>

    <main>
        <!-- SECCIÓN CLIENTES -->
        <?php if ($_SESSION['user_role'] === 'admin'): ?>
        <section id="clientes" class="view-section active">
            <h2>Registrar Cliente</h2>
            <p class="section-hint">📱 El cliente recibirá sus citas por Telegram. Solo necesitas registrar su número de teléfono.</p>
            <form id="form-cliente">
                <div class="form-group">
                    <label>Nombre del Cliente</label>
                    <input type="text" id="cliente-nombre" required placeholder="Ej: Juan Pérez">
                </div>
                <div class="form-group">
                    <label>Teléfono (con código de país)</label>
                    <input type="tel" id="cliente-telefono" required placeholder="Ej: +573001234567">
                </div>
                <button type="submit" class="btn-submit">Registrar Cliente</button>
            </form>

            <div class="list-container">
                <h3>Directorio de Clientes</h3>
                <div id="clientes-content">Cargando...</div>
            </div>
        </section>

        <!-- SECCIÓN VETERINARIOS -->
        <section id="veterinarios" class="view-section">
            <h2>Añadir Veterinario</h2>
            <form id="form-veterinario">
                <div class="form-group">
                    <label>Nombre Completo</label>
                    <input type="text" id="vet-nombre" required placeholder="Ej: Dra. María López">
                </div>
                <div class="form-group">
                    <label>Usuario de Acceso</label>
                    <input type="text" id="vet-usuario" required placeholder="Ej: mlopez">
                </div>
                <div class="form-group">
                    <label>Contraseña</label>
                    <input type="password" id="vet-password" required placeholder="Contraseña para el veterinario">
                </div>
                <button type="submit" class="btn-submit">Registrar Veterinario</button>
            </form>

            <div class="list-container">
                <h3>Staff Médico</h3>
                <div id="veterinarios-content">Cargando...</div>
            </div>
        </section>
        <?php endif; ?>

        <!-- SECCIÓN VER CITAS -->
        <section id="citas-admin" class="view-section <?php echo $_SESSION['user_role'] === 'vet' ? 'active' : ''; ?>">
            <h2>📅 Citas Agendadas</h2>
            <p class="section-hint">Citas agendadas por clientes (Telegram) y citas de control (Panel Web).</p>
            <div class="list-container">
                <div id="citas-content">Cargando...</div>
            </div>
        </section>

        <!-- SECCIÓN AGENDAR CITA DE CONTROL -->
        <section id="agendar-control" class="view-section">
            <h2>🩺 Agendar Cita de Control</h2>
            <p class="section-hint">Agenda una cita de seguimiento para una mascota ya registrada. Se enviará notificación al cliente por Telegram.</p>
            <form id="form-control">
                <div class="form-group">
                    <label>Cliente</label>
                    <select id="ctrl-cliente" required>
                        <option value="">-- Seleccionar Cliente --</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Mascota</label>
                    <select id="ctrl-mascota" required disabled>
                        <option value="">-- Primero selecciona un cliente --</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Veterinario</label>
                    <select id="ctrl-veterinario" required>
                        <option value="">-- Seleccionar Veterinario --</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Fecha</label>
                    <input type="date" id="ctrl-fecha" required>
                </div>
                <div class="form-group">
                    <label>Hora</label>
                    <select id="ctrl-hora" required>
                        <option value="">-- Seleccionar Hora --</option>
                        <option value="08:00">08:00</option>
                        <option value="08:30">08:30</option>
                        <option value="09:00">09:00</option>
                        <option value="09:30">09:30</option>
                        <option value="10:00">10:00</option>
                        <option value="10:30">10:30</option>
                        <option value="11:00">11:00</option>
                        <option value="14:00">14:00</option>
                        <option value="14:30">14:30</option>
                        <option value="15:00">15:00</option>
                        <option value="15:30">15:30</option>
                        <option value="16:00">16:00</option>
                        <option value="16:30">16:30</option>
                        <option value="17:00">17:00</option>
                    </select>
                </div>
                <button type="submit" class="btn-submit">📩 Agendar y Notificar al Cliente</button>
            </form>
        </section>
    </main>

    <div id="toast">Notificación</div>

    <script src="app.js"></script>
</body>

</html>