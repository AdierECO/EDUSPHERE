<?php
session_start();
require_once '../php/Conexion.php';

// Verificar autenticación y rol de administrador
if (!isset($_SESSION['idUsuario']) || $_SESSION['rol'] != 'Admin') {
    header("Location: ../html/INICIO.html");
    exit();
}

// Verificar conexión a la base de datos
if (!$conexion) {
    die("Error de conexión: " . mysqli_connect_error());
}

// Obtener y sanitizar parámetros de filtrado
$filtroInstitucion = isset($_GET['institucion']) ? intval($_GET['institucion']) : '';
$filtroGrupo = isset($_GET['grupo']) ? $conexion->real_escape_string($_GET['grupo']) : '';
$filtroMaestro = isset($_GET['maestro']) ? intval($_GET['maestro']) : '';

// Consulta SQL base para obtener materias
$sql = "SELECT 
            m.idMateria, 
            m.nombreMateria, 
            i.nombreInstitucion, 
            g.idGrupo, 
            CONCAT(u.nombre, ' ', u.apellidos) AS nombreMaestro
        FROM materias m
        JOIN instituciones i ON m.idInstitucion = i.idInstitucion
        JOIN grupos g ON m.idGrupo = g.idGrupo
        JOIN usuarios u ON m.id_maestro = u.idUsuario
        WHERE 1=1";

// Añadir condiciones de filtrado

if (!empty($filtroGrupo)) {
    $sql .= " AND m.idGrupo = '$filtroGrupo'";
}
if (!empty($filtroMaestro)) {
    $sql .= " AND m.id_maestro = $filtroMaestro";
}

$sql .= " ORDER BY m.nombreMateria";

// Ejecutar consulta principal
$resultMaterias = $conexion->query($sql);
if (!$resultMaterias) {
    die("Error al obtener materias: " . $conexion->error);
}

// Obtener datos para los filtros
$instituciones = $conexion->query("SELECT idInstitucion, nombreInstitucion FROM instituciones ORDER BY nombreInstitucion");
$grupos = $conexion->query("SELECT DISTINCT idGrupo FROM grupos ORDER BY idGrupo");
$maestros = $conexion->query("SELECT idUsuario, nombre, apellidos FROM usuarios WHERE rol = 'Maestro' ORDER BY nombre");

// Obtener datos del administrador
$sqlAdmin = "SELECT nombre, apellidos, foto_perfil FROM usuarios WHERE idUsuario = ?";
$stmtAdmin = $conexion->prepare($sqlAdmin);
$stmtAdmin->bind_param("i", $_SESSION['idUsuario']);
$stmtAdmin->execute();
$resultAdmin = $stmtAdmin->get_result();

if ($resultAdmin->num_rows > 0) {
    $admin = $resultAdmin->fetch_assoc();
    $nombreCompleto = htmlspecialchars($admin['nombre'] . ' ' . $admin['apellidos']);
    $fotoPerfil = !empty($admin['foto_perfil']) ? '../IMAGENES/Usuarios/' . htmlspecialchars($admin['foto_perfil']) : '../IMAGENES/Admin.jpg';
} else {
    $nombreCompleto = "Administrador";
    $fotoPerfil = '../IMAGENES/Admin.jpg';
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Materias</title>
    <link rel="stylesheet" href="../css/registrarMA.css">
    <link rel="stylesheet" href="../css/Tabla.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/botonPerfil.css">
</head>

<body>
    <header class="header">
        <img src="../IMAGENES/logo.gif" alt="EDUSPHERE" class="logo">
        <h2>VER MATERIAS</h2>
        <nav class="nav-bar">
            <a href="../html/Admin.php">Inicio</a>
            <a href="" class="active">Administración</a>
            <a href="../html/registrarMaterias.php">Añadir</a>
        </nav>
        <div class="user-info" id="userDropdown">
            <div class="user-display">
                <img src="<?= $fotoPerfil ?>" alt="Foto de perfil" class="profile-pic">
                <span><?= $nombreCompleto ?></span>
                <i class="fas fa-chevron-down dropdown-arrow"></i>
            </div>
            <div class="dropdown-menu" id="dropdownMenu">
                <a href="../html/perfilUsuario.php"><i class="fas fa-user-circle"></i> Perfil</a>
                <a href="../php/cerrar_sesion.php"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a>
            </div>
        </div>
    </header>
    <div class="container">
        <h1>Lista de Materias</h1>

        <!-- Formulario de filtrado -->
        <form method="GET" action="" id="formFiltro">
            <label for="grupo">Filtrar por Grupo:</label>
            <select id="grupo" name="grupo" onchange="this.form.submit()">
                <option value="">Todos los grupos</option>
                <?php
                if ($grupos->num_rows > 0) {
                    while ($row = $grupos->fetch_assoc()) {
                        $selected = ($filtroGrupo == $row['idGrupo']) ? "selected" : "";
                        echo "<option value='{$row['idGrupo']}' $selected>{$row['idGrupo']}</option>";
                    }
                } else {
                    echo "<option value=''>No hay grupos disponibles</option>";
                }
                ?>
            </select>
        </form>

        <!-- Tabla de materias -->
        <table>
            <thead>
                <tr>
                    <th>ID Materia</th>
                    <th>Nombre de la Materia</th>
                    <th>Institución</th>
                    <th>Grupo</th>
                    <th>Maestro</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($resultMaterias->num_rows > 0): ?>
                    <?php while ($materia = $resultMaterias->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($materia['idMateria']) ?></td>
                            <td><?= htmlspecialchars($materia['nombreMateria']) ?></td>
                            <td><?= htmlspecialchars($materia['nombreInstitucion']) ?></td>
                            <td><?= htmlspecialchars($materia['idGrupo']) ?></td>
                            <td><?= htmlspecialchars($materia['nombreMaestro']) ?></td>
                            <td>
                                <a href="editarMaterias.php?id=<?= $materia['idMateria'] ?>" class="btn btn-update">
                                    <i class="fa fa-pencil-square"></i>
                                </a>
                                <a href="#" onclick="confirmarEliminacion(<?= $materia['idMateria'] ?>)" class="btn btn-remove">
                                    <i class="fa fa-trash-o"></i>
                                </a>
                            </td>

                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6">No se encontraron materias con los filtros seleccionados.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../html/JS/botonPerfil.js"></script>

    <script>
        // Mostrar mensajes al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);

            // Mensaje de registro exitoso
            if (urlParams.has('registro') && urlParams.get('registro') === 'exitoso') {
                Swal.fire({
                    icon: 'success',
                    title: '¡Registro exitoso!',
                    text: 'La materia ha sido registrada correctamente',
                    showConfirmButton: true,
                    timer: 3000
                }).then(() => {
                    // Limpiar parámetros de la URL
                    window.history.replaceState({}, document.title, window.location.pathname);
                });
            }

            // Mensaje de edición exitosa
            if (urlParams.has('edicion') && urlParams.get('edicion') === 'exitosa') {
                Swal.fire({
                    icon: 'success',
                    title: '¡Edición exitosa!',
                    text: 'Los datos de la materia han sido actualizados',
                    showConfirmButton: true,
                    timer: 3000
                }).then(() => {
                    window.history.replaceState({}, document.title, window.location.pathname);
                });
            }

            // Mensaje de eliminación exitosa
            if (urlParams.has('eliminacion') && urlParams.get('eliminacion') === 'exitosa') {
                Swal.fire({
                    icon: 'success',
                    title: '¡Eliminación exitosa!',
                    text: 'La materia ha sido eliminada correctamente',
                    showConfirmButton: true,
                    timer: 3000
                }).then(() => {
                    window.history.replaceState({}, document.title, window.location.pathname);
                });
            }

            // Mensajes de error
            if (urlParams.has('error')) {
                const errorMessages = {
                    'registro': 'Error al registrar la materia',
                    'edicion': 'Error al editar los datos de la materia',
                    'eliminacion': 'Error al eliminar la materia',
                    'general': 'Ocurrió un error inesperado'
                };

                const errorType = urlParams.get('error');
                const errorMessage = errorMessages[errorType] || errorMessages['general'];

                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: errorMessage,
                    confirmButtonText: 'Aceptar'
                }).then(() => {
                    window.history.replaceState({}, document.title, window.location.pathname);
                });
            }
        });

        // Función para confirmar eliminación
        function confirmarEliminacion(idMateria) {
            Swal.fire({
                title: '¿Estás seguro?',
                text: "¡Esta acción eliminará la materia y no podrá recuperarse!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `eliminarMaterias.php?id=${idMateria}`;
                }
            });
        }
    </script>
</body>

</html>