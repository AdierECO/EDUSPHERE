<?php
include 'conexion.php';

// Configurar para mostrar errores (solo en desarrollo)
ini_set('display_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrarPadre'])) {
    // Sanitizar y validar datos
    $nombre = filter_input(INPUT_POST, 'nombre', FILTER_SANITIZE_STRING);
    $apellidos = filter_input(INPUT_POST, 'apellidos', FILTER_SANITIZE_STRING);
    $correo = filter_input(INPUT_POST, 'correo', FILTER_SANITIZE_EMAIL);
    $contrasena = $_POST['contrasena'];
    $correoEstudiante = filter_input(INPUT_POST, 'correoEstudiante', FILTER_SANITIZE_EMAIL);

    // Validaciones básicas
    if (empty($nombre) || empty($apellidos) || empty($correo) || empty($contrasena) || empty($correoEstudiante)) {
        header('Location: ../html/registrarPadre.php?error=campos_vacios');
        exit();
    }

    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        header('Location: ../html/registrarPadre.php?error=correo_invalido');
        exit();
    }

    // Verificar si el correo del padre ya existe
    $sqlCheckPadre = "SELECT idPadre FROM Padres WHERE correo = ?";
    $stmtCheckPadre = $conexion->prepare($sqlCheckPadre);
    $stmtCheckPadre->bind_param("s", $correo);
    $stmtCheckPadre->execute();
    
    if ($stmtCheckPadre->get_result()->num_rows > 0) {
        header('Location: ../html/registrarPadre.php?error=correo_existente');
        exit();
    }

    // Verificar si el estudiante existe
    $sqlCheckEstudiante = "SELECT idEstudiante FROM Estudiantes WHERE correo = ?";
    $stmtCheckEstudiante = $conexion->prepare($sqlCheckEstudiante);
    $stmtCheckEstudiante->bind_param("s", $correoEstudiante);
    $stmtCheckEstudiante->execute();
    $resultEstudiante = $stmtCheckEstudiante->get_result();

    if ($resultEstudiante->num_rows === 0) {
        header('Location: ../html/registrarPadre.php?error=estudiante_no_existe');
        exit();
    }

    // Hash de la contraseña
    $contrasenaHash = password_hash($contrasena, PASSWORD_DEFAULT);

    // Iniciar transacción
    $conexion->begin_transaction();

    try {
        // Insertar padre
        $sqlInsertPadre = "INSERT INTO Padres (nombre, apellidos, correo, contrasena, correoEstudiante) VALUES (?, ?, ?, ?, ?)";
        $stmtInsertPadre = $conexion->prepare($sqlInsertPadre);
        $stmtInsertPadre->bind_param("sssss", $nombre, $apellidos, $correo, $contrasenaHash, $correoEstudiante);
        
        if (!$stmtInsertPadre->execute()) {
            throw new Exception("Error al registrar al padre: " . $stmtInsertPadre->error);
        }

        $idPadre = $conexion->insert_id;
        $idEstudiante = $resultEstudiante->fetch_assoc()['idEstudiante'];

        // Crear relación padre-estudiante
        $sqlRelacionar = "INSERT INTO estudiantes_padres (idPadre, idEstudiante) VALUES (?, ?)";
        $stmtRelacionar = $conexion->prepare($sqlRelacionar);
        $stmtRelacionar->bind_param("ii", $idPadre, $idEstudiante);
        
        if (!$stmtRelacionar->execute()) {
            throw new Exception("Error al relacionar al padre con el estudiante: " . $stmtRelacionar->error);
        }

        // Confirmar transacción
        $conexion->commit();
        
        // Redirigir con éxito
        header('Location: ../php/verPadres.php?registro=exitoso');
        exit();

    } catch (Exception $e) {
        // Revertir transacción en caso de error
        $conexion->rollback();
        error_log("Error al registrar padre: " . $e->getMessage());
        header('Location: ../html/registrarPadre.php?error=error_registro');
        exit();
    }
} else {
    header('Location: ../html/registrarPadre.php');
    exit();
}
?>