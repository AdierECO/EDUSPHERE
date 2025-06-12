<?php
session_start();
require_once '../php/Conexion.php';

// Verificar sesión y rol de maestro
if (!isset($_SESSION['idUsuario']) || $_SESSION['rol'] != 'Maestro') {
    header("Location: INICIO.html");
    exit();
}

$idMaestro = $_SESSION['idUsuario'];
$error = '';
$success = '';

// Obtener datos actuales del maestro
$sql = "SELECT * FROM Usuarios WHERE idUsuario = ?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $idMaestro);
$stmt->execute();
$result = $stmt->get_result();
$maestro = $result->fetch_assoc();

// Procesar formulario de actualización
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = filter_input(INPUT_POST, 'nombre', FILTER_SANITIZE_STRING);
    $apellidos = filter_input(INPUT_POST, 'apellidos', FILTER_SANITIZE_STRING);
    $correo = filter_input(INPUT_POST, 'correo', FILTER_SANITIZE_EMAIL);
    $contrasena = trim($_POST['contrasena']);
    $confirmarContrasena = trim($_POST['confirmarContrasena']);

    // Validaciones básicas
    if (empty($nombre) || empty($apellidos) || empty($correo)) {
        $error = "Todos los campos son obligatorios";
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = "Correo electrónico no válido";
    } elseif (!empty($contrasena) && $contrasena !== $confirmarContrasena) {
        $error = "Las contraseñas no coinciden";
    } elseif (!empty($contrasena) && strlen($contrasena) < 8) {
        $error = "La contraseña debe tener al menos 8 caracteres";
    } else {
        // Crear directorio de imágenes si no existe
        $directorioImagenes = "../IMAGENES/Usuarios/";
        if (!file_exists($directorioImagenes)) {
            if (!mkdir($directorioImagenes, 0755, true)) {
                $error = "No se pudo crear el directorio de imágenes";
            }
        }

        // Procesar imagen si no hay errores y se subió una nueva
        $nombreImagen = $maestro['foto_perfil'];

        if (empty($error) && isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] == UPLOAD_ERR_OK) {
            $extension = pathinfo($_FILES['foto_perfil']['name'], PATHINFO_EXTENSION);
            $nombreImagen = 'maestro_' . $idMaestro . '_' . time() . '.' . $extension;
            $rutaCompleta = $directorioImagenes . $nombreImagen;

            // Validar imagen
            $tipo = exif_imagetype($_FILES['foto_perfil']['tmp_name']);
            if ($tipo && ($tipo == IMAGETYPE_JPEG || $tipo == IMAGETYPE_PNG)) {
                if (move_uploaded_file($_FILES['foto_perfil']['tmp_name'], $rutaCompleta)) {
                    // Eliminar imagen anterior si no es la default
                    if ($maestro['foto_perfil'] != 'default.png' && file_exists($directorioImagenes . $maestro['foto_perfil'])) {
                        @unlink($directorioImagenes . $maestro['foto_perfil']);
                    }
                } else {
                    $error = "Error al subir la imagen";
                    $nombreImagen = $maestro['foto_perfil']; // Mantener la anterior
                }
            } else {
                $error = "Formato de imagen no válido. Solo JPG/PNG";
            }
        }

        if (empty($error)) {
            // Actualizar datos del maestro
            if (!empty($contrasena)) {
                $contrasenaHash = password_hash($contrasena, PASSWORD_DEFAULT);
                $sqlUpdate = "UPDATE Usuarios SET nombre=?, apellidos=?, correo=?, contrasena=?, foto_perfil=? WHERE idUsuario=?";
                $stmtUpdate = $conexion->prepare($sqlUpdate);
                $stmtUpdate->bind_param("sssssi", $nombre, $apellidos, $correo, $contrasenaHash, $nombreImagen, $idMaestro);
            } else {
                $sqlUpdate = "UPDATE Usuarios SET nombre=?, apellidos=?, correo=?, foto_perfil=? WHERE idUsuario=?";
                $stmtUpdate = $conexion->prepare($sqlUpdate);
                $stmtUpdate->bind_param("ssssi", $nombre, $apellidos, $correo, $nombreImagen, $idMaestro);
            }

            if ($stmtUpdate->execute()) {
                $success = "Perfil actualizado correctamente";
                // Actualizar datos en sesión
                $_SESSION['nombre'] = $nombre;
                $_SESSION['correo'] = $correo;
                // Recargar datos del maestro
                $maestro['nombre'] = $nombre;
                $maestro['apellidos'] = $apellidos;
                $maestro['correo'] = $correo;
                $maestro['foto_perfil'] = $nombreImagen;
            } else {
                $error = "Error al actualizar los datos del maestro";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - Maestro</title>
    <link rel="stylesheet" href="../css/perfilUsuario.css">
    <link rel="stylesheet" href="../css/botonPerfil.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <header class="header">
        <img src="../IMAGENES/logo.gif" alt="EDUSPHERE" class="logo">
        <h2>PADRES</h2>
        <nav class="nav-bar">
            <a href="../html/Docente.php">Inicio</a>
        </nav>
        <div class="user-info" id="userDropdown">
            <div class="user-display">
                <img src="../IMAGENES/Usuarios/<?= htmlspecialchars($maestro['foto_perfil']) ?>" alt="Foto de perfil" class="profile-pic">
                <span><?= htmlspecialchars($maestro['nombre'] . ' ' . $maestro['apellidos']) ?></span>
                <i class="fas fa-chevron-down dropdown-arrow"></i>
            </div>
            <div class="dropdown-menu" id="dropdownMenu">
                <a href="perfilUsuario.php"><i class="fas fa-user-circle"></i> Perfil</a>
                <a href="../php/cerrar_Sesion.php"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a>
            </div>
        </div>
    </header>

    <div class="profile-container">
        <div class="profile-header">
            <h1><i class="fas fa-user-circle"></i> Mi Perfil</h1>
            <a href="Docente.php" class="back-button"><i class="fas fa-arrow-left"></i> Volver</a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="profile-form">
            <div class="form-section">
                <h2><i class="fas fa-id-card"></i> Información Personal</h2>

                <div class="photo-upload">
                    <div class="photo-preview">
                        <img src="../IMAGENES/Usuarios/<?= htmlspecialchars($maestro['foto_perfil']) ?>"
                            alt="Foto de perfil" id="photoPreview">
                        <label for="foto_perfil" class="upload-button">
                            <i class="fas fa-camera"></i>
                        </label>
                        <input type="file" name="foto_perfil" id="foto_perfil" accept="image/jpeg, image/png">
                    </div>
                </div>

                <div class="form-group">
                    <label for="nombre"><i class="fas fa-user"></i> Nombre:</label>
                    <input type="text" id="nombre" name="nombre" value="<?= htmlspecialchars($maestro['nombre']) ?>" required>
                </div>

                <div class="form-group">
                    <label for="apellidos"><i class="fas fa-users"></i> Apellidos:</label>
                    <input type="text" id="apellidos" name="apellidos" value="<?= htmlspecialchars($maestro['apellidos']) ?>" required>
                </div>
            </div>

            <div class="form-section">
                <h2><i class="fas fa-envelope"></i> Información de Contacto</h2>

                <div class="form-group">
                    <label for="correo"><i class="fas fa-at"></i> Correo Electrónico:</label>
                    <input type="email" id="correo" name="correo" value="<?= htmlspecialchars($maestro['correo']) ?>" required>
                </div>
            </div>

            <div class="form-section">
                <h2><i class="fas fa-lock"></i> Seguridad</h2>

                <div class="form-group">
                    <label for="contrasena"><i class="fas fa-key"></i> Nueva Contraseña:</label>
                    <input type="password" id="contrasena" name="contrasena" placeholder="Dejar en blanco para no cambiar">
                    <small class="hint">Mínimo 8 caracteres</small>
                </div>

                <div class="form-group">
                    <label for="confirmarContrasena"><i class="fas fa-key"></i> Confirmar Contraseña:</label>
                    <input type="password" id="confirmarContrasena" name="confirmarContrasena"
                        placeholder="Confirmar nueva contraseña">
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="save-button">
                    <i class="fas fa-save"></i> Guardar Cambios
                </button>
                <button type="button" class="cancel-button" onclick="window.location.href='Docente.php'">
                    <i class="fas fa-times"></i> Cancelar
                </button>
            </div>
        </form>
    </div>

    <script src="../html/JS/botonPerfil.js"></script>
    <script>
        // Mostrar vista previa de la imagen seleccionada
        document.getElementById('foto_perfil').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('photoPreview').src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>

</html>