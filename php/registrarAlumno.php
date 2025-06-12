<?php
include '../php/Conexion.php';

// Configurar encabezados para evitar caché
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Función para redireccionar con mensaje de error
function redirectWithError($errorCode) {
    header("Location: ../html/registrarAlumno.php?error=" . urlencode($errorCode));
    exit();
}

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrarEstudiante'])) {
    // Validar campos obligatorios
    $requiredFields = ['nombre', 'apellidos', 'correo', 'contrasena'];
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            redirectWithError('campos_vacios');
        }
    }

    // Sanitizar y validar datos
    $nombre = trim(filter_input(INPUT_POST, 'nombre', FILTER_SANITIZE_STRING));
    $apellidos = trim(filter_input(INPUT_POST, 'apellidos', FILTER_SANITIZE_STRING));
    $correo = trim(filter_input(INPUT_POST, 'correo', FILTER_SANITIZE_EMAIL));
    $contrasena = $_POST['contrasena'];
    $idGrupo = trim(filter_input(INPUT_POST, 'idGrupo', FILTER_SANITIZE_STRING));

    // Validar formato del correo
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        redirectWithError('correo_invalido');
    }

    // Validar longitud mínima de contraseña
    if (strlen($contrasena) < 8) {
        redirectWithError('contrasena_corta');
    }

    // Verificar si el correo ya existe
    $sql_check = "SELECT idEstudiante FROM estudiantes WHERE correo = ?";
    $stmt_check = $conexion->prepare($sql_check);
    if ($stmt_check === false) {
        error_log("Error al preparar consulta: " . $conexion->error);
        redirectWithError('error_registro');
    }
    
    $stmt_check->bind_param("s", $correo);
    if (!$stmt_check->execute()) {
        error_log("Error al ejecutar consulta: " . $stmt_check->error);
        redirectWithError('error_registro');
    }
    
    $stmt_check->store_result();
    if ($stmt_check->num_rows > 0) {
        $stmt_check->close();
        redirectWithError('correo_existente');
    }
    $stmt_check->close();

    // Verificar si el grupo existe (si se proporcionó)
    if (!empty($idGrupo)) {
        $sql_group_check = "SELECT idGrupo FROM grupos WHERE idGrupo = ?";
        $stmt_group_check = $conexion->prepare($sql_group_check);
        if ($stmt_group_check === false) {
            error_log("Error al preparar consulta de grupo: " . $conexion->error);
            redirectWithError('error_registro');
        }
        
        $stmt_group_check->bind_param("s", $idGrupo);
        if (!$stmt_group_check->execute()) {
            error_log("Error al verificar grupo: " . $stmt_group_check->error);
            redirectWithError('error_registro');
        }
        
        $stmt_group_check->store_result();
        if ($stmt_group_check->num_rows == 0) {
            $stmt_group_check->close();
            redirectWithError('grupo_invalido');
        }
        $stmt_group_check->close();
    }

    // Hashear la contraseña
    $contrasenaHash = password_hash($contrasena, PASSWORD_DEFAULT);
    if ($contrasenaHash === false) {
        redirectWithError('error_registro');
    }

    // Iniciar transacción
    $conexion->begin_transaction();

    try {
        // Insertar el estudiante
        $sql = "INSERT INTO estudiantes (nombre, apellidos, correo, contrasena, idGrupo) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $conexion->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Error al preparar consulta: " . $conexion->error);
        }

        $stmt->bind_param("sssss", $nombre, $apellidos, $correo, $contrasenaHash, $idGrupo);

        if (!$stmt->execute()) {
            throw new Exception("Error al ejecutar consulta: " . $stmt->error);
        }

        $conexion->commit();
        header('Location: verEstudiantes.php?registro=exitoso');
        exit();
    } catch (Exception $e) {
        $conexion->rollback();
        error_log("Error al registrar estudiante: " . $e->getMessage());
        redirectWithError('error_registro');
    }
}

// Si no es POST, redirigir al formulario
header("Location: ../html/registrarAlumno.php");
exit();
?>