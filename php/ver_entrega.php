<?php
session_start();

if (!isset($_SESSION['idEstudiante'])) {
    header("Location: ../html/loginEstudiante.html");
    exit();
}

include '../php/Conexion.php';

if (!isset($_GET['idTarea'])) {
    header("Location: ../html/Usuario.php");
    exit();
}

$idTarea = $_GET['idTarea'];
$idEstudiante = $_SESSION['idEstudiante'];

// Obtener información de la tarea
$sqlTarea = "SELECT t.idTarea, t.titulo, t.descripcion, t.idMateria, t.fecha_limite, m.nombreMateria
             FROM tareas t
             JOIN materias m ON t.idMateria = m.idMateria
             WHERE t.idTarea = ?";
$stmtTarea = $conexion->prepare($sqlTarea);
$stmtTarea->bind_param("i", $idTarea);
$stmtTarea->execute();
$resultTarea = $stmtTarea->get_result();

if ($resultTarea->num_rows == 0) {
    header("Location: Usuario.php");
    exit();
}

$tarea = $resultTarea->fetch_assoc();

// Verificar si la fecha límite ha pasado (solo comparando fechas, no horas)
$fechaLimitePasada = false;
if ($tarea['fecha_limite']) {
    $fechaLimite = new DateTime($tarea['fecha_limite']);
    $hoy = new DateTime();
    // Comparar solo las fechas (ignorando la hora)
    $fechaLimite->setTime(0, 0, 0);
    $hoy->setTime(0, 0, 0);
    $fechaLimitePasada = $hoy > $fechaLimite;
}

// Obtener información de la entrega
$sqlEntrega = "SELECT e.idEntrega, e.rutaArchivo, e.fechaEntrega, e.calificacion, e.comentarios
               FROM entregas e
               WHERE e.idTarea = ? AND e.idEstudiante = ?";
$stmtEntrega = $conexion->prepare($sqlEntrega);
$stmtEntrega->bind_param("ii", $idTarea, $idEstudiante);
$stmtEntrega->execute();
$resultEntrega = $stmtEntrega->get_result();

if ($resultEntrega->num_rows == 0) {
    header("Location: entregar_tarea.php?idTarea=" . $idTarea);
    exit();
}

$entrega = $resultEntrega->fetch_assoc();

// Obtener información del estudiante (incluyendo idGrupo)
$sqlEstudiante = "SELECT nombre, apellidos, foto_perfil, idGrupo FROM estudiantes WHERE idEstudiante = ?";
$stmtEstudiante = $conexion->prepare($sqlEstudiante);
$stmtEstudiante->bind_param("i", $idEstudiante);
$stmtEstudiante->execute();
$resultEstudiante = $stmtEstudiante->get_result();

$nombre = 'Usuario';
$apellidos = '';
$foto_perfil = 'default.png';
$idGrupo = null;
if ($resultEstudiante->num_rows > 0) {
    $rowEstudiante = $resultEstudiante->fetch_assoc();
    $nombre = $rowEstudiante['nombre'];
    $apellidos = $rowEstudiante['apellidos'];
    $foto_perfil = $rowEstudiante['foto_perfil'];
    $idGrupo = $rowEstudiante['idGrupo'];
}

// Consulta para contar notificaciones no leídas
if ($idGrupo) {
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
    $stmtNotificaciones->close();
} else {
    $numNoLeidas = 0;
}

$stmtTarea->close();
$stmtEntrega->close();
$stmtEstudiante->close();
$conexion->close();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrega - <?php echo htmlspecialchars($tarea['titulo']); ?></title>
    <link rel="stylesheet" href="../css/ver_entrega.css">
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
        <h2>VER TAREA</h2>
        <nav class="nav-bar">
            <a href="../html/Usuario.php"><i class="fas fa-home"></i> INICIO</a>
            <a href="../html/Notificaciones.php"><i class="fas fa-bell"></i> NOTIFICACIONES
                <?php if ($numNoLeidas > 0): ?>
                    <span class="notification-badge"><?= $numNoLeidas ?></span>
                <?php endif; ?>
            </a>
        </nav>
        <div class="user-info" id="userDropdown">
            <div class="user-display">
                <span><?php echo htmlspecialchars($nombre . ' ' . $apellidos); ?></span>
                <img src="../IMAGENES/estudiantes/<?php echo htmlspecialchars($foto_perfil); ?>" alt="Foto perfil" class="profile-pic">
                <i class="fas fa-chevron-down dropdown-arrow"></i>
            </div>
            <div class="dropdown-menu" id="dropdownMenu">
                <a href="../html/Perfil.php"><i class="fas fa-user-circle"></i> Perfil</a>
                <form action="../php/cerrar_sesion.php" method="post">
                    <button type="submit" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</button>
                </form>
            </div>
        </div>
    </header>

    <main class="container">
        <div class="card">
            <div class="card-header">
                <div class="header-info">
                    <h3><?php echo htmlspecialchars($tarea['titulo']); ?></h3>
                    <span><?php echo htmlspecialchars($tarea['nombreMateria']); ?></span>
                </div>
                <a href="../html/tareas_alumno.php?idMateria=<?php echo $tarea['idMateria']; ?>" class="btn-regresar">
                    <i class="fas fa-arrow-left"></i> Regresar
                </a>
            </div>

            <div class="card-body">
                <div class="tarea-info">
                    <h4>Descripción de la tarea:</h4>
                    <p><?php echo nl2br(htmlspecialchars($tarea['descripcion'])); ?></p>

                    <?php if ($tarea['fecha_limite']): ?>
                        <div class="fecha-info">
                            <p><strong>Fecha límite:</strong> <?php echo date('d/m/Y', strtotime($tarea['fecha_limite'])); ?></p>
                            <?php if ($fechaLimitePasada): ?>
                                <div class="resultado error">
                                    <i class="fas fa-exclamation-circle"></i>
                                    La fecha límite para esta tarea ha pasado.
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="entrega-info">
                    <h4>Tu entrega:</h4>

                    <div class="entrega-detalle">
                        <p><strong>Fecha de entrega:</strong> <?php echo date('d/m/Y H:i', strtotime($entrega['fechaEntrega'])); ?></p>

                        <?php if ($entrega['calificacion'] !== null): ?>
                            <p><strong>Calificación:</strong>
                                <span class="calificacion <?php
                                    if ($entrega['calificacion'] < 5) echo 'baja';
                                    elseif ($entrega['calificacion'] >= 9) echo 'alta';
                                    else echo 'media';
                                ?>">
                                    <?php echo htmlspecialchars($entrega['calificacion']); ?>
                                </span>
                            </p>
                        <?php else: ?>
                            <p><strong>Calificación:</strong> Pendiente</p>
                        <?php endif; ?>

                        <?php if (!empty($entrega['comentarios'])): ?>
                            <div class="comentarios-box">
                                <strong>Comentarios:</strong>
                                <p><?php echo nl2br(htmlspecialchars($entrega['comentarios'])); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="archivo-info">
                        <p><strong>Archivo entregado:</strong></p>
                        <?php
                        $extension = pathinfo($entrega['rutaArchivo'], PATHINFO_EXTENSION);
                        $icono = 'fa-file';

                        switch (strtolower($extension)) {
                            case 'pdf':
                                $icono = 'fa-file-pdf';
                                break;
                            case 'docx':
                                $icono = 'fa-file-word';
                                break;
                            case 'xlsx':
                                $icono = 'fa-file-excel';
                                break;
                            case 'pptx':
                                $icono = 'fa-file-powerpoint';
                                break;
                            case 'zip':
                            case 'rar':
                                $icono = 'fa-file-archive';
                                break;
                        }
                        ?>
                        <div class="archivo-box">
                            <i class="fas <?php echo $icono; ?>"></i>
                            <span><?php echo basename($entrega['rutaArchivo']); ?></span>
                            <a href="../entregas/<?php echo htmlspecialchars($entrega['rutaArchivo']); ?>" download class="btn-descargar">
                                <i class="fas fa-download"></i> Descargar
                            </a>
                        </div>
                    </div>
                </div>

                <?php if (!$fechaLimitePasada): ?>
                <div class="form-actions">
                    <a href="entregar_tarea.php?idTarea=<?php echo $idTarea; ?>" class="btn-actualizar">
                        <i class="fas fa-edit"></i> Actualizar entrega
                    </a>
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