<?php
session_start();
require_once '../php/Conexion.php';

// Inicializar variables
$error = '';
$success = '';
$correo = '';

// Procesar formulario de recuperación
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $correo = filter_input(INPUT_POST, 'correo', FILTER_SANITIZE_EMAIL);
    
    // Validar correo
    if (empty($correo)) {
        $error = "Por favor ingrese su correo electrónico";
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = "Correo electrónico no válido";
    } else {
        // Buscar el correo en todas las tablas de usuarios
        $encontrado = false;
        $tipoUsuario = '';
        $idUsuario = 0;
        $nombre = '';
        
        // Buscar en estudiantes
        $sql = "SELECT idEstudiante, nombre FROM estudiantes WHERE correo = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("s", $correo);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $encontrado = true;
            $tipoUsuario = 'estudiante';
            $row = $result->fetch_assoc();
            $idUsuario = $row['idEstudiante'];
            $nombre = $row['nombre'];
        }
        
        // Buscar en padres si no se encontró en estudiantes
        if (!$encontrado) {
            $sql = "SELECT idPadre, nombre FROM padres WHERE correo = ?";
            $stmt = $conexion->prepare($sql);
            $stmt->bind_param("s", $correo);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $encontrado = true;
                $tipoUsuario = 'padre';
                $row = $result->fetch_assoc();
                $idUsuario = $row['idPadre'];
                $nombre = $row['nombre'];
            }
        }
        
        // Buscar en usuarios (maestros/admins) si no se encontró en las anteriores
        if (!$encontrado) {
            $sql = "SELECT idUsuario, nombre FROM usuarios WHERE correo = ?";
            $stmt = $conexion->prepare($sql);
            $stmt->bind_param("s", $correo);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $encontrado = true;
                $tipoUsuario = 'usuario';
                $row = $result->fetch_assoc();
                $idUsuario = $row['idUsuario'];
                $nombre = $row['nombre'];
            }
        }
        
        if ($encontrado) {
            // Generar token único
            $token = bin2hex(random_bytes(32));
            $expiracion = date("Y-m-d H:i:s", strtotime("+1 hour"));
            
            // Guardar token en la base de datos
            $sql = "INSERT INTO recuperacion_contraseñas (correo, token, expiracion, tipo_usuario, id_usuario) 
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $conexion->prepare($sql);
            $stmt->bind_param("ssssi", $correo, $token, $expiracion, $tipoUsuario, $idUsuario);
            
            if ($stmt->execute()) {
                // Enviar correo con el enlace de recuperación
                $asunto = "Recuperación de contraseña - EduSphere";
                $enlace = "https://tudominio.com/nuevaContraseña.php?token=$token";
                $mensaje = "Hola $nombre,\n\n";
                $mensaje .= "Hemos recibido una solicitud para restablecer tu contraseña en EduSphere.\n";
                $mensaje .= "Por favor haz clic en el siguiente enlace para crear una nueva contraseña:\n";
                $mensaje .= "$enlace\n\n";
                $mensaje .= "Si no solicitaste este cambio, puedes ignorar este mensaje.\n";
                $mensaje .= "El enlace expirará en 1 hora.\n\n";
                $mensaje .= "Atentamente,\nEl equipo de EduSphere";
                
                $headers = "From: no-reply@edusphere.com";
                
                if (mail($correo, $asunto, $mensaje, $headers)) {
                    $success = "Se ha enviado un correo con instrucciones para restablecer tu contraseña.";
                } else {
                    $error = "Error al enviar el correo. Por favor intenta nuevamente.";
                }
            } else {
                $error = "Error al procesar la solicitud. Por favor intenta nuevamente.";
            }
        } else {
            $error = "No se encontró una cuenta asociada a este correo electrónico.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña - EduSphere</title>
    <link rel="stylesheet" href="../css/Contraseñas.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    li
</head>

<body>
    <div class="recovery-container">
        <img src="../IMAGENES/logo.gif" alt="EDUSPHERE" class="logo">
        <h1>Recuperar Contraseña</h1>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
            </div>
        <?php else: ?>
            <p>Ingresa tu correo electrónico y te enviaremos un enlace para restablecer tu contraseña.</p>
            
            <form method="POST" class="recovery-form">
                <div class="form-group">
                    <label for="correo"><i class="fas fa-envelope"></i> Correo Electrónico:</label>
                    <input type="email" id="correo" name="correo" value="<?= htmlspecialchars($correo) ?>" required>
                </div>
                
                <button type="submit" class="btn">
                    <i class="fas fa-paper-plane"></i> Enviar enlace de recuperación
                </button>
            </form>
        <?php endif; ?>
        
        <a href="../html/INICIO.html" class="back-link">
            <i class="fas fa-arrow-left"></i> Volver al inicio de sesión
        </a>
    </div>
</body>

</html>