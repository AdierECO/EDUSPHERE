<?php
session_start();
require_once '../php/Conexion.php';

// Verificar sesión
if (!isset($_SESSION['idPadre'])) {
    header("Location: loginPadres.html");
    exit();
}

$idPadre = $_SESSION['idPadre'];
$error = '';
$success = '';

// Obtener datos actuales del padre
$sql = "SELECT * FROM Padres WHERE idPadre = ?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $idPadre);
$stmt->execute();
$result = $stmt->get_result();
$padre = $result->fetch_assoc();

// Obtener ID del estudiante actual
$sqlEstudiante = "SELECT e.idEstudiante 
                 FROM Estudiantes e
                 JOIN estudiantes_padres ep ON e.idEstudiante = ep.idEstudiante
                 WHERE ep.idPadre = ?";
$stmtEst = $conexion->prepare($sqlEstudiante);
$stmtEst->bind_param("i", $idPadre);
$stmtEst->execute();
$resultEst = $stmtEst->get_result();
$estudianteActual = $resultEst->fetch_assoc();
$idEstudianteActual = $estudianteActual ? $estudianteActual['idEstudiante'] : null;

// Procesar formulario de actualización
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = filter_input(INPUT_POST, 'nombre', FILTER_SANITIZE_STRING);
    $apellidos = filter_input(INPUT_POST, 'apellidos', FILTER_SANITIZE_STRING);
    $correo = filter_input(INPUT_POST, 'correo', FILTER_SANITIZE_EMAIL);
    $nuevoCorreoEstudiante = filter_input(INPUT_POST, 'correoEstudiante', FILTER_SANITIZE_EMAIL);
    $contrasena = trim($_POST['contrasena']);
    $confirmarContrasena = trim($_POST['confirmarContrasena']);

    // Validaciones básicas
    if (empty($nombre) || empty($apellidos) || empty($correo) || empty($nuevoCorreoEstudiante)) {
        $error = "Todos los campos son obligatorios";
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = "Correo electrónico no válido";
    } elseif (!filter_var($nuevoCorreoEstudiante, FILTER_VALIDATE_EMAIL)) {
        $error = "Correo del estudiante no válido";
    } elseif (!empty($contrasena) && $contrasena !== $confirmarContrasena) {
        $error = "Las contraseñas no coinciden";
    } elseif (!empty($contrasena) && strlen($contrasena) < 8) {
        $error = "La contraseña debe tener al menos 8 caracteres";
    } else {
        // Crear directorio de imágenes si no existe
        $directorioImagenes = "../IMAGENES/Padres/";
        if (!file_exists($directorioImagenes)) {
            if (!mkdir($directorioImagenes, 0755, true)) {
                $error = "No se pudo crear el directorio de imágenes";
            }
        }

        // Procesar imagen si no hay errores y se subió una nueva
        $nombreImagen = $padre['foto_perfil'];

        if (empty($error) && isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] == UPLOAD_ERR_OK) {
            $extension = pathinfo($_FILES['foto_perfil']['name'], PATHINFO_EXTENSION);
            $nombreImagen = 'padre_' . $idPadre . '_' . time() . '.' . $extension;
            $rutaCompleta = $directorioImagenes . $nombreImagen;

            // Validar imagen
            $tipo = exif_imagetype($_FILES['foto_perfil']['tmp_name']);
            if ($tipo && ($tipo == IMAGETYPE_JPEG || $tipo == IMAGETYPE_PNG)) {
                if (move_uploaded_file($_FILES['foto_perfil']['tmp_name'], $rutaCompleta)) {
                    // Eliminar imagen anterior si no es la default
                    if ($padre['foto_perfil'] != 'default.png' && file_exists($directorioImagenes . $padre['foto_perfil'])) {
                        @unlink($directorioImagenes . $padre['foto_perfil']);
                    }
                } else {
                    $error = "Error al subir la imagen";
                    $nombreImagen = $padre['foto_perfil']; // Mantener la anterior
                }
            } else {
                $error = "Formato de imagen no válido. Solo JPG/PNG";
            }
        }

        if (empty($error)) {
            // Iniciar transacción para asegurar integridad de datos
            $conexion->begin_transaction();

            try {
                // 1. Obtener ID del nuevo estudiante si el correo cambió
                $idNuevoEstudiante = null;
                $actualizarRelacion = ($nuevoCorreoEstudiante != $padre['correoEstudiante']);

                if ($actualizarRelacion) {
                    $sqlNuevoEst = "SELECT idEstudiante FROM Estudiantes WHERE correo = ?";
                    $stmtNuevoEst = $conexion->prepare($sqlNuevoEst);
                    $stmtNuevoEst->bind_param("s", $nuevoCorreoEstudiante);
                    $stmtNuevoEst->execute();
                    $resultNuevoEst = $stmtNuevoEst->get_result();

                    if ($resultNuevoEst->num_rows > 0) {
                        $idNuevoEstudiante = $resultNuevoEst->fetch_assoc()['idEstudiante'];
                    } else {
                        throw new Exception("El correo del estudiante no existe en el sistema");
                    }
                }

                // 2. Actualizar datos del padre
                if (!empty($contrasena)) {
                    $contrasenaHash = password_hash($contrasena, PASSWORD_DEFAULT);
                    $sqlPadre = "UPDATE Padres SET nombre=?, apellidos=?, correo=?, correoEstudiante=?, contrasena=?, foto_perfil=? WHERE idPadre=?";
                    $stmtPadre = $conexion->prepare($sqlPadre);
                    $stmtPadre->bind_param("ssssssi", $nombre, $apellidos, $correo, $nuevoCorreoEstudiante, $contrasenaHash, $nombreImagen, $idPadre);
                } else {
                    $sqlPadre = "UPDATE Padres SET nombre=?, apellidos=?, correo=?, correoEstudiante=?, foto_perfil=? WHERE idPadre=?";
                    $stmtPadre = $conexion->prepare($sqlPadre);
                    $stmtPadre->bind_param("sssssi", $nombre, $apellidos, $correo, $nuevoCorreoEstudiante, $nombreImagen, $idPadre);
                }

                if (!$stmtPadre->execute()) {
                    throw new Exception("Error al actualizar los datos del padre");
                }

                // 3. Actualizar relación en estudiantes_padres si cambió el correo del estudiante
                if ($actualizarRelacion && $idNuevoEstudiante) {
                    // Eliminar relación anterior
                    $sqlDeleteRel = "DELETE FROM estudiantes_padres WHERE idPadre = ?";
                    $stmtDeleteRel = $conexion->prepare($sqlDeleteRel);
                    $stmtDeleteRel->bind_param("i", $idPadre);

                    if (!$stmtDeleteRel->execute()) {
                        throw new Exception("Error al eliminar la relación anterior");
                    }

                    // Crear nueva relación
                    $sqlInsertRel = "INSERT INTO estudiantes_padres (idEstudiante, idPadre) VALUES (?, ?)";
                    $stmtInsertRel = $conexion->prepare($sqlInsertRel);
                    $stmtInsertRel->bind_param("ii", $idNuevoEstudiante, $idPadre);

                    if (!$stmtInsertRel->execute()) {
                        throw new Exception("Error al crear la nueva relación");
                    }
                }

                // Confirmar transacción
                $conexion->commit();

                $success = "Perfil actualizado correctamente";
                // Actualizar datos en sesión
                $_SESSION['nombre'] = $nombre;
                // Recargar datos del padre
                $padre['nombre'] = $nombre;
                $padre['apellidos'] = $apellidos;
                $padre['correo'] = $correo;
                $padre['correoEstudiante'] = $nuevoCorreoEstudiante;
                $padre['foto_perfil'] = $nombreImagen;
            } catch (Exception $e) {
                $conexion->rollback();
                $error = $e->getMessage();
            }
        }
    }
}
// Obtener cantidad de notificaciones no leídas (avisos + tareas) para los hijos del padre
$sqlNotificaciones = "SELECT (
    SELECT COUNT(a.id) 
    FROM avisos a
    JOIN estudiantes e ON a.idGrupo = e.idGrupo
    JOIN estudiantes_padres ep ON e.idEstudiante = ep.idEstudiante
    WHERE ep.idPadre = ?
    AND a.id NOT IN (
        SELECT nv.id_notificacion 
        FROM notificaciones_vistas nv
        WHERE nv.idEstudiante = e.idEstudiante AND nv.tipo_notificacion = 'aviso'
    )
) + (
    SELECT COUNT(t.idTarea)
    FROM tareas t
    JOIN estudiantes e ON t.idGrupo = e.idGrupo
    JOIN estudiantes_padres ep ON e.idEstudiante = ep.idEstudiante
    WHERE ep.idPadre = ?
    AND t.idTarea NOT IN (
        SELECT nv.id_notificacion 
        FROM notificaciones_vistas nv
        WHERE nv.idEstudiante = e.idEstudiante AND nv.tipo_notificacion = 'tarea'
    )
) AS total_notificaciones";

$stmtNotificaciones = $conexion->prepare($sqlNotificaciones);
$stmtNotificaciones->bind_param("ii", $idPadre, $idPadre);
$stmtNotificaciones->execute();
$resultNotificaciones = $stmtNotificaciones->get_result();
$totalNotificacionesNoLeidas = $resultNotificaciones->fetch_assoc()['total_notificaciones'];
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - Edusphere</title>
    <link rel="stylesheet" href="../css/perfilPadre.css">
    <link rel="stylesheet" href="../css/botonPerfil.css">
    <link rel="stylesheet" href="../css/notificacionesLeidas.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <header class="header">
        <img src="../IMAGENES/logo.gif" alt="EDUSPHERE" class="logo">
        <h2>PADRES</h2>
        <nav class="nav-bar">
            <a href="../html/PADRES.php">Inicio</a>
        </nav>
        <div class="header-icons">
            <!-- Icono de notificaciones -->
            <div class="notification-icon" id="notificationBell">
                <i class="fas fa-bell"></i>
                <?php if ($totalNotificacionesNoLeidas > 0): ?>
                    <span class="notification-badge"><?php echo $totalNotificacionesNoLeidas; ?></span>
                <?php endif; ?>
                <div class="notification-dropdown" id="notificationDropdown">
                    <div class="notification-header">
                        <h3>Notificaciones Recientes</h3>
                        <span class="mark-all-read">Marcar todo como leído</span>
                    </div>
                    <div class="notification-list" id="notificationList">
                        <div class="loading-notifications">Cargando notificaciones...</div>
                    </div>
                    <div class="notification-footer">
                        <a href="anuncios.php">Ver todos los avisos</a>
                        <a href="tareasPadres.php" style="margin-left: 10px;">Ver todas las tareas</a>
                    </div>
                </div>
            </div>
            <div class="user-info" id="userDropdown">
                <div class="user-display">
                    <img src="../IMAGENES/Padres/<?= htmlspecialchars($padre['foto_perfil']) ?>" alt="Foto de perfil" class="profile-pic">
                    <span><?= htmlspecialchars($padre['nombre'] . ' ' . $padre['apellidos']) ?></span>
                    <i class="fas fa-chevron-down dropdown-arrow"></i>
                </div>
                <div class="dropdown-menu" id="dropdownMenu">
                    <a href="perfilPadre.php"><i class="fas fa-user-circle"></i> Perfil</a>
                    <a href="../php/cerrar_Sesion.php"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a>
                </div>
            </div>
        </div>
    </header>

    <div class="profile-container">
        <div class="profile-header">
            <h1><i class="fas fa-user-circle"></i> Mi Perfil</h1>
            <a href="PADRES.php" class="back-button"><i class="fas fa-arrow-left"></i> Volver</a>
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
                        <img src="../IMAGENES/Padres/<?= htmlspecialchars($padre['foto_perfil']) ?>"
                            alt="Foto de perfil" id="photoPreview">
                        <label for="foto_perfil" class="upload-button">
                            <i class="fas fa-camera"></i> Cambiar foto
                        </label>
                        <input type="file" name="foto_perfil" id="foto_perfil" accept="image/jpeg, image/png">
                    </div>
                </div>

                <div class="form-group">
                    <label for="nombre"><i class="fas fa-user"></i> Nombre:</label>
                    <input type="text" id="nombre" name="nombre" value="<?= htmlspecialchars($padre['nombre']) ?>" required>
                </div>

                <div class="form-group">
                    <label for="apellidos"><i class="fas fa-users"></i> Apellidos:</label>
                    <input type="text" id="apellidos" name="apellidos" value="<?= htmlspecialchars($padre['apellidos']) ?>" required>
                </div>
            </div>

            <div class="form-section">
                <h2><i class="fas fa-envelope"></i> Información de Contacto</h2>

                <div class="form-group">
                    <label for="correo"><i class="fas fa-at"></i> Correo Electrónico:</label>
                    <input type="email" id="correo" name="correo" value="<?= htmlspecialchars($padre['correo']) ?>" required>
                </div>

                <div class="form-group">
                    <label for="correoEstudiante"><i class="fas fa-child"></i> Correo del Estudiante:</label>
                    <input type="email" id="correoEstudiante" name="correoEstudiante"
                        value="<?= htmlspecialchars($padre['correoEstudiante']) ?>" required>
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
                <button type="button" class="cancel-button" onclick="window.location.href='PADRES.php'">
                    <i class="fas fa-times"></i> Cancelar
                </button>
            </div>
        </form>
    </div>
    <script src="../html/JS/botonPerfil.js"></script>
    <script>
        document.getElementById('notificationBell').addEventListener('click', function(e) {
            e.stopPropagation();
            const dropdown = document.getElementById('notificationDropdown');
            dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';

            if (dropdown.style.display === 'block') {
                cargarNotificaciones();
            }
        });

        // Función para cargar notificaciones via AJAX
        function cargarNotificaciones() {
            fetch('../php/obtenerAvisos.php?padreId=<?php echo $idPadre; ?>')
                .then(response => response.text())
                .then(data => {
                    document.getElementById('notificationList').innerHTML = data;
                });
        }

        // Cerrar dropdown al hacer clic fuera
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.notification-icon')) {
                document.getElementById('notificationDropdown').style.display = 'none';
            }
            if (!e.target.closest('.user-info')) {
                document.getElementById('dropdownMenu').style.display = 'none';
            }
        });
        document.querySelector('.mark-all-read')?.addEventListener('click', function() {
            fetch('../php/marcarAvisos.php?padreId=<?php echo $idPadre; ?>')
                .then(() => {
                    cargarNotificaciones();
                    const badge = document.querySelector('.notification-badge');
                    if (badge) badge.remove();
                    <?php $_SESSION['notificaciones_no_leidas'] = 0; ?>
                });
        });
    </script>
</body>

</html>