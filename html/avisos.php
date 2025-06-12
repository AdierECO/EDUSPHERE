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

// Obtener la lista de grupos asignados al profesor
$sql_grupos = "SELECT g.idGrupo
               FROM grupos g
               JOIN usuarios u ON g.id_maestro = u.idUsuario
               WHERE u.idUsuario = ?";
$stmt_grupos = $conexion->prepare($sql_grupos);
$stmt_grupos->bind_param("i", $idUsuario);
$stmt_grupos->execute();
$resultado_grupos = $stmt_grupos->get_result();

// Procesar el formulario de agregar aviso
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['agregar_aviso'])) {
    $mensaje = $_POST['mensaje'];
    $idGrupo = $_POST['idGrupo'];
    $prioridad = $_POST['prioridad'];

    $sql = "INSERT INTO avisos (mensaje, fecha_publicacion, idUsuario, idGrupo, prioridad)
            VALUES (?, NOW(), ?, ?, ?)";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("siss", $mensaje, $idUsuario, $idGrupo, $prioridad);

    if ($stmt->execute()) {
        echo "<script>alert('Aviso agregado correctamente.');</script>";
    } else {
        echo "<script>alert('Error al agregar el aviso.');</script>";
    }
}

// Procesar la eliminación de un aviso
if (isset($_GET['eliminar'])) {
    $id = $_GET['eliminar'];
    
    $sql = "DELETE FROM avisos WHERE id = ? AND idUsuario = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("ii", $id, $idUsuario);

    if ($stmt->execute()) {
        echo "<script>alert('Aviso eliminado correctamente.');</script>";
    } else {
        echo "<script>alert('Error al eliminar el aviso.');</script>";
    }
}

// Procesar la modificación de un aviso
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['modificar_aviso'])) {
    $id = $_POST['id'];
    $mensaje = $_POST['mensaje'];
    $idGrupo = $_POST['idGrupo'];
    $prioridad = $_POST['prioridad'];

    $sql = "UPDATE avisos
            SET mensaje = ?, idGrupo = ?, prioridad = ?
            WHERE id = ? AND idUsuario = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("sssii", $mensaje, $idGrupo, $prioridad, $id, $idUsuario);

    if ($stmt->execute()) {
        echo "<script>alert('Aviso modificado correctamente.');</script>";
    } else {
        echo "<script>alert('Error al modificar el aviso.');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Avisos</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/botonPerfil.css">
    <link rel="stylesheet" href="../css/Avisos.css">
</head>

<body>
    <header class="header">
        <img src="../IMAGENES/logo.gif" alt="EDUSPHERE" class="logo">
        <h2>AVISOS</h2>
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
        <h1>Agregar Nuevo Aviso</h1>
        <form method="POST" action="avisos.php">
            <input type="hidden" name="agregar_aviso">
            <div class="form-group">
                <label for="mensaje">Mensaje:</label>
                <textarea id="mensaje" name="mensaje" required></textarea>
            </div>
            <div class="form-group">
                <label for="idGrupo">Grupo:</label>
                <select id="idGrupo" name="idGrupo" required>
                    <?php
                    if ($resultado_grupos->num_rows > 0) {
                        while ($fila = $resultado_grupos->fetch_assoc()) {
                            echo "<option value='" . htmlspecialchars($fila['idGrupo']) . "'>" . htmlspecialchars($fila['idGrupo']) . "</option>";
                        }
                    } else {
                        echo "<option value=''>No hay grupos disponibles</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="form-group">
                <label for="prioridad">Prioridad:</label>
                <select id="prioridad" name="prioridad" required>
                    <option value="baja">Baja (Azul)</option>
                    <option value="media">Media (Amarillo)</option>
                    <option value="alta">Alta (Rojo)</option>
                </select>
            </div>
            <button type="submit" class="btn-submit">Agregar Aviso</button>
        </form>

        <div class="avisos-lista">
            <h2>Avisos Publicados</h2>
            <?php
            // Obtener los avisos de la base de datos
            $sql = "SELECT * FROM avisos WHERE idUsuario = ?";
            $stmt = $conexion->prepare($sql);
            $stmt->bind_param("i", $idUsuario);
            $stmt->execute();
            $resultado = $stmt->get_result();

            if ($resultado->num_rows > 0) {
                while ($fila = $resultado->fetch_assoc()) {
                    echo "<div class='aviso-item' data-prioridad='" . htmlspecialchars($fila['prioridad']) . "'>";
                    echo "<p>" . htmlspecialchars($fila['mensaje']) . "</p>";
                    echo "<small>Publicado el: " . htmlspecialchars($fila['fecha_publicacion']) . " - Prioridad: " . ucfirst(htmlspecialchars($fila['prioridad'])) . "</small>";
                    echo "<div class='acciones'>";
                    echo "<a href='avisos.php?eliminar=" . $fila['id'] . "' onclick='return confirm(\"¿Estás seguro de eliminar este aviso?\");'>Eliminar</a>";
                    echo "<a href='#' onclick='mostrarFormularioEdicion(" . $fila['id'] . ", \"" . addslashes(htmlspecialchars($fila['mensaje'])) . "\", \"" . htmlspecialchars($fila['idGrupo']) . "\", \"" . htmlspecialchars($fila['prioridad']) . "\");'>Modificar</a>";
                    echo "</div>";
                    echo "</div>";
                }
            } else {
                echo "<p>No hay avisos publicados.</p>";
            }
            ?>
        </div>

        <!-- Formulario de edición (oculto por defecto) -->
        <div id="formulario-edicion" style="display: none;">
            <h2>Modificar Aviso</h2>
            <form method="POST" action="avisos.php">
                <input type="hidden" name="modificar_aviso">
                <input type="hidden" id="id-aviso" name="id">
                <div class="form-group">
                    <label for="mensaje-edicion">Mensaje:</label>
                    <textarea id="mensaje-edicion" name="mensaje" required></textarea>
                </div>
                <div class="form-group">
                    <label for="idGrupo-edicion">Grupo:</label>
                    <select id="idGrupo-edicion" name="idGrupo" required>
                        <?php
                        // Re-ejecutar la consulta para obtener los grupos
                        $resultado_grupos->data_seek(0);
                        if ($resultado_grupos->num_rows > 0) {
                            while ($fila = $resultado_grupos->fetch_assoc()) {
                                echo "<option value='" . htmlspecialchars($fila['idGrupo']) . "'>" . htmlspecialchars($fila['idGrupo']) . "</option>";
                            }
                        } else {
                            echo "<option value=''>No hay grupos disponibles</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="prioridad-edicion">Prioridad:</label>
                    <select id="prioridad-edicion" name="prioridad" required>
                        <option value="baja">Baja (Verde)</option>
                        <option value="media">Media (Naranja)</option>
                        <option value="alta">Alta (Rojo)</option>
                    </select>
                </div>
                <button type="submit" class="btn-submit">Guardar Cambios</button>
                <button type="button" onclick="ocultarFormularioEdicion();" class="btn-cancelar">Cancelar</button>
            </form>
        </div>
    </main>

    <script src="../html/JS/botonPerfil.js"></script>
    
    <script>
        // Función para mostrar el formulario de edición
        function mostrarFormularioEdicion(id, mensaje, idGrupo, prioridad) {
            document.getElementById('id-aviso').value = id;
            document.getElementById('mensaje-edicion').value = mensaje;
            document.getElementById('idGrupo-edicion').value = idGrupo;
            document.getElementById('prioridad-edicion').value = prioridad;
            document.getElementById('formulario-edicion').style.display = 'block';
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // Función para ocultar el formulario de edición
        function ocultarFormularioEdicion() {
            document.getElementById('formulario-edicion').style.display = 'none';
        }
    </script>
</body>
</html>

<?php
$conexion->close();
?>