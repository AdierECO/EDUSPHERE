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
    header("Location: loginEstudiante.html");
    exit();
}

$estudiante = $resultEstudiante->fetch_assoc();
$idGrupo = $estudiante['idGrupo'];

// Obtener las materias del grupo del estudiante
$sqlMaterias = "SELECT m.idMateria, m.nombreMateria, u.nombre, u.apellidos
                FROM materias m
                JOIN usuarios u ON m.id_maestro = u.idUsuario
                WHERE m.idGrupo = ?";
$stmtMaterias = $conexion->prepare($sqlMaterias);
$stmtMaterias->bind_param("s", $idGrupo);
$stmtMaterias->execute();
$resultMaterias = $stmtMaterias->get_result();

$materias = [];
while ($rowMateria = $resultMaterias->fetch_assoc()) {
    $materias[] = $rowMateria;
}

// CONSULTA OPTIMIZADA solo para contar notificaciones no leídas
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
$stmtMaterias->close();
$stmtNotificaciones->close();
$conexion->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio Estudiante</title>
    <link rel="stylesheet" href="../css/Usuario.css">
    <link rel="stylesheet" href="../css/botonPerfil.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="../js/botonPerfil.js"></script>
</head>
<body>
    <header class="header">
        <img src="../IMAGENES/logo.gif" alt="EDUSPHERE" class="logo">
        <h2>MATERIAS</h2>
        <nav class="nav-bar">
            <a href="Usuario.php" class="active"><i class="fas fa-home"></i> INICIO</a>
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
        <section class="card-grid">
            <?php foreach ($materias as $materia): ?>
                <div class="card">
                    <div class="card-header" style="background-color: #0A2A4D;">
                        <h3><?= htmlspecialchars($materia['nombreMateria']) ?></h3>
                        <span><?= htmlspecialchars($materia['nombre'] . ' ' . $materia['apellidos']) ?></span>
                    </div>
                    <div class="card-body">
                        <p><strong>Detalles:</strong> Materia del grupo <?= htmlspecialchars($idGrupo) ?></p>
                    </div>
                    <div class="card-footer">
                        <a href="tareas_alumno.php?idMateria=<?= $materia['idMateria'] ?>">
                            <button>Ver</button>
                        </a>
                        <a href="material.php?materia=<?= urlencode($materia['nombreMateria']) ?>">
                            <button>Material</button>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>
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