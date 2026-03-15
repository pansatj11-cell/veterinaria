// app.js
// Lógica de interfaz (Frontend) - Panel Admin

// Manejo de la navegación SPA
const navBtns = document.querySelectorAll('.nav-btn');
const sections = document.querySelectorAll('.view-section');

navBtns.forEach(btn => {
    btn.addEventListener('click', () => {
        navBtns.forEach(b => b.classList.remove('active'));
        sections.forEach(s => s.classList.remove('active'));

        btn.classList.add('active');
        const targetId = btn.getAttribute('data-target');
        document.getElementById(targetId).classList.add('active');

        if (targetId === 'clientes') cargarClientes();
        if (targetId === 'veterinarios') cargarVeterinarios();
        if (targetId === 'citas-admin') cargarListaCitas();
    });
});

// Utilidad para notificaciones
function showToast(message, isError = false) {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.style.backgroundColor = isError ? '#e74c3c' : '#4a90e2';
    toast.classList.add('show');
    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}

// === API CLIENTES ===
document.getElementById('form-cliente').addEventListener('submit', async (e) => {
    e.preventDefault();
    const payload = {
        nombre: document.getElementById('cliente-nombre').value,
        telefono: document.getElementById('cliente-telefono').value
    };

    const res = await fetch('api_clientes.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });

    const data = await res.json();
    if (data.success) {
        showToast('Cliente registrado exitosamente');
        document.getElementById('form-cliente').reset();
        cargarClientes();
    } else {
        showToast(data.message || 'Error', true);
    }
});

async function eliminarCliente(id, nombre) {
    if (!confirm(`¿Estás seguro de eliminar al cliente "${nombre}"?`)) return;
    const res = await fetch(`api_clientes.php`, { 
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'delete', id: id })
    });
    const data = await res.json();
    if (data.success) {
        showToast('Cliente eliminado');
        cargarClientes();
    } else {
        showToast(data.message || 'Error al eliminar', true);
    }
}

async function cargarClientes() {
    const res = await fetch('api_clientes.php');
    const json = await res.json();
    const container = document.getElementById('clientes-content');
    container.innerHTML = '';
    if (json.success && json.data.length > 0) {
        json.data.forEach(c => {
            const vinculado = c.telegram_chat_id ? '✅ Vinculado' : '⏳ Pendiente';
            container.innerHTML += `<div class="list-item">
                <div class="item-info">
                    <b>${c.nombre}</b><br>
                    📞 Tel: ${c.telefono || 'N/A'}<br>
                    <span class="telegram-status ${c.telegram_chat_id ? 'linked' : 'pending'}">${vinculado}</span>
                </div>
                <button class="btn-delete" onclick="eliminarCliente(${c.id}, '${c.nombre.replace(/'/g, "\\'")}')">🗑️</button>
            </div>`;
        });
    } else {
        container.innerHTML = 'No hay clientes registrados.';
    }
}

// === API VETERINARIOS ===
document.getElementById('form-veterinario').addEventListener('submit', async (e) => {
    e.preventDefault();
    const payload = { nombre: document.getElementById('vet-nombre').value };

    const res = await fetch('api_veterinarios.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });

    const data = await res.json();
    if (data.success) {
        showToast('Veterinario registrado');
        document.getElementById('form-veterinario').reset();
        cargarVeterinarios();
    } else {
        showToast(data.message || 'Error', true);
    }
});

async function eliminarVeterinario(id, nombre) {
    if (!confirm(`¿Estás seguro de eliminar al veterinario "${nombre}"?`)) return;
    const res = await fetch(`api_veterinarios.php`, { 
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'delete', id: id })
    });
    const data = await res.json();
    if (data.success) {
        showToast('Veterinario eliminado');
        cargarVeterinarios();
    } else {
        showToast(data.message || 'Error al eliminar', true);
    }
}

async function cargarVeterinarios() {
    const res = await fetch('api_veterinarios.php');
    const json = await res.json();
    const container = document.getElementById('veterinarios-content');
    container.innerHTML = '';
    if (json.success && json.data.length > 0) {
        json.data.forEach(v => {
            container.innerHTML += `<div class="list-item">
                <div class="item-info">👨‍⚕️ <b>${v.nombre}</b></div>
                <button class="btn-delete" onclick="eliminarVeterinario(${v.id}, '${v.nombre.replace(/'/g, "\\'")}')">🗑️</button>
            </div>`;
        });
    } else {
        container.innerHTML = 'No hay veterinarios registrados.';
    }
}

// === VER CITAS (con opción de cancelar) ===
async function cancelarCita(id) {
    if (!confirm('¿Estás seguro de cancelar esta cita?')) return;
    const res = await fetch(`api_citas.php`, { 
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'delete', id: id })
    });
    const data = await res.json();
    if (data.success) {
        showToast('Cita cancelada');
        cargarListaCitas();
    } else {
        showToast(data.message || 'Error al cancelar', true);
    }
}

async function cargarListaCitas() {
    const res = await fetch('api_citas.php');
    const json = await res.json();
    const container = document.getElementById('citas-content');
    container.innerHTML = '';
    if (json.success && json.data.length > 0) {
        json.data.forEach(c => {
            container.innerHTML += `
            <div class="list-item cita-item">
                <div class="item-info">
                    📅 <b>${c.fecha}</b> a las <b>${c.hora}</b><br>
                    🐶 Cliente: ${c.cliente_nombre}<br>
                    🩺 Vet: ${c.veterinario_nombre}<br>
                    Estado: ${c.estado}
                </div>
                <button class="btn-delete btn-cancel" onclick="cancelarCita(${c.id})">❌ Cancelar</button>
            </div>`;
        });
    } else {
        container.innerHTML = 'No hay citas registradas aún.';
    }
}

// Inicializar la vista por defecto
cargarClientes();
