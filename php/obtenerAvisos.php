<?php
session_start();
require_once 'Conexion.php';

if (!isset($_SESSION['idPadre'])) {
    die(json_encode(['error' => 'Acceso no autorizado']));
}

$padreId = $_GET['padreId'];

// Obtener avisos no leídos
$sqlAvisos = "SELECT a.id, a.mensaje, a.fecha_publicacion, a.prioridad, 
               m.nombreMateria, g.idGrupo as grupo
        FROM avisos a
        LEFT JOIN materias m ON a.idGrupo = m.idGrupo
        LEFT JOIN grupos g ON a.idGrupo = g.idGrupo
        WHERE a.idGrupo IN (
            SELECT e.idGrupo 
            FROM estudiantes e
            JOIN estudiantes_padres ep ON e.idEstudiante = ep.idEstudiante
            WHERE ep.idPadre = ?
        )
        AND a.id NOT IN (
            SELECT nv.id_notificacion 
            FROM notificaciones_vistas nv
            JOIN estudiantes_padres ep ON nv.idEstudiante = ep.idEstudiante
            WHERE ep.idPadre = ? AND nv.tipo_notificacion = 'aviso'
        )
        ORDER BY a.fecha_publicacion DESC
        LIMIT 5";

// Obtener tareas no leídas
$sqlTareas = "SELECT t.idTarea, t.titulo, t.fecha_publicacion, t.fecha_limite,
               m.nombreMateria, g.idGrupo as grupo
        FROM tareas t
        LEFT JOIN materias m ON t.idGrupo = m.idGrupo
        LEFT JOIN grupos g ON t.idGrupo = g.idGrupo
        WHERE t.idGrupo IN (
            SELECT e.idGrupo 
            FROM estudiantes e
            JOIN estudiantes_padres ep ON e.idEstudiante = ep.idEstudiante
            WHERE ep.idPadre = ?
        )
        AND t.idTarea NOT IN (
            SELECT nv.id_notificacion 
            FROM notificaciones_vistas nv
            JOIN estudiantes_padres ep ON nv.idEstudiante = ep.idEstudiante
            WHERE ep.idPadre = ? AND nv.tipo_notificacion = 'tarea'
        )
        ORDER BY t.fecha_publicacion DESC
        LIMIT 5";

// Preparar y ejecutar consulta de avisos
$stmtAvisos = $conexion->prepare($sqlAvisos);
$stmtAvisos->bind_param("ii", $padreId, $padreId);
$stmtAvisos->execute();
$avisos = $stmtAvisos->get_result()->fetch_all(MYSQLI_ASSOC);

// Preparar y ejecutar consulta de tareas
$stmtTareas = $conexion->prepare($sqlTareas);
$stmtTareas->bind_param("ii", $padreId, $padreId);
$stmtTareas->execute();
$tareas = $stmtTareas->get_result()->fetch_all(MYSQLI_ASSOC);

// Combinar y ordenar por fecha
$notificaciones = array_merge(
    array_map(function($aviso) {
        return [
            'tipo' => 'aviso',
            'id' => $aviso['id'],
            'titulo' => 'Aviso: ' . ($aviso['nombreMateria'] ?? 'General'),
            'mensaje' => $aviso['mensaje'],
            'fecha' => $aviso['fecha_publicacion'],
            'grupo' => $aviso['grupo'],
            'prioridad' => $aviso['prioridad'],
            'icono' => 'bell'
        ];
    }, $avisos),
    array_map(function($tarea) {
        return [
            'tipo' => 'tarea',
            'id' => $tarea['idTarea'],
            'titulo' => 'Tarea: ' . $tarea['nombreMateria'],
            'mensaje' => $tarea['titulo'] . ' (Entrega: ' . date('d/m/Y', strtotime($tarea['fecha_limite'])) . ')',
            'fecha' => $tarea['fecha_publicacion'],
            'grupo' => $tarea['grupo'],
            'prioridad' => 'media', // Prioridad media por defecto para tareas
            'icono' => 'tasks'
        ];
    }, $tareas)
);

// Ordenar por fecha descendente
usort($notificaciones, function($a, $b) {
    return strtotime($b['fecha']) - strtotime($a['fecha']);
});

if (empty($notificaciones)) {
    echo '<div class="notification-item">
            <i class="fas fa-check-circle prioridad-baja"></i>
            <div class="notification-content">
                <p class="notification-message">No tienes notificaciones nuevas</p>
            </div>
          </div>';
} else {
    foreach ($notificaciones as $notif) {
        // Determinar clase CSS según prioridad
        $clasePrioridad = '';
        switch ($notif['prioridad']) {
            case 'alta': $clasePrioridad = 'prioridad-alta'; break;
            case 'media': $clasePrioridad = 'prioridad-media'; break;
            default: $clasePrioridad = 'prioridad-baja';
        }
        
        echo '<div class="notification-item no-leido">
                <i class="fas fa-'.$notif['icono'].' '.$clasePrioridad.'"></i>
                <div class="notification-content">
                    <p class="notification-message"><strong>'.$notif['titulo'].'</strong> - '.htmlspecialchars($notif['mensaje']).'</p>
                    <small class="notification-time">
                        '.date('d/m/Y H:i', strtotime($notif['fecha'])).' · 
                        '.$notif['grupo'].'
                    </small>
                </div>
              </div>';
    }
}
?>