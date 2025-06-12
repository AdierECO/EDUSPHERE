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
    <title>Estudiantes por Grupo</title>
    <link rel="stylesheet" href="../css/registrarMA.css">
    <link rel="stylesheet" href="../css/Tabla.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/botonPerfil.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
    <header class="header">
        <img src="../IMAGENES/logo.gif" alt="EDUSPHERE" class="logo">
        <h2>VER ALUMNOS</h2>
        <nav class="nav-bar">
            <a href="../html/Admin.php">Inicio</a>
            <a href="" class="active">Administración</a>
            <a href="../html/registrarAlumno.php">Añadir</a>
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
        <?php
        include '../php/Conexion.php';
        ?>

        <section class="form-section">
            <h3>Seleccionar Grupo</h3>
            <form action="" method="GET" id="formGrupo">
                <label for="grupo">Seleccione un grupo:</label>
                <select name="grupo" id="grupo" required>
                    <option value="">Seleccione un grupo</option>
                    <?php
                    // Obtener la lista de grupos
                    $resultGrupos = $conexion->query("SELECT * FROM grupos");
                    while ($row = $resultGrupos->fetch_assoc()) {
                        $selected = (isset($_GET['grupo']) && $_GET['grupo'] == $row['idGrupo']) ? 'selected' : '';
                        echo "<option value='{$row['idGrupo']}' $selected>{$row['idGrupo']}</option>";
                    }
                    ?>
                </select>
            </form>
        </section>

        <?php
        if (isset($_GET['grupo'])) {
            $idGrupo = $_GET['grupo'];

            // Obtener los estudiantes del grupo seleccionado
            $sqlEstudiantes = "SELECT * FROM Estudiantes WHERE idGrupo = ?";
            $stmt = $conexion->prepare($sqlEstudiantes);
            $stmt->bind_param("s", $idGrupo);
            $stmt->execute();
            $resultEstudiantes = $stmt->get_result();

            if ($resultEstudiantes->num_rows > 0) {
                echo "<section class='estudiantes-section'>
                        <h3>Estudiantes del Grupo: $idGrupo</h3>
                        <table>
                            <thead>
                                <tr>
                                    <th>Matrícula</th>
                                    <th>Nombre</th>
                                    <th>Apellidos</th>
                                    <th>Correo</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>";

                while ($row = $resultEstudiantes->fetch_assoc()) {
                    echo "<tr>
                            <td>{$row['idEstudiante']}</td>
                            <td>{$row['nombre']}</td>
                            <td>{$row['apellidos']}</td>
                            <td>{$row['correo']}</td>
                            <td>
                                <a href='editarEstudiante.php?id={$row['idEstudiante']}' class='btn-update'><i class='fa fa-pencil-square' aria-hidden='true'></i></button></a>
                                <a href='#' onclick='confirmarEliminacion({$row['idEstudiante']})' class='btn-remove'><i class='fa fa-trash-o' aria-hidden='true'></i></a>
                            </td>
                          </tr>";
                }

                echo "</tbody>
                      </table>
                      </section>";
            } else {
                echo "<p>No hay estudiantes en este grupo.</p>";
            }
        }
        ?>
    </main>

    <script src="../html/JS/botonPerfil.js"></script>
    <script>
        // Auto-submit del formulario al cambiar grupo
        document.getElementById('grupo').addEventListener('change', function() {
            document.getElementById('formGrupo').submit();
        });

        // Función para confirmar eliminación con SweetAlert
        function confirmarEliminacion(idEstudiante) {
            Swal.fire({
                title: '¿Estás seguro?',
                text: "¡No podrás revertir esto!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `eliminarEstudiante.php?id=${idEstudiante}`;
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
                    text: 'Estudiante registrado correctamente',
                    timer: 3000,
                    timerProgressBar: true
                });
            }

            if (urlParams.has('edicion')) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Éxito!',
                    text: 'Estudiante editado correctamente',
                    timer: 3000,
                    timerProgressBar: true
                });
            }

            if (urlParams.has('eliminacion')) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Éxito!',
                    text: 'Estudiante eliminado correctamente',
                    timer: 3000,
                    timerProgressBar: true
                });
            }

            if (urlParams.has('error')) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: urlParams.get('error'),
                    timer: 3000,
                    timerProgressBar: true
                });
            }
        });
    </script>

</body>

</html>