<?php
include 'Conexion.php';

// Configurar para mostrar errores (solo en desarrollo)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Validar y obtener el ID de la materia
$idMateria = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$idMateria) {
    header('Location: verMaterias.php?error=id_invalido');
    exit();
}

// Verificar si la materia existe antes de intentar eliminarla
$sqlCheck = "SELECT idMateria FROM Materias WHERE idMateria = ?";
$stmtCheck = $conexion->prepare($sqlCheck);
$stmtCheck->bind_param("i", $idMateria);
$stmtCheck->execute();

if ($stmtCheck->get_result()->num_rows === 0) {
    header('Location: verMaterias.php?error=materia_no_encontrada');
    exit();
}

// Iniciar transacción para asegurar la integridad de los datos
$conexion->begin_transaction();

try {
    // Primero eliminar las calificaciones relacionadas con la materia
    $sqlDeleteCalificaciones = "DELETE FROM Calificaciones WHERE idTarea IN (SELECT idTarea FROM Tareas WHERE idMateria = ?)";
    $stmtCalificaciones = $conexion->prepare($sqlDeleteCalificaciones);
    $stmtCalificaciones->bind_param("i", $idMateria);
    $stmtCalificaciones->execute();
    
    // Eliminar las entregas de tareas relacionadas
    $sqlDeleteEntregas = "DELETE FROM Entregas WHERE idTarea IN (SELECT idTarea FROM Tareas WHERE idMateria = ?)";
    $stmtEntregas = $conexion->prepare($sqlDeleteEntregas);
    $stmtEntregas->bind_param("i", $idMateria);
    $stmtEntregas->execute();
    
    // Eliminar las tareas de la materia
    $sqlDeleteTareas = "DELETE FROM Tareas WHERE idMateria = ?";
    $stmtTareas = $conexion->prepare($sqlDeleteTareas);
    $stmtTareas->bind_param("i", $idMateria);
    $stmtTareas->execute();
    
    // Finalmente, eliminar la materia
    $sqlDeleteMateria = "DELETE FROM Materias WHERE idMateria = ?";
    $stmtMateria = $conexion->prepare($sqlDeleteMateria);
    $stmtMateria->bind_param("i", $idMateria);
    $stmtMateria->execute();
    
    // Confirmar la transacción si todo salió bien
    $conexion->commit();
    
    header('Location: verMaterias.php?eliminacion=exitosa');
    exit();
    
} catch (mysqli_sql_exception $e) {
    // Revertir la transacción en caso de error
    $conexion->rollback();
    
    error_log("Error al eliminar materia: " . $e->getMessage());
    header('Location: verMaterias.php?error=eliminacion');
    exit();
}