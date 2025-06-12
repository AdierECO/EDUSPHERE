<?php
include '../php/Conexion.php';

// Verificar si se proporcionó un ID válido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: verEstudiantes.php?error=invalid_id');
    exit();
}

$idEstudiante = (int)$_GET['id'];

// Iniciar transacción para asegurar la integridad de los datos
$conexion->begin_transaction();

try {
    // 1. Verificar si el estudiante existe
    $sqlCheck = "SELECT idEstudiante FROM Estudiantes WHERE idEstudiante = ?";
    $stmtCheck = $conexion->prepare($sqlCheck);
    $stmtCheck->bind_param("i", $idEstudiante);
    $stmtCheck->execute();
    
    if ($stmtCheck->get_result()->num_rows === 0) {
        throw new Exception("El estudiante no existe");
    }

    // 2. Eliminar las calificaciones del estudiante
    $sqlDeleteCalificaciones = "DELETE FROM calificaciones WHERE idEstudiante = ?";
    $stmtDeleteCalificaciones = $conexion->prepare($sqlDeleteCalificaciones);
    if (!$stmtDeleteCalificaciones) {
        throw new Exception("Error preparando eliminación de calificaciones: " . $conexion->error);
    }
    $stmtDeleteCalificaciones->bind_param("i", $idEstudiante);
    if (!$stmtDeleteCalificaciones->execute()) {
        throw new Exception("Error eliminando calificaciones: " . $stmtDeleteCalificaciones->error);
    }

    // 3. Eliminar las asistencias del estudiante
    $sqlDeleteAsistencias = "DELETE FROM asistencias WHERE idEstudiante = ?";
    $stmtDeleteAsistencias = $conexion->prepare($sqlDeleteAsistencias);
    if (!$stmtDeleteAsistencias) {
        throw new Exception("Error preparando eliminación de asistencias: " . $conexion->error);
    }
    $stmtDeleteAsistencias->bind_param("i", $idEstudiante);
    if (!$stmtDeleteAsistencias->execute()) {
        throw new Exception("Error eliminando asistencias: " . $stmtDeleteAsistencias->error);
    }

    // 4. Eliminar las entregas de tareas del estudiante
    $sqlDeleteEntregas = "DELETE FROM entregas WHERE idEstudiante = ?";
    $stmtDeleteEntregas = $conexion->prepare($sqlDeleteEntregas);
    if (!$stmtDeleteEntregas) {
        throw new Exception("Error preparando eliminación de entregas: " . $conexion->error);
    }
    $stmtDeleteEntregas->bind_param("i", $idEstudiante);
    if (!$stmtDeleteEntregas->execute()) {
        throw new Exception("Error eliminando entregas: " . $stmtDeleteEntregas->error);
    }

    // 5. Eliminar la relación con padres
    $sqlDeleteRelacionPadres = "DELETE FROM estudiantes_padres WHERE idEstudiante = ?";
    $stmtDeleteRelacionPadres = $conexion->prepare($sqlDeleteRelacionPadres);
    if (!$stmtDeleteRelacionPadres) {
        throw new Exception("Error preparando eliminación de relación con padres: " . $conexion->error);
    }
    $stmtDeleteRelacionPadres->bind_param("i", $idEstudiante);
    if (!$stmtDeleteRelacionPadres->execute()) {
        throw new Exception("Error eliminando relación con padres: " . $stmtDeleteRelacionPadres->error);
    }

    // 6. Finalmente, eliminar al estudiante
    $sqlDeleteEstudiante = "DELETE FROM Estudiantes WHERE idEstudiante = ?";
    $stmtDeleteEstudiante = $conexion->prepare($sqlDeleteEstudiante);
    if (!$stmtDeleteEstudiante) {
        throw new Exception("Error preparando eliminación del estudiante: " . $conexion->error);
    }
    $stmtDeleteEstudiante->bind_param("i", $idEstudiante);
    if (!$stmtDeleteEstudiante->execute()) {
        throw new Exception("Error eliminando estudiante: " . $stmtDeleteEstudiante->error);
    }

    // Confirmar la transacción si todo fue exitoso
    $conexion->commit();
    
    // Redirigir con mensaje de éxito
    header('Location: verEstudiantes.php?eliminacion=exitosa');
    exit();

} catch (Exception $e) {
    // Revertir la transacción en caso de error
    $conexion->rollback();
    
    // Registrar error en logs
    error_log("Error al eliminar estudiante ID $idEstudiante: " . $e->getMessage());
    
    // Redirigir con error específico
    header('Location: verEstudiantes.php?error=delete_failed&message=' . urlencode($e->getMessage()));
    exit();
}
?>