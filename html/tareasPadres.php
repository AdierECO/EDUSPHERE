<?php
require_once '../php/Conexion.php';

session_start();
if (!isset($_SESSION['idPadre'])) {
    header("Location: loginPadres.html");
    exit();
}

$idPadre = $_SESSION['idPadre'];

// Obtener datos del padre
$sqlPadre = "SELECT nombre, apellidos, foto_perfil FROM Padres WHERE idPadre = ?";
$stmtPadre = $conexion->prepare($sqlPadre);
$stmtPadre->bind_param("i", $idPadre);
$stmtPadre->execute();
$resultPadre = $stmtPadre->get_result();

if ($resultPadre->num_rows > 0) {
    $padre = $resultPadre->fetch_assoc();
    $nombreCompleto = $padre['nombre'] . ' ' . $padre['apellidos'];
    $fotoPerfil = !empty($padre['foto_perfil']) ? '../IMAGENES/Padres/' . $padre['foto_perfil'] : '../IMAGENES/Admin.jpg';
} else {
    $nombreCompleto = "Usuario";
    $fotoPerfil = '../IMAGENES/Admin.jpg';
}

// Obtener tareas de los hijos
$sqlTareas = "SELECT t.* 
             FROM tareas t
             JOIN estudiantes e ON t.idGrupo = e.idGrupo
             JOIN estudiantes_padres ep ON e.idEstudiante = ep.idEstudiante
             WHERE ep.idPadre = ?
             ORDER BY t.fecha_publicacion DESC";

$stmtTareas = $conexion->prepare($sqlTareas);
if ($stmtTareas === false) {
    die("Error en la consulta: " . $conexion->error);
}

$stmtTareas->bind_param("i", $idPadre);
$stmtTareas->execute();
$resultTareas = $stmtTareas->get_result();
$tareas = $resultTareas->fetch_all(MYSQLI_ASSOC);

$sqlNotificaciones = "SELECT (
    SELECT COUNT(a.id) 
    FROM avisos a
    JOIN estudiantes e ON a.idGrupo = e.idGrupo
    JOIN estudiantes_padres ep ON e.idEstudiante = ep.idEstudiante
    WHERE ep.idPadre = ?
    AND a.id NOT IN (
        SELECT nv.id_notificacion 
        FROM notificaciones_vistas nv
        WHERE nv.idEstudiante = e.idEstudiante AND nv.tipo_notificacion = 'aviso'
    )
) + (
    SELECT COUNT(t.idTarea)
    FROM tareas t
    JOIN estudiantes e ON t.idGrupo = e.idGrupo
    JOIN estudiantes_padres ep ON e.idEstudiante = ep.idEstudiante
    WHERE ep.idPadre = ?
    AND t.idTarea NOT IN (
        SELECT nv.id_notificacion 
        FROM notificaciones_vistas nv
        WHERE nv.idEstudiante = e.idEstudiante AND nv.tipo_notificacion = 'tarea'
    )
) AS total_notificaciones";

$stmtNotificaciones = $conexion->prepare($sqlNotificaciones);
$stmtNotificaciones->bind_param("ii", $idPadre, $idPadre);
$stmtNotificaciones->execute();
$resultNotificaciones = $stmtNotificaciones->get_result();
$totalNotificacionesNoLeidas = $resultNotificaciones->fetch_assoc()['total_notificaciones'];

$stmtPadre->close();
$stmtTareas->close();
$conexion->close();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tareas de tus hijos | Edusphere</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/tareasPadres.css">
    <link rel="stylesheet" href="../css/botonPerfil.css">
    <link rel="stylesheet" href="../css/notificacionesLeidas.css">
    <style>
        .no-tasks-message {
            text-align: center;
            padding: 20px;
            color: #666;
        }

        .no-tasks-message i {
            font-size: 40px;
            margin-bottom: 10px;
            color: #aaa;
        }
    </style>
</head>

<body>
    <header class="header">
        <img src="../IMAGENES/logo.gif" alt="EDUSPHERE" class="logo">
        <h2>PADRES</h2>
        <nav class="nav-bar">
            <a href="PADRES.php">Inicio</a>
            <a href="" class="active">Tareas</a>
        </nav>
        <div class="header-icons">
            <!-- Icono de notificaciones -->
            <div class="notification-icon" id="notificationBell">
                <i class="fas fa-bell"></i>
                <?php if ($totalNotificacionesNoLeidas > 0): ?>
                    <span class="notification-badge"><?php echo $totalNotificacionesNoLeidas; ?></span>
                <?php endif; ?>
                <div class="notification-dropdown" id="notificationDropdown">
                    <div class="notification-header">
                        <h3>Notificaciones Recientes</h3>
                        <span class="mark-all-read">Marcar todo como leído</span>
                    </div>
                    <div class="notification-list" id="notificationList">
                        <div class="loading-notifications">Cargando notificaciones...</div>
                    </div>
                    <div class="notification-footer">
                        <a href="anuncios.php">Ver todos los avisos</a>
                        <a href="tareasPadres.php" style="margin-left: 10px;">Ver todas las tareas</a>
                    </div>
                </div>
            </div>
            <div class="user-info" id="userDropdown">
                <div class="user-display">
                    <img src="<?= htmlspecialchars($fotoPerfil) ?>" alt="Foto de perfil" class="profile-pic">
                    <span><?= htmlspecialchars($nombreCompleto) ?></span>
                    <i class="fas fa-chevron-down dropdown-arrow"></i>
                </div>
                <div class="dropdown-menu" id="dropdownMenu">
                    <a href="perfilPadre.php"><i class="fas fa-user-circle"></i> Perfil</a>
                    <a href="../php/cerrar_sesion.php"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a>
                </div>
            </div>
        </div>
    </header>

    <main class="container">
        <h1>Tareas asignadas a tus hijos</h1>

        <div class="card">
            <div class="tasks-header">
                <h2><i class="fas fa-tasks"></i> Lista de tareas</h2>
                <div class="filter-options">
                    <button class="filter-btn active" data-filter="all">Todas</button>
                    <button class="filter-btn" data-filter="pending">Pendientes</button>
                    <button class="filter-btn" data-filter="completed">Calificadas</button>
                </div>
            </div>

            <?php if (!empty($tareas)): ?>
                <table class="task-table">
                    <thead>
                        <tr>
                            <th>Tarea</th>
                            <th>Fecha</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tareas as $tarea): ?>
                            <tr data-status="<?= !empty($tarea['calificacion']) ? 'completed' : 'pending' ?>">
                                <td>
                                    <div class="task-title"><?= htmlspecialchars($tarea['titulo']) ?></div>
                                    <div class="task-desc"><?= htmlspecialchars($tarea['descripcion']) ?></div>
                                </td>
                                <td class="task-date">
                                    <i class="far fa-calendar-alt"></i>
                                    <?= date('d/m/Y', strtotime($tarea['fecha_publicacion'])) ?>
                                    <br>
                                    <small><?= date('H:i', strtotime($tarea['fecha_publicacion'])) ?></small>
                                </td>
                                <td>
                                    <?php if (!empty($tarea['calificacion'])): ?>
                                        <span class="task-status status-completed">
                                            <i class="fas fa-check-circle"></i> Calificada: <?= $tarea['calificacion'] ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="task-status status-pending">
                                            <i class="far fa-clock"></i> Pendiente
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-tasks">
                    <i class="far fa-smile"></i>
                    <p>No hay tareas asignadas actualmente</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        <p>Edusphere © <?= date('Y') ?> - Todos los derechos reservados</p>
    </footer>

    <script src="../html/JS/botonPerfil.js"></script>
    <script>
        // Filtrado de tareas
        document.addEventListener('DOMContentLoaded', function() {
            const filterButtons = document.querySelectorAll('.filter-btn');
            const taskRows = document.querySelectorAll('tbody tr');
            const noTasksElement = document.querySelector('.no-tasks');

            filterButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Actualizar botón activo
                    filterButtons.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');

                    const filter = this.getAttribute('data-filter');

                    // Filtrar tareas
                    let visibleTasks = 0;
                    taskRows.forEach(row => {
                        const rowStatus = row.getAttribute('data-status');

                        if (filter === 'all' || rowStatus === filter) {
                            row.style.display = '';
                            visibleTasks++;
                        } else {
                            row.style.display = 'none';
                        }
                    });

                    // Mostrar mensaje si no hay tareas visibles
                    if (noTasksElement) {
                        noTasksElement.style.display = visibleTasks === 0 ? 'block' : 'none';
                    }
                });
            });
        });
        document.getElementById('notificationBell').addEventListener('click', function(e) {
            e.stopPropagation();
            const dropdown = document.getElementById('notificationDropdown');
            dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';

            if (dropdown.style.display === 'block') {
                cargarNotificaciones();
            }
        });

        // Función para cargar notificaciones via AJAX
        function cargarNotificaciones() {
            fetch('../php/obtenerAvisos.php?padreId=<?php echo $idPadre; ?>')
                .then(response => response.text())
                .then(data => {
                    document.getElementById('notificationList').innerHTML = data;
                });
        }

        // Cerrar dropdown al hacer clic fuera
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.notification-icon')) {
                document.getElementById('notificationDropdown').style.display = 'none';
            }
            if (!e.target.closest('.user-info')) {
                document.getElementById('dropdownMenu').style.display = 'none';
            }
        });
        document.querySelector('.mark-all-read')?.addEventListener('click', function() {
            fetch('../php/marcarAvisos.php?padreId=<?php echo $idPadre; ?>')
                .then(() => {
                    cargarNotificaciones();
                    const badge = document.querySelector('.notification-badge');
                    if (badge) badge.remove();
                    <?php $_SESSION['notificaciones_no_leidas'] = 0; ?>
                });
        });
    </script>
</body>

</html>