<?php
session_start();
require_once 'Conexion.php';

if (!isset($_GET['padreId'])) {
    die("Parámetro padreId faltante");
}

$idPadre = $_GET['padreId'];

// Obtener todos los estudiantes asociados al padre
$sqlEstudiantes = "SELECT e.idEstudiante 
                   FROM estudiantes e
                   JOIN estudiantes_padres ep ON e.idEstudiante = ep.idEstudiante
                   WHERE ep.idPadre = ?";
$stmtEstudiantes = $conexion->prepare($sqlEstudiantes);
$stmtEstudiantes->bind_param("i", $idPadre);
$stmtEstudiantes->execute();
$estudiantes = $stmtEstudiantes->get_result()->fetch_all(MYSQLI_ASSOC);

// Marcar avisos y tareas como leídos para cada estudiante
foreach ($estudiantes as $estudiante) {
    $idEstudiante = $estudiante['idEstudiante'];
    
    // Avisos no vistos
    $sqlAvisos = "INSERT INTO notificaciones_vistas (idEstudiante, tipo_notificacion, id_notificacion)
                  SELECT ?, 'aviso', a.id
                  FROM avisos a
                  WHERE a.idGrupo = (SELECT idGrupo FROM estudiantes WHERE idEstudiante = ?)
                  AND a.id NOT IN (
                      SELECT id_notificacion 
                      FROM notificaciones_vistas 
                      WHERE idEstudiante = ? AND tipo_notificacion = 'aviso'
                  )";
    
    // Tareas no vistas
    $sqlTareas = "INSERT INTO notificaciones_vistas (idEstudiante, tipo_notificacion, id_notificacion)
                  SELECT ?, 'tarea', t.idTarea
                  FROM tareas t
                  WHERE t.idGrupo = (SELECT idGrupo FROM estudiantes WHERE idEstudiante = ?)
                  AND t.idTarea NOT IN (
                      SELECT id_notificacion 
                      FROM notificaciones_vistas 
                      WHERE idEstudiante = ? AND tipo_notificacion = 'tarea'
                  )";
    
    $stmtAvisos = $conexion->prepare($sqlAvisos);
    $stmtAvisos->bind_param("iii", $idEstudiante, $idEstudiante, $idEstudiante);
    $stmtAvisos->execute();
    
    $stmtTareas = $conexion->prepare($sqlTareas);
    $stmtTareas->bind_param("iii", $idEstudiante, $idEstudiante, $idEstudiante);
    $stmtTareas->execute();
}

echo json_encode(['success' => true]);
?>