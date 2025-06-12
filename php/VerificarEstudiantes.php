<?php
session_start(); // Iniciar la sesión
include 'Conexion.php'; // Asegúrate de que la ruta sea correcta

// Verificar si se enviaron los datos del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = $_POST['username']; // El campo "username" en el formulario es el correo
    $password = $_POST['password'];

    // Consulta SQL para obtener el estudiante por correo
    $sql = "SELECT idEstudiante, nombre, apellidos, idGrupo, contrasena FROM estudiantes WHERE correo = ?";
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
            $_SESSION['idEstudiante'] = $row['idEstudiante'];
            $_SESSION['nombre'] = $row['nombre'];
            $_SESSION['apellidos'] = $row['apellidos'];
            $_SESSION['correo'] = $correo;
            $_SESSION['idGrupo'] = $row['idGrupo'];

            // Redirigir al estudiante a su página correspondiente
            header('Location: ../html/Usuario.php'); // Cambia a Usuario.php
            exit();
        } else {
            header('Location: ../html/loginEstudiante.html?error=invalid_credentials');
            exit();
        }
    } else {
        header('Location: ../html/loginEstudiante.html?error=user_not_found');
        exit();
    }
} else {
    header('Location: ../html/loginEstudiante.html?error=invalid_request');
    exit();
}

$stmt->close();
$conexion->close();
?>