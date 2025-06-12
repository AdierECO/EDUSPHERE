<?php
session_start();

if (!isset($_SESSION['idEstudiante'])) {
    header("Location: loginEstudiante.html");
    exit();
}

if (!isset($_GET['materia'])) {
    header("Location: Usuario.php");
    exit();
}

$materia = htmlspecialchars($_GET['materia']);

include '../php/Conexion.php';

$idEstudiante = $_SESSION['idEstudiante'];

// Obtener información del estudiante
$sqlEstudiante = "SELECT nombre, apellidos, foto_perfil, idGrupo FROM estudiantes WHERE idEstudiante = ?";
$stmtEstudiante = $conexion->prepare($sqlEstudiante);
$stmtEstudiante->bind_param("i", $idEstudiante);
$stmtEstudiante->execute();
$resultEstudiante = $stmtEstudiante->get_result();

if ($resultEstudiante->num_rows == 0) {
    header("Location: loginEstudiante.html");
    exit();
}

$estudiante = $resultEstudiante->fetch_assoc();
$idGrupo = $estudiante['idGrupo'];

// Consulta para contar notificaciones no leídas
$sqlNotificacionesNoLeidas = "SELECT (
                                SELECT COUNT(*) FROM (
                                    SELECT t.idTarea FROM tareas t
                                    WHERE t.idGrupo = ?
                                    AND NOT EXISTS (
                                        SELECT 1 FROM notificaciones_vistas nv
                                        WHERE nv.idEstudiante = ?
                                        AND nv.tipo_notificacion = 'tarea'
                                        AND nv.id_notificacion = t.idTarea
                                    )
                                    
                                    UNION ALL
                                    
                                    SELECT a.id FROM avisos a
                                    WHERE a.idGrupo = ?
                                    AND NOT EXISTS (
                                        SELECT 1 FROM notificaciones_vistas nv
                                        WHERE nv.idEstudiante = ?
                                        AND nv.tipo_notificacion = 'aviso'
                                        AND nv.id_notificacion = a.id
                                    )
                                ) AS notificaciones_no_leidas
                              ) AS total";

$stmtNotificaciones = $conexion->prepare($sqlNotificacionesNoLeidas);
$stmtNotificaciones->bind_param("sisi", $idGrupo, $idEstudiante, $idGrupo, $idEstudiante);
$stmtNotificaciones->execute();
$resultNotificaciones = $stmtNotificaciones->get_result();
$numNoLeidas = $resultNotificaciones->fetch_assoc()['total'] ?? 0;

$stmtEstudiante->close();
$stmtNotificaciones->close();
$conexion->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Material de <?= htmlspecialchars($materia) ?></title>
    <link rel="stylesheet" href="../css/material.css">
    <link rel="stylesheet" href="../css/botonPerfil.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .notification-badge {
            background-color: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 3px 8px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 5px;
            animation: pulse 1.5s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
    </style>
    <script defer src="../js/botonPerfil.js"></script>
</head>
<body>
    <header class="header">
        <img src="../IMAGENES/logo.gif" alt="EDUSPHERE" class="logo">
        <h2>BIBLIOTECA</h2>
        <nav class="nav-bar">
            <a href="Usuario.php" class="nav-home"><i class="fas fa-home"></i> INICIO</a>
            <a href="Notificaciones.php"><i class="fas fa-bell"></i> NOTIFICACIONES
                <?php if ($numNoLeidas > 0): ?>
                    <span class="notification-badge"><?= $numNoLeidas ?></span>
                <?php endif; ?>
            </a>
        </nav>
        <div class="user-info" id="userDropdown">
            <div class="user-display">
                <span><?= htmlspecialchars($estudiante['nombre'] . ' ' . $estudiante['apellidos']) ?></span>
                <img src="../IMAGENES/estudiantes/<?= htmlspecialchars($estudiante['foto_perfil']) ?>" alt="Foto perfil" class="profile-pic">
                <i class="fas fa-chevron-down dropdown-arrow"></i>
            </div>
            <div class="dropdown-menu" id="dropdownMenu">
                <a href="Perfil.php"><i class="fas fa-user-circle"></i> Perfil</a>
                <form action="../php/cerrar_sesion.php" method="post">
                    <button type="submit" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</button>
                </form>
            </div>
        </div>
    </header>

    <main class="container">
        <div class="header-container">
            <h1>Resultados de búsqueda para: <?= htmlspecialchars($materia) ?></h1>
            <a href="Usuario.php" class="back-button"><i class="fas fa-arrow-left"></i> Regresar</a>
        </div>
        <div class="search-bar-container">
            <input type="text" id="searchInput" placeholder="Buscar...">
        </div>
        <section id="resultados" class="resultados">
            <p>Buscando material...</p>
        </section>
    </main>

    <script>
        const materia = "<?= htmlspecialchars($materia) ?>";
        const searchInput = document.getElementById('searchInput');
        const resultados = document.getElementById('resultados');

        function fetchBooks(query) {
            fetch(`https://openlibrary.org/search.json?q=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    resultados.innerHTML = '';

                    if (data.docs?.length > 0) {
                        data.docs.slice(0, 100000).forEach(libro => {
                            resultados.innerHTML += `
                                <a href="${libro.key ? `https://openlibrary.org${libro.key}` : '#'}" target="_blank" class="resultado">
                                    <img src="${libro.cover_i ? `https://covers.openlibrary.org/b/id/${libro.cover_i}-M.jpg` : 'https://via.placeholder.com/100x150?text=Sin+portada'}"
                                         alt="Portada del libro" class="portada">
                                    <div class="info">
                                        <h4>${libro.title || 'Título no disponible'}</h4>
                                        <p><strong>Autor:</strong> ${libro.author_name?.join(', ') || 'Autor no disponible'}</p>
                                        <p><strong>Año:</strong> ${libro.first_publish_year || 'Año no disponible'}</p>
                                    </div>
                                </a>
                            `;
                        });
                    } else {
                        resultados.innerHTML = '<p>No se encontraron resultados.</p>';
                    }
                })
                .catch(() => {
                    resultados.innerHTML = '<p>Error al buscar material.</p>';
                });
        }

        // Buscar libros al cargar la página
        fetchBooks(materia);

        // Buscar libros en tiempo real al escribir en el campo de búsqueda
        searchInput.addEventListener('input', () => {
            const query = searchInput.value.trim();
            if (query) {
                fetchBooks(`${materia} ${query}`);
            } else {
                fetchBooks(materia);
            }
        });

        // Menú desplegable del usuario
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('.user-display').addEventListener('click', function() {
                document.querySelector('.dropdown-menu').classList.toggle('show');
            });

            // Cerrar menú al hacer clic fuera
            window.addEventListener('click', function(event) {
                if (!event.target.matches('.user-display') && !event.target.closest('.user-display')) {
                    const dropdowns = document.querySelectorAll('.dropdown-menu');
                    dropdowns.forEach(dropdown => {
                        if (dropdown.classList.contains('show')) {
                            dropdown.classList.remove('show');
                        }
                    });
                }
            });

            // Temporizador de inactividad
            let inactivityTime = 180000; // 3 minutos
            let timeout;

            function resetTimer() {
                clearTimeout(timeout);
                timeout = setTimeout(logout, inactivityTime);
            }

            function logout() {
                window.location.href = '../php/cerrar_sesion.php';
            }

            // Eventos que reinician el temporizador
            window.onload = resetTimer;
            window.onmousemove = resetTimer;
            window.onmousedown = resetTimer;
            window.onclick = resetTimer;
            window.onscroll = resetTimer;
            window.onkeypress = resetTimer;
        });
    </script>
</body>
</html>