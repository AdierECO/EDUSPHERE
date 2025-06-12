<?php
session_start();

if (!isset($_SESSION['idEstudiante'])) {
    header("Location: loginEstudiante.html");
    exit();
}

include '../php/Conexion.php';

// Obtener información del estudiante
$idEstudiante = $_SESSION['idEstudiante'];
$sqlEstudiante = "SELECT nombre, apellidos, correo, idGrupo, foto_perfil FROM estudiantes WHERE idEstudiante = ?";
$stmtEstudiante = $conexion->prepare($sqlEstudiante);
$stmtEstudiante->bind_param("i", $idEstudiante);
$stmtEstudiante->execute();
$resultEstudiante = $stmtEstudiante->get_result();

if ($resultEstudiante->num_rows == 0) {
    header("Location: loginEstudiante.html");
    exit();
}

$estudiante = $resultEstudiante->fetch_assoc();
$idGrupo = $estudiante['idGrupo'];

// Procesar actualización de foto de perfil
$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['foto_perfil'])) {
    $directorio = "../IMAGENES/estudiantes/";

    if (!file_exists($directorio)) {
        mkdir($directorio, 0777, true);
    }

    if ($estudiante['foto_perfil'] != 'default.png' && file_exists($directorio . $estudiante['foto_perfil'])) {
        unlink($directorio . $estudiante['foto_perfil']);
    }

    $nombre_archivo = 'estudiante_' . $idEstudiante . '_' . time() . '.' . pathinfo($_FILES['foto_perfil']['name'], PATHINFO_EXTENSION);
    $ruta_completa = $directorio . $nombre_archivo;

    $extension = strtolower(pathinfo($ruta_completa, PATHINFO_EXTENSION));
    $permitidos = ['jpg', 'jpeg', 'png', 'gif'];

    if (in_array($extension, $permitidos)) {
        if (move_uploaded_file($_FILES['foto_perfil']['tmp_name'], $ruta_completa)) {
            $update_sql = "UPDATE estudiantes SET foto_perfil = ? WHERE idEstudiante = ?";
            $update_stmt = $conexion->prepare($update_sql);
            $update_stmt->bind_param("si", $nombre_archivo, $idEstudiante);

            if ($update_stmt->execute()) {
                $estudiante['foto_perfil'] = $nombre_archivo;
                $mensaje = "Foto de perfil actualizada correctamente.";
            } else {
                $error = "Error al actualizar la foto en la base de datos.";
            }
            $update_stmt->close();
        } else {
            $error = "Error al subir la imagen.";
        }
    } else {
        $error = "Solo se permiten archivos JPG, JPEG, PNG y GIF.";
    }
}

// Procesar cambio de contraseña
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cambiar_contrasena'])) {
    $contrasena_actual = $_POST['contrasena_actual'];
    $nueva_contrasena = $_POST['nueva_contrasena'];
    $confirmar_contrasena = $_POST['confirmar_contrasena'];

    if ($nueva_contrasena !== $confirmar_contrasena) {
        $error = "Las contraseñas nuevas no coinciden.";
    } elseif (strlen($nueva_contrasena) < 8) {
        $error = "La contraseña debe tener al menos 8 caracteres.";
    } else {
        $sql_verificar = "SELECT contrasena FROM estudiantes WHERE idEstudiante = ?";
        $stmt_verificar = $conexion->prepare($sql_verificar);
        $stmt_verificar->bind_param("i", $idEstudiante);
        $stmt_verificar->execute();
        $result_verificar = $stmt_verificar->get_result();

        if ($result_verificar->num_rows > 0) {
            $row = $result_verificar->fetch_assoc();
            if (password_verify($contrasena_actual, $row['contrasena'])) {
                $hashed_password = password_hash($nueva_contrasena, PASSWORD_DEFAULT);
                $sql_actualizar = "UPDATE estudiantes SET contrasena = ? WHERE idEstudiante = ?";
                $stmt_actualizar = $conexion->prepare($sql_actualizar);
                $stmt_actualizar->bind_param("si", $hashed_password, $idEstudiante);

                if ($stmt_actualizar->execute()) {
                    $mensaje = "Contraseña actualizada correctamente.";
                } else {
                    $error = "Error al actualizar la contraseña.";
                }
                $stmt_actualizar->close();
            } else {
                $error = "La contraseña actual es incorrecta.";
            }
        }
        $stmt_verificar->close();
    }
}

// CONSULTA para contar notificaciones no leídas
$sqlNotificacionesNoLeidas = "SELECT (
                                SELECT COUNT(*) FROM (
                                    SELECT t.idTarea FROM tareas t
                                    WHERE t.idGrupo = ?
                                    AND NOT EXISTS (
                                        SELECT 1 FROM notificaciones_vistas nv
                                        WHERE nv.idEstudiante = ?
                                        AND nv.tipo_notificacion = 'tarea'
                                        AND nv.id_notificacion = t.idTarea
                                    )

                                    UNION ALL

                                    SELECT a.id FROM avisos a
                                    WHERE a.idGrupo = ?
                                    AND NOT EXISTS (
                                        SELECT 1 FROM notificaciones_vistas nv
                                        WHERE nv.idEstudiante = ?
                                        AND nv.tipo_notificacion = 'aviso'
                                        AND nv.id_notificacion = a.id
                                    )
                                ) AS notificaciones_no_leidas
                              ) AS total";

$stmtNotificaciones = $conexion->prepare($sqlNotificacionesNoLeidas);
$stmtNotificaciones->bind_param("sisi", $idGrupo, $idEstudiante, $idGrupo, $idEstudiante);
$stmtNotificaciones->execute();
$resultNotificaciones = $stmtNotificaciones->get_result();
$numNoLeidas = $resultNotificaciones->fetch_assoc()['total'] ?? 0;

$stmtEstudiante->close();
$stmtNotificaciones->close();
$conexion->close();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - EDUSPHERE</title>
    <link rel="stylesheet" href="../css/Perfil.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <!-- Header -->
    <header class="header">
        <div class="header-left">
            <img src="../IMAGENES/logo.gif" alt="EDUSPHERE" class="logo">
            <h1 class="logo-text">PERFIL</h1>
        </div>

        <div class="header-center">
            <nav class="nav-bar">
                <a href="Usuario.php"><i class="fas fa-home"></i> INICIO</a>
                <a href="Notificaciones.php"><i class="fas fa-bell"></i> NOTIFICACIONES
                    <?php if ($numNoLeidas > 0): ?>
                        <span class="notification-badge"><?= $numNoLeidas ?></span>
                    <?php endif; ?>
                </a>
            </nav>
        </div>

        <div class="header-right">
            <div class="user-dropdown" id="userDropdown">
                <div class="user-display">
                    <span class="user-name"><?= htmlspecialchars($estudiante['nombre'] . ' ' . $estudiante['apellidos']) ?></span>
                    <img src="../IMAGENES/estudiantes/<?= htmlspecialchars($estudiante['foto_perfil']) ?>" alt="Foto perfil" class="profile-pic">
                    <i class="fas fa-chevron-down dropdown-arrow"></i>
                </div>
                <div class="dropdown-menu" id="dropdownMenu">
                    <a href="Perfil.php"><i class="fas fa-user-circle"></i> Perfil</a>
                    <form action="../php/cerrar_sesion.php" method="post">
                        <button type="submit" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="profile-container">
        <?php if ($mensaje): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Sección Foto de Perfil -->
        <section class="profile-section">
            <h2><i class="fas fa-camera"></i> Foto de Perfil</h2>
            <div class="profile-picture-container">
                <form method="post" enctype="multipart/form-data" class="photo-form" id="photo-form">
                    <div class="profile-picture-wrapper">
                        <img src="../IMAGENES/estudiantes/<?= htmlspecialchars($estudiante['foto_perfil']) ?>" alt="Foto de perfil" id="profile-picture">
                        <label for="foto_perfil" class="change-photo-btn">
                            <i class="fas fa-camera"></i>
                            <input type="file" id="foto_perfil" name="foto_perfil" accept="image/*" style="display:none;">
                        </label>
                    </div>
                </form>
            </div>
        </section>

        <!-- Sección Información Personal -->
        <section class="profile-section">
            <h2><i class="fas fa-info-circle"></i> Información Personal</h2>
            <ul class="info-list">
                <li>
                    <span class="info-label"><i class="fas fa-user"></i> Nombre:</span>
                    <span class="info-value"><?= htmlspecialchars($estudiante['nombre']) ?></span>
                </li>
                <li>
                    <span class="info-label"><i class="fas fa-user-tag"></i> Apellidos:</span>
                    <span class="info-value"><?= htmlspecialchars($estudiante['apellidos']) ?></span>
                </li>
                <li>
                    <span class="info-label"><i class="fas fa-envelope"></i> Correo:</span>
                    <span class="info-value"><?= htmlspecialchars($estudiante['correo']) ?></span>
                </li>
                <li>
                    <span class="info-label"><i class="fas fa-users"></i> Grupo:</span>
                    <span class="info-value"><?= htmlspecialchars($idGrupo) ?></span>
                </li>
            </ul>
        </section>

        <!-- Sección Seguridad -->
        <section class="profile-section">
            <h2><i class="fas fa-lock"></i> Seguridad</h2>
            <form method="POST" class="security-form">
                <div class="form-group">
                    <label for="current_password"><i class="fas fa-key"></i> Contraseña Actual:</label>
                    <input type="password" id="current_password" name="contrasena_actual" required>
                </div>

                <div class="form-group">
                    <label for="new_password"><i class="fas fa-key"></i> Nueva Contraseña:</label>
                    <input type="password" id="new_password" name="nueva_contrasena" required>
                    <small class="password-hint">
                        Mínimo 8 caracteres, incluyendo mayúsculas, minúsculas y números
                    </small>
                </div>

                <div class="form-group">
                    <label for="confirm_password"><i class="fas fa-key"></i> Confirmar Contraseña:</label>
                    <input type="password" id="confirm_password" name="confirmar_contrasena" required>
                </div>

                <div class="form-actions">
                    <button type="submit" name="cambiar_contrasena" class="save-btn">
                        <i class="fas fa-save"></i> Guardar Cambios
                    </button>
                    <button type="button" class="cancel-btn" onclick="window.location.reload();">Cancelar</button>
                </div>
            </form>
        </section>
    </main>

    <script>
        // Mostrar/ocultar menú desplegable
        document.querySelector('.user-display').addEventListener('click', function() {
            document.querySelector('.dropdown-menu').classList.toggle('show');
        });

        // Cerrar menú al hacer clic fuera
        window.addEventListener('click', function(event) {
            if (!event.target.closest('.user-dropdown')) {
                document.querySelector('.dropdown-menu').classList.remove('show');
            }
        });

        // Validación de contraseña
        const newPassword = document.getElementById('new_password');
        const confirmPassword = document.getElementById('confirm_password');

        function validatePassword() {
            if (newPassword.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity("Las contraseñas no coinciden");
            } else {
                confirmPassword.setCustomValidity('');
            }

            if (newPassword.value.length < 8) {
                newPassword.setCustomValidity("La contraseña debe tener al menos 8 caracteres");
            } else if (!/[A-Z]/.test(newPassword.value)) {
                newPassword.setCustomValidity("La contraseña debe contener al menos una mayúscula");
            } else if (!/[0-9]/.test(newPassword.value)) {
                newPassword.setCustomValidity("La contraseña debe contener al menos un número");
            } else {
                newPassword.setCustomValidity('');
            }
        }

        newPassword.addEventListener('input', validatePassword);
        confirmPassword.addEventListener('input', validatePassword);

        // Vista previa de imagen
        document.getElementById('foto_perfil').addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('profile-picture').src = e.target.result;
                }
                reader.readAsDataURL(this.files[0]);
            }
        });

        // Temporizador de inactividad
        let inactivityTime = 180000; // 3 minutos
        let timeout;

        function resetTimer() {
            clearTimeout(timeout);
            timeout = setTimeout(logout, inactivityTime);
        }

        function logout() {
            window.location.href = '../php/cerrar_sesion.php';
        }

        // Eventos que reinician el temporizador
        window.onload = resetTimer;
        window.onmousemove = resetTimer;
        window.onmousedown = resetTimer;
        window.onclick = resetTimer;
        window.onscroll = resetTimer;
        window.onkeypress = resetTimer;
    </script>
</body>

</html>