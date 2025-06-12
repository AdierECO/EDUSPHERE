<?php
include '../php/Conexion.php';

// Verificar si se proporcionó un ID válido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: verMaestros.php?error=invalid_id');
    exit();
}

$idMaestro = (int)$_GET['id'];

// Iniciar transacción para asegurar la integridad
$conexion->begin_transaction();

try {
    // 1. Verificar si el docente existe
    $sqlCheck = "SELECT idUsuario FROM usuarios WHERE idUsuario = ? AND rol = 'Maestro'";
    $stmtCheck = $conexion->prepare($sqlCheck);
    $stmtCheck->bind_param("i", $idMaestro);
    $stmtCheck->execute();
    
    if ($stmtCheck->get_result()->num_rows === 0) {
        throw new Exception("El docente no existe o no tiene el rol correcto");
    }

    // 2. Manejar relaciones específicas sin afectar otros registros
    
    // a) Desvincular grupos (establecer a NULL o a otro docente)
    $sqlUpdateGrupos = "UPDATE grupos SET id_maestro = NULL WHERE id_maestro = ?";
    $stmtGrupos = $conexion->prepare($sqlUpdateGrupos);
    $stmtGrupos->bind_param("i", $idMaestro);
    $stmtGrupos->execute();
    
    // b) Desvincular materias (establecer a NULL o a otro docente)
    $sqlUpdateMaterias = "UPDATE materias SET id_maestro = NULL WHERE id_maestro = ?";
    $stmtMaterias = $conexion->prepare($sqlUpdateMaterias);
    $stmtMaterias->bind_param("i", $idMaestro);
    $stmtMaterias->execute();
    
    // c) Eliminar solo avisos de este docente
    $sqlDeleteAvisos = "DELETE FROM avisos WHERE idUsuario = ?";
    $stmtAvisos = $conexion->prepare($sqlDeleteAvisos);
    $stmtAvisos->bind_param("i", $idMaestro);
    $stmtAvisos->execute();
    
    // d) Eliminar solo tareas de este docente
    $sqlDeleteTareas = "DELETE FROM tareas WHERE idUsuario = ?";
    $stmtTareas = $conexion->prepare($sqlDeleteTareas);
    $stmtTareas->bind_param("i", $idMaestro);
    $stmtTareas->execute();

    // 3. Finalmente, eliminar al docente
    $sqlDeleteDocente = "DELETE FROM usuarios WHERE idUsuario = ? AND rol = 'Maestro'";
    $stmtDeleteDocente = $conexion->prepare($sqlDeleteDocente);
    $stmtDeleteDocente->bind_param("i", $idMaestro);
    $stmtDeleteDocente->execute();

    // Confirmar transacción
    $conexion->commit();
    
    // Redirigir con éxito
    header('Location: verMaestros.php?success=1');
    exit();

} catch (Exception $e) {
    // Revertir transacción en caso de error
    $conexion->rollback();
    
    // Registrar error
    error_log("Error al eliminar docente ID $idMaestro: " . $e->getMessage());
    
    // Redirigir con error
    header('Location: verMaestros.php?error=delete_failed&details=' . urlencode($e->getMessage()));
    exit();
}
?>