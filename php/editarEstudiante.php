<?php
include '../php/Conexion.php';

// Verificar si se proporcionó un ID de estudiante válido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: verEstudiantes.php?error=id_invalido');
    exit();
}

$idEstudiante = (int)$_GET['id'];

// Obtener los datos del estudiante
$sqlEstudiante = "SELECT * FROM Estudiantes WHERE idEstudiante = ?";
$stmtEstudiante = $conexion->prepare($sqlEstudiante);
$stmtEstudiante->bind_param("i", $idEstudiante);
$stmtEstudiante->execute();
$resultEstudiante = $stmtEstudiante->get_result();

if ($resultEstudiante->num_rows === 0) {
    header('Location: verEstudiantes.php?error=estudiante_no_encontrado');
    exit();
}

$rowEstudiante = $resultEstudiante->fetch_assoc();
$nombreEstudiante = $rowEstudiante['nombre'];
$apellidosEstudiante = $rowEstudiante['apellidos'];
$correoEstudiante = $rowEstudiante['correo'];
$idGrupoEstudiante = $rowEstudiante['idGrupo'];

// Obtener la lista de grupos
$sqlGrupos = "SELECT * FROM grupos";
$resultGrupos = $conexion->query($sqlGrupos);

// Procesar el formulario de edición
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar y sanitizar los datos
    $nombre = filter_input(INPUT_POST, 'nombre', FILTER_SANITIZE_STRING);
    $apellidos = filter_input(INPUT_POST, 'apellidos', FILTER_SANITIZE_STRING);
    $correo = filter_input(INPUT_POST, 'correo', FILTER_SANITIZE_EMAIL);
    $idGrupo = $_POST['idGrupo'];
    $contrasena = trim($_POST['contrasena']);

    // Validaciones adicionales
    if (!preg_match("/^[A-Za-zÁÉÍÓÚáéíóúñÑ\s]+$/", $nombre)) {
        header('Location: editarEstudiante.php?id='.$idEstudiante.'&error=nombre_invalido');
        exit();
    }

    if (!preg_match("/^[A-Za-zÁÉÍÓÚáéíóúñÑ\s]+$/", $apellidos)) {
        header('Location: editarEstudiante.php?id='.$idEstudiante.'&error=apellidos_invalidos');
        exit();
    }

    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        header('Location: editarEstudiante.php?id='.$idEstudiante.'&error=correo_invalido');
        exit();
    }

    // Verificar si el correo ya existe (excepto para el estudiante actual)
    $sqlCheckEmail = "SELECT idEstudiante FROM Estudiantes WHERE correo = ? AND idEstudiante != ?";
    $stmtCheckEmail = $conexion->prepare($sqlCheckEmail);
    $stmtCheckEmail->bind_param("si", $correo, $idEstudiante);
    $stmtCheckEmail->execute();
    
    if ($stmtCheckEmail->get_result()->num_rows > 0) {
        header('Location: editarEstudiante.php?id='.$idEstudiante.'&error=correo_existente');
        exit();
    }

    // Verificar que el grupo exista
    $sqlCheckGrupo = "SELECT idGrupo FROM grupos WHERE idGrupo = ?";
    $stmtCheckGrupo = $conexion->prepare($sqlCheckGrupo);
    $stmtCheckGrupo->bind_param("s", $idGrupo);
    $stmtCheckGrupo->execute();
    
    if ($stmtCheckGrupo->get_result()->num_rows === 0) {
        header('Location: editarEstudiante.php?id='.$idEstudiante.'&error=grupo_invalido');
        exit();
    }

    // Si se proporcionó una nueva contraseña, validar y hashear
    if (!empty($contrasena)) {
        if (strlen($contrasena) < 8) {
            header('Location: editarEstudiante.php?id='.$idEstudiante.'&error=contrasena_corta');
            exit();
        }
        $contrasenaHash = password_hash($contrasena, PASSWORD_DEFAULT);
    }

    // Iniciar transacción
    $conexion->begin_transaction();

    try {
        // Actualizar datos del estudiante
        if (!empty($contrasena)) {
            $sqlUpdate = "UPDATE Estudiantes SET nombre = ?, apellidos = ?, correo = ?, idGrupo = ?, contrasena = ? WHERE idEstudiante = ?";
            $stmtUpdate = $conexion->prepare($sqlUpdate);
            $stmtUpdate->bind_param("sssssi", $nombre, $apellidos, $correo, $idGrupo, $contrasenaHash, $idEstudiante);
        } else {
            $sqlUpdate = "UPDATE Estudiantes SET nombre = ?, apellidos = ?, correo = ?, idGrupo = ? WHERE idEstudiante = ?";
            $stmtUpdate = $conexion->prepare($sqlUpdate);
            $stmtUpdate->bind_param("ssssi", $nombre, $apellidos, $correo, $idGrupo, $idEstudiante);
        }

        if (!$stmtUpdate->execute()) {
            throw new Exception("Error al actualizar el estudiante: " . $stmtUpdate->error);
        }

        $conexion->commit();
        header('Location: verEstudiantes.php?edicion=exitosa');
        exit();
    } catch (Exception $e) {
        $conexion->rollback();
        error_log("Error al editar estudiante ID $idEstudiante: " . $e->getMessage());
        header('Location: editarEstudiante.php?id='.$idEstudiante.'&error=error_actualizacion');
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Estudiante</title>
    <link rel="stylesheet" href="../css/editar.css">
</head>

<body>
    <div class="container">
        <h1>Editar Estudiante</h1>

        <form method="POST" id="formEditarEstudiante">
            <label for="nombre">Nombre:</label>
            <input type="text" name="nombre" id="nombre" value="<?php echo htmlspecialchars($nombreEstudiante); ?>" required
                   pattern="[A-Za-zÁÉÍÓÚáéíóúñÑ\s]+" title="Solo se permiten letras y espacios"
                   oninput="this.value = this.value.replace(/[^A-Za-zÁÉÍÓÚáéíóúñÑ\s]/g, '')">

            <label for="apellidos">Apellidos:</label>
            <input type="text" name="apellidos" id="apellidos" value="<?php echo htmlspecialchars($apellidosEstudiante); ?>" required
                   pattern="[A-Za-zÁÉÍÓÚáéíóúñÑ\s]+" title="Solo se permiten letras y espacios"
                   oninput="this.value = this.value.replace(/[^A-Za-zÁÉÍÓÚáéíóúñÑ\s]/g, '')">

            <label for="correo">Correo Electrónico:</label>
            <input type="email" name="correo" id="correo" value="<?php echo htmlspecialchars($correoEstudiante); ?>" required>

            <label for="idGrupo">Grupo:</label>
            <select name="idGrupo" id="idGrupo" required>
                <?php
                while ($rowGrupo = $resultGrupos->fetch_assoc()) {
                    $selected = ($rowGrupo['idGrupo'] == $idGrupoEstudiante) ? "selected" : "";
                    echo "<option value='{$rowGrupo['idGrupo']}' $selected>{$rowGrupo['idGrupo']}</option>";
                }
                ?>
            </select>

            <label for="contrasena">Nueva Contraseña:</label>
            <input type="password" name="contrasena" id="contrasena" placeholder="Dejar en blanco para mantener la contraseña actual"
                   minlength="8" title="La contraseña debe tener al menos 8 caracteres">

            <div class="botones-container">
                <button type="submit">Guardar Cambios</button>
                <button type="button" class="btn-cancelar" onclick="window.location.href='verEstudiantes.php'">Cancelar</button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
    // Mostrar mensajes de error al cargar la página
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        
        if (urlParams.has('error')) {
            const errorMessages = {
                'campos_vacios': 'Todos los campos son obligatorios',
                'correo_invalido': 'El formato del correo electrónico es inválido',
                'correo_existente': 'El correo electrónico ya está registrado',
                'estudiante_no_existe': 'El correo del estudiante no existe',
                'error_registro': 'Ocurrió un error al registrar el padre'
            };
            
            const errorType = urlParams.get('error');
            const errorMessage = errorMessages[errorType] || 'Ocurrió un error desconocido';
            
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: errorMessage,
                confirmButtonText: 'Aceptar',
                willClose: () => {
                    // Limpiar parámetros de la URL sin recargar
                    history.replaceState(null, null, window.location.pathname);
                }
            });
        }
    });
</script>
</body>

</html>