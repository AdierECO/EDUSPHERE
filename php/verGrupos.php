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
    <title>Ver Grupos</title>
    <link rel="stylesheet" href="../css/registrarMA.css">
    <link rel="stylesheet" href="../css/Tabla.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/botonPerfil.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
            border: 1px solid #c3e6cb;
        }
    </style>
</head>

<body>
    <header class="header">
        <img src="../IMAGENES/logo.gif" alt="EDUSPHERE" class="logo">
        <h2>VER GRUPOS</h2>
        <nav class="nav-bar">
            <a href="../html/Admin.php">Inicio</a>
            <a href="" class="active">Administración</a>
            <a href="../html/asignarGrupos.php">Añadir</a>
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
            <h3>Lista de Grupos Registrados</h3>
        </section>

        <?php
        include 'Conexion.php';

        // Consulta SQL para obtener todos los grupos y los datos de los maestros asociados
        $sqlGrupos = "
            SELECT 
                grupos.idGrupo,  
                grupos.id_maestro, 
                Usuarios.nombre AS nombreMaestro, 
                Usuarios.apellidos AS apellidosMaestro, 
                Usuarios.correo AS correoMaestro
            FROM grupos
            LEFT JOIN Usuarios ON grupos.id_maestro = Usuarios.idUsuario
        ";
        $resultGrupos = $conexion->query($sqlGrupos);

        if ($resultGrupos->num_rows > 0) {
            echo "<section class='grupos-section'>
                    <table>
                        <thead>
                            <tr>
                                <th>ID Grupo</th>
                                <th>ID Maestro</th>
                                <th>Nombre Maestro</th>
                                <th>Apellidos Maestro</th>
                                <th>Correo Maestro</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>";

            while ($row = $resultGrupos->fetch_assoc()) {
                echo "<tr>
                        <td>{$row['idGrupo']}</td>
                        <td>{$row['id_maestro']}</td>
                        <td>{$row['nombreMaestro']}</td>
                        <td>{$row['apellidosMaestro']}</td>
                        <td>{$row['correoMaestro']}</td>
                        <td>
                            <a href='editarGrupo.php?id={$row['idGrupo']}' class='btn-update'><i class='fa fa-pencil-square' aria-hidden='true'></i></button></a>
                            <a href='#' onclick='confirmarEliminacion(\"{$row['idGrupo']}\")' class='btn-remove'><i class='fa fa-trash-o' aria-hidden='true'></i></a>
                        </td>
                      </tr>";
            }

            echo "</tbody>
                  </table>
                  </section>";
        } else {
            echo "<p>No hay grupos registrados.</p>";
        }
        ?>
    </main>

    <script src="../html/JS/botonPerfil.js"></script>
    <script>
        // Función para confirmar eliminación con SweetAlert
        function confirmarEliminacion(idGrupo) {
            Swal.fire({
                title: '¿Estás seguro?',
                html: '<p>¡Esta acción eliminará permanentemente el grupo y todos sus datos asociados!</p>' +
                    '<p>Esto incluye:</p>' +
                    '<ul>' +
                    '<li>Todos los estudiantes del grupo</li>' +
                    '<li>Sus calificaciones</li>' +
                    '<li>Asistencias</li>' +
                    '<li>Entregas de tareas</li>' +
                    '</ul>',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar todo',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `eliminarGrupo.php?id=${idGrupo}`;
                }
            });
        }

        // Mostrar alertas según parámetros de URL
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);

            if (urlParams.has('registro')) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Éxito!',
                    text: 'Grupo registrado correctamente',
                    timer: 3000,
                    timerProgressBar: true,
                    willClose: () => {
                        // Limpiar parámetros de la URL
                        history.replaceState(null, null, window.location.pathname);
                    }
                });
            }

            if (urlParams.has('edicion')) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Éxito!',
                    text: 'Grupo editado correctamente',
                    timer: 3000,
                    timerProgressBar: true,
                    willClose: () => {
                        // Limpiar parámetros de la URL
                        history.replaceState(null, null, window.location.pathname);
                    }
                });
            }

            if (urlParams.has('eliminacion')) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Éxito!',
                    text: 'Grupo eliminado correctamente',
                    timer: 3000,
                    timerProgressBar: true,
                    willClose: () => {
                        // Limpiar parámetros de la URL
                        history.replaceState(null, null, window.location.pathname);
                    }
                });
            }

            if (urlParams.has('error')) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: urlParams.get('error'),
                    timer: 5000,
                    timerProgressBar: true,
                    willClose: () => {
                        // Limpiar parámetros de la URL
                        history.replaceState(null, null, window.location.pathname);
                    }
                });
            }
        });
    </script>

</body>

</html>