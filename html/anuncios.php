<?php
require_once '../php/Conexion.php';

session_start();
if (!isset($_SESSION['idPadre'])) {
    header("Location: loginPadres.html");
    exit();
}

$idPadre = $_SESSION['idPadre'];

// Obtener el idEstudiante asociado al padre
$sqlEstudiante = "SELECT e.idEstudiante, e.nombre, e.apellidos, e.idGrupo
                  FROM estudiantes e
                  JOIN estudiantes_padres ep ON e.idEstudiante = ep.idEstudiante
                  WHERE ep.idPadre = ?";
$stmtEstudiante = mysqli_prepare($conexion, $sqlEstudiante);
mysqli_stmt_bind_param($stmtEstudiante, "i", $idPadre);
mysqli_stmt_execute($stmtEstudiante);
$resultEstudiante = mysqli_stmt_get_result($stmtEstudiante);
$estudiante = mysqli_fetch_assoc($resultEstudiante);

if (!$estudiante) {
    die("No se encontró estudiante asociado a este padre.");
}

$idEstudiante = $estudiante['idEstudiante'];
$nombreEstudiante = $estudiante['nombre'] . ' ' . $estudiante['apellidos'];
$idGrupo = $estudiante['idGrupo'];

// Obtener avisos para el grupo del estudiante o avisos generales
// VERSIÓN CORREGIDA - usando la columna correcta para la unión
$sqlAvisos = "SELECT a.id, a.mensaje, a.fecha_publicacion, a.prioridad, 
                     u.nombre as nombre_usuario, u.apellidos as apellidos_usuario
              FROM avisos a
              JOIN usuarios u ON a.idUsuario = u.idUsuario  -- Cambiado a la columna correcta
              WHERE a.idGrupo = ?
              ORDER BY 
                CASE a.prioridad 
                    WHEN 'alta' THEN 1
                    WHEN 'media' THEN 2
                    WHEN 'baja' THEN 3
                END,
                a.fecha_publicacion DESC";
$stmtAvisos = mysqli_prepare($conexion, $sqlAvisos);
mysqli_stmt_bind_param($stmtAvisos, "s", $idGrupo);
mysqli_stmt_execute($stmtAvisos);
$resultAvisos = mysqli_stmt_get_result($stmtAvisos);
$avisos = mysqli_fetch_all($resultAvisos, MYSQLI_ASSOC);

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
    // Datos por defecto si no encuentra al padre
    $nombreCompleto = "Usuario";
    $fotoPerfil = '../IMAGENES/Admin.jpg';
}
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

mysqli_stmt_close($stmtEstudiante);
mysqli_stmt_close($stmtAvisos);
mysqli_close($conexion);
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Avisos Escolares | Edusphere</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/anuncios.css">
    <link rel="stylesheet" href="../css/botonPerfil.css">
    <link rel="stylesheet" href="../css/notificacionesLeidas.css">
</head>

<body>
    <header class="header">
        <img src="../IMAGENES/logo.gif" alt="EDUSPHERE" class="logo">
        <h2>PADRES</h2>
        <nav class="nav-bar">
            <a href="PADRES.php">Inicio</a>
            <a href="" class="active">Avisos</a>
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
                    <img src="<?= $fotoPerfil ?>" alt="Foto de perfil" class="profile-pic">
                    <span><?= $nombreCompleto ?></span>
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
        <h1>Avisos Escolares</h1>

        <div class="avisos-container">
            <?php if (!empty($avisos)): ?>
                <?php foreach ($avisos as $aviso):
                    $prioridadClass = '';
                    $prioridadBgClass = '';

                    switch ($aviso['prioridad']) {
                        case 'alta':
                            $prioridadClass = 'priority-high';
                            $prioridadBgClass = 'priority-high-bg';
                            break;
                        case 'media':
                            $prioridadClass = 'priority-medium';
                            $prioridadBgClass = 'priority-medium-bg';
                            break;
                        case 'baja':
                            $prioridadClass = 'priority-low';
                            $prioridadBgClass = 'priority-low-bg';
                            break;
                    }
                ?>
                    <div class="aviso-card <?= $prioridadClass ?>">
                        <div class="aviso-header">
                            <div class="aviso-title">Aviso Importante</div>
                            <div class="aviso-priority <?= $prioridadBgClass ?>">
                                Prioridad <?= $aviso['prioridad'] ?>
                            </div>
                        </div>

                        <div class="aviso-meta">
                            <div class="aviso-meta-item">
                                <i class="far fa-user"></i>
                                <span><?= htmlspecialchars($aviso['nombre_usuario'] . ' ' . $aviso['apellidos_usuario']) ?></span>
                            </div>
                            <div class="aviso-meta-item">
                                <i class="far fa-calendar-alt"></i>
                                <span><?= date('d/m/Y H:i', strtotime($aviso['fecha_publicacion'])) ?></span>
                            </div>
                        </div>

                        <div class="aviso-content">
                            <?= nl2br(htmlspecialchars($aviso['mensaje'])) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-avisos">
                    <i class="far fa-bell-slash"></i>
                    <p>No hay avisos disponibles en este momento</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        <p>Edusphere © <?= date('Y') ?> - Sistema de Avisos Escolares</p>
    </footer>

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
        document.addEventListener('DOMContentLoaded', function() {
            // Efecto de aparición progresiva de los avisos
            const avisos = document.querySelectorAll('.aviso-card');

            avisos.forEach((aviso, index) => {
                setTimeout(() => {
                    aviso.style.opacity = '1';
                    aviso.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>

</html>