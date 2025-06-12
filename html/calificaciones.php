<?php
session_start();
require_once '../php/Conexion.php';

if (!isset($_SESSION['idUsuario'])) {
    header("Location: INICIO.html");
    exit();
}

// Obtener datos del padre
$idPadre = $_SESSION['idUsuario'];
$sql = "SELECT nombre, apellidos, foto_perfil FROM Usuarios WHERE idUsuario = ?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $idPadre);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $padre = $result->fetch_assoc();
    $nombreCompleto = $padre['nombre'] . ' ' . $padre['apellidos'];
    $fotoPerfil = !empty($padre['foto_perfil']) ? '../IMAGENES/Usuarios/' . $padre['foto_perfil'] : '../IMAGENES/Docente.jpg';
} else {
    // Datos por defecto si no encuentra al padre
    $nombreCompleto = "Usuario";
    $fotoPerfil = '../IMAGENES/Docente.jpg';
}
$id_usuario = $_SESSION['idUsuario'];

// Función para obtener estudiantes de un grupo
function getEstudiantes($grupoId, $conexion) {
    $sql = "SELECT idEstudiante, nombre, apellidos
            FROM estudiantes
            WHERE idGrupo = ?
            ORDER BY apellidos, nombre";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("s", $grupoId);
    $stmt->execute();
    $result = $stmt->get_result();

    $estudiantes = [];
    while ($row = $result->fetch_assoc()) {
        $estudiantes[] = $row;
    }
    return $estudiantes;
}

// Función para obtener tareas pendientes
function getTareasPendientes($estudianteId, $grupoId, $usuarioId, $conexion) {
    $sql = "SELECT t.idTarea, t.titulo, t.descripcion,
                   m.idMateria, m.nombreMateria,
                   e.idEntrega, e.rutaArchivo, e.fechaEntrega,
                   e.calificacion, e.comentarios
            FROM tareas t
            JOIN materias m ON t.idMateria = m.idMateria
            LEFT JOIN entregas e ON t.idTarea = e.idTarea AND e.idEstudiante = ?
            WHERE m.idGrupo = ? AND m.id_maestro = ?
            ORDER BY m.nombreMateria, t.fecha_publicacion DESC";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("isi", $estudianteId, $grupoId, $usuarioId);
    $stmt->execute();
    $result = $stmt->get_result();

    $tareas = [];
    while ($row = $result->fetch_assoc()) {
        $tarea = [
            'idTarea' => $row['idTarea'],
            'titulo' => $row['titulo'],
            'descripcion' => $row['descripcion'],
            'idMateria' => $row['idMateria'],
            'nombreMateria' => $row['nombreMateria'],
            'calificacion' => $row['calificacion'],
            'comentarios' => $row['comentarios']
        ];

        if ($row['idEntrega']) {
            $tarea['entrega'] = [
                'rutaArchivo' => $row['rutaArchivo'],
                'fechaEntrega' => $row['fechaEntrega']
            ];
        }

        $tareas[] = $tarea;
    }
    return $tareas;
}

// Función para guardar o actualizar calificación
function guardarCalificacion($data, $conexion) {
    $idEstudiante = intval($data['idEstudiante']);
    $idTarea = intval($data['idTarea']);
    $calificacion = floatval($data['calificacion']);
    $comentarios = $conexion->real_escape_string($data['comentarios']);

    if ($idEstudiante <= 0 || $idTarea <= 0 || $calificacion < 0 || $calificacion > 10) {
        return ['success' => false, 'message' => 'Datos inválidos'];
    }

    // Verificar si la calificación ya existe en la tabla entregas
    $sql_check = "SELECT idEntrega FROM entregas WHERE idTarea = ? AND idEstudiante = ?";
    $stmt_check = $conexion->prepare($sql_check);
    $stmt_check->bind_param("ii", $idTarea, $idEstudiante);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows > 0) {
        // Actualizar calificación existente en la tabla entregas
        $sql_entregas = "UPDATE entregas
                         SET calificacion = ?, comentarios = ?
                         WHERE idTarea = ? AND idEstudiante = ?";
        $stmt_entregas = $conexion->prepare($sql_entregas);
        $stmt_entregas->bind_param("dsii", $calificacion, $comentarios, $idTarea, $idEstudiante);
    } else {
        // Insertar nueva calificación en la tabla entregas
        $sql_entregas = "INSERT INTO entregas (idTarea, idEstudiante, calificacion, comentarios)
                         VALUES (?, ?, ?, ?)";
        $stmt_entregas = $conexion->prepare($sql_entregas);
        $stmt_entregas->bind_param("iids", $idTarea, $idEstudiante, $calificacion, $comentarios);
    }

    // Insertar o actualizar calificación en la tabla calificaciones
    $sql_calificaciones = "INSERT INTO calificaciones (idEstudiante, materia, calificacion, fecha, idTarea)
                           VALUES (?, 'Materia', ?, CURDATE(), ?)
                           ON DUPLICATE KEY UPDATE calificacion = VALUES(calificacion), fecha = CURDATE()";
    $stmt_calificaciones = $conexion->prepare($sql_calificaciones);
    $stmt_calificaciones->bind_param("idi", $idEstudiante, $calificacion, $idTarea);

    // Actualizar la columna calificacion en la tabla tareas
    $sql_tareas = "UPDATE tareas
                   SET calificacion = ?
                   WHERE idTarea = ?";
    $stmt_tareas = $conexion->prepare($sql_tareas);
    $stmt_tareas->bind_param("di", $calificacion, $idTarea);

    // Ejecutar todas las consultas
    $success_entregas = $stmt_entregas->execute();
    $success_calificaciones = $stmt_calificaciones->execute();
    $success_tareas = $stmt_tareas->execute();

    if ($success_entregas && $success_calificaciones && $success_tareas) {
        // Enviar aviso al padre sobre la calificación de la tarea
        $mensaje = "La tarea '" . $data['titulo'] . "' ha sido calificada con " . $calificacion . ".";
        $idUsuario = $_SESSION['idUsuario'];
        $idInstitucion = 1; // Replace with the correct institution ID
        $idGrupo = $data['idGrupo'];
        $prioridad = 'media';
        enviarAviso($mensaje, $idUsuario, $idInstitucion, $idGrupo, $prioridad, $conexion);

        return ['success' => true, 'idEstudiante' => $idEstudiante, 'idTarea' => $idTarea];
    } else {
        return ['success' => false, 'message' => 'Error al guardar en la base de datos'];
    }
}

// Función para enviar aviso a la tabla de avisos
function enviarAviso($mensaje, $idUsuario, $idInstitucion, $idGrupo, $prioridad, $conexion) {
    $fecha_publicacion = date('Y-m-d'); // Get the current date
    $sql = "INSERT INTO avisos (mensaje, fecha_publicacion, idUsuario, idInstitucion, idGrupo, prioridad)
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("ssisss", $mensaje, $fecha_publicacion, $idUsuario, $idInstitucion, $idGrupo, $prioridad);
    return $stmt->execute();
}

// Función para calcular el promedio por parcial
function calcularPromedioParcial($estudianteId, $conexion) {
    $promedios = [];
    $meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    for ($i = 0; $i < 12; $i += 4) {
        $inicio = sprintf('%02d-01', $i + 1);
        $fin = sprintf('%02d-31', $i + 4);

        $sql = "SELECT AVG(e.calificacion) as promedio
                FROM entregas e
                JOIN tareas t ON e.idTarea = t.idTarea
                WHERE e.idEstudiante = ? AND t.fecha_publicacion BETWEEN ? AND ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("iss", $estudianteId, $inicio, $fin);
        $stmt->execute();
        $result = $stmt->get_result();
        $promedio = $result->fetch_assoc()['promedio'];

        $promedios[] = [
            'periodo' => $meses[$i] . ' - ' . $meses[$i + 3],
            'promedio' => $promedio ? number_format($promedio, 2) : 'N/A'
        ];
    }

    return $promedios;
}

// Función para generar el archivo CSV
function generarCSVPromedios($estudianteId, $conexion) {
    $promedios = calcularPromedioParcial($estudianteId, $conexion);

    $csv = "Periodo,Promedio\n";
    foreach ($promedios as $promedio) {
        $csv .= "{$promedio['periodo']},{$promedio['promedio']}\n";
    }

    $filename = "promedios_parciales_estudiante_{$estudianteId}.csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $csv;
    exit;
}

// Manejo de solicitudes AJAX
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    header('Content-Type: application/json');

    if ($_GET['action'] === 'getEstudiantes' && isset($_GET['grupo'])) {
        echo json_encode(getEstudiantes($_GET['grupo'], $conexion));
        exit;
    }

    if ($_GET['action'] === 'getTareasPendientes' && isset($_GET['estudiante']) && isset($_GET['grupo'])) {
        echo json_encode(getTareasPendientes(
            $_GET['estudiante'],
            $_GET['grupo'],
            $id_usuario,
            $conexion
        ));
        exit;
    }

    if ($_GET['action'] === 'descargarPromedios' && isset($_GET['estudiante'])) {
        $estudianteId = intval($_GET['estudiante']);
        generarCSVPromedios($estudianteId, $conexion);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'guardarCalificacion') {
    header('Content-Type: application/json');
    echo json_encode(guardarCalificacion($_POST, $conexion));
    exit;
}

// Obtener grupos asignados al docente
$sql_grupos = "SELECT DISTINCT g.idGrupo
               FROM grupos g
               JOIN materias m ON g.idGrupo = m.idGrupo
               WHERE m.id_maestro = ?
               ORDER BY g.idGrupo";
$stmt = $conexion->prepare($sql_grupos);
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$resultado_grupos = $stmt->get_result();

// Obtener datos del primer grupo para precargar estudiantes
$primer_grupo = null;
$estudiantes = [];

if ($resultado_grupos && $resultado_grupos->num_rows > 0) {
    $resultado_grupos->data_seek(0);
    $primer_grupo = $resultado_grupos->fetch_assoc();
    $estudiantes = getEstudiantes($primer_grupo['idGrupo'], $conexion);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calificaciones | EDUSPHERE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/Calificaciones.css">
    <link rel="stylesheet" href="../css/botonPerfil.css">
    <style>
        /* Estilos adicionales para mejorar la tabla */
        .calificaciones-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .calificaciones-table th, .calificaciones-table td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: center;
        }

        .calificaciones-table th {
            background-color: #f4f4f4;
            font-weight: bold;
        }

        .calificaciones-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .calificaciones-table tr:hover {
            background-color: #f1f1f1;
        }

        .calificaciones-table .promedio-cell {
            font-weight: bold;
            color: #333;
        }

        .calificaciones-table .promedio-bajo {
            color: #d00000;
        }

        .calificaciones-table .alert-icon {
            margin-left: 5px;
            color: #d00000;
        }

        .btn-descargar {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            margin-top: 10px;
            border-radius: 5px;
            display: inline-block;
        }

        .btn-descargar:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
<header class="header">
        <img src="../IMAGENES/logo.gif" alt="EDUSPHERE" class="logo">
        <h2>CALIFICACIONES</h2>
        <nav class="nav-bar">
            <a href="Docente.php" class="active">INICIO</a>
        </nav>
        <div class="user-info" id="userDropdown">
            <div class="user-display">
                <img src="<?= $fotoPerfil ?>" alt="Foto de perfil" class="profile-pic">
                <span><?= $nombreCompleto ?></span>
                <i class="fas fa-chevron-down dropdown-arrow"></i>
            </div>
            <div class="dropdown-menu" id="dropdownMenu">
                <a href="Perfil_docente.php"><i class="fas fa-user-circle"></i> Perfil</a>
                <a href="../php/cerrar_sesion.php"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a>
            </div>
        </div>
    </header>
    </header>

    <main class="container">
        <h1 class="page-title">
            <span><i class="fas fa-clipboard-list"></i> Gestión de Calificaciones</span>
        </h1>

        <div class="tab-container">
            <div class="tab active" onclick="openTab('ver-calificaciones')">
                <i class="fas fa-list"></i> Ver Calificaciones
            </div>
            <div class="tab" onclick="openTab('calificar-tareas')">
                <i class="fas fa-edit"></i> Calificar Tareas
            </div>
        </div>

        <div id="ver-calificaciones" class="tab-content active">
            <?php if ($resultado_grupos && $resultado_grupos->num_rows > 0): ?>
                <?php $resultado_grupos->data_seek(0); while ($grupo = $resultado_grupos->fetch_assoc()): ?>
                    <div class="grupo-card">
                        <div class="grupo-header">
                            <h3 class="grupo-title">
                                <i class="fas fa-users"></i> Grupo: <?php echo htmlspecialchars($grupo['idGrupo']); ?>
                            </h3>
                        </div>

                        <?php
                        $idGrupo = $grupo['idGrupo'];
                        $estudiantes_grupo = getEstudiantes($idGrupo, $conexion);
                        ?>

                        <?php if (!empty($estudiantes_grupo)): ?>
                            <?php foreach ($estudiantes_grupo as $estudiante): ?>
                                <div class="estudiante-section" id="estudiante-<?php echo $estudiante['idEstudiante']; ?>">
                                    <h4 class="estudiante-title">
                                        <i class="fas fa-user-graduate"></i>
                                        <?php echo htmlspecialchars($estudiante['apellidos'] . ", " . $estudiante['nombre']); ?>
                                    </h4>
                                    <button class="btn-descargar" data-estudiante="<?php echo $estudiante['idEstudiante']; ?>">
                                        <i class="fas fa-download"></i> Descargar Promedios
                                    </button>

                                    <?php
                                    $idEstudiante = $estudiante['idEstudiante'];
                                    $sql_promedio = "SELECT AVG(e.calificacion) as promedio
                                                   FROM entregas e
                                                   JOIN tareas t ON e.idTarea = t.idTarea
                                                   JOIN materias m ON t.idMateria = m.idMateria
                                                   WHERE e.idEstudiante = ? AND m.idGrupo = ?";
                                    $stmt = $conexion->prepare($sql_promedio);
                                    $stmt->bind_param("is", $idEstudiante, $idGrupo);
                                    $stmt->execute();
                                    $resultado_promedio = $stmt->get_result();
                                    $promedio = $resultado_promedio->fetch_assoc()['promedio'];
                                    $promedio_class = ($promedio && $promedio <= 5.0) ? 'promedio-bajo' : '';

                                    if ($promedio && $promedio <= 5.0) {
                                        $mensaje = "El estudiante tiene un promedio por debajo del 50%.";
                                        $idUsuario = $_SESSION['idUsuario'];
                                        $idInstitucion = 1; // Replace with the correct institution ID
                                        $idGrupo = $grupo['idGrupo'];
                                        $prioridad = 'alta'; // Asegúrate de que la prioridad sea 'alta'
                                        enviarAviso($mensaje, $idUsuario, $idInstitucion, $idGrupo, $prioridad, $conexion);
                                    }
                                    ?>

                                    <table class="calificaciones-table">
                                        <thead>
                                            <tr>
                                                <th>Materia</th>
                                                <th>Tarea</th>
                                                <th>Calificación</th>
                                                <th>Estado</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $sql_materias = "SELECT m.idMateria, m.nombreMateria
                                                           FROM materias m
                                                           WHERE m.idGrupo = ? AND m.id_maestro = ?
                                                           ORDER BY m.nombreMateria";
                                            $stmt = $conexion->prepare($sql_materias);
                                            $stmt->bind_param("si", $idGrupo, $id_usuario);
                                            $stmt->execute();
                                            $resultado_materias = $stmt->get_result();
                                            ?>

                                            <?php if ($resultado_materias && $resultado_materias->num_rows > 0): ?>
                                                <?php while ($materia = $resultado_materias->fetch_assoc()): ?>
                                                    <?php
                                                    $idMateria = $materia['idMateria'];
                                                    $sql_tareas = "SELECT t.idTarea, t.titulo,
                                                                 e.idEntrega, e.rutaArchivo, e.fechaEntrega,
                                                                 e.calificacion, e.comentarios
                                                                 FROM tareas t
                                                                 LEFT JOIN entregas e ON t.idTarea = e.idTarea AND e.idEstudiante = ?
                                                                 WHERE t.idMateria = ?
                                                                 ORDER BY t.fecha_publicacion";
                                                    $stmt = $conexion->prepare($sql_tareas);
                                                    $stmt->bind_param("ii", $idEstudiante, $idMateria);
                                                    $stmt->execute();
                                                    $resultado_tareas = $stmt->get_result();
                                                    ?>

                                                    <?php if ($resultado_tareas && $resultado_tareas->num_rows > 0): ?>
                                                        <?php $first_row = true; while ($tarea = $resultado_tareas->fetch_assoc()): ?>
                                                            <tr data-tarea="<?php echo $tarea['idTarea']; ?>" data-estudiante="<?php echo $idEstudiante; ?>">
                                                                <?php if ($first_row): ?>
                                                                    <td rowspan="<?php echo $resultado_tareas->num_rows; ?>">
                                                                        <?php echo htmlspecialchars($materia['nombreMateria']); ?>
                                                                    </td>
                                                                    <?php $first_row = false; ?>
                                                                <?php endif; ?>

                                                                <td><?php echo htmlspecialchars($tarea['titulo']); ?></td>
                                                                <td class="calificacion"><?php echo isset($tarea['calificacion']) ? number_format($tarea['calificacion'], 2) : '-'; ?></td>
                                                                <td>
                                                                    <?php if ($tarea['idEntrega']): ?>
                                                                        <span class="badge estado" style="background-color: <?php echo isset($tarea['calificacion']) ? '#38b000' : '#ffaa00'; ?>; color: white;">
                                                                            <?php echo isset($tarea['calificacion']) ? 'Calificada' : 'Entregada'; ?>
                                                                        </span>
                                                                    <?php else: ?>
                                                                        <span class="badge estado" style="background-color: #d00000; color: white;">No entregada</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                            </tr>
                                                        <?php endwhile; ?>
                                                    <?php else: ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($materia['nombreMateria']); ?></td>
                                                            <td colspan="3">No hay tareas asignadas para esta materia</td>
                                                        </tr>
                                                    <?php endif; ?>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="4">No tienes materias asignadas en este grupo</td>
                                                </tr>
                                            <?php endif; ?>

                                            <?php
                                            // Ensure all subjects are displayed, even if no tasks are assigned
                                            $sql_all_materias = "SELECT m.idMateria, m.nombreMateria
                                                               FROM materias m
                                                               WHERE m.idGrupo = ? AND m.id_maestro = ?
                                                               ORDER BY m.nombreMateria";
                                            $stmt = $conexion->prepare($sql_all_materias);
                                            $stmt->bind_param("si", $idGrupo, $id_usuario);
                                            $stmt->execute();
                                            $resultado_all_materias = $stmt->get_result();

                                            while ($materia = $resultado_all_materias->fetch_assoc()):
                                                $idMateria = $materia['idMateria'];
                                                $sql_check_tareas = "SELECT COUNT(*) as tarea_count
                                                                     FROM tareas t
                                                                     WHERE t.idMateria = ?
                                                                     AND t.idGrupo = ?";
                                                $stmt_check = $conexion->prepare($sql_check_tareas);
                                                $stmt_check->bind_param("is", $idMateria, $idGrupo);
                                                $stmt_check->execute();
                                                $resultado_check = $stmt_check->get_result();
                                                $tarea_count = $resultado_check->fetch_assoc()['tarea_count'];

                                                if ($tarea_count == 0): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($materia['nombreMateria']); ?></td>
                                                        <td colspan="3">No hay tareas asignadas para esta materia</td>
                                                    </tr>
                                                <?php endif; ?>
                                            <?php endwhile; ?>

                                            <tr class="promedio-row">
                                                <td colspan="2" style="text-align: right; font-weight: bold;">Promedio General:</td>
                                                <td colspan="2" class="promedio-cell <?php echo $promedio_class; ?>">
                                                    <?php echo $promedio ? number_format($promedio, 2) : "N/A"; ?>
                                                    <?php if ($promedio && $promedio <= 5.0): ?>
                                                        <span class="alert-icon" title="Promedio bajo"><i class="fas fa-exclamation-triangle"></i></span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-user-graduate"></i>
                                <p>No hay estudiantes en este grupo</p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <p>No tienes grupos asignados</p>
                </div>
            <?php endif; ?>
        </div>

        <div id="calificar-tareas" class="tab-content">
            <div class="calificar-container">
                <h2><i class="fas fa-edit"></i> Calificar Tareas</h2>

                <div class="form-row">
                    <div class="form-group">
                        <label for="grupo-calificar">Grupo:</label>
                        <select id="grupo-calificar" name="grupo" class="form-control-pj" required disabled>
                            <option value="">Seleccione un grupo</option>
                            <?php if ($resultado_grupos && $resultado_grupos->num_rows > 0): ?>
                                <?php $resultado_grupos->data_seek(0); while ($grupo = $resultado_grupos->fetch_assoc()): ?>
                                    <option value="<?php echo htmlspecialchars($grupo['idGrupo']); ?>"
                                        <?php echo ($primer_grupo && $grupo['idGrupo'] == $primer_grupo['idGrupo']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($grupo['idGrupo']); ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="estudiante-calificar">Estudiante:</label>
                        <select id="estudiante-calificar" name="idEstudiante" class="form-control-pj" required>
                            <option value="">Seleccione un estudiante</option>
                            <?php if (!empty($estudiantes)): ?>
                                <?php foreach ($estudiantes as $estudiante): ?>
                                    <option value="<?php echo htmlspecialchars($estudiante['idEstudiante']); ?>">
                                        <?php echo htmlspecialchars($estudiante['apellidos'] . ', ' . $estudiante['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>

                <div id="tareas-pendientes">
                    <div class="empty-state">
                        <i class="fas fa-tasks"></i>
                        <p>Seleccione un estudiante para ver las tareas pendientes de calificación</p>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <script src="../html/JS/botonPerfil.js"></script>
    <script>
        // Función para cambiar entre pestañas
        function openTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });

            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });

            document.getElementById(tabId).classList.add('active');
            event.currentTarget.classList.add('active');
        }

        // Cargar estudiantes cuando se selecciona un grupo
        document.getElementById('grupo-calificar').addEventListener('change', function() {
            const grupoId = this.value;
            const estudianteSelect = document.getElementById('estudiante-calificar');

            if (grupoId) {
                estudianteSelect.innerHTML = '<option value="">Cargando estudiantes...</option>';

                fetch(`?action=getEstudiantes&grupo=${encodeURIComponent(grupoId)}`)
                    .then(response => response.json())
                    .then(data => {
                        estudianteSelect.innerHTML = '<option value="">Seleccione un estudiante</option>';
                        data.forEach(estudiante => {
                            estudianteSelect.innerHTML += `<option value="${estudiante.idEstudiante}">${estudiante.apellidos}, ${estudiante.nombre}</option>`;
                        });
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        estudianteSelect.innerHTML = '<option value="">Error al cargar estudiantes</option>';
                    });
            } else {
                estudianteSelect.innerHTML = '<option value="">Seleccione un estudiante</option>';
                document.getElementById('tareas-pendientes').innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-tasks"></i>
                        <p>Seleccione un estudiante para ver las tareas pendientes de calificación</p>
                    </div>`;
            }
        });

        // Cargar tareas pendientes cuando se selecciona un estudiante
        document.getElementById('estudiante-calificar').addEventListener('change', function() {
            const estudianteId = this.value;
            const grupoId = document.getElementById('grupo-calificar').value;
            const tareasPendientes = document.getElementById('tareas-pendientes');

            if (estudianteId && grupoId) {
                tareasPendientes.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-spinner fa-spin"></i>
                        <p>Cargando tareas pendientes...</p>
                    </div>`;

                fetch(`?action=getTareasPendientes&estudiante=${estudianteId}&grupo=${encodeURIComponent(grupoId)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.length > 0) {
                            let html = '<div class="tareas-list">';
                            data.forEach(tarea => {
                                html += `
                                <div class="tarea-card">
                                    <div class="tarea-header">
                                        <h4>${tarea.titulo}</h4>
                                        <span class="materia-badge">${tarea.nombreMateria}</span>
                                    </div>

                                    <div class="tarea-body">
                                        <p>${tarea.descripcion || 'Sin descripción'}</p>

                                        ${tarea.entrega ? `
                                            <div class="entrega-info">
                                                <p><strong>Fecha de entrega:</strong> ${formatDateTime(tarea.entrega.fechaEntrega)}</p>
                                                <p><strong>Archivo:</strong> <a href="../entregas/${tarea.entrega.rutaArchivo}" target="_blank" class="file-link">${tarea.entrega.rutaArchivo.split('_').pop()}</a></p>
                                            </div>
                                        ` : `

                                        `}

                                        <form class="calificacion-form" data-tarea="${tarea.idTarea}">
                                            <input type="hidden" name="idEstudiante" value="${estudianteId}">
                                            <input type="hidden" name="titulo" value="${tarea.titulo}">
                                            <input type="hidden" name="idGrupo" value="${grupoId}">
                                            <div class="form-row">
                                                <div class="form-group">
                                                    <label for="calificacion-${tarea.idTarea}">Calificación (0-10):</label>
                                                    <input type="number" id="calificacion-${tarea.idTarea}" name="calificacion"
                                                        class="form-control" min="0" max="10" step="0.1"
                                                        value="${tarea.calificacion || ''}" required>
                                                </div>

                                                <div class="form-group">
                                                    <label for="comentarios-${tarea.idTarea}">Comentarios:</label>
                                                    <textarea id="comentarios-${tarea.idTarea}" name="comentarios"
                                                        class="form-control-com" rows="2">${tarea.comentarios || ''}</textarea>
                                                </div>
                                            </div>

                                            <button type="submit" class="btn-calificar">
                                                <i class="fas fa-save"></i> Guardar Calificación
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                `;
                            });
                            html += '</div>';
                            tareasPendientes.innerHTML = html;

                            document.querySelectorAll('.calificacion-form').forEach(form => {
                                form.addEventListener('submit', function(e) {
                                    e.preventDefault();
                                    guardarCalificacion(this);
                                });
                            });
                        } else {
                            tareasPendientes.innerHTML = `
                                <div class="empty-state">
                                    <i class="fas fa-check-circle"></i>
                                    <p>No hay tareas pendientes de calificación para este estudiante</p>
                                </div>`;
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        tareasPendientes.innerHTML = `
                            <div class="empty-state">
                                <i class="fas fa-exclamation-triangle"></i>
                                <p>Error al cargar las tareas pendientes</p>
                            </div>`;
                    });
            } else {
                tareasPendientes.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-tasks"></i>
                        <p>Seleccione un estudiante para ver las tareas pendientes de calificación</p>
                    </div>`;
            }
        });

        // Función para formatear fecha y hora
        function formatDateTime(dateTimeStr) {
            if (!dateTimeStr) return 'No especificada';
            const date = new Date(dateTimeStr);
            return date.toLocaleString('es-MX');
        }

        // Función para guardar o actualizar la calificación
        function guardarCalificacion(form) {
            const formData = new FormData(form);
            const tareaId = form.dataset.tarea;
            const estudianteId = formData.get('idEstudiante');

            formData.append('action', 'guardarCalificacion');
            formData.append('idTarea', tareaId);
            formData.append('idEstudiante', estudianteId);

            const calificacion = parseFloat(formData.get('calificacion'));
            if (isNaN(calificacion) || calificacion < 0 || calificacion > 10) {
                alert('Por favor ingrese una calificación válida entre 0 y 10');
                return;
            }

            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
            submitBtn.disabled = true;

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;

                if (data.success) {
                    // Mostrar mensaje de éxito
                    showAlert('success', 'Calificación guardada correctamente');

                    // Actualizar la tabla de calificaciones
                    updateGradeTable(estudianteId, tareaId, calificacion);

                    // Recargar la lista de tareas pendientes
                    document.getElementById('estudiante-calificar').dispatchEvent(new Event('change'));
                } else {
                    showAlert('error', 'Error al guardar la calificación: ' + (data.message || 'Error desconocido'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                showAlert('error', 'Error en la conexión con el servidor');
            });
        }

        // Función para actualizar la tabla de calificaciones
        function updateGradeTable(estudianteId, tareaId, calificacion) {
            // Buscar la fila correspondiente en la tabla
            const row = document.querySelector(`tr[data-tarea="${tareaId}"][data-estudiante="${estudianteId}"]`);

            if (row) {
                // Actualizar la celda de calificación
                const gradeCell = row.querySelector('.calificacion');
                if (gradeCell) {
                    gradeCell.textContent = calificacion.toFixed(2);
                }

                // Actualizar el estado
                const statusCell = row.querySelector('.estado');
                if (statusCell) {
                    statusCell.textContent = 'Calificada';
                    statusCell.style.backgroundColor = '#38b000';
                }

                // Recalcular el promedio
                updateAverage(estudianteId, row.closest('table'));
            }
        }

        // Función para recalcular el promedio
        function updateAverage(estudianteId, table) {
            let sum = 0;
            let count = 0;

            // Sumar todas las calificaciones numéricas
            table.querySelectorAll(`tr[data-estudiante="${estudianteId}"] .calificacion`).forEach(cell => {
                const grade = parseFloat(cell.textContent);
                if (!isNaN(grade)) {
                    sum += grade;
                    count++;
                }
            });

            // Actualizar el promedio
            const averageCell = table.querySelector('.promedio-cell');
            if (averageCell && count > 0) {
                const average = sum / count;
                averageCell.textContent = average.toFixed(2);

                // Actualizar clase CSS según el promedio
                if (average <= 5.0) {
                    averageCell.classList.add('promedio-bajo');

                    // Agregar icono de alerta si no existe
                    if (!averageCell.querySelector('.alert-icon')) {
                        averageCell.innerHTML += `<span class="alert-icon" title="Promedio bajo"><i class="fas fa-exclamation-triangle"></i></span>`;
                    }
                } else {
                    averageCell.classList.remove('promedio-bajo');
                    const alertIcon = averageCell.querySelector('.alert-icon');
                    if (alertIcon) {
                        alertIcon.remove();
                    }
                }

                // Verificar promedios bajos después de actualizar
                checkLowAverages();
            }
        }

        // Función para mostrar alertas
        function showAlert(type, message) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type}`;
            alertDiv.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                ${message}
            `;

            document.body.appendChild(alertDiv);

            setTimeout(() => {
                alertDiv.remove();
            }, 3000);
        }

        // Función para verificar promedios bajos y enviar avisos automáticamente
        function checkLowAverages() {
            const promediosBajos = [];

            // Buscar todos los estudiantes con promedio bajo
            document.querySelectorAll('.promedio-cell').forEach(cell => {
                const promedio = parseFloat(cell.textContent);
                if (promedio <= 5.0) {
                    const estudianteRow = cell.closest('.estudiante-section');
                    const estudianteId = estudianteRow.id.split('-')[1];
                    const estudianteNombre = estudianteRow.querySelector('.estudiante-title').textContent.trim();
                    const grupoId = estudianteRow.closest('.grupo-card').querySelector('.grupo-title').textContent.replace('Grupo:', '').trim();

                    promediosBajos.push({
                        id: estudianteId,
                        nombre: estudianteNombre,
                        grupo: grupoId,
                        promedio: promedio
                    });

                    // Enviar aviso automáticamente
                    sendSingleParentNotification(estudianteId);
                }
            });

            // Si hay promedios bajos, mostrar alerta
            if (promediosBajos.length > 0) {
                showLowAverageAlert(promediosBajos);
            }
        }

        // Función para mostrar la alerta de promedios bajos
        function showLowAverageAlert(estudiantes) {
            // Verificar si ya hay un modal abierto
            if (document.querySelector('.modal-alerta')) {
                return;
            }

            const modal = document.createElement('div');
            modal.className = 'modal-alerta';
            modal.innerHTML = `
                <div class="modal-content">
                    <div class="modal-header">
                        <h3><i class="fas fa-exclamation-triangle"></i> Alertas de Rendimiento</h3>
                        <button class="close-modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <p>Los siguientes estudiantes tienen un promedio por debajo del 50%:</p>
                        ${estudiantes.map(est => `
                            <div class="estudiante-alerta">
                                <strong>${est.nombre}</strong> (Grupo ${est.grupo})<br>
                                Promedio actual: ${est.promedio.toFixed(2)}/10
                            </div>
                        `).join('')}
                    </div>
                    <div class="modal-footer">
                        <button class="btn-cerrar-modal">Cerrar</button>
                    </div>
                </div>
            `;

            document.body.appendChild(modal);
            modal.style.display = 'flex';

            // Event listeners para los botones del modal
            modal.querySelector('.close-modal').addEventListener('click', () => {
                modal.remove();
            });

            modal.querySelector('.btn-cerrar-modal').addEventListener('click', () => {
                modal.remove();
            });
        }

        // Función para enviar notificación individual automáticamente
        function sendSingleParentNotification(estudianteId) {
            fetch('enviar_aviso.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    idEstudiante: estudianteId,
                    mensaje: "El estudiante tiene un promedio por debajo del 50%.",
                    idUsuario: <?php echo $_SESSION['idUsuario']; ?>,
                    idInstitucion: 1,
                    idGrupo: document.getElementById('grupo-calificar').value,
                    prioridad: 'alta'
                })
            })
            .then(response => response.json())
            .then(data => {
                console.log('Notificación enviada:', data);
            })
            .catch(error => {
                console.error('Error al enviar notificación:', error);
            });
        }

        // Cargar tareas pendientes para el grupo y estudiante por defecto
        document.addEventListener('DOMContentLoaded', function() {
            const grupoId = document.getElementById('grupo-calificar').value;
            const estudianteId = document.getElementById('estudiante-calificar').value;

            if (grupoId && estudianteId) {
                document.getElementById('estudiante-calificar').dispatchEvent(new Event('change'));
            }

            // Verificar promedios bajos al cargar la página
            setTimeout(checkLowAverages, 1500);
        });

        // Manejar el evento de clic en el botón de descarga
        document.querySelectorAll('.btn-descargar').forEach(button => {
            button.addEventListener('click', function() {
                const estudianteId = this.dataset.estudiante;
                window.location.href = `?action=descargarPromedios&estudiante=${estudianteId}`;
            });
        });
    </script>
</body>
</html>

<?php
$conexion->close();
?>
