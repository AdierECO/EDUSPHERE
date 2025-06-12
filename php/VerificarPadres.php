<?php
session_start();
require_once 'Conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar campos vacíos primero
    if (empty($_POST['username']) || empty($_POST['password'])) {
        header("Location: ../html/loginPadres.html?error=campos_vacios");
        exit();
    }

    // Validar y limpiar los datos de entrada
    $correo = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        header("Location: ../html/loginPadres.html?error=credenciales");
        exit();
    }

    try {
        // Consulta SQL para obtener el padre por correo
        $sql = "SELECT idPadre, nombre, apellidos, correoEstudiante, contrasena 
                FROM Padres 
                WHERE correo = ?";
        $stmt = $conexion->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Error al preparar la consulta: " . $conexion->error);
        }

        $stmt->bind_param("s", $correo);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            header("Location: ../html/loginPadres.html?error=no_encontrado");
            exit();
        }

        $row = $result->fetch_assoc();
        
        if (!password_verify($password, $row['contrasena'])) {
            header("Location: ../html/loginPadres.html?error=credenciales");
            exit();
        }

        // Autenticación exitosa
        $_SESSION['idPadre'] = $row['idPadre'];
        $_SESSION['nombre'] = $row['nombre'];
        $_SESSION['apellidos'] = $row['apellidos'];
        $_SESSION['correo'] = $correo;
        $_SESSION['correoEstudiante'] = $row['correoEstudiante'];

        header("Location: ../html/PADRES.php");
        exit();

    } catch (Exception $e) {
        error_log("Error en VerificarPadres.php: " . $e->getMessage());
        header("Location: ../html/loginPadres.html?error=credenciales");
        exit();
    }
} else {
    // Método de solicitud no válido
    header("Location: ../html/loginPadres.html?error=metodo_no_valido");
    exit();
}
?>