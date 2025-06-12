<?php
require_once '../php/Conexion.php';

session_start();
if (!isset($_SESSION['idPadre'])) {
    header("Location: loginPadres.html");
    exit();
}

$idPadre = $_SESSION['idPadre'];

// 1. Obtener información del padre (para el header)
$sqlPadre = "SELECT nombre, apellidos, foto_perfil FROM padres WHERE idPadre = ?";
$stmtPadre = mysqli_prepare($conexion, $sqlPadre);
if (!$stmtPadre) {
    die("Error en la preparación de la consulta: " . mysqli_error($conexion));
}

mysqli_stmt_bind_param($stmtPadre, "i", $idPadre);
if (!mysqli_stmt_execute($stmtPadre)) {
    die("Error al ejecutar la consulta: " . mysqli_stmt_error($stmtPadre));
}

$resultPadre = mysqli_stmt_get_result($stmtPadre);
$padre = mysqli_fetch_assoc($resultPadre);

if (!$padre) {
    die("Error: Información de padre no encontrada.");
}

$nombreCompleto = htmlspecialchars($padre['nombre'] . ' ' . $padre['apellidos']);
$fotoPerfil = !empty($padre['foto_perfil']) ? '../IMAGENES/Padres/' . $padre['foto_perfil'] : '../IMAGENES/Admin.jpg';

// 2. Obtener el estudiante asociado al padre
$sqlEstudiante = "SELECT e.idEstudiante, e.nombre, e.apellidos 
                  FROM estudiantes e
                  JOIN estudiantes_padres ep ON e.idEstudiante = ep.idEstudiante
                  WHERE ep.idPadre = ?";
$stmtEstudiante = mysqli_prepare($conexion, $sqlEstudiante);
if (!$stmtEstudiante) {
    die("Error en la preparación de la consulta: " . mysqli_error($conexion));
}

mysqli_stmt_bind_param($stmtEstudiante, "i", $idPadre);
if (!mysqli_stmt_execute($stmtEstudiante)) {
    die("Error al ejecutar la consulta: " . mysqli_stmt_error($stmtEstudiante));
}

$resultEstudiante = mysqli_stmt_get_result($stmtEstudiante);
$estudiante = mysqli_fetch_assoc($resultEstudiante);

if (!$estudiante) {
    die("No se encontró estudiante asociado a este padre.");
}

$idEstudiante = $estudiante['idEstudiante'];
$nombreEstudiante = htmlspecialchars($estudiante['nombre'] . ' ' . $estudiante['apellidos']);

// 3. Obtener asistencias del estudiante
$sqlAsistencias = "SELECT fecha, estadoAsistencia 
                   FROM asistencias 
                   WHERE idEstudiante = ?
                   ORDER BY fecha DESC";
$stmtAsistencias = mysqli_prepare($conexion, $sqlAsistencias);
if (!$stmtAsistencias) {
    die("Error en la preparación de la consulta: " . mysqli_error($conexion));
}

mysqli_stmt_bind_param($stmtAsistencias, "i", $idEstudiante);
if (!mysqli_stmt_execute($stmtAsistencias)) {
    die("Error al ejecutar la consulta: " . mysqli_stmt_error($stmtAsistencias));
}

$resultAsistencias = mysqli_stmt_get_result($stmtAsistencias);
$asistencias = mysqli_fetch_all($resultAsistencias, MYSQLI_ASSOC);

// Preparar datos para el calendario
$asistenciasMap = [];
foreach ($asistencias as $asistencia) {
    $fecha = $asistencia['fecha'];
    $asistenciasMap[$fecha] = $asistencia['estadoAsistencia'];
}

// Obtener mes y año actual (o los pasados por GET)
$mes = isset($_GET['mes']) ? intval($_GET['mes']) : date('n');
$anio = isset($_GET['anio']) ? intval($_GET['anio']) : date('Y');

// Validar mes y año
if ($mes < 1 || $mes > 12) $mes = date('n');
if ($anio < 2000 || $anio > 2100) $anio = date('Y');

// Preparar nombres de meses
$nombreMes = [
    1 => 'Enero',
    2 => 'Febrero',
    3 => 'Marzo',
    4 => 'Abril',
    5 => 'Mayo',
    6 => 'Junio',
    7 => 'Julio',
    8 => 'Agosto',
    9 => 'Septiembre',
    10 => 'Octubre',
    11 => 'Noviembre',
    12 => 'Diciembre'
];

// Calcular días del mes y primer día
$primerDia = date('N', strtotime("{$anio}-{$mes}-01"));
$diasEnMes = date('t', strtotime("{$anio}-{$mes}-01"));

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

// Cerrar conexiones
mysqli_stmt_close($stmtPadre);
mysqli_stmt_close($stmtEstudiante);
mysqli_stmt_close($stmtAsistencias);
mysqli_close($conexion);
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asistencias Escolares | Edusphere</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/asistenciasPadres.css">
    <link rel="stylesheet" href="../css/botonPerfil.css">
    <link rel="stylesheet" href="../css/notificacionesLeidas.css">
</head>

<body>
    <header class="header">
        <img src="../IMAGENES/logo.gif" alt="EDUSPHERE" class="logo">
        <h2>PADRES</h2>
        <nav class="nav-bar">
            <a href="../html/PADRES.php">Inicio</a>
            <a href="" class="active">Asistencias</a>
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
        <div class="calendar-controls">
            <a href="?mes=<?= $mes - 1 <= 0 ? 12 : $mes - 1 ?>&anio=<?= $mes - 1 <= 0 ? $anio - 1 : $anio ?>"
                class="nav-button">
                <i class="fas fa-chevron-left"></i>
            </a>

            <h1><?= htmlspecialchars($nombreMes[$mes]) ?> <?= htmlspecialchars($anio) ?></h1>

            <a href="?mes=<?= $mes + 1 >= 13 ? 1 : $mes + 1 ?>&anio=<?= $mes + 1 >= 13 ? $anio + 1 : $anio ?>"
                class="nav-button">
                <i class="fas fa-chevron-right"></i>
            </a>
        </div>

        <div class="calendar-container">
            <div class="calendar-header">
                <div class="day-header">Lun</div>
                <div class="day-header">Mar</div>
                <div class="day-header">Mié</div>
                <div class="day-header">Jue</div>
                <div class="day-header">Vie</div>
                <div class="day-header">Sáb</div>
                <div class="day-header">Dom</div>
            </div>

            <div class="calendar-grid">
                <?php
                // Espacios vacíos al inicio
                for ($i = 1; $i < $primerDia; $i++) {
                    echo '<div class="calendar-day empty"></div>';
                }

                // Días del mes
                for ($dia = 1; $dia <= $diasEnMes; $dia++) {
                    $fechaCompleta = sprintf("%04d-%02d-%02d", $anio, $mes, $dia);
                    $estado = $asistenciasMap[$fechaCompleta] ?? null;

                    $claseDia = 'calendar-day';
                    $icono = '';
                    $tooltip = '';

                    if ($estado) {
                        $claseDia .= $estado == 'Asistió' ? ' present' : ' absent';
                        $icono = $estado == 'Asistió' ? 'fa-check-circle' : 'fa-times-circle';
                        $tooltip = "data-status='" . htmlspecialchars($estado) . "'";
                    }

                    // Resaltar día actual
                    $hoy = date('Y-m-d');
                    if ($fechaCompleta == $hoy) {
                        $claseDia .= ' today';
                    }

                    echo "<div class='{$claseDia}' {$tooltip}>
                            <div class='day-number'>{$dia}</div>
                            <div class='day-status'><i class='fas {$icono}'></i></div>
                          </div>";
                }

                // Espacios vacíos al final
                $totalCeldas = $primerDia + $diasEnMes - 1;
                $filasNecesarias = ceil($totalCeldas / 7);
                $celdasVacias = $filasNecesarias * 7 - $totalCeldas;

                for ($i = 0; $i < $celdasVacias; $i++) {
                    echo '<div class="calendar-day empty"></div>';
                }
                ?>
            </div>
        </div>

        <div class="legend">
            <div class="legend-item">
                <div class="color-box present"></div>
                <span>Asistió</span>
            </div>
            <div class="legend-item">
                <div class="color-box absent"></div>
                <span>No asistió</span>
            </div>
            <div class="legend-item">
                <div class="color-box today"></div>
                <span>Hoy</span>
            </div>
            <div class="legend-item">
                <div class="color-box empty"></div>
                <span>Sin registro</span>
            </div>
        </div>
    </main>

    <footer>
        <p>Edusphere © <?= date('Y') ?> - Todos los derechos reservados</p>
    </footer>

    <script src="../html/JS/botonPerfil.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tooltips para los días con asistencia
            const calendarDays = document.querySelectorAll('.calendar-day[data-status]');

            calendarDays.forEach(day => {
                const status = day.getAttribute('data-status');
                const tooltip = document.createElement('div');
                tooltip.className = 'tooltip';
                tooltip.textContent = status;

                day.addEventListener('mouseenter', function(e) {
                    document.body.appendChild(tooltip);

                    const rect = day.getBoundingClientRect();
                    tooltip.style.left = `${rect.left + window.scrollX}px`;
                    tooltip.style.top = `${rect.top + window.scrollY - 40}px`;
                    tooltip.style.opacity = '1';
                });

                day.addEventListener('mouseleave', function() {
                    tooltip.style.opacity = '0';
                    setTimeout(() => {
                        if (tooltip.parentNode) {
                            tooltip.parentNode.removeChild(tooltip);
                        }
                    }, 300);
                });
            });

            // Navegación con teclado
            document.addEventListener('keydown', function(e) {
                const currentMonth = <?= $mes ?>;
                const currentYear = <?= $anio ?>;

                if (e.key === 'ArrowLeft') {
                    // Mes anterior
                    const prevMonth = currentMonth - 1 <= 0 ? 12 : currentMonth - 1;
                    const prevYear = currentMonth - 1 <= 0 ? currentYear - 1 : currentYear;
                    window.location.href = `?mes=${prevMonth}&anio=${prevYear}`;
                } else if (e.key === 'ArrowRight') {
                    // Mes siguiente
                    const nextMonth = currentMonth + 1 >= 13 ? 1 : currentMonth + 1;
                    const nextYear = currentMonth + 1 >= 13 ? currentYear + 1 : currentYear;
                    window.location.href = `?mes=${nextMonth}&anio=${nextYear}`;
                }
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