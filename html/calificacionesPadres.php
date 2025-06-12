<?php
require_once '../php/Conexion.php';

session_start();
if (!isset($_SESSION['idPadre'])) {
    header("Location: loginPadres.html");
    exit();
}

$idPadre = $_SESSION['idPadre'];

// Obtener el idEstudiante asociado al padre
$sqlEstudiante = "SELECT e.idEstudiante, e.nombre, e.apellidos 
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

// Obtener calificaciones por tareas del estudiante (sin dividir entre 10)
$sqlCalificaciones = "SELECT c.idCalificacion, c.materia, c.calificacion, 
                             c.fecha, c.estado_alumno, t.titulo as titulo_tarea
                      FROM calificaciones c
                      LEFT JOIN tareas t ON c.idTarea = t.idTarea
                      WHERE c.idEstudiante = ?
                      ORDER BY c.fecha DESC";
$stmtCalificaciones = mysqli_prepare($conexion, $sqlCalificaciones);
mysqli_stmt_bind_param($stmtCalificaciones, "i", $idEstudiante);
mysqli_stmt_execute($stmtCalificaciones);
$resultCalificaciones = mysqli_stmt_get_result($stmtCalificaciones);
$calificaciones = mysqli_fetch_all($resultCalificaciones, MYSQLI_ASSOC);

// Preparar datos para gráfica
$materias = [];
$promedios = [];
$colors = [];

if (!empty($calificaciones)) {
    $sumaPorMateria = [];
    $contadorPorMateria = [];

    foreach ($calificaciones as $calificacion) {
        $materia = $calificacion['materia'];
        if (!isset($sumaPorMateria[$materia])) {
            $sumaPorMateria[$materia] = 0;
            $contadorPorMateria[$materia] = 0;
        }
        $sumaPorMateria[$materia] += $calificacion['calificacion'];
        $contadorPorMateria[$materia]++;
    }

    foreach ($sumaPorMateria as $materia => $suma) {
        $materias[] = $materia;
        $promedio = $suma / $contadorPorMateria[$materia];
        $promedios[] = round($promedio, 1);

        // Asignar color según el promedio (escala 0-10)
        if ($promedio >= 9) {
            $colors[] = '#28a745'; // Verde
        } elseif ($promedio >= 8) {
            $colors[] = '#7bc043'; // Verde claro
        } elseif ($promedio >= 7) {
            $colors[] = '#ffc107'; // Amarillo
        } elseif ($promedio >= 6) {
            $colors[] = '#fd7e14'; // Naranja
        } else {
            $colors[] = '#dc3545'; // Rojo
        }
    }
}
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
mysqli_stmt_close($stmtCalificaciones);
mysqli_close($conexion);
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calificaciones | Edusphere</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/calificacionesPadres.css">
    <link rel="stylesheet" href="../css/botonPerfil.css">
    <link rel="stylesheet" href="../css/notificacionesLeidas.css">
    <script src="../html/JS/botonPerfil.js"></script>
</head>

<body>
    <header class="header">
        <img src="../IMAGENES/logo.gif" alt="EDUSPHERE" class="logo">
        <h2>PADRES</h2>
        <nav class="nav-bar">
            <a href="PADRES.php">Inicio</a>
            <a href="" class="active">Calificaciones</a>
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
                    <img src="../IMAGENES/Padres/<?= htmlspecialchars($padre['foto_perfil']) ?>" alt="Foto de perfil" class="profile-pic">
                    <span><?= htmlspecialchars($padre['nombre'] . ' ' . $padre['apellidos']) ?></span>
                    <i class="fas fa-chevron-down dropdown-arrow"></i>
                </div>
                <div class="dropdown-menu" id="dropdownMenu">
                    <a href="perfilPadre.php"><i class="fas fa-user-circle"></i> Perfil</a>
                    <a href="../php/cerrar_sesion.php"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a>
                </div>
            </div>
        </div>
    </header>

    <div class="container py-4">

        <!-- Gráfica de promedios -->
        <?php if (!empty($materias)): ?>
            <div class="chart-container">
                <h4 class="mb-4"><i class="fas fa-chart-bar me-2"></i>Promedios por Materia</h4>
                <div class="chart-wrapper">
                    <canvas id="promediosChart"></canvas>
                </div>
            </div>
        <?php endif; ?>

        <!-- Listado de calificaciones -->
        <h4 class="mb-4"><i class="fas fa-clipboard-list me-2"></i>Detalle de Calificaciones</h4>

        <?php if (!empty($calificaciones)): ?>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                <?php foreach ($calificaciones as $calificacion):
                    // Determinar clase de tarjeta según la calificación (escala 0-10)
                    $cardClass = '';
                    $iconClass = '';
                    if ($calificacion['calificacion'] >= 9) {
                        $cardClass = 'card-excellent';
                        $iconClass = 'fas fa-star text-success';
                    } elseif ($calificacion['calificacion'] >= 8) {
                        $cardClass = 'card-good';
                        $iconClass = 'fas fa-thumbs-up text-success';
                    } elseif ($calificacion['calificacion'] >= 7) {
                        $cardClass = 'card-regular';
                        $iconClass = 'fas fa-check-circle text-warning';
                    } elseif ($calificacion['calificacion'] >= 6) {
                        $cardClass = 'card-bad';
                        $iconClass = 'fas fa-exclamation-circle text-warning';
                    } else {
                        $cardClass = 'card-fail';
                        $iconClass = 'fas fa-times-circle text-danger';
                    }

                    // Clase para el estado del alumno
                    $estadoClass = '';
                    $estadoIcon = '';
                    if ($calificacion['estado_alumno'] == 'Va bien') {
                        $estadoClass = 'estado-bien';
                        $estadoIcon = 'fas fa-smile';
                    } elseif ($calificacion['estado_alumno'] == 'Necesita aplicarse') {
                        $estadoClass = 'estado-aplicarse';
                        $estadoIcon = 'fas fa-meh';
                    } elseif ($calificacion['estado_alumno'] == 'Necesita ayuda') {
                        $estadoClass = 'estado-ayuda';
                        $estadoIcon = 'fas fa-frown';
                    }
                ?>
                    <div class="col">
                        <div class="card card-calificacion <?= $cardClass ?>">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h5 class="card-title mb-0">
                                        <i class="<?= $iconClass ?> me-2"></i>
                                        <?= htmlspecialchars($calificacion['materia']) ?>
                                    </h5>
                                    <span class="badge bg-primary rounded-pill">
                                        <?= number_format($calificacion['calificacion'], 1) ?>
                                    </span>
                                </div>

                                <?php if (!empty($calificacion['titulo_tarea'])): ?>
                                    <h6 class="card-subtitle mb-2 text-muted">
                                        <i class="far fa-file-alt me-2"></i>
                                        <?= htmlspecialchars($calificacion['titulo_tarea']) ?>
                                    </h6>
                                <?php endif; ?>

                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <small class="text-muted">
                                        <i class="far fa-calendar-alt me-1"></i>
                                        <?= date('d/m/Y', strtotime($calificacion['fecha'])) ?>
                                    </small>

                                    <?php if (!empty($calificacion['estado_alumno'])): ?>
                                        <span class="badge-estado <?= $estadoClass ?>">
                                            <i class="<?= $estadoIcon ?> me-1"></i>
                                            <?= htmlspecialchars($calificacion['estado_alumno']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="far fa-file-excel"></i>
                <h5>No hay calificaciones registradas</h5>
                <p class="text-muted">Actualmente no hay calificaciones disponibles para mostrar.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer>
        <p>Edusphere © <?= date('Y') ?> - Todos los derechos reservados</p>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../html/JS/botonPerfil.js"></script>

    <!-- Script para gráfica -->
    <?php if (!empty($materias)): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const ctx = document.getElementById('promediosChart');
                if (ctx) {
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: <?= json_encode($materias) ?>,
                            datasets: [{
                                label: 'Promedio',
                                data: <?= json_encode($promedios) ?>,
                                backgroundColor: <?= json_encode($colors) ?>,
                                borderColor: '#ffffff',
                                borderWidth: 1,
                                barPercentage: 0.7
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    max: 10, // Máximo en 10 para la escala
                                    ticks: {
                                        stepSize: 1, // Mostrar marcas cada 1 punto
                                        callback: function(value) {
                                            return value; // Mostrar valores simples
                                        }
                                    },
                                    grid: {
                                        color: 'rgba(0, 0, 0, 0.05)'
                                    }
                                },
                                x: {
                                    grid: {
                                        display: false
                                    }
                                }
                            },
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            return 'Promedio: ' + context.parsed.y.toFixed(1);
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
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
    <?php endif; ?>
</body>

</html>