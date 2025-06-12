<?php
session_start();

if (!isset($_SESSION['idEstudiante'])) {
    header("Location: loginEstudiante.html");
    exit();
}

include '../php/Conexion.php';

if (!isset($_GET['idMateria'])) {
    header("Location: Usuario.php");
    exit();
}

$idMateria = $_GET['idMateria'];
$idEstudiante = $_SESSION['idEstudiante'];

// Obtener información de la materia
$sqlMateria = "SELECT m.nombreMateria, u.nombre, u.apellidos, m.idGrupo
               FROM materias m
               JOIN usuarios u ON m.id_maestro = u.idUsuario
               WHERE m.idMateria = ?";
$stmtMateria = $conexion->prepare($sqlMateria);
$stmtMateria->bind_param("i", $idMateria);
$stmtMateria->execute();
$resultMateria = $stmtMateria->get_result();

if ($resultMateria->num_rows == 0) {
    header("Location: Usuario.php");
    exit();
}

$materia = $resultMateria->fetch_assoc();
$idGrupo = $materia['idGrupo'];

// Obtener tareas de la materia
$sqlTareas = "SELECT t.idTarea, t.titulo, t.descripcion, t.fecha_publicacion, t.fecha_limite,
              e.idEntrega, e.calificacion, e.fechaEntrega
              FROM tareas t
              LEFT JOIN entregas e ON t.idTarea = e.idTarea AND e.idEstudiante = ?
              WHERE t.idMateria = ?
              ORDER BY t.fecha_publicacion DESC";
$stmtTareas = $conexion->prepare($sqlTareas);
$stmtTareas->bind_param("ii", $idEstudiante, $idMateria);
$stmtTareas->execute();
$resultTareas = $stmtTareas->get_result();

$tareas = [];
while ($row = $resultTareas->fetch_assoc()) {
    $tareas[] = $row;
}

// Obtener información del estudiante
$sqlEstudiante = "SELECT nombre, apellidos, foto_perfil FROM estudiantes WHERE idEstudiante = ?";
$stmtEstudiante = $conexion->prepare($sqlEstudiante);
$stmtEstudiante->bind_param("i", $idEstudiante);
$stmtEstudiante->execute();
$resultEstudiante = $stmtEstudiante->get_result();

$nombre = 'Usuario';
$apellidos = '';
$foto_perfil = 'default.png';
if ($resultEstudiante->num_rows > 0) {
    $rowEstudiante = $resultEstudiante->fetch_assoc();
    $nombre = $rowEstudiante['nombre'];
    $apellidos = $rowEstudiante['apellidos'];
    $foto_perfil = $rowEstudiante['foto_perfil'];
}

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

$stmtMateria->close();
$stmtTareas->close();
$stmtEstudiante->close();
$stmtNotificaciones->close();
$conexion->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($materia['nombreMateria']) ?> - Tareas</title>
    <link rel="stylesheet" href="../css/tareas_alumno.css">
    <link rel="stylesheet" href="../css/botonPerfil.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        <h2>TAREAS</h2>
        <nav class="nav-bar">
            <a href="Usuario.php"><i class="fas fa-home"></i> INICIO</a>
            <a href="Notificaciones.php"><i class="fas fa-bell"></i> NOTIFICACIONES
                <?php if ($numNoLeidas > 0): ?>
                    <span class="notification-badge"><?= $numNoLeidas ?></span>
                <?php endif; ?>
            </a>
        </nav>
        <div class="user-info" id="userDropdown">
            <div class="user-display">
                <span><?= htmlspecialchars($nombre . ' ' . $apellidos) ?></span>
                <img src="../IMAGENES/estudiantes/<?= htmlspecialchars($foto_perfil) ?>" alt="Foto perfil" class="profile-pic">
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
        <div class="card">
            <div class="card-header" style="background-color: #0A2A4D;">
                <div class="header-content">
                    <h3><?= htmlspecialchars($materia['nombreMateria']) ?></h3>
                    <span>Profesor: <?= htmlspecialchars($materia['nombre'] . ' ' . $materia['apellidos']) ?></span>
                </div>
                <div class="regresar-btn">
                    <a href="Usuario.php" class="btn btn-regresar">
                        <i class="fas fa-arrow-left"></i> Regresar
                    </a>
                </div>
            </div>

            <div class="card-body">
                <h4>Tareas asignadas</h4>

                <?php if (empty($tareas)): ?>
                    <div class="no-tareas">
                        <i class="fas fa-tasks"></i>
                        <p>No hay tareas asignadas para esta materia.</p>
                    </div>
                <?php else: ?>
                    <div class="tareas-grid">
                        <?php foreach ($tareas as $tarea): ?>
                            <div class="tarea-item <?= isset($tarea['idEntrega']) ? 'entregada' : 'pendiente' ?>">
                                <div class="tarea-content">
                                    <h5><?= htmlspecialchars($tarea['titulo']) ?></h5>
                                    <p><?= htmlspecialchars($tarea['descripcion']) ?></p>
                                    <div class="tarea-meta">
                                        <span><i class="fas fa-calendar-alt"></i> Publicada: <?= date('d/m/Y', strtotime($tarea['fecha_publicacion'])) ?></span>
                                        <?php if ($tarea['fecha_limite']): ?>
                                            <span><i class="fas fa-clock"></i> Fecha límite: <?= date('d/m/Y', strtotime($tarea['fecha_limite'])) ?></span>
                                        <?php endif; ?>
                                        <?php if (isset($tarea['idEntrega'])): ?>
                                            <span class="estado entregado"><i class="fas fa-check-circle"></i> Entregado el <?= date('d/m/Y H:i', strtotime($tarea['fechaEntrega'])) ?></span>
                                            <?php if ($tarea['calificacion'] !== null): ?>
                                                <span class="calificacion"><i class="fas fa-star"></i> Calificación: <?= htmlspecialchars($tarea['calificacion']) ?></span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="estado pendiente"><i class="fas fa-exclamation-circle"></i> Pendiente de entrega</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="tarea-actions">
                                    <?php if (isset($tarea['idEntrega'])): ?>
                                        <a href="../php/ver_entrega.php?idTarea=<?= $tarea['idTarea'] ?>" class="btn btn-ver">
                                            <i class="fas fa-eye"></i> Ver entrega
                                        </a>
                                    <?php else: ?>
                                        <a href="../php/entregar_tarea.php?idTarea=<?= $tarea['idTarea'] ?>" class="btn btn-entregar">
                                            <i class="fas fa-upload"></i> Entregar
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
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