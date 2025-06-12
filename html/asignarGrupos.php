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
    <title>Asignación de Grupos</title>
    <link rel="stylesheet" href="../css/registrarMA.css">
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/botonPerfil.css">
</head>

<body>

<header class="header">
        <img src="../IMAGENES/logo.gif" alt="EDUSPHERE" class="logo">
        <h2>REGISTRAR GRUPO</h2>
        <nav class="nav-bar">
            <a href="../html/Admin.php">Inicio</a>
            <a href="../php/verGrupos.php">Administración</a>
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
            <form id="asignarGrupoForm" action="../php/asignarGrupos.php" method="POST">
                <h3>Asignar Grupo</h3>

                <div class="form-group">
                    <label for="maestroGrupo">Seleccionar Maestro:</label>
                    <select name="maestroGrupo" id="maestroGrupo" required>
                        <option value="">Seleccione un maestro</option>
                        <?php
                        include '../php/Conexion.php';
                        $result = $conexion->query("SELECT * FROM Usuarios WHERE rol = 'Maestro'");
                        while ($row = $result->fetch_assoc()) {
                            echo "<option value='{$row['idUsuario']}'>{$row['idUsuario']} - {$row['nombre']} {$row['apellidos']}</option>";
                        }
                        ?>
                    </select>
                    <div id="maestroError" class="error-message"></div>
                </div>

                <div class="form-group">
                    <label for="grupo">Nombre del Grupo:</label>
                    <input type="text" name="grupo" id="grupo" 
                           pattern="[A-Za-z0-9]+" 
                           maxlength="12"
                           title="Solo letras y números, máximo 12 caracteres"
                           required>
                    <div id="grupoError" class="error-message"></div>
                </div>

                <button type="submit" name="asignarGrupo">Asignar Grupo</button>
            </form>
        </section>
    </main>

    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../html/JS/botonPerfil.js"></script>
    <script>
        // Mostrar mensajes de error/success al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            
            // Manejar mensajes de error
            if (urlParams.has('error')) {
                const errorMessages = {
                    'formato_grupo_invalido': 'Solo letras y números, máximo 12 caracteres',
                    'maestro_en_otro_grupo': 'Este maestro ya está asignado a otro grupo',
                    'maestro_no_valido': 'El maestro seleccionado no es válido',
                    'grupo_existente': 'El grupo ya existe en el sistema',
                    'error_asignacion': 'Ocurrió un error al asignar el grupo'
                };
                
                const errorType = urlParams.get('error');
                const errorMessage = errorMessages[errorType] || 'Ocurrió un error desconocido';
                
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: errorMessage,
                    confirmButtonColor: '#d33',
                    willClose: () => {
                        // Limpiar parámetros de la URL
                        window.history.replaceState({}, document.title, window.location.pathname);
                    }
                });
            }
        });

    </script>
</body>
</html>