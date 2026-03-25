// app.js
// Lógica de interfaz (Frontend) - Panel Admin

// Manejo de la navegación SPA
const navBtns = document.querySelectorAll('.nav-btn');
const sections = document.querySelectorAll('.view-section');

navBtns.forEach(btn => {
    btn.addEventListener('click', () => {
        const targetId = btn.getAttribute('data-target');
        const targetSection = document.getElementById(targetId);
        
        if (targetSection) {
            navBtns.forEach(b => b.classList.remove('active'));
            sections.forEach(s => s.classList.remove('active'));

            btn.classList.add('active');
            targetSection.classList.add('active');

            if (targetId === 'clientes') cargarClientes();
            if (targetId === 'veterinarios') cargarVeterinarios();
            if (targetId === 'citas-admin') cargarListaCitas();
            if (targetId === 'agendar-control') cargarFormControl();
        }
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
const formCliente = document.getElementById('form-cliente');
if (formCliente) {
    formCliente.addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
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
                formCliente.reset();
                cargarClientes();
            } else {
                showToast(data.message || 'Error', true);
            }
        } catch (error) {
            console.error('Error al registrar cliente:', error);
            showToast('Error de conexión con el servidor', true);
        }
    });
}

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
const formVeterinario = document.getElementById('form-veterinario');
if (formVeterinario) {
    formVeterinario.addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
            const payload = { 
                nombre: document.getElementById('vet-nombre').value,
                usuario: document.getElementById('vet-usuario').value,
                password: document.getElementById('vet-password').value
            };

            const res = await fetch('api_veterinarios.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const data = await res.json();
            if (data.success) {
                showToast('Veterinario registrado');
                formVeterinario.reset();
                cargarVeterinarios();
            } else {
                showToast(data.message || 'Error', true);
            }
        } catch (error) {
            console.error('Error al registrar veterinario:', error);
            showToast('Error de conexión con el servidor', true);
        }
    });
}

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
                <div class="item-info">
                    👨‍⚕️ <b>${v.nombre}</b><br>
                    <small>👤 Usuario: ${v.usuario || 'N/A'}</small>
                </div>
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
                    🐶 Mascota: <b>${c.mascota_nombre || 'No especificada'}</b><br>
                    👤 Cliente: ${c.cliente_nombre}<br>
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

// === AGENDAR CITA DE CONTROL ===
async function cargarFormControl() {
    try {
        // Cargar clientes en el select
        const resC = await fetch('api_clientes.php');
        const jsonC = await resC.json();
        const selCliente = document.getElementById('ctrl-cliente');
        if (selCliente) {
            selCliente.innerHTML = '<option value="">-- Seleccionar Cliente --</option>';
            if (jsonC.success) {
                jsonC.data.forEach(c => {
                    selCliente.innerHTML += `<option value="${c.id}">${c.nombre}</option>`;
                });
            }
        }

        // Cargar veterinarios en el select
        const resV = await fetch('api_veterinarios.php');
        const jsonV = await resV.json();
        const selVet = document.getElementById('ctrl-veterinario');
        if (selVet) {
            selVet.innerHTML = '<option value="">-- Seleccionar Veterinario --</option>';
            if (jsonV.success) {
                jsonV.data.forEach(v => {
                    selVet.innerHTML += `<option value="${v.id}">${v.nombre}</option>`;
                });
            }
        }

        // Configurar fecha mínima (mañana)
        const fechaInput = document.getElementById('ctrl-fecha');
        if (fechaInput) {
            const manana = new Date();
            manana.setDate(manana.getDate() + 1);
            fechaInput.min = manana.toISOString().split('T')[0];
        }
    } catch (error) {
        console.error('Error cargando form control:', error);
    }
}

// Cuando se selecciona un cliente, cargar sus mascotas
const ctrlCliente = document.getElementById('ctrl-cliente');
if (ctrlCliente) {
    ctrlCliente.addEventListener('change', async () => {
        const clienteId = ctrlCliente.value;
        const selMascota = document.getElementById('ctrl-mascota');
        
        if (!clienteId) {
            selMascota.innerHTML = '<option value="">-- Primero selecciona un cliente --</option>';
            selMascota.disabled = true;
            return;
        }

        try {
            const res = await fetch(`api_mascotas.php?cliente_id=${clienteId}`);
            const json = await res.json();
            selMascota.innerHTML = '<option value="">-- Seleccionar Mascota --</option>';
            if (json.success && json.data.length > 0) {
                json.data.forEach(m => {
                    selMascota.innerHTML += `<option value="${m.id}">🐾 ${m.nombre} (${m.especie || 'Sin especie'})</option>`;
                });
                selMascota.disabled = false;
            } else {
                selMascota.innerHTML = '<option value="">Este cliente no tiene mascotas registradas</option>';
                selMascota.disabled = true;
            }
        } catch (error) {
            console.error('Error cargando mascotas:', error);
            showToast('Error al cargar mascotas', true);
        }
    });
}

// Enviar formulario de cita de control
const formControl = document.getElementById('form-control');
if (formControl) {
    formControl.addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
            const payload = {
                cliente_id: document.getElementById('ctrl-cliente').value,
                mascota_id: document.getElementById('ctrl-mascota').value,
                veterinario_id: document.getElementById('ctrl-veterinario').value,
                fecha: document.getElementById('ctrl-fecha').value,
                hora: document.getElementById('ctrl-hora').value,
                tipo: 'control'
            };

            const res = await fetch('api_citas.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const data = await res.json();
            if (data.success) {
                showToast('✅ Cita de control agendada. Notificación enviada al cliente.');
                formControl.reset();
                document.getElementById('ctrl-mascota').disabled = true;
                document.getElementById('ctrl-mascota').innerHTML = '<option value="">-- Primero selecciona un cliente --</option>';
            } else {
                showToast(data.message || 'Error al agendar', true);
            }
        } catch (error) {
            console.error('Error al agendar control:', error);
            showToast('Error de conexión con el servidor', true);
        }
    });
}

// Inicializar la vista por defecto según lo que esté activo (PHP lo decide)
const activeSection = document.querySelector('.view-section.active');
if (activeSection) {
    const targetId = activeSection.id;
    if (targetId === 'clientes') cargarClientes();
    if (targetId === 'veterinarios') cargarVeterinarios();
    if (targetId === 'citas-admin') cargarListaCitas();
    if (targetId === 'agendar-control') cargarFormControl();
}
