<?php
session_start();
require_once '../php/Conexion.php';

// Verificar si el padre está logueado
if (!isset($_SESSION['idUsuario']) || $_SESSION['rol'] != 'Admin') {
    header("Location: ../html/INICIO.html");
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
    $fotoPerfil = !empty($padre['foto_perfil']) ? '../IMAGENES/Usuarios/' . $padre['foto_perfil'] : '../IMAGENES/Admin.jpg';
} else {
    // Datos por defecto si no encuentra al padre
    $nombreCompleto = "Usuario";
    $fotoPerfil = '../IMAGENES/Admin.jpg';
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Padres</title>
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
        <h2>VER PADRES</h2>
        <nav class="nav-bar">
            <a href="../html/Admin.php">Inicio</a>
            <a href="" class="active">Administración</a>
            <a href="../html/registrarPadre.php">Añadir</a>
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

    <main class="container">
        <section class="form-section">
            <h3>Lista de Padres Registrados</h3>
            <form action="" method="GET" id="formGrupo">
                <select name="grupo" id="grupo">
                    <option value="">Todos los grupos</option>
                    <?php
                    include '../php/Conexion.php';
                    // Obtener la lista de grupos
                    $resultGrupos = $conexion->query("SELECT * FROM grupos");
                    while ($row = $resultGrupos->fetch_assoc()) {
                        $selected = (isset($_GET['grupo']) && $_GET['grupo'] == $row['idGrupo']) ? "selected" : "";
                        echo "<option value='{$row['idGrupo']}' $selected>{$row['idGrupo']}</option>";
                    }
                    ?>
                </select>
            </form>
        </section>

        <?php
        include '../php/Conexion.php';

        // Obtener el grupo seleccionado (si existe)
        $grupoSeleccionado = isset($_GET['grupo']) ? $_GET['grupo'] : '';

        // Consulta SQL para obtener padres y sus hijos
        $sqlPadres = "
            SELECT 
                Padres.idPadre, 
                Padres.nombre AS nombrePadre, 
                Padres.apellidos AS apellidosPadre, 
                Padres.correo AS correoPadre, 
                Padres.correoEstudiante AS correoEstudiante, 
                Estudiantes.idEstudiante, 
                Estudiantes.nombre AS nombreEstudiante, 
                Estudiantes.apellidos AS apellidosEstudiante, 
                Estudiantes.idGrupo 
            FROM Padres
            LEFT JOIN estudiantes_padres ON Padres.idPadre = estudiantes_padres.idPadre
            LEFT JOIN Estudiantes ON estudiantes_padres.idEstudiante = Estudiantes.idEstudiante
            WHERE ('$grupoSeleccionado' = '' OR Estudiantes.idGrupo = '$grupoSeleccionado')
        ";
        $resultPadres = $conexion->query($sqlPadres);

        if ($resultPadres->num_rows > 0) {
            echo "<section>
                    <table>
                        <thead>
                            <tr>
                                <th>ID Padre</th>
                                <th>Nombre Padre</th>
                                <th>Apellidos Padre</th>
                                <th>Correo Padre</th>
                                <th>Correo Hijo</th>
                                <th>ID Estudiante</th>
                                <th>Nombre Estudiante</th>
                                <th>Apellidos Estudiante</th>
                                <th>Grupo</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>";

            while ($row = $resultPadres->fetch_assoc()) {
                echo "<tr>
                        <td>{$row['idPadre']}</td>
                        <td>{$row['nombrePadre']}</td>
                        <td>{$row['apellidosPadre']}</td>
                        <td>{$row['correoPadre']}</td>
                        <td>{$row['correoEstudiante']}</td>
                        <td>{$row['idEstudiante']}</td>
                        <td>{$row['nombreEstudiante']}</td>
                        <td>{$row['apellidosEstudiante']}</td>
                        <td>{$row['idGrupo']}</td>
                        <td>
                            <a href='editarPadre.php?id={$row['idPadre']}' class='btn-update'><i class='fa fa-pencil-square' aria-hidden='true'></i></a>
                            <a href='#' onclick='confirmarEliminacion({$row['idPadre']})' class='btn-remove'><i class='fa fa-trash-o' aria-hidden='true'></i></a>
                        </td>
                      </tr>";
            }

            echo "</tbody>
                  </table>
                  </section>";
        } else {
            echo "<p>No hay padres registrados" . ($grupoSeleccionado ? " en este grupo" : "") . ".</p>";
        }
        ?>
    </main>

    <!-- SweetAlert2 JS -->
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
                    text: 'El padre ha sido registrado correctamente',
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
                    text: 'Los datos del padre han sido actualizados',
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
                    text: 'El padre ha sido eliminado correctamente',
                    showConfirmButton: true,
                    timer: 3000
                }).then(() => {
                    window.history.replaceState({}, document.title, window.location.pathname);
                });
            }

            // Mensajes de error
            if (urlParams.has('error')) {
                const errorMessages = {
                    'registro': 'Error al registrar el padre',
                    'edicion': 'Error al editar los datos del padre',
                    'eliminacion': 'Error al eliminar el padre',
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
        function confirmarEliminacion(idPadre) {
            Swal.fire({
                title: '¿Estás seguro?',
                text: "¡Esta acción eliminará al padre y su relación con el estudiante!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `eliminarPadre.php?id=${idPadre}`;
                }
            });
        }

        // Enviar formulario automáticamente al cambiar grupo
        document.getElementById('grupo').addEventListener('change', function() {
            document.getElementById('formGrupo').submit();
        });
    </script>
</body>

</html>