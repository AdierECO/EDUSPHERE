<?php
// Incluir el archivo de conexión
session_start();
include '../php/Conexion.php';

if (!isset($_SESSION['idUsuario']) || $_SESSION['rol'] != 'Admin') {
    header("Location: INICIO.html");
    exit();
}

if (!$conexion) {
    die("Error de conexión: " . mysqli_connect_error());
}

// Obtener la lista de instituciones
$sqlInstituciones = "SELECT idinstitucion, nombreinstitucion FROM Instituciones";
$resultInstituciones = $conexion->query($sqlInstituciones);

// Obtener la lista de grupos con sus maestros
$sqlGrupos = "SELECT g.idGrupo, u.idUsuario, CONCAT(u.nombre, ' ', u.apellidos) AS nombreMaestro 
              FROM Grupos g
              JOIN Usuarios u ON g.id_maestro = u.idUsuario";
$resultGrupos = $conexion->query($sqlGrupos);

// Obtener datos del usuario
$idUsuario = $_SESSION['idUsuario'];
$sql = "SELECT nombre, apellidos, foto_perfil FROM Usuarios WHERE idUsuario = ?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $idUsuario);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $usuario = $result->fetch_assoc();
    $nombreCompleto = $usuario['nombre'] . ' ' . $usuario['apellidos'];
    $fotoPerfil = !empty($usuario['foto_perfil']) ? '../IMAGENES/Usuarios/' . $usuario['foto_perfil'] : '../IMAGENES/Admin.jpg';
} else {
    $nombreCompleto = "Usuario";
    $fotoPerfil = '../IMAGENES/Admin.jpg';
}

// Preparar datos de grupos para JavaScript
$gruposData = [];
while ($row = $resultGrupos->fetch_assoc()) {
    $gruposData[$row['idGrupo']] = [
        'idMaestro' => $row['idUsuario'],
        'nombreMaestro' => $row['nombreMaestro']
    ];
}
$gruposJson = json_encode($gruposData);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Materia</title>
    <link rel="stylesheet" href="../css/registrarMA.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/botonPerfil.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <header class="header">
        <img src="../IMAGENES/logo.gif" alt="EDUSPHERE" class="logo">
        <h2>REGISTRAR MATERIAS</h2>
        <nav class="nav-bar">
            <a href="../html/Admin.php">Inicio</a>
            <a href="../php/verMaterias.php">Administración</a>
            <a href="" class="active">Añadir</a>
        </nav>
        <div class="user-info" id="userDropdown">
            <div class="user-display">
                <img src="<?= $fotoPerfil ?>" alt="Foto de perfil" class="profile-pic">
                <span><?= $nombreCompleto ?></span>
                <i class="fas fa-chevron-down dropdown-arrow"></i>
            </div>
            <div class="dropdown-menu" id="dropdownMenu">
                <a href="perfilUsuario.php"><i class="fas fa-user-circle"></i> Perfil</a>
                <a href="../php/cerrar_sesion.php"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a>
            </div>
        </div>
    </header>
    <div class="container">
        <h1>Registrar Nueva Materia</h1>
        <form method="POST" action="../php/registrarMaterias.php">
            <label for="nombreMateria">Nombre de la Materia:</label>
            <input type="text" id="nombreMateria" name="nombreMateria"
                minlength="3"
                maxlength="50"
                pattern="[A-Za-zÁ-ú0-9\s\-]+"
                title="Solo letras, números, espacios y guiones" required>

            <label for="idInstitucion">Institución:</label>
            <select id="idInstitucion" name="idInstitucion" required>
                <?php
                if ($resultInstituciones->num_rows > 0) {
                    while ($row = $resultInstituciones->fetch_assoc()) {
                        echo "<option value='{$row['idinstitucion']}'>{$row['nombreinstitucion']}</option>";
                    }
                } else {
                    echo "<option value=''>No hay instituciones disponibles</option>";
                }
                ?>
            </select>

            <label for="idGrupo">Grupo:</label>
            <select id="idGrupo" name="idGrupo" required>
                <option value="">Seleccione un grupo</option>
                <?php
                // Reset pointer para reutilizar el resultado
                $resultGrupos->data_seek(0);
                while ($row = $resultGrupos->fetch_assoc()) {
                    echo "<option value='{$row['idGrupo']}' data-maestro='{$row['idUsuario']}'>{$row['idGrupo']} - {$row['nombreMaestro']}</option>";
                }
                ?>
            </select>

            <!-- Campo oculto para el maestro -->
            <input type="hidden" id="id_maestro" name="id_maestro" value="">

            <button type="submit">Registrar Materia</button>
        </form>
    </div>
    <script src="../html/JS/botonPerfil.js"></script>
    <script>
        // Pasar datos de grupos a JavaScript
        const gruposData = <?= $gruposJson ?>;
        
        document.addEventListener('DOMContentLoaded', function() {
            const grupoSelect = document.getElementById('idGrupo');
            const maestroInput = document.getElementById('id_maestro');
            
            // Manejar cambio de grupo
            grupoSelect.addEventListener('change', function() {
                const grupoSeleccionado = this.value;
                if (grupoSeleccionado && gruposData[grupoSeleccionado]) {
                    maestroInput.value = gruposData[grupoSeleccionado].idMaestro;
                } else {
                    maestroInput.value = '';
                }
            });

            // Manejar mensajes de error
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('error')) {
                const errorMessages = {
                    'campos_vacios': 'Todos los campos son requeridos',
                    'nombre_invalido': 'El nombre debe tener entre 3 y 100 caracteres',
                    'materia_existente': 'Esta materia ya existe en el grupo seleccionado',
                    'institucion_no_existe': 'La institución seleccionada no existe',
                    'grupo_no_existe': 'El grupo seleccionado no existe',
                    'maestro_no_valido': 'El maestro seleccionado no es válido',
                    'error_registro': 'Error al registrar la materia',
                    'error_bd': 'Error en la base de datos'
                };

                const errorType = urlParams.get('error');
                const errorMessage = errorMessages[errorType] || 'Ocurrió un error desconocido';

                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: errorMessage,
                    confirmButtonColor: '#d33'
                }).then(() => {
                    window.history.replaceState({}, document.title, window.location.pathname);
                });
            }
        });
    </script>
</body>
</html>