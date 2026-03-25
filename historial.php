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
    <div class="container">
        <header>
            <h1>🩺 Historial Clínico</h1>
            <nav>
                <a href="index.php">📅 Citas</a>
                <a href="logout.php">Cerrar Sesión</a>
            </nav>
        </header>

        <main>
            <section id="pet-list-section">
                <h2>Listado de Mascotas</h2>
                <div id="pets-container" class="grid-container">
                    <!-- Pets will be loaded here -->
                </div>
            </section>

            <section id="pet-history-section" style="display:none;">
                <button onclick="showPetList()" class="btn-secondary">⬅ Volver al listado</button>
                <h2 id="current-pet-name">Historial de Mascota</h2>
                
                <div id="history-container" class="history-list">
                    <!-- History entries will be loaded here -->
                </div>

                <div class="card">
                    <h3>Nuevo Diagnóstico</h3>
                    <form id="add-history-form">
                        <input type="hidden" id="hist-pet-id">
                        <div class="form-group">
                            <label>Veterinario:</label>
                            <select id="hist-vet-id" required></select>
                        </div>
                        <div class="form-group">
                            <label>Diagnóstico:</label>
                            <textarea id="hist-diag" required></textarea>
                        </div>
                        <div class="form-group">
                            <label>Tratamiento:</label>
                            <textarea id="hist-trat" required></textarea>
                        </div>
                        <div class="form-group">
                            <label>Costo ($):</label>
                            <input type="number" id="hist-costo" step="0.01" required>
                        </div>
                        <button type="submit" class="btn-primary">Guardar Historial</button>
                    </form>
                </div>
            </section>
        </main>
    </div>

    <script>
        async function loadPets() {
            const res = await fetch('api_historial.php?action=list_pets');
            const pets = await res.json();
            const container = document.getElementById('pets-container');
            container.innerHTML = '';
            
            pets.forEach(pet => {
                const card = document.createElement('div');
                card.className = 'card pet-card';
                card.innerHTML = `
                    <h3>${pet.nombre}</h3>
                    <p><b>Raza:</b> ${pet.raza}</p>
                    <p><b>Dueño:</b> ${pet.cliente_nombre}</p>
                    <button onclick="viewHistory(${pet.id}, '${pet.nombre}')" class="btn-small">Ver Historial</button>
                `;
                container.appendChild(card);
            });
        }

        async function viewHistory(petId, petName) {
            document.getElementById('pet-list-section').style.display = 'none';
            document.getElementById('pet-history-section').style.display = 'block';
            document.getElementById('current-pet-name').innerText = `Historial de ${petName}`;
            document.getElementById('hist-pet-id').value = petId;
            
            loadHistory(petId);
        }

        function showPetList() {
            document.getElementById('pet-list-section').style.display = 'block';
            document.getElementById('pet-history-section').style.display = 'none';
        }

        async function loadHistory(petId) {
            const res = await fetch(`api_historial.php?action=get_pet_history&pet_id=${petId}`);
            const history = await res.json();
            const container = document.getElementById('history-container');
            container.innerHTML = '';

            if (history.length === 0) {
                container.innerHTML = '<p>No hay registros clínicos para esta mascota.</p>';
                return;
            }

            history.forEach(h => {
                const entry = document.createElement('div');
                entry.className = 'history-entry card';
                entry.innerHTML = `
                    <p class="date">📅 ${h.fecha}</p>
                    <p><b>Médico:</b> ${h.vet_nombre}</p>
                    <p><b>Diagnóstico:</b> ${h.diagnostico}</p>
                    <p><b>Tratamiento:</b> ${h.tratamiento}</p>
                    <p class="price"><b>Costo:</b> $${h.costo}</p>
                `;
                container.appendChild(entry);
            });
        }

        async function loadVeterinarios() {
            const res = await fetch('api_veterinarios.php');
            const json = await res.json();
            const vets = json.data;
            const select = document.getElementById('hist-vet-id');
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
                alert('Registro guardado');
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
