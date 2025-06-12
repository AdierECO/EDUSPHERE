<?php
include 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrarUsuario'])) {
    // Validar y sanitizar datos
    $matricula = filter_input(INPUT_POST, 'matricula', FILTER_SANITIZE_NUMBER_INT);
    $nombre = filter_input(INPUT_POST, 'nombre', FILTER_SANITIZE_STRING);
    $apellidos = filter_input(INPUT_POST, 'apellidos', FILTER_SANITIZE_STRING);
    $correo = filter_input(INPUT_POST, 'correo', FILTER_SANITIZE_EMAIL);
    $contrasena = $_POST['contrasena'];
    $rol = 'Maestro';

    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        header('Location: ../html/registrarMaestro.html?error=correo_invalido');
        exit();
    }

    // Verificar matrícula única
    $sqlCheckMatricula = "SELECT idUsuario FROM Usuarios WHERE idUsuario = ?";
    $stmtCheckMatricula = $conexion->prepare($sqlCheckMatricula);
    $stmtCheckMatricula->bind_param("i", $matricula);
    $stmtCheckMatricula->execute();
    
    if ($stmtCheckMatricula->get_result()->num_rows > 0) {
        header('Location: ../html/registrarMaestro.html?error=matricula_existente');
        exit();
    }

    // Verificar correo único
    $sqlCheckEmail = "SELECT correo FROM Usuarios WHERE correo = ?";
    $stmtCheckEmail = $conexion->prepare($sqlCheckEmail);
    $stmtCheckEmail->bind_param("s", $correo);
    $stmtCheckEmail->execute();
    
    if ($stmtCheckEmail->get_result()->num_rows > 0) {
        header('Location: ../html/registrarMaestro.html?error=correo_existente');
        exit();
    }

    // Hash de contraseña
    $contrasenaHash = password_hash($contrasena, PASSWORD_DEFAULT);

    // Insertar con consulta preparada
    $sql = "INSERT INTO Usuarios (idUsuario, nombre, apellidos, rol, correo, contrasena) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("isssss", $matricula, $nombre, $apellidos, $rol, $correo, $contrasenaHash);
    
    if ($stmt->execute()) {
        header('Location: ../php/verMaestros.php?success=registro_exitoso');
        exit();
    } else {
        header('Location: ../html/registrarMaestro.html?error=error_registro');
        exit();
    }
} else {
    header('Location: ../html/registrarMaestro.html');
    exit();
}
?>