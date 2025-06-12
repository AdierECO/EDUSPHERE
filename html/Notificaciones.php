<?php
session_start();

if (!isset($_SESSION['idEstudiante'])) {
    header("Location: loginEstudiante.html");
    exit();
}

include '../php/Conexion.php';

// Obtener información del estudiante
$idEstudiante = $_SESSION['idEstudiante'];
$sqlEstudiante = "SELECT nombre, apellidos, idGrupo, foto_perfil FROM estudiantes WHERE idEstudiante = ?";
$stmtEstudiante = $conexion->prepare($sqlEstudiante);
$stmtEstudiante->bind_param("i", $idEstudiante);
$stmtEstudiante->execute();
$resultEstudiante = $stmtEstudiante->get_result();

if ($resultEstudiante->num_rows == 0) {
    header("Location: loginEstudiante.php");
    exit();
}

$estudiante = $resultEstudiante->fetch_assoc();
$idGrupo = $estudiante['idGrupo'];

// Función para marcar una notificación como vista
function marcarComoVista($conexion, $idEstudiante, $tipo, $idNotificacion)
{
    $sql = "INSERT INTO notificaciones_vistas (idEstudiante, tipo_notificacion, id_notificacion)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE fecha_visto = CURRENT_TIMESTAMP()";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("isi", $idEstudiante, $tipo, $idNotificacion);
    $stmt->execute();
}

// Función para verificar si una notificación ha sido vista
function esNotificacionVista($conexion, $idEstudiante, $tipo, $idNotificacion)
{
    $sql = "SELECT 1 FROM notificaciones_vistas
            WHERE idEstudiante = ? AND tipo_notificacion = ? AND id_notificacion = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("isi", $idEstudiante, $tipo, $idNotificacion);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

// Marcar notificaciones como leídas si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['marcar_leidas'])) {
    // Marcar todos los avisos como vistos
    $sqlAvisos = "SELECT id FROM avisos WHERE idGrupo = ?";
    $stmtAvisos = $conexion->prepare($sqlAvisos);
    $stmtAvisos->bind_param("s", $idGrupo);
    $stmtAvisos->execute();
    $resultAvisos = $stmtAvisos->get_result();

    while ($aviso = $resultAvisos->fetch_assoc()) {
        marcarComoVista($conexion, $idEstudiante, 'aviso', $aviso['id']);
    }

    // Marcar todas las tareas como vistas
    $sqlTareas = "SELECT idTarea FROM tareas WHERE idGrupo = ?";
    $stmtTareas = $conexion->prepare($sqlTareas);
    $stmtTareas->bind_param("s", $idGrupo);
    $stmtTareas->execute();
    $resultTareas = $stmtTareas->get_result();

    while ($tarea = $resultTareas->fetch_assoc()) {
        marcarComoVista($conexion, $idEstudiante, 'tarea', $tarea['idTarea']);
    }

    // Redirigir para evitar reenvío del formulario
    header("Location: Notificaciones.php");
    exit();
}

// Consulta para avisos del grupo del estudiante
$sqlAvisos = "SELECT a.id, a.mensaje AS titulo, a.mensaje AS descripcion, a.fecha_publicacion,
              NULL AS fecha_limite, u.nombre, u.apellidos, 'Aviso' AS tipo,
              NULL AS calificacion, NULL AS nombreMateria, NULL AS idMateria, NULL AS idTarea,
              NULL AS estado_entrega, NULL AS estado_tarea
              FROM avisos a
              JOIN usuarios u ON a.idUsuario = u.idUsuario
              WHERE a.idGrupo = ?";

// Consulta para tareas del grupo del estudiante
$sqlTareas = "SELECT t.idTarea AS id, t.titulo, t.descripcion, t.fecha_publicacion, t.fecha_limite,
              u.nombre, u.apellidos, 'Tarea' AS tipo, NULL AS calificacion, m.nombreMateria, m.idMateria, t.idTarea,
              CASE
                  WHEN e.idEntrega IS NULL THEN 'no_entregada'
                  ELSE 'entregada'
              END AS estado_entrega,
              CASE
                  WHEN e.idEntrega IS NULL AND t.fecha_limite <= DATE_ADD(CURDATE(), INTERVAL 1 DAY) THEN 'pendiente'
                  ELSE NULL
              END AS estado_tarea
              FROM tareas t
              JOIN materias m ON t.idMateria = m.idMateria
              JOIN usuarios u ON m.id_maestro = u.idUsuario
              LEFT JOIN entregas e ON e.idTarea = t.idTarea AND e.idEstudiante = ?
              WHERE t.idGrupo = ?";

// Consulta para calificaciones del estudiante
$sqlCalificaciones = "SELECT e.idEntrega AS id, ta.titulo AS titulo,
                     ta.titulo AS descripcion, e.fechaEntrega AS fecha_publicacion,
                     ta.fecha_limite, u.nombre, u.apellidos, 'Calificacion' AS tipo,
                     e.calificacion, m.nombreMateria, m.idMateria, ta.idTarea,
                     'entregada' AS estado_entrega, NULL AS estado_tarea
                     FROM entregas e
                     JOIN tareas ta ON e.idTarea = ta.idTarea
                     JOIN materias m ON ta.idMateria = m.idMateria
                     JOIN usuarios u ON m.id_maestro = u.idUsuario
                     WHERE e.idEstudiante = ? AND e.calificacion IS NOT NULL";

// Preparar y ejecutar consultas
$avisos = [];
$tareas = [];
$calificaciones = [];

// Obtener avisos
$stmtAvisos = $conexion->prepare($sqlAvisos);
$stmtAvisos->bind_param("s", $idGrupo);
$stmtAvisos->execute();
$resultAvisos = $stmtAvisos->get_result();
while ($aviso = $resultAvisos->fetch_assoc()) {
    $aviso['leido'] = esNotificacionVista($conexion, $idEstudiante, 'aviso', $aviso['id']);
    $avisos[] = $aviso;
}

// Obtener tareas
$stmtTareas = $conexion->prepare($sqlTareas);
$stmtTareas->bind_param("is", $idEstudiante, $idGrupo);
$stmtTareas->execute();
$resultTareas = $stmtTareas->get_result();
while ($tarea = $resultTareas->fetch_assoc()) {
    $tarea['leido'] = esNotificacionVista($conexion, $idEstudiante, 'tarea', $tarea['idTarea']);
    $tareas[] = $tarea;
}

// Obtener calificaciones
$stmtCalificaciones = $conexion->prepare($sqlCalificaciones);
$stmtCalificaciones->bind_param("i", $idEstudiante);
$stmtCalificaciones->execute();
$resultCalificaciones = $stmtCalificaciones->get_result();
while ($calificacion = $resultCalificaciones->fetch_assoc()) {
    $calificacion['leido'] = true; // Las calificaciones siempre se consideran leídas
    $calificaciones[] = $calificacion;
}

// Combinar todos los resultados
$notificaciones = array_merge($avisos, $tareas, $calificaciones);

// Ordenar por fecha descendente y estado de lectura
usort($notificaciones, function ($a, $b) {
    if ($a['leido'] == $b['leido']) {
        return strtotime($b['fecha_publicacion']) - strtotime($a['fecha_publicacion']);
    }
    return $a['leido'] ? 1 : -1;
});

// Contar notificaciones no leídas
$numNoLeidas = 0;
foreach ($notificaciones as $notificacion) {
    if (!$notificacion['leido']) {
        $numNoLeidas++;
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notificaciones</title>
    <link rel="stylesheet" href="../css/Notificaciones.css">
    <link rel="stylesheet" href="../css/botonPerfil.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="../js/botonPerfil.js"></script>
</head>

<body>
    <header class="header">
        <img src="../IMAGENES/logo.gif" alt="EDUSPHERE" class="logo">
        <h2>NOTIFICACIONES</h2>
        <nav class="nav-bar">
            <a href="Usuario.php"><i class="fas fa-home"></i> INICIO</a>
            <a href="Notificaciones.php" class="active"><i class="fas fa-bell"></i> NOTIFICACIONES
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
        <div class="notification-header-container">
            <?php if ($numNoLeidas > 0): ?>
                <form method="post" class="mark-read-form">
                    <button type="submit" name="marcar_leidas" class="btn-mark-read">
                        <i class="fas fa-check-circle"></i> Marcar todas como leídas
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <div class="notification-list">
            <?php if (!empty($notificaciones)) : ?>
                <?php foreach ($notificaciones as $notificacion) : ?>
                    <?php
                    // Marcar como leída al visualizarla (excepto calificaciones)
                    if (!$notificacion['leido'] && $notificacion['tipo'] !== 'Calificacion') {
                        $tipo = strtolower($notificacion['tipo']);
                        marcarComoVista($conexion, $idEstudiante, $tipo, $notificacion['id']);
                        $notificacion['leido'] = true;
                    }
                    ?>
                    <div class="notification-item
                        <?= $notificacion['tipo'] === 'Aviso' ? 'notification-aviso' : '' ?>
                        <?= $notificacion['tipo'] === 'Calificacion' ? 'notification-calificacion' : '' ?>
                        <?= $notificacion['tipo'] === 'Tarea' ? 'notification-tarea' : '' ?>
                        <?= $notificacion['estado_tarea'] === 'pendiente' ? 'notification-pendiente' : '' ?>
                        <?= !$notificacion['leido'] ? 'notification-unread' : '' ?>">

                        <?php if (!$notificacion['leido']): ?>
                            <div class="unread-indicator"></div>
                        <?php endif; ?>

                        <div class="notification-content">
                            <div class="notification-header">
                                <h3><?= htmlspecialchars($notificacion['titulo']) ?></h3>
                                <?php if ($notificacion['tipo'] === 'Calificacion') : ?>
                                    <span class="calificacion-badge <?php
                                                                    if ($notificacion['calificacion'] < 5) echo 'baja';
                                                                    elseif ($notificacion['calificacion'] >= 9) echo 'alta';
                                                                    else echo 'media';
                                                                    ?>">
                                        <?= htmlspecialchars($notificacion['calificacion']) ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($notificacion['estado_tarea'] === 'pendiente') : ?>
                                    <span class="pendiente-badge">¡Pendiente!</span>
                                <?php endif; ?>
                            </div>

                            <div class="notification-body">
                                <p><?= htmlspecialchars($notificacion['descripcion']) ?></p>

                                <?php if ($notificacion['tipo'] === 'Tarea' || $notificacion['tipo'] === 'Calificacion') : ?>
                                    <div class="tarea-info">
                                        <span><i class="fas fa-book"></i> <?= htmlspecialchars($notificacion['nombreMateria']) ?></span>
                                        <?php if ($notificacion['fecha_limite']) : ?>
                                            <span><i class="fas fa-clock"></i> Fecha límite: <?= date('d/m/Y', strtotime($notificacion['fecha_limite'])) ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="notification-footer">
                                <span class="notification-date"><i class="fas fa-calendar-alt"></i> <?= date('d/m/Y H:i', strtotime($notificacion['fecha_publicacion'])) ?></span>
                                <span class="notification-author"><i class="fas fa-user"></i> <?= htmlspecialchars($notificacion['nombre'] . ' ' . $notificacion['apellidos']) ?></span>
                                <span class="notification-type"><?= $notificacion['tipo'] ?></span>

                                <?php if ($notificacion['tipo'] === 'Tarea' && $notificacion['idMateria'] && $notificacion['idTarea']) : ?>
                                    <?php if ($notificacion['estado_entrega'] === 'no_entregada') : ?>
                                        <a href="../php/entregar_tarea.php?idTarea=<?= $notificacion['idTarea'] ?>" class="btn-entregar">
                                            <i class="fas fa-paper-plane"></i> Entregar
                                        </a>
                                    <?php else : ?>
                                        <a href="../php/ver_entrega.php?idTarea=<?= $notificacion['idTarea'] ?>" class="btn-ver">
                                            <i class="fas fa-eye"></i> Ver entrega
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else : ?>
                <p class="no-notifications">No hay notificaciones</p>
            <?php endif; ?>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Menú desplegable del usuario
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
