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

// Obtener las materias asignadas al docente (usuario con rol 'Maestro')
$sql_materias = "SELECT m.idMateria, m.nombreMateria, m.idGrupo
                 FROM materias m
                 JOIN usuarios u ON m.id_maestro = u.idUsuario
                 WHERE u.idUsuario = ? AND u.rol = 'Maestro'";
$stmt_materias = $conexion->prepare($sql_materias);
$stmt_materias->bind_param("i", $idPadre);
$stmt_materias->execute();
$resultado_materias = $stmt_materias->get_result();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Materias y Alumnos Asignados</title>
    <link rel="stylesheet" href="../css/Docente.css">
    <link rel="stylesheet" href="../css/Materias.css">
    <link rel="stylesheet" href="../css/botonPerfil.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<header class="header">
        <img src="../IMAGENES/logo.gif" alt="EDUSPHERE" class="logo">
        <h2>MATERIAS</h2>
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
        <h1>Materias y Alumnos Asignados</h1>
        <div class="table-container">
            <?php
            if ($resultado_materias && $resultado_materias->num_rows > 0) {
                echo '<table>';
                echo '<thead>';
                echo '<tr>';
                echo '<th width="35%">Materia</th>';
                echo '<th width="15%">Grupo</th>';
                echo '<th width="50%">Estudiantes matriculados</th>';
                echo '</tr>';
                echo '</thead>';
                echo '<tbody>';

                while ($fila = $resultado_materias->fetch_assoc()) {
                    echo '<tr>';
                    echo '<td><strong>' . htmlspecialchars($fila['nombreMateria']) . '</strong></td>';
                    echo '<td>' . htmlspecialchars($fila['idGrupo']) . '</td>';

                    // Obtener los estudiantes del grupo
                    $idGrupo = $fila['idGrupo'];
                    $sql_estudiantes = "SELECT idEstudiante, nombre, apellidos
                                       FROM estudiantes
                                       WHERE idGrupo = ?";
                    $stmt_estudiantes = $conexion->prepare($sql_estudiantes);
                    $stmt_estudiantes->bind_param("s", $idGrupo); // Use 's' for string type
                    $stmt_estudiantes->execute();
                    $resultado_estudiantes = $stmt_estudiantes->get_result();

                    // Debugging: Check the value of idGrupo and the number of students fetched
                    echo '<!-- Debug: idGrupo = ' . htmlspecialchars($idGrupo) . ', Number of students = ' . $resultado_estudiantes->num_rows . ' -->';

                    echo '<td>';
                    if ($resultado_estudiantes && $resultado_estudiantes->num_rows > 0) {
                        echo '<table class="estudiantes-table">';
                        echo '<thead><tr><th>Nombre</th><th>Apellidos</th></tr></thead>';
                        echo '<tbody>';
                        while ($estudiante = $resultado_estudiantes->fetch_assoc()) {
                            echo '<tr>';
                            echo '<td>' . htmlspecialchars($estudiante['nombre']) . '</td>';
                            echo '<td>' . htmlspecialchars($estudiante['apellidos']) . '</td>';
                            echo '</tr>';
                        }
                        echo '</tbody>';
                        echo '</table>';
                    } else {
                        echo '<span class="no-data">No hay estudiantes matriculados</span>';
                    }
                    echo '</td>';

                    echo '</tr>';
                }

                echo '</tbody>';
                echo '</table>';
            } else {
                echo '<p class="no-data">No tienes materias asignadas actualmente</p>';
            }
            ?>
        </div>
    </main>
    <script src="../html/JS/botonPerfil.js"></script>
</body>
</html>

<?php
$conexion->close(); // Cierra la conexión
?>
