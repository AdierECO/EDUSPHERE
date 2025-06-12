<?php
session_start();
require_once '../php/Conexion.php';

if (!isset($_SESSION['idUsuario'])) {
    header("Location: INICIO.html");
    exit();
}

// Obtener el idUsuario de la sesión
$idUsuario = $_SESSION['idUsuario'];

// Obtener datos del docente
$sql = "SELECT nombre, apellidos, foto_perfil FROM Usuarios WHERE idUsuario = ?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $idUsuario);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $docente = $result->fetch_assoc();
    $nombreCompleto = htmlspecialchars($docente['nombre'] . ' ' . $docente['apellidos']);
    $fotoPerfil = !empty($docente['foto_perfil']) ? '../IMAGENES/Usuarios/' . htmlspecialchars($docente['foto_perfil']) : '../IMAGENES/Docente.jpg';
} else {
    $nombreCompleto = "Docente";
    $fotoPerfil = '../IMAGENES/Docente.jpg';
}

// Mensaje de estado
$mensaje = "";

// Procesar el formulario de agregar tarea
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['agregar_tarea'])) {
    $titulo = $_POST['titulo'];
    $descripcion = $_POST['descripcion'];
    $fecha_limite = $_POST['fecha_limite'];
    $idMateria = $_POST['idMateria'];
    $idGrupo = $_POST['idGrupo'];

    $sql = "INSERT INTO tareas (titulo, descripcion, fecha_publicacion, fecha_limite, idUsuario, idMateria, idGrupo)
            VALUES (?, ?, NOW(), ?, ?, ?, ?)";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("sssiis", $titulo, $descripcion, $fecha_limite, $idUsuario, $idMateria, $idGrupo);

    if ($stmt->execute()) {
        $mensaje = "Tarea agregada correctamente.";
    } else {
        $mensaje = "Error al agregar la tarea.";
    }
}

// Procesar la eliminación de una tarea
if (isset($_GET['eliminar'])) {
    $id = $_GET['eliminar'];
    $sql = "DELETE FROM tareas WHERE idTarea = ? AND idUsuario = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("ii", $id, $idUsuario);

    if ($stmt->execute()) {
        $mensaje = "Tarea eliminada correctamente.";
    } else {
        $mensaje = "Error al eliminar la tarea.";
    }
}

// Procesar la modificación de una tarea
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['modificar_tarea'])) {
    $id = $_POST['id'];
    $titulo = $_POST['titulo'];
    $descripcion = $_POST['descripcion'];
    $fecha_limite = $_POST['fecha_limite'];
    $idMateria = $_POST['idMateria'];
    $idGrupo = $_POST['idGrupo'];
    $calificacion = $_POST['calificacion'];

    $sql = "UPDATE tareas SET titulo = ?, descripcion = ?, fecha_limite = ?, idMateria = ?, idGrupo = ?, calificacion = ?
            WHERE idTarea = ? AND idUsuario = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("sssisiis", $titulo, $descripcion, $fecha_limite, $idMateria, $idGrupo, $calificacion, $id, $idUsuario);

    if ($stmt->execute()) {
        $mensaje = "Tarea modificada correctamente.";
    } else {
        $mensaje = "Error al modificar la tarea.";
    }
}

// Obtener el grupo asignado al docente para mostrar en los select
$idGrupo = null;
$sql = "SELECT g.idGrupo FROM grupos g JOIN usuarios u ON g.id_maestro = u.idUsuario WHERE u.idUsuario = ?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $idUsuario);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $fila = $result->fetch_assoc();
    $idGrupo = $fila['idGrupo'];
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agregar Tareas</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/botonPerfil.css">
    <link rel="stylesheet" href="../css/Tareas.css">
</head>

<body>
<header class="header">
    <img src="../IMAGENES/logo.gif" alt="EDUSPHERE" class="logo">
    <h2>TAREAS A SUBIR</h2>
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
    <h1>Agregar Nueva Tarea</h1>
    <?php if (!empty($mensaje)): ?>
        <div class="mensaje"><?= $mensaje ?></div>
    <?php endif; ?>
    <form method="POST" action="tareas.php">
        <input type="hidden" name="agregar_tarea">
        <div class="form-group">
            <label for="titulo">Título:</label>
            <input type="text" id="titulo" name="titulo" required>
        </div>
        <div class="form-group">
            <label for="descripcion">Descripción:</label>
            <textarea id="descripcion" name="descripcion" required></textarea>
        </div>
        <div class="form-group">
            <label for="fecha_limite">Fecha Límite:</label>
            <input type="date" id="fecha_limite" name="fecha_limite" required>
        </div>
        <div class="form-group">
            <label for="idMateria">Materia:</label>
            <select id="idMateria" name="idMateria" required>
                <?php
                if ($idGrupo) {
                    $sql = "SELECT * FROM materias WHERE idGrupo = ?";
                    $stmt = $conexion->prepare($sql);
                    $stmt->bind_param("s", $idGrupo);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows > 0) {
                        while ($fila = $result->fetch_assoc()) {
                            echo "<option value='" . htmlspecialchars($fila['idMateria']) . "'>" . htmlspecialchars($fila['nombreMateria']) . "</option>";
                        }
                    } else {
                        echo "<option value=''>No hay materias asignadas</option>";
                    }
                } else {
                    echo "<option value=''>No hay grupos asignados</option>";
                }
                ?>
            </select>
        </div>
        <div class="form-group">
            <label for="idGrupo">Grupo:</label>
            <select id="idGrupo" name="idGrupo" required>
                <?php
                if ($idGrupo) {
                    echo "<option value='" . htmlspecialchars($idGrupo) . "'>" . htmlspecialchars($idGrupo) . "</option>";
                } else {
                    echo "<option value=''>No hay grupos asignados</option>";
                }
                ?>
            </select>
        </div>
        <button type="submit" class="btn-submit">Agregar Tarea</button>
    </form>

    <div class="tareas-lista">
        <h2>Tareas Publicadas</h2>
        <?php
        $sql = "SELECT t.*, m.nombreMateria, g.idGrupo
                FROM tareas t
                LEFT JOIN materias m ON t.idMateria = m.idMateria
                LEFT JOIN grupos g ON t.idGrupo = g.idGrupo
                WHERE t.idUsuario = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("i", $idUsuario);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            while ($fila = $result->fetch_assoc()) {
                echo "<div class='tarea-item'>";
                echo "<h3>" . htmlspecialchars($fila['titulo']) . "</h3>";
                echo "<p>" . htmlspecialchars($fila['descripcion']) . "</p>";
                echo "<small>Materia: " . htmlspecialchars($fila['nombreMateria']) . " | Grupo: " . htmlspecialchars($fila['idGrupo']) . "</small>";
                echo "<small> Calificación: " . ($fila['calificacion'] !== null ? htmlspecialchars($fila['calificacion']) : 'Sin calificar') . "</small>";
                echo "<small> Fecha Límite: " . htmlspecialchars($fila['fecha_limite']) . "</small>";
                echo "<small> Publicado el: " . htmlspecialchars($fila['fecha_publicacion']) . "</small>";
                echo "<div class='acciones'>";
                echo "<a href='tareas.php?eliminar=" . htmlspecialchars($fila['idTarea']) . "' onclick='return confirm(\"¿Estás seguro de eliminar esta tarea?\");'>Eliminar</a>";
                echo "<a href='#' onclick='mostrarFormularioEdicion(" . htmlspecialchars($fila['idTarea']) . ", \"" . addslashes(htmlspecialchars($fila['titulo'])) . "\", \"" . addslashes(htmlspecialchars($fila['descripcion'])) . "\", \"" . htmlspecialchars($fila['fecha_limite']) . "\", " . htmlspecialchars($fila['idMateria']) . ", \"" . htmlspecialchars($fila['idGrupo']) . "\", " . ($fila['calificacion'] !== null ? htmlspecialchars($fila['calificacion']) : 'null') . ");'>Modificar</a>";
                echo "</div>";
                echo "</div>";
            }
        } else {
            echo "<p>No hay tareas publicadas.</p>";
        }
        ?>
    </div>

    <!-- Formulario de edición (oculto por defecto) -->
    <div id="formulario-edicion" style="display: none;">
        <h2>Modificar Tarea</h2>
        <form method="POST" action="tareas.php">
            <input type="hidden" name="modificar_tarea">
            <input type="hidden" id="id-tarea" name="id">
            <div class="form-group">
                <label for="titulo-edicion">Título:</label>
                <input type="text" id="titulo-edicion" name="titulo" required>
            </div>
            <div class="form-group">
                <label for="descripcion-edicion">Descripción:</label>
                <textarea id="descripcion-edicion" name="descripcion" required></textarea>
            </div>
            <div class="form-group">
                <label for="fecha_limite-edicion">Fecha Límite:</label>
                <input type="date" id="fecha_limite-edicion" name="fecha_limite" required>
            </div>
            <div class="form-group">
                <label for="idMateria-edicion">Materia:</label>
                <select id="idMateria-edicion" name="idMateria" required>
                    <?php
                    if ($idGrupo) {
                        $sql = "SELECT * FROM materias WHERE idGrupo = ?";
                        $stmt = $conexion->prepare($sql);
                        $stmt->bind_param("s", $idGrupo);
                        $stmt->execute();
                        $result = $stmt->get_result();

                        if ($result->num_rows > 0) {
                            while ($fila = $result->fetch_assoc()) {
                                echo "<option value='" . htmlspecialchars($fila['idMateria']) . "'>" . htmlspecialchars($fila['nombreMateria']) . "</option>";
                            }
                        }
                    }
                    ?>
                </select>
            </div>
            <div class="form-group">
                <label for="idGrupo-edicion">Grupo:</label>
                <select id="idGrupo-edicion" name="idGrupo" required>
                    <?php
                    if ($idGrupo) {
                        echo "<option value='" . htmlspecialchars($idGrupo) . "'>" . htmlspecialchars($idGrupo) . "</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="form-group">
                <label for="calificacion-edicion">Calificación:</label>
                <input type="number" id="calificacion-edicion" name="calificacion" step="0.01" min="0" max="100">
            </div>
            <button type="submit" class="btn-submit">Guardar Cambios</button>
            <button type="button" onclick="ocultarFormularioEdicion();" class="btn-regresar">Cancelar</button>
        </form>
    </div>
</main>

<script src="../html/JS/botonPerfil.js"></script>

<script>
    function mostrarFormularioEdicion(id, titulo, descripcion, fecha_limite, idMateria, idGrupo, calificacion) {
        document.getElementById('id-tarea').value = id;
        document.getElementById('titulo-edicion').value = titulo;
        document.getElementById('descripcion-edicion').value = descripcion;
        document.getElementById('fecha_limite-edicion').value = fecha_limite;
        document.getElementById('idMateria-edicion').value = idMateria;
        document.getElementById('idGrupo-edicion').value = idGrupo;
        document.getElementById('calificacion-edicion').value = calificacion !== 'null' ? calificacion : '';
        document.getElementById('formulario-edicion').style.display = 'block';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function ocultarFormularioEdicion() {
        document.getElementById('formulario-edicion').style.display = 'none';
    }
</script>
</body>
</html>

<?php
$conexion->close();
?>
