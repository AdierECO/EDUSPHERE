<?php
include 'Conexion.php';

// Configurar encabezados para evitar caché
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Función para redireccionar con mensaje de error
function redirectWithError($errorCode) {
    header("Location: editarGrupo.php?id=" . $_GET['id'] . "&error=" . urlencode($errorCode));
    exit();
}

// Verificar si se proporcionó un ID de grupo válido
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: verGrupos.php?error=id_no_proporcionado');
    exit();
}

$idGrupoActual = $_GET['id'];

// Obtener los datos del grupo
$sqlGrupo = "SELECT idGrupo, id_maestro FROM grupos WHERE idGrupo = ?";
$stmtGrupo = $conexion->prepare($sqlGrupo);
$stmtGrupo->bind_param("s", $idGrupoActual);

if (!$stmtGrupo->execute()) {
    header('Location: verGrupos.php?error=error_consulta');
    exit();
}

$resultGrupo = $stmtGrupo->get_result();

if ($resultGrupo->num_rows === 0) {
    header('Location: verGrupos.php?error=grupo_no_encontrado');
    exit();
}

$rowGrupo = $resultGrupo->fetch_assoc();
$idMaestroActual = $rowGrupo['id_maestro'];

// Obtener la lista de maestros
$sqlMaestros = "SELECT idUsuario, nombre, apellidos FROM Usuarios WHERE rol = 'Maestro'";
$resultMaestros = $conexion->query($sqlMaestros);

// Procesar el formulario de edición
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar y sanitizar datos
    $idGrupoNuevo = trim($_POST['idGrupo']);
    $idMaestro = (int)$_POST['idMaestro'];

    // Validaciones
    if (empty($idGrupoNuevo)) {
        redirectWithError('id_grupo_vacio');
    }

    if (strlen($idGrupoNuevo) > 12) {
        redirectWithError('id_grupo_largo');
    }

    if (!preg_match('/^[A-Za-z0-9]+$/', $idGrupoNuevo)) {
        redirectWithError('id_grupo_invalido');
    }

    // Verificar si el nuevo ID de grupo ya existe (excepto para el actual)
    if ($idGrupoNuevo !== $idGrupoActual) {
        $sqlVerificarGrupo = "SELECT idGrupo FROM grupos WHERE idGrupo = ?";
        $stmtVerificarGrupo = $conexion->prepare($sqlVerificarGrupo);
        $stmtVerificarGrupo->bind_param("s", $idGrupoNuevo);
        
        if (!$stmtVerificarGrupo->execute()) {
            redirectWithError('error_verificacion');
        }
        
        if ($stmtVerificarGrupo->get_result()->num_rows > 0) {
            redirectWithError('grupo_existente');
        }
    }

    // Verificar que el maestro existe
    $sqlVerificarMaestro = "SELECT idUsuario FROM Usuarios WHERE idUsuario = ? AND rol = 'Maestro'";
    $stmtVerificarMaestro = $conexion->prepare($sqlVerificarMaestro);
    $stmtVerificarMaestro->bind_param("i", $idMaestro);
    
    if (!$stmtVerificarMaestro->execute()) {
        redirectWithError('error_verificacion');
    }
    
    if ($stmtVerificarMaestro->get_result()->num_rows === 0) {
        redirectWithError('maestro_no_existe');
    }

    // Iniciar transacción
    $conexion->begin_transaction();

    try {
        // 1. Actualizar el grupo
        $sqlUpdateGrupo = "UPDATE grupos SET idGrupo = ?, id_maestro = ? WHERE idGrupo = ?";
        $stmtUpdateGrupo = $conexion->prepare($sqlUpdateGrupo);
        $stmtUpdateGrupo->bind_param("sis", $idGrupoNuevo, $idMaestro, $idGrupoActual);
        
        if (!$stmtUpdateGrupo->execute()) {
            throw new Exception("Error al actualizar grupo: " . $stmtUpdateGrupo->error);
        }

        // 2. Actualizar estudiantes
        $sqlUpdateEstudiantes = "UPDATE estudiantes SET idGrupo = ? WHERE idGrupo = ?";
        $stmtUpdateEstudiantes = $conexion->prepare($sqlUpdateEstudiantes);
        $stmtUpdateEstudiantes->bind_param("ss", $idGrupoNuevo, $idGrupoActual);
        
        if (!$stmtUpdateEstudiantes->execute()) {
            throw new Exception("Error al actualizar estudiantes: " . $stmtUpdateEstudiantes->error);
        }

        // 3. Actualizar usuarios (si es necesario)
        $sqlUpdateUsuarios = "UPDATE usuarios SET idGrupo = ? WHERE idGrupo = ?";
        $stmtUpdateUsuarios = $conexion->prepare($sqlUpdateUsuarios);
        $stmtUpdateUsuarios->bind_param("ss", $idGrupoNuevo, $idGrupoActual);
        
        if (!$stmtUpdateUsuarios->execute()) {
            throw new Exception("Error al actualizar usuarios: " . $stmtUpdateUsuarios->error);
        }

        $conexion->commit();
        header('Location: verGrupos.php?edicion=exitosa');
        exit();
    } catch (Exception $e) {
        $conexion->rollback();
        error_log("Error al editar grupo: " . $e->getMessage());
        redirectWithError('error_actualizacion');
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Grupo</title>
    <link rel="stylesheet" href="../css/editar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .alert-error {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Editar Grupo</h1>
        
        <?php if (isset($_GET['error'])): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const errorMessages = {
                        'id_grupo_largo': 'El ID del grupo no puede tener más de 12 caracteres',
                        'grupo_existente': 'El ID del grupo ya existe',
                        'error_verificacion': 'Error al verificar los datos',
                        'error_actualizacion': 'Error al actualizar el grupo'
                    };
                    
                    const errorType = new URLSearchParams(window.location.search).get('error');
                    const errorMessage = errorMessages[errorType] || 'Ocurrió un error desconocido';
                    
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: errorMessage,
                        confirmButtonText: 'Aceptar',
                        willClose: () => {
                            history.replaceState(null, null, window.location.pathname + '?id=<?php echo $idGrupoActual; ?>');
                        }
                    });
                });
            </script>
        <?php endif; ?>

        <form method="POST" id="formEditarGrupo">
            <label for="idGrupo">ID Grupo:</label>
            <input type="text" name="idGrupo" id="idGrupo" 
                   value="<?php echo htmlspecialchars($idGrupoActual); ?>" 
                   required maxlength="12"
                   pattern="[A-Za-z0-9]+"
                   title="Solo letras y números, máximo 12 caracteres">

            <label for="idMaestro">Maestro Asignado:</label>
            <select name="idMaestro" id="idMaestro" required>
                <?php if ($resultMaestros->num_rows > 0): ?>
                    <?php while ($rowMaestro = $resultMaestros->fetch_assoc()): ?>
                        <option value="<?php echo $rowMaestro['idUsuario']; ?>"
                            <?php echo ($rowMaestro['idUsuario'] == $idMaestroActual) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($rowMaestro['nombre'] . ' ' . $rowMaestro['apellidos'] . ' (ID: ' . $rowMaestro['idUsuario'] . ')'); ?>
                        </option>
                    <?php endwhile; ?>
                <?php else: ?>
                    <option value="">No hay maestros disponibles</option>
                <?php endif; ?>
            </select>

            <div class="botones-container">
                <button type="submit">Guardar Cambios</button>
                <button type="button" class="btn-cancelar" onclick="window.location.href='verGrupos.php'">Cancelar</button>
            </div>
        </form>
    </div>
</body>

</html>