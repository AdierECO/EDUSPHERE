<?php
include '../php/Conexion.php';

if (isset($_GET['id'])) {
    $idPadre = (int)$_GET['id'];

    // Obtener los datos del padre
    $sqlPadre = "SELECT * FROM Padres WHERE idPadre = ?";
    $stmtPadre = $conexion->prepare($sqlPadre);
    $stmtPadre->bind_param("i", $idPadre);
    $stmtPadre->execute();
    $resultPadre = $stmtPadre->get_result();

    if ($resultPadre->num_rows > 0) {
        $rowPadre = $resultPadre->fetch_assoc();
        $nombrePadre = $rowPadre['nombre'];
        $apellidosPadre = $rowPadre['apellidos'];
        $correoPadre = $rowPadre['correo'];
        $correoEstudiante = $rowPadre['correoEstudiante'];
        
        // Obtener el ID del estudiante asociado
        $sqlEstudiante = "SELECT idEstudiante FROM Estudiantes WHERE correo = ?";
        $stmtEstudiante = $conexion->prepare($sqlEstudiante);
        $stmtEstudiante->bind_param("s", $correoEstudiante);
        $stmtEstudiante->execute();
        $resultEstudiante = $stmtEstudiante->get_result();
        
        $idEstudianteActual = ($resultEstudiante->num_rows > 0) ? $resultEstudiante->fetch_assoc()['idEstudiante'] : null;
    } else {
        header('Location: verPadres.php?error=padre_no_encontrado');
        exit();
    }
} else {
    header('Location: verPadres.php?error=id_no_proporcionado');
    exit();
}

// Procesar el formulario de edición
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar y sanitizar los datos
    $nombre = filter_input(INPUT_POST, 'nombre', FILTER_SANITIZE_STRING);
    $apellidos = filter_input(INPUT_POST, 'apellidos', FILTER_SANITIZE_STRING);
    $correo = filter_input(INPUT_POST, 'correo', FILTER_SANITIZE_EMAIL);
    $nuevoCorreoEstudiante = filter_input(INPUT_POST, 'correoEstudiante', FILTER_SANITIZE_EMAIL);
    $contrasena = trim($_POST['contrasena']);

    // Validaciones adicionales
    $errores = [];
    
    if (!preg_match("/^[A-Za-zÁÉÍÓÚáéíóúñÑ\s]+$/", $nombre)) {
        $errores[] = "nombre_invalido";
    }

    if (!preg_match("/^[A-Za-zÁÉÍÓÚáéíóúñÑ\s]+$/", $apellidos)) {
        $errores[] = "apellidos_invalidos";
    }

    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $errores[] = "correo_invalido";
    }

    if (!filter_var($nuevoCorreoEstudiante, FILTER_VALIDATE_EMAIL)) {
        $errores[] = "correo_estudiante_invalido";
    }

    // Si hay errores, redirigir
    if (!empty($errores)) {
        header('Location: editarPadre.php?id='.$idPadre.'&error='.$errores[0]);
        exit();
    }

    // Verificar si el nuevo correo de estudiante existe
    $sqlCheckEstudiante = "SELECT idEstudiante FROM Estudiantes WHERE correo = ?";
    $stmtCheckEstudiante = $conexion->prepare($sqlCheckEstudiante);
    $stmtCheckEstudiante->bind_param("s", $nuevoCorreoEstudiante);
    $stmtCheckEstudiante->execute();
    $resultCheckEstudiante = $stmtCheckEstudiante->get_result();
    
    if ($resultCheckEstudiante->num_rows === 0) {
        header('Location: editarPadre.php?id='.$idPadre.'&error=estudiante_no_existe');
        exit();
    }
    
    $nuevoIdEstudiante = $resultCheckEstudiante->fetch_assoc()['idEstudiante'];

    // Si se proporcionó una nueva contraseña, validar y hashear
    if (!empty($contrasena)) {
        if (strlen($contrasena) < 8) {
            header('Location: editarPadre.php?id='.$idPadre.'&error=contrasena_corta');
            exit();
        }
        $contrasenaHash = password_hash($contrasena, PASSWORD_DEFAULT);
    }

    // Iniciar transacción
    $conexion->begin_transaction();

    try {
        // 1. Actualizar datos del padre
        if (!empty($contrasena)) {
            $sqlUpdatePadre = "UPDATE Padres SET nombre = ?, apellidos = ?, correo = ?, correoEstudiante = ?, contrasena = ? WHERE idPadre = ?";
            $stmtUpdatePadre = $conexion->prepare($sqlUpdatePadre);
            $stmtUpdatePadre->bind_param("sssssi", $nombre, $apellidos, $correo, $nuevoCorreoEstudiante, $contrasenaHash, $idPadre);
        } else {
            $sqlUpdatePadre = "UPDATE Padres SET nombre = ?, apellidos = ?, correo = ?, correoEstudiante = ? WHERE idPadre = ?";
            $stmtUpdatePadre = $conexion->prepare($sqlUpdatePadre);
            $stmtUpdatePadre->bind_param("ssssi", $nombre, $apellidos, $correo, $nuevoCorreoEstudiante, $idPadre);
        }

        if (!$stmtUpdatePadre->execute()) {
            throw new Exception("Error al actualizar el padre: " . $stmtUpdatePadre->error);
        }

        // 2. Actualizar la relación en estudiantes_padres si el correo del estudiante cambió
        if ($nuevoCorreoEstudiante != $correoEstudiante) {
            // Eliminar la relación anterior si existe
            $sqlDeleteRelacion = "DELETE FROM estudiantes_padres WHERE idPadre = ?";
            $stmtDeleteRelacion = $conexion->prepare($sqlDeleteRelacion);
            $stmtDeleteRelacion->bind_param("i", $idPadre);
            
            if (!$stmtDeleteRelacion->execute()) {
                throw new Exception("Error al eliminar relación anterior: " . $stmtDeleteRelacion->error);
            }

            // Crear la nueva relación
            $sqlInsertRelacion = "INSERT INTO estudiantes_padres (idEstudiante, idPadre) VALUES (?, ?)";
            $stmtInsertRelacion = $conexion->prepare($sqlInsertRelacion);
            $stmtInsertRelacion->bind_param("ii", $nuevoIdEstudiante, $idPadre);
            
            if (!$stmtInsertRelacion->execute()) {
                throw new Exception("Error al crear nueva relación: " . $stmtInsertRelacion->error);
            }
        }

        $conexion->commit();
        header('Location: verPadres.php?edicion=exitosa');
        exit();
    } catch (Exception $e) {
        $conexion->rollback();
        error_log("Error al editar padre ID $idPadre: " . $e->getMessage());
        header('Location: editarPadre.php?id='.$idPadre.'&error=error_actualizacion');
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Padre</title>
    <link rel="stylesheet" href="../css/editar.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>
<body>
    <div class="container">
        <h1>Editar Padre</h1>

        <form method="POST" id="formEditarPadre">
            <label for="nombre">Nombre:</label>
            <input type="text" name="nombre" id="nombre" value="<?php echo htmlspecialchars($nombrePadre); ?>" required
                   pattern="[A-Za-zÁÉÍÓÚáéíóúñÑ\s]+" title="Solo se permiten letras y espacios">

            <label for="apellidos">Apellidos:</label>
            <input type="text" name="apellidos" id="apellidos" value="<?php echo htmlspecialchars($apellidosPadre); ?>" required
                   pattern="[A-Za-zÁÉÍÓÚáéíóúñÑ\s]+" title="Solo se permiten letras y espacios">

            <label for="correo">Correo Electrónico:</label>
            <input type="email" name="correo" id="correo" value="<?php echo htmlspecialchars($correoPadre); ?>" required>

            <label for="correoEstudiante">Correo del Estudiante:</label>
            <input type="email" name="correoEstudiante" id="correoEstudiante" 
                   value="<?php echo htmlspecialchars($correoEstudiante); ?>" required>

            <label for="contrasena">Nueva Contraseña:</label>
            <input type="password" name="contrasena" id="contrasena" 
                   placeholder="Dejar en blanco para mantener la contraseña actual"
                   minlength="8" title="La contraseña debe tener al menos 8 caracteres">

            <div class="botones-container">
                <button type="submit">Guardar Cambios</button>
                <button type="button" class="btn-cancelar" onclick="window.location.href='verPadres.php'">Cancelar</button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        
        if (urlParams.has('error')) {
            const errorMessages = {
                'nombre_invalido': 'El nombre solo puede contener letras y espacios',
                'apellidos_invalidos': 'Los apellidos solo pueden contener letras y espacios',
                'correo_invalido': 'El formato del correo electrónico es inválido',
                'correo_estudiante_invalido': 'El formato del correo del estudiante es inválido',
                'estudiante_no_existe': 'El correo del estudiante no existe en el sistema',
                'contrasena_corta': 'La contraseña debe tener al menos 8 caracteres',
                'error_actualizacion': 'Ocurrió un error al actualizar los datos'
            };
            
            const errorType = urlParams.get('error');
            const errorMessage = errorMessages[errorType] || 'Ocurrió un error desconocido';
            
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: errorMessage,
                confirmButtonText: 'Aceptar',
                willClose: () => {
                    history.replaceState(null, null, window.location.pathname);
                }
            });
        }
    });
    </script>
</body>
</html>