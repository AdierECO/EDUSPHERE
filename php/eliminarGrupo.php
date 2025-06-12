<?php
include 'Conexion.php';

// Verificar si se proporcionó el ID del grupo
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: verGrupos.php?error=ID de grupo no proporcionado");
    exit();
}

$idGrupo = $_GET['id'];

// Desactivar temporalmente las verificaciones de clave foránea
$conexion->query("SET FOREIGN_KEY_CHECKS = 0");

$conexion->begin_transaction();

try {
    // 1. Verificar existencia del grupo (como string)
    $checkGroup = $conexion->prepare("SELECT idGrupo FROM grupos WHERE idGrupo = ?");
    $checkGroup->bind_param("s", $idGrupo);
    $checkGroup->execute();
    
    if ($checkGroup->get_result()->num_rows === 0) {
        throw new Exception("El grupo no existe");
    }

    // 2. Obtener todos los estudiantes del grupo (como string)
    $getStudents = $conexion->prepare("SELECT idEstudiante FROM estudiantes WHERE idGrupo = ?");
    $getStudents->bind_param("s", $idGrupo);
    $getStudents->execute();
    $students = $getStudents->get_result()->fetch_all(MYSQLI_ASSOC);

    // 3. Para cada estudiante, eliminar sus relaciones (idEstudiante es integer)
    foreach ($students as $student) {
        $idEstudiante = $student['idEstudiante'];
        
        // Eliminar calificaciones (integer)
        $deleteGrades = $conexion->prepare("DELETE FROM calificaciones WHERE idEstudiante = ?");
        $deleteGrades->bind_param("i", $idEstudiante);
        $deleteGrades->execute();
        
        // Eliminar entregas de tareas (integer)
        $deleteDeliveries = $conexion->prepare("DELETE FROM entregas WHERE idEstudiante = ?");
        $deleteDeliveries->bind_param("i", $idEstudiante);
        $deleteDeliveries->execute();
        
        // Eliminar asistencias (integer)
        $deleteAttendances = $conexion->prepare("DELETE FROM asistencias WHERE idEstudiante = ?");
        $deleteAttendances->bind_param("i", $idEstudiante);
        $deleteAttendances->execute();
        
        // Eliminar relación con padres (integer)
        $deleteParentRelations = $conexion->prepare("DELETE FROM estudiantes_padres WHERE idEstudiante = ?");
        $deleteParentRelations->bind_param("i", $idEstudiante);
        $deleteParentRelations->execute();
    }

    // 4. Eliminar estudiantes del grupo (como string)
    $deleteStudents = $conexion->prepare("DELETE FROM estudiantes WHERE idGrupo = ?");
    $deleteStudents->bind_param("s", $idGrupo);
    $deleteStudents->execute();

    // 5. Eliminar tareas del grupo (como string)
    $deleteTasks = $conexion->prepare("DELETE FROM tareas WHERE idGrupo = ?");
    $deleteTasks->bind_param("s", $idGrupo);
    $deleteTasks->execute();

    // 6. Eliminar materias del grupo (como string)
    $deleteSubjects = $conexion->prepare("DELETE FROM materias WHERE idGrupo = ?");
    $deleteSubjects->bind_param("s", $idGrupo);
    $deleteSubjects->execute();

    // 7. Eliminar avisos del grupo (como integer - necesita conversión)
    // Primero obtener el ID numérico del grupo si es necesario
    // O usar directamente el string si la columna fue modificada a VARCHAR
    $deleteNotices = $conexion->prepare("DELETE FROM avisos WHERE idGrupo = ?");
    $deleteNotices->bind_param("s", $idGrupo);
    $deleteNotices->execute();

    // 8. Actualizar usuarios (maestros) asignados a este grupo (como string)
    $updateUsers = $conexion->prepare("UPDATE usuarios SET idGrupo = NULL WHERE idGrupo = ?");
    $updateUsers->bind_param("s", $idGrupo);
    $updateUsers->execute();

    // 9. Eliminar el grupo (como string)
    $deleteGroup = $conexion->prepare("DELETE FROM grupos WHERE idGrupo = ?");
    $deleteGroup->bind_param("s", $idGrupo);
    $deleteGroup->execute();

    $conexion->commit();
    
    // Redireccionar con éxito
    header("Location: verGrupos.php?success=Grupo eliminado correctamente con todos sus datos");
    exit();

} catch (Exception $e) {
    $conexion->rollback();
    header("Location: verGrupos.php?error=" . urlencode($e->getMessage()));
    exit();
} finally {
    // Reactivar verificaciones de clave foránea
    $conexion->query("SET FOREIGN_KEY_CHECKS = 1");
}
?>