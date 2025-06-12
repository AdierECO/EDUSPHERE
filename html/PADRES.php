<?php
session_start();
require_once '../php/Conexion.php';

// Verificar si el padre está logueado
if (!isset($_SESSION['idPadre'])) {
    header("Location: loginPadres.html");
    exit();
}

// Obtener datos del padre
$idPadre = $_SESSION['idPadre'];
$sql = "SELECT nombre, apellidos, foto_perfil FROM Padres WHERE idPadre = ?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $idPadre);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $padre = $result->fetch_assoc();
    $nombreCompleto = $padre['nombre'] . ' ' . $padre['apellidos'];
    $fotoPerfil = !empty($padre['foto_perfil']) ? '../IMAGENES/Padres/' . $padre['foto_perfil'] : '../IMAGENES/Admin.jpg';
} else {
    $nombreCompleto = "Usuario";
    $fotoPerfil = '../IMAGENES/Admin.jpg';
}

// Obtener cantidad de notificaciones no leídas (avisos + tareas) para los hijos del padre
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
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interfaz para Padres</title>
    <link rel="stylesheet" href="../css/Padre.css">
    <link rel="stylesheet" href="../css/botonPerfil.css">
    <link rel="stylesheet" href="../css/notificacionesLeidas.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <!-- Cabecera -->
    <header class="header">
        <img src="../IMAGENES/logo.gif" alt="EDUSPHERE" class="logo">
        <h2>PADRES</h2>
        <nav class="nav-bar">
            <a href="../html/Admin.html" class="active">Inicio</a>
        </nav>

        <!-- Contenedor de iconos -->
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

            <!-- Perfil de usuario -->
            <div class="user-info" id="userDropdown">
                <div class="user-display">
                    <img src="<?php echo $fotoPerfil; ?>" alt="Foto de perfil" class="profile-pic">
                    <span><?php echo htmlspecialchars($nombreCompleto); ?></span>
                    <i class="fas fa-chevron-down dropdown-arrow"></i>
                </div>
                <div class="dropdown-menu" id="dropdownMenu">
                    <a href="../html/perfilPadre.php">
                        <i class="fas fa-user-circle"></i> Perfil
                    </a>
                    <a href="../php/cerrar_sesion.php">
                        <i class="fas fa-sign-out-alt"></i> Cerrar sesión
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main class="container">
        <!-- Botones de Navegación -->
        <div class="card-grid">
            <div class="card" onclick="window.location.href='calificacionesPadres.php'">
                <img src="../IMAGENES/Calificaciones.png" alt="Calificaciones">
                <h2>Calificaciones</h2>
            </div>
            <div class="card" onclick="window.location.href='tareasPadres.php'">
                <img src="../IMAGENES/Tareas.png" alt="Tareas y Actividades">
                <h2>Tareas</h2>
            </div>

            <div class="card" onclick="window.location.href='asistenciasPadres.php'">
                <img src="../IMAGENES/Estudiante.png" alt="Asistencias">
                <h2>Asistencias</h2>
            </div>
            <div class="card" onclick="window.location.href='anuncios.php'">
                <img src="../IMAGENES/Avisos.png" alt="Anuncios">
                <h2>Anuncios</h2>
            </div>
        </div>
    </main>

    <script src="../html/JS/botonPerfil.js"></script>
    <script>
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