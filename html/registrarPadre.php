<?php
session_start();
require_once '../php/Conexion.php';

if (!isset($_SESSION['idUsuario']) || $_SESSION['rol'] != 'Admin') {
    header("Location: INICIO.html");
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
    <title>Registro de Padres</title>
    <link rel="stylesheet" href="../css/registrarMA.css">
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/botonPerfil.css">
</head>

<body>
    <header class="header">
        <img src="../IMAGENES/logo.gif" alt="EDUSPHERE" class="logo">
        <h2>REGISTRAR PADRES</h2>
        <nav class="nav-bar">
            <a href="../html/Admin.php">Inicio</a>
            <a href="../php/verPadres.php">Administración</a>
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

    <main class="container">
        <section class="form-section">
            <form action="../php/registrarPadre.php" method="POST" id="formRegistroPadre">
                <h3>Registrar Padre</h3>

                <label for="nombre">Nombre:</label>
                <input type="text" name="nombre" id="nombre" required
                    pattern="[A-Za-zÁÉÍÓÚáéíóúñÑ\s]+" title="Solo se permiten letras y espacios"
                    oninput="this.value = this.value.replace(/[^A-Za-zÁÉÍÓÚáéíóúñÑ\s]/g, '')">

                <label for="apellidos">Apellidos:</label>
                <input type="text" name="apellidos" id="apellidos" required
                    pattern="[A-Za-zÁÉÍÓÚáéíóúñÑ\s]+" title="Solo se permiten letras y espacios"
                    oninput="this.value = this.value.replace(/[^A-Za-zÁÉÍÓÚáéíóúñÑ\s]/g, '')">

                <label for="correo">Correo Electrónico:</label>
                <input type="email" name="correo" id="correo" required>

                <label for="contrasena">Contraseña:</label>
                <input type="password" name="contrasena" id="contrasena" required
                    minlength="8" title="La contraseña debe tener al menos 8 caracteres">

                <label for="correoEstudiante">Correo del Estudiante:</label>
                <input type="email" name="correoEstudiante" id="correoEstudiante" required>

                <button type="submit" name="registrarPadre">Registrar</button>
            </form>
        </section>
    </main>

    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../html/JS/botonPerfil.js"></script>

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