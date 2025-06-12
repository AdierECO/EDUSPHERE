<?php
include '../php/Conexion.php';

// Verificar si se proporcionó un ID válido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: verPadres.php?error=invalid_id');
    exit();
}

$idPadre = (int)$_GET['id'];

// Iniciar transacción para asegurar la integridad de los datos
$conexion->begin_transaction();

try {
    // 1. Verificar si el padre existe
    $sqlCheck = "SELECT idPadre FROM Padres WHERE idPadre = ?";
    $stmtCheck = $conexion->prepare($sqlCheck);
    $stmtCheck->bind_param("i", $idPadre);
    $stmtCheck->execute();
    
    if ($stmtCheck->get_result()->num_rows === 0) {
        throw new Exception("El padre no existe");
    }

    // 2. Eliminar la relación en estudiantes_padres
    $sqlDeleteRelacion = "DELETE FROM estudiantes_padres WHERE idPadre = ?";
    $stmtDeleteRelacion = $conexion->prepare($sqlDeleteRelacion);
    if (!$stmtDeleteRelacion) {
        throw new Exception("Error preparando eliminación de relación: " . $conexion->error);
    }
    $stmtDeleteRelacion->bind_param("i", $idPadre);
    if (!$stmtDeleteRelacion->execute()) {
        throw new Exception("Error eliminando relación: " . $stmtDeleteRelacion->error);
    }

    // 3. Finalmente, eliminar al padre de la tabla Padres
    $sqlDeletePadre = "DELETE FROM Padres WHERE idPadre = ?";
    $stmtDeletePadre = $conexion->prepare($sqlDeletePadre);
    if (!$stmtDeletePadre) {
        throw new Exception("Error preparando eliminación del padre: " . $conexion->error);
    }
    $stmtDeletePadre->bind_param("i", $idPadre);
    if (!$stmtDeletePadre->execute()) {
        throw new Exception("Error eliminando padre: " . $stmtDeletePadre->error);
    }

    // Confirmar la transacción si todo fue exitoso
    $conexion->commit();
    
    // Redirigir con mensaje de éxito
    header('Location: verPadres.php?eliminacion=exitosa');
    exit();

} catch (Exception $e) {
    // Revertir la transacción en caso de error
    $conexion->rollback();
    
    // Registrar error en logs
    error_log("Error al eliminar padre ID $idPadre: " . $e->getMessage());
    
    // Redirigir con error específico
    header('Location: verPadres.php?error=delete_failed');
    exit();
}
?>