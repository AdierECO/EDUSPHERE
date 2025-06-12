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
    <title>Ver Maestros</title>
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
        <h2>VER MAESTROS</h2>
        <nav class="nav-bar">
            <a href="../html/Admin.php">Inicio</a>
            <a href="" class="active">Administración</a>
            <a href="../html/registrarMaestro.php">Añadir</a>
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
            <h3>Lista de Maestros Registrados</h3>
        </section>

        <?php
        include '../php/Conexion.php';

        $sqlMaestros = "SELECT * FROM Usuarios WHERE rol = 'Maestro'";
        $resultMaestros = $conexion->query($sqlMaestros);

        if ($resultMaestros->num_rows > 0) {
            echo "<section>
                    <table>
                        <thead>
                            <tr>
                                <th>ID Maestro</th>
                                <th>Nombre</th>
                                <th>Apellidos</th>
                                <th>Correo</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>";

            while ($row = $resultMaestros->fetch_assoc()) {
                echo "<tr>
                        <td>{$row['idUsuario']}</td>
                        <td>{$row['nombre']}</td>
                        <td>{$row['apellidos']}</td>
                        <td>{$row['correo']}</td>
                        <td>
                            <a href='editarMaestro.php?id={$row['idUsuario']}' class='btn-update'><i class='fa fa-pencil-square' aria-hidden='true'></i></a>
                            <a href='#' onclick='confirmarEliminacion({$row['idUsuario']})' class='btn-remove'><i class='fa fa-trash-o' aria-hidden='true'></i></a>
                        </td>
                      </tr>";
            }

            echo "</tbody>
                  </table>
                  </section>";
        } else {
            echo "<p>No hay maestros registrados.</p>";
        }
        ?>
    </main>

    <!-- SweetAlert JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../html/JS/botonPerfil.js"></script>

    <script>
        // Mostrar mensajes al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);

            // Manejar mensaje de eliminación exitosa
            if (urlParams.has('success') && urlParams.get('success') === '1') {
                Swal.fire({
                    icon: 'success',
                    title: '¡Éxito!',
                    text: 'Maestro eliminado correctamente',
                    showConfirmButton: true,
                    timer: 3000
                }).then(() => {
                    // Limpiar parámetros de la URL
                    window.history.replaceState({}, document.title, window.location.pathname);
                });
            }

            // Manejar mensaje de registro exitoso
            if (urlParams.has('success') && urlParams.get('success') === 'registro_exitoso') {
                Swal.fire({
                    icon: 'success',
                    title: '¡Éxito!',
                    text: 'Maestro registrado correctamente',
                    showConfirmButton: true,
                    timer: 3000
                }).then(() => {
                    // Limpiar parámetros de la URL
                    window.history.replaceState({}, document.title, window.location.pathname);
                });
            }

            if (urlParams.has('success') && urlParams.get('success') === 'editado') {
                Swal.fire({
                    icon: 'success',
                    title: '¡Éxito!',
                    text: 'Maestro editado correctamente',
                    showConfirmButton: true,
                    timer: 3000
                }).then(() => {
                    // Limpiar parámetros de la URL
                    window.history.replaceState({}, document.title, window.location.pathname);
                });
            }

            // Manejar errores
            if (urlParams.has('error')) {
                const errorMessages = {
                    'eliminar': 'No se pudo eliminar el maestro',
                    'registro': 'Error al registrar el maestro',
                    'default': 'Ocurrió un error inesperado'
                };

                const errorType = urlParams.get('error');
                const errorMessage = errorMessages[errorType] || errorMessages['default'];

                Swal.fire({
                    icon: 'error',
                    title: '¡Error!',
                    text: errorMessage,
                    showConfirmButton: true
                }).then(() => {
                    // Limpiar parámetros de la URL
                    window.history.replaceState({}, document.title, window.location.pathname);
                });
            }
        });

        // Función para confirmar eliminación
        function confirmarEliminacion(idMaestro) {
            Swal.fire({
                title: '¿Estás seguro?',
                text: "¡No podrás revertir esta acción!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `eliminarMaestro.php?id=${idMaestro}`;
                }
            });
        }
    </script>
</body>

</html>