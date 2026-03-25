<?php
// historial.php
require_once __DIR__ . '/auth_check.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial Clínico - Veterinaria</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container full-height">
        <header>
            <h1>🩺 Centro de Gestión Clínica</h1>
            <nav>
                <a href="index.php">📅 Agenda</a>
                <a href="logout.php">Cerrar Sesión</a>
            </nav>
        </header>

        <div class="split-view">
            <!-- Columna Izquierda: Listado -->
            <aside class="sidebar">
                <h2>🐾 Mascotas</h2>
                <div id="pets-container" class="pet-nav-list">
                    <!-- Pets will be loaded here -->
                </div>
            </aside>

            <!-- Columna Derecha: Detalle -->
            <main class="content-area">
                <div id="welcome-message" class="empty-state">
                    <p>Selecciona una mascota de la lista para ver su historial clínico.</p>
                </div>

                <section id="pet-history-section" style="display:none;">
                    <div class="history-header">
                        <h2 id="current-pet-name">Historial</h2>
                        <span id="pet-info-badge" class="badge"></span>
                    </div>
                    
                    <div class="history-layout">
                        <div id="history-container" class="history-scroll">
                            <!-- History entries -->
                        </div>

                        <div class="add-entry-card card">
                            <h3>➕ Nuevo Registro</h3>
                            <form id="add-history-form">
                                <input type="hidden" id="hist-pet-id">
                                <div class="form-group">
                                    <label>Veterinario:</label>
                                    <select id="hist-vet-id" required></select>
                                </div>
                                <div class="form-group">
                                    <label>Diagnóstico:</label>
                                    <textarea id="hist-diag" placeholder="Describe los síntomas y hallazgos..." required></textarea>
                                </div>
                                <div class="form-group">
                                    <label>Tratamiento / Receta:</label>
                                    <textarea id="hist-trat" placeholder="Medicamentos, dosis y cuidados..." required></textarea>
                                </div>
                                <div class="form-group">
                                    <label>Costo de la Consulta ($):</label>
                                    <input type="number" id="hist-costo" step="0.01" required>
                                </div>
                                <button type="submit" class="btn-primary">Guardar y Notificar</button>
                            </form>
                        </div>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <script>
        let currentPetId = null;

        async function loadPets() {
            try {
                const res = await fetch('api_historial.php?action=list_pets');
                const pets = await res.json();
                const container = document.getElementById('pets-container');
                container.innerHTML = '';
                
                if (pets.length === 0) {
                    container.innerHTML = '<p class="text-muted">No hay mascotas registradas.</p>';
                    return;
                }

                pets.forEach(pet => {
                    const item = document.createElement('div');
                    item.className = 'pet-nav-item';
                    item.id = `pet-item-${pet.id}`;
                    item.onclick = () => viewHistory(pet);
                    item.innerHTML = `
                        <div class="pet-icon">🐾</div>
                        <div class="pet-details">
                            <span class="pet-name">${pet.nombre}</span>
                            <span class="pet-owner">${pet.cliente_nombre}</span>
                        </div>
                    `;
                    container.appendChild(item);
                });
            } catch (e) {
                console.error("Error al cargar mascotas:", e);
            }
        }

        async function viewHistory(pet) {
            currentPetId = pet.id;
            // UI Updates
            document.querySelectorAll('.pet-nav-item').forEach(i => i.classList.remove('active'));
            document.getElementById(`pet-item-${pet.id}`).classList.add('active');
            
            document.getElementById('welcome-message').style.display = 'none';
            document.getElementById('pet-history-section').style.display = 'block';
            
            document.getElementById('current-pet-name').innerText = pet.nombre;
            document.getElementById('pet-info-badge').innerText = `${pet.especie || 'Animal'} · ${pet.raza}`;
            document.getElementById('hist-pet-id').value = pet.id;
            
            loadHistory(pet.id);
        }

        async function loadHistory(petId) {
            const res = await fetch(`api_historial.php?action=get_pet_history&pet_id=${petId}`);
            const history = await res.json();
            const container = document.getElementById('history-container');
            container.innerHTML = '';

            if (history.length === 0) {
                container.innerHTML = '<p class="empty-msg">No hay registros previos para esta mascota.</p>';
                return;
            }

            history.forEach(h => {
                const entry = document.createElement('div');
                entry.className = 'history-card';
                entry.innerHTML = `
                    <div class="entry-meta">
                        <span class="entry-date">📅 ${h.fecha}</span>
                        <span class="entry-vet">🩺 Dr. ${h.vet_nombre}</span>
                    </div>
                    <div class="entry-body">
                        <p><b>Diagnóstico:</b> ${h.diagnostico}</p>
                        <hr>
                        <p><b>Tratamiento:</b> ${h.tratamiento}</p>
                    </div>
                    <div class="entry-footer">
                        <span class="cost">Valor: $${h.costo}</span>
                    </div>
                `;
                container.appendChild(entry);
            });
        }

        async function loadVeterinarios() {
            const res = await fetch('api_veterinarios.php');
            const json = await res.json();
            const vets = json.data;
            const select = document.getElementById('hist-vet-id');
            select.innerHTML = '<option value="">Selecciona Médico...</option>';
            vets.forEach(v => {
                const opt = document.createElement('option');
                opt.value = v.id;
                opt.textContent = v.nombre;
                select.appendChild(opt);
            });
        }

        document.getElementById('add-history-form').onsubmit = async (e) => {
            e.preventDefault();
            const data = {
                mascota_id: document.getElementById('hist-pet-id').value,
                veterinario_id: document.getElementById('hist-vet-id').value,
                diagnostico: document.getElementById('hist-diag').value,
                tratamiento: document.getElementById('hist-trat').value,
                costo: document.getElementById('hist-costo').value
            };

            const res = await fetch('api_historial.php?action=add_entry', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(data)
            });

            if (await res.json()) {
                alert('🩺 Historial guardado y notificación enviada al cliente.');
                loadHistory(data.mascota_id);
                e.target.reset();
            }
        };

        window.onload = () => {
            loadPets();
            loadVeterinarios();
        };
    </script>
</body>
</html>
