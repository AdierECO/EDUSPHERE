<?php
session_start();
include 'Conexion.php';

// Verificar si se enviaron los datos del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = $_POST['username'];
    $password = $_POST['password'];

    // Consulta SQL para obtener el usuario por correo
    $sql = "SELECT idUsuario, nombre, apellidos, rol, contrasena FROM Usuarios WHERE correo = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("s", $correo);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $contrasenaHash = $row['contrasena'];

        // Verificar la contraseña
        if (password_verify($password, $contrasenaHash)) {
            // Credenciales válidas
            $_SESSION['idUsuario'] = $row['idUsuario'];
            $_SESSION['nombre'] = $row['nombre'];
            $_SESSION['apellidos'] = $row['apellidos'];
            $_SESSION['correo'] = $correo;
            $_SESSION['rol'] = $row['rol'];

            // Redirigir según el rol
            switch ($row['rol']) {
                case 'Admin':
                    header('Location: ../html/Admin.php');
                    exit();
                case 'Maestro':
                    header('Location: ../html/Docente.php');
                    exit();
                default:
                    header('Location: ../html/loginDocentesAdmins.html?error=invalid_role');
                    exit();
            }
        } else {
            // Contraseña incorrecta
            header('Location: ../html/loginDocentesAdmins.html?error=invalid_credentials');
            exit();
        }
    } else {
        // Usuario no encontrado
        header('Location: ../html/loginDocentesAdmins.html?error=user_not_found');
        exit();
    }
} else {
    // Método de solicitud no válido
    header('Location: ../html/loginDocentesAdmins.html?error=invalid_request');
    exit();
}
?>