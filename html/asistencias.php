<?php
session_start();
require_once '../php/Conexion.php';

if (!isset($_SESSION['idUsuario'])) {
    header("Location: INICIO.html");
    exit();
}

// Obtener datos del docente
$idUsuario = $_SESSION['idUsuario'];
$sql = "SELECT nombre, apellidos, foto_perfil FROM Usuarios WHERE idUsuario = ?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $idUsuario);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $docente = $result->fetch_assoc();
    $nombreCompleto = $docente['nombre'] . ' ' . $docente['apellidos'];
    $fotoPerfil = !empty($docente['foto_perfil']) ? '../IMAGENES/Usuarios/' . $docente['foto_perfil'] : '../IMAGENES/Docente.jpg';
} else {
    $nombreCompleto = "Docente";
    $fotoPerfil = '../IMAGENES/Docente.jpg';
}

// Descargar reporte de asistencia
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['descargar_reporte'])) {
    $fechaReporte = htmlspecialchars($_POST['fecha_reporte']);

    // Primero obtenemos el grupo del docente
    $sql_grupo = "SELECT g.idGrupo FROM grupos g JOIN usuarios u ON g.id_maestro = u.idUsuario WHERE u.idUsuario = ?";
    $stmt = $conexion->prepare($sql_grupo);
    $stmt->bind_param("i", $idUsuario);
    $stmt->execute();
    $result_grupo = $stmt->get_result();
    
    if ($result_grupo->num_rows > 0) {
        $grupo = $result_grupo->fetch_assoc();
        $idGrupo = $grupo['idGrupo'];

        $sql_reporte = "SELECT e.nombre, e.apellidos, a.fecha, a.estadoAsistencia
                        FROM asistencias a
                        JOIN estudiantes e ON a.idEstudiante = e.idEstudiante
                        WHERE a.fecha = ? AND e.idGrupo = ?
                        ORDER BY e.nombre";
        $stmt = $conexion->prepare($sql_reporte);
        $stmt->bind_param("ss", $fechaReporte, $idGrupo);
        $stmt->execute();
        $resultado_reporte = $stmt->get_result();

        // Generar CSV
        $csv = "Nombre,Apellidos,Fecha,Estado\n";
        if ($resultado_reporte && $resultado_reporte->num_rows > 0) {
            while ($fila = $resultado_reporte->fetch_assoc()) {
                $csv .= "{$fila['nombre']},{$fila['apellidos']},{$fila['fecha']},{$fila['estadoAsistencia']}\n";
            }
        }

        // Descargar el archivo CSV
        header('Content-Type: text/csv');
        header("Content-Disposition: attachment; filename=reporte_asistencias_$fechaReporte.csv");
        echo $csv;
        exit();
    }
}

// Obtener estudiantes y asistencias del grupo del profesor
$sql_grupo = "SELECT g.idGrupo
              FROM grupos g
              JOIN usuarios u ON g.id_maestro = u.idUsuario
              WHERE u.idUsuario = ?";
$stmt = $conexion->prepare($sql_grupo);
$stmt->bind_param("i", $idUsuario);
$stmt->execute();
$resultado_grupo = $stmt->get_result();

if ($resultado_grupo->num_rows > 0) {
    $fila_grupo = $resultado_grupo->fetch_assoc();
    $idGrupo = $fila_grupo['idGrupo'];

    $sql_estudiantes = "SELECT idEstudiante, nombre, apellidos FROM estudiantes WHERE idGrupo = ?";
    $stmt = $conexion->prepare($sql_estudiantes);
    $stmt->bind_param("s", $idGrupo);
    $stmt->execute();
    $resultado_estudiantes = $stmt->get_result();

    $sql_asistencias = "SELECT a.idAsistencia, e.nombre, e.apellidos, a.fecha, a.estadoAsistencia
                        FROM asistencias a
                        JOIN estudiantes e ON a.idEstudiante = e.idEstudiante
                        WHERE e.idGrupo = ?
                        ORDER BY a.fecha DESC";
    $stmt = $conexion->prepare($sql_asistencias);
    $stmt->bind_param("s", $idGrupo);
    $stmt->execute();
    $resultado_asistencias = $stmt->get_result();
} else {
    $resultado_estudiantes = false;
    $resultado_asistencias = false;
}

// Procesar el formulario de registro de asistencia
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['registrar_asistencia'])) {
    $fecha = $_POST['fecha'];
    $asistencias = $_POST['asistencia'];

    // Verificar si ya existe registro para esta fecha
    $sql_verificar = "SELECT idAsistencia FROM asistencias WHERE fecha = ? AND idEstudiante = ?";
    $stmt_verificar = $conexion->prepare($sql_verificar);

    $sql_insertar = "INSERT INTO asistencias (idEstudiante, fecha, estadoAsistencia) VALUES (?, ?, ?)";
    $stmt_insertar = $conexion->prepare($sql_insertar);

    $sql_actualizar = "UPDATE asistencias SET estadoAsistencia = ? WHERE idEstudiante = ? AND fecha = ?";
    $stmt_actualizar = $conexion->prepare($sql_actualizar);

    foreach ($asistencias as $idEstudiante => $estado) {
        $stmt_verificar->bind_param("si", $fecha, $idEstudiante);
        $stmt_verificar->execute();
        $resultado_verificar = $stmt_verificar->get_result();

        if ($resultado_verificar->num_rows > 0) {
            // Actualizar registro existente
            $stmt_actualizar->bind_param("sis", $estado, $idEstudiante, $fecha);
            $stmt_actualizar->execute();
        } else {
            // Insertar nuevo registro
            $stmt_insertar->bind_param("iss", $idEstudiante, $fecha, $estado);
            $stmt_insertar->execute();
        }
    }

    echo "<script>alert('Asistencias registradas correctamente.');</script>";
    // Recargar la página para mostrar los nuevos datos
    echo "<script>window.location.href = 'asistencias.php';</script>";
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Asistencias</title>
    <link rel="stylesheet" href="../css/botonPerfil.css">
    <link rel="stylesheet" href="../css/Asistencias.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <header class="header">
        <img src="../IMAGENES/logo.gif" alt="EDUSPHERE" class="logo">
        <h2>ASISTENCIAS</h2>
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

    <main class="container">
        <h1>Registro de Asistencias</h1>

        <!-- Formulario para registrar asistencia -->
        <form method="POST" action="asistencias.php">
            <div class="form-group">
                <label for="fecha">Fecha:</label>
                <input type="date" id="fecha" name="fecha" required>
            </div>

            <h2>Lista de Estudiantes</h2>
            <table>
                <thead>
                    <tr>
                        <th>Estudiante</th>
                        <th>Asistencia</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($resultado_estudiantes && $resultado_estudiantes->num_rows > 0) {
                        while ($fila = $resultado_estudiantes->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($fila['nombre']) . " " . htmlspecialchars($fila['apellidos']) . "</td>";
                            echo "<td>";
                            echo "<div class='attendance-switch'>";

                            // Asistió (verde)
                            echo "<div class='switch-container'>";
                            echo "<label class='switch'>";
                            echo "<input type='radio' name='asistencia[" . $fila['idEstudiante'] . "]' value='Asistió' required>";
                            echo "<span class='slider'></span>";
                            echo "</label>";
                            echo "<span class='switch-label'>Asistió</span>";
                            echo "</div>";

                            // No asistió (rojo)
                            echo "<div class='switch-container'>";
                            echo "<label class='switch switch-ausencia'>";
                            echo "<input type='radio' name='asistencia[" . $fila['idEstudiante'] . "]' value='No asistió'>";
                            echo "<span class='slider'></span>";
                            echo "</label>";
                            echo "<span class='switch-label'>No asistió</span>";
                            echo "</div>";

                            echo "</div>"; // Cierra attendance-switch
                            echo "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='2'>No hay estudiantes registrados en este grupo.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>

            <button type="submit" name="registrar_asistencia" class="btn-submit">Registrar Asistencias</button>
        </form>

        <!-- Formulario para descargar reporte -->
        <form method="POST" action="asistencias.php">
            <div class="form-group">
                <label for="fecha_reporte">Fecha del Reporte:</label>
                <input type="date" id="fecha_reporte" name="fecha_reporte" required>
            </div>
            <button type="submit" name="descargar_reporte" class="btn-submit">Descargar Reporte</button>
        </form>

        <!-- Mostrar historial de asistencias -->
    </main>
    <script src="../html/JS/botonPerfil.js"></script>
</body>
</html>

<?php
$conexion->close();
?>