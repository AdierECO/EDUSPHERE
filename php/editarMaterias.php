<?php
include 'Conexion.php';

// Configurar para mostrar errores (solo en desarrollo)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Validar y obtener el ID de la materia
$idMateria = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$idMateria) {
    header('Location: verMaterias.php?error=id_invalido');
    exit();
}

// Obtener los datos de la materia
$sqlMateria = "SELECT * FROM Materias WHERE idMateria = ?";
$stmtMateria = $conexion->prepare($sqlMateria);
$stmtMateria->bind_param("i", $idMateria);
$stmtMateria->execute();
$resultMateria = $stmtMateria->get_result();

if ($resultMateria->num_rows === 0) {
    header('Location: verMaterias.php?error=materia_no_encontrada');
    exit();
}

$materia = $resultMateria->fetch_assoc();

// Obtener listas para los dropdowns
$sqlInstituciones = "SELECT idInstitucion, nombreInstitucion FROM Instituciones";
$resultInstituciones = $conexion->query($sqlInstituciones);

// Obtener grupos con información del maestro asignado
$sqlGrupos = "SELECT g.idGrupo, u.idUsuario, CONCAT(u.nombre, ' ', u.apellidos) AS nombreMaestro 
              FROM Grupos g
              LEFT JOIN Usuarios u ON g.id_maestro = u.idUsuario";
$resultGrupos = $conexion->query($sqlGrupos);

// Procesar el formulario de edición
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombreMateria = filter_input(INPUT_POST, 'nombreMateria', FILTER_SANITIZE_STRING);
    $idInstitucion = filter_input(INPUT_POST, 'idInstitucion', FILTER_VALIDATE_INT);
    $idGrupo = filter_input(INPUT_POST, 'idGrupo', FILTER_SANITIZE_STRING);
    $id_maestro = filter_input(INPUT_POST, 'id_maestro', FILTER_VALIDATE_INT);

    // Validaciones
    if (empty($nombreMateria) || empty($idInstitucion) || empty($idGrupo)) {
        header("Location: editarMaterias.php?id=$idMateria&error=campos_vacios");
        exit();
    }

    // Verificar si la combinación materia-grupo ya existe (excluyendo la actual)
    $sqlCheck = "SELECT idMateria FROM Materias WHERE nombreMateria = ? AND idGrupo = ? AND idMateria != ?";
    $stmtCheck = $conexion->prepare($sqlCheck);
    $stmtCheck->bind_param("ssi", $nombreMateria, $idGrupo, $idMateria);
    $stmtCheck->execute();
    
    if ($stmtCheck->get_result()->num_rows > 0) {
        header("Location: editarMaterias.php?id=$idMateria&error=materia_existente");
        exit();
    }

    // Consulta SQL para actualizar la materia
    $sqlUpdate = "
        UPDATE Materias 
        SET nombreMateria = ?, idInstitucion = ?, idGrupo = ?, id_maestro = ?
        WHERE idMateria = ?
    ";
    $stmtUpdate = $conexion->prepare($sqlUpdate);
    $stmtUpdate->bind_param("sisii", $nombreMateria, $idInstitucion, $idGrupo, $id_maestro, $idMateria);

    try {
        $stmtUpdate->execute();
        header("Location: verMaterias.php?edicion=exitosa");
        exit();
    } catch (mysqli_sql_exception $e) {
        error_log("Error al actualizar materia: " . $e->getMessage());
        $errorCode = ($e->getCode() == 1452) ? 'relacion_invalida' : 'error_actualizacion';
        header("Location: editarMaterias.php?id=$idMateria&error=$errorCode");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Materia</title>
    <link rel="stylesheet" href="../css/editar.css">
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>

<body>
    <div class="container">
        <h1>Editar Materia</h1>
        <form id="formEditarMateria" method="POST" action="">
            <input type="hidden" id="id_maestro" name="id_maestro" value="<?php echo $materia['id_maestro']; ?>">

            <label for="nombreMateria">Nombre de la Materia:</label>
            <input type="text" id="nombreMateria" name="nombreMateria" 
                   value="<?php echo htmlspecialchars($materia['nombreMateria']); ?>" 
                   required
                   minlength="3"
                   maxlength="100">

            <label for="idInstitucion">Institución:</label>
            <select id="idInstitucion" name="idInstitucion" required>
                <option value="">Seleccione una institución</option>
                <?php
                if ($resultInstituciones->num_rows > 0) {
                    $resultInstituciones->data_seek(0);
                    while ($row = $resultInstituciones->fetch_assoc()) {
                        $selected = ($row['idInstitucion'] == $materia['idInstitucion']) ? "selected" : "";
                        echo "<option value='{$row['idInstitucion']}' $selected>{$row['nombreInstitucion']}</option>";
                    }
                }
                ?>
            </select>

            <label for="idGrupo">Grupo:</label>
            <select id="idGrupo" name="idGrupo" required>
                <option value="">Seleccione un grupo</option>
                <?php
                if ($resultGrupos->num_rows > 0) {
                    $resultGrupos->data_seek(0);
                    while ($row = $resultGrupos->fetch_assoc()) {
                        $selected = ($row['idGrupo'] == $materia['idGrupo']) ? "selected" : "";
                        echo "<option value='{$row['idGrupo']}' data-maestro='{$row['idUsuario']}' $selected>{$row['idGrupo']} - {$row['nombreMaestro']}</option>";
                    }
                }
                ?>
            </select>

            <div class="botones-container">
                <button type="submit">Guardar Cambios</button>
                <button type="button" class="btn-cancelar" onclick="window.location.href='verMaterias.php'">Cancelar</button>
            </div>
        </form>
    </div>

    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const grupoSelect = document.getElementById('idGrupo');
            const maestroHidden = document.getElementById('id_maestro');
            const maestroDisplay = document.getElementById('maestroAsignado');

            // Actualizar maestro cuando se selecciona un grupo
            grupoSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                const maestroId = selectedOption.getAttribute('data-maestro');
                const maestroNombre = selectedOption.text.split('-')[1].trim();
                
                maestroHidden.value = maestroId;
                maestroDisplay.textContent = maestroNombre;
            });

            // Manejar mensajes de error
            const urlParams = new URLSearchParams(window.location.search);
            
            if (urlParams.has('error')) {
                const errorMessages = {
                    'id_invalido': 'ID de materia no válido',
                    'materia_no_encontrada': 'Materia no encontrada',
                    'campos_vacios': 'Todos los campos son requeridos',
                    'materia_existente': 'Esta materia ya existe en el grupo seleccionado',
                    'relacion_invalida': 'El grupo, institución o maestro seleccionado no existe',
                    'error_actualizacion': 'Error al actualizar la materia'
                };
                
                const errorType = urlParams.get('error');
                const errorMessage = errorMessages[errorType] || 'Ocurrió un error desconocido';
                
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: errorMessage,
                    confirmButtonColor: '#d33'
                });
            }
        });
    </script>
</body>
</html>