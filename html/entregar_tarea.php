<?php
session_start();

if (!isset($_SESSION['idEstudiante'])) {
    header("Location: loginEstudiante.html");
    exit();
}

include '../php/Conexion.php';

$idMateria = $_GET['idMateria'];
$idEstudiante = $_SESSION['idEstudiante'];

// Obtener información del estudiante
$sqlEstudiante = "SELECT nombre, apellidos, idGrupo, foto_perfil FROM estudiantes WHERE idEstudiante = ?";
$stmtEstudiante = $conexion->prepare($sqlEstudiante);
$stmtEstudiante->bind_param("i", $idEstudiante);
$stmtEstudiante->execute();
$resultEstudiante = $stmtEstudiante->get_result();

if ($resultEstudiante->num_rows > 0) {
    $estudiante = $resultEstudiante->fetch_assoc();
    $nombre = $estudiante['nombre'];
    $apellidos = $estudiante['apellidos'];
    $idGrupo = $estudiante['idGrupo'];
    $foto_perfil = $estudiante['foto_perfil'] ?? 'default.png';
} else {
    header("Location: loginEstudiante.html");
    exit();
}

// Obtener tareas de la materia
$sqlTareas = "SELECT t.idTarea, t.titulo, t.descripcion, t.fecha_publicacion,
                     t.calificacion as calificacion_maxima,
                     u.nombre as profesor_nombre, u.apellidos as profesor_apellidos
              FROM tareas t
              JOIN usuarios u ON t.idUsuario = u.idUsuario
              WHERE t.idMateria = ? AND t.idGrupo = ?";
$stmtTareas = $conexion->prepare($sqlTareas);
$stmtTareas->bind_param("is", $idMateria, $idGrupo);
$stmtTareas->execute();
$tareas = $stmtTareas->get_result()->fetch_all(MYSQLI_ASSOC);

// Obtener entregas existentes del estudiante
$sqlEntregas = "SELECT e.*, t.titulo as tarea_titulo
                FROM entregas e
                JOIN tareas t ON e.idTarea = t.idTarea
                WHERE e.idEstudiante = ? AND t.idMateria = ?";
$stmtEntregas = $conexion->prepare($sqlEntregas);
$stmtEntregas->bind_param("ii", $idEstudiante, $idMateria);
$stmtEntregas->execute();
$entregas = $stmtEntregas->get_result()->fetch_all(MYSQLI_ASSOC);

// Crear un array asociativo para fácil acceso a las entregas
$entregasPorTarea = [];
foreach ($entregas as $entrega) {
    $entregasPorTarea[$entrega['idTarea']] = $entrega;
}

// Manejar mensajes de éxito/error
$mensaje = '';
$claseMensaje = '';
if (isset($_GET['success'])) {
    $mensaje = '¡Tarea entregada correctamente!';
    $claseMensaje = 'success';
} elseif (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'ya_entregado':
            $mensaje = 'Error: Ya has entregado esta tarea';
            break;
        case 'formato':
            $mensaje = 'Error: Formato de archivo no permitido';
            break;
        case 'tamano':
            $mensaje = 'Error: El archivo excede el tamaño máximo (20MB)';
            break;
        case 'subida':
            $mensaje = 'Error: Hubo un problema al subir el archivo';
            break;
        case 'db':
            $mensaje = 'Error: Problema con la base de datos';
            break;
        default:
            $mensaje = 'Error: Ocurrió un problema al procesar tu entrega';
    }
    $claseMensaje = 'error';
}

$stmtEstudiante->close();
$stmtTareas->close();
$stmtEntregas->close();
$conexion->close();

// Función para obtener el ícono según la extensión del archivo
function obtenerIconoArchivo($archivo)
{
    $extension = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));

    $iconos = [
        'pdf' => 'fa-file-pdf',
        'doc' => 'fa-file-word',
        'docx' => 'fa-file-word',
        'xls' => 'fa-file-excel',
        'xlsx' => 'fa-file-excel',
        'ppt' => 'fa-file-powerpoint',
        'pptx' => 'fa-file-powerpoint',
        'zip' => 'fa-file-archive',
        'rar' => 'fa-file-archive'
    ];

    return isset($iconos[$extension]) ? $iconos[$extension] : 'fa-file';
}

// Función para extraer texto de archivos DOCX (simplificada)
function extraerTextoDocx($filePath)
{
    $content = '';
    $zip = zip_open($filePath);

    if ($zip && is_resource($zip)) {
        while ($zipEntry = zip_read($zip)) {
            if (zip_entry_name($zipEntry) == "word/document.xml") {
                $content = zip_entry_read($zipEntry, zip_entry_filesize($zipEntry));
                $content = strip_tags($content);
                $content = preg_replace('/[^\S\n]+/', ' ', $content);
                $content = preg_replace('/\n+/', "\n", $content);
                break;
            }
        }
        zip_close($zip);
    }

    return substr($content, 0, 1000) . (strlen($content) > 1000 ? '...' : '');
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entregar Tarea - EDUSPHERE</title>
    <link rel="stylesheet" href="../css/entregar_tarea.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <header class="header">
        <img src="../IMAGENES/logo.gif" alt="EDUSPHERE" class="logo">
        <h2 class="header-title">EDUSPHERE</h2>
        <nav class="nav-bar">
            <a href="Usuario.php" class="nav-home"><i class="fas fa-home"></i> Inicio</a>
            <a href="Perfil.php"><i class="fas fa-user"></i> Perfil</a>
            <a href="Notificaciones.php"><i class="fas fa-bell"></i> Notificaciones</a>
        </nav>
        <div class="user-info">
            <div class="user-dropdown">
                <div class="user-display">
                    <span><?php echo htmlspecialchars($nombre . ' ' . $apellidos); ?></span>
                    <img src="../IMAGENES/estudiantes/<?php echo $foto_perfil; ?>" alt="Foto perfil" class="profile-pic-header">
                    <i class="fas fa-chevron-down dropdown-icon"></i>
                </div>
                <div class="dropdown-menu">
                    <form action="../php/cerrar_sesion.php" method="post">
                        <button type="submit" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <main class="container">
        <div class="entregas-container">
            <h1 class="tareas-title">Tareas</h1>

            <?php if (!empty($mensaje)): ?>
                <div class="mensaje <?php echo $claseMensaje; ?>">
                    <?php echo $mensaje; ?>
                </div>
            <?php endif; ?>

            <?php if (empty($tareas)): ?>
                <div class="no-tareas">
                    <p>No hay tareas asignadas para esta materia.</p>
                </div>
            <?php else: ?>
                <?php foreach ($tareas as $tarea): ?>
                    <div class="tarea-card">
                        <div class="tarea-header">
                            <h2 class="tarea-title"><span class="tarea-label">Tarea:</span> <span class="tarea-id"><?php echo htmlspecialchars($tarea['idTarea']); ?></span></h2>
                            <?php if (isset($entregasPorTarea[$tarea['idTarea']])): ?>
                                <span class="badge-entregado"><i class="fas fa-check-circle"></i> Entregado</span>
                            <?php else: ?>
                                <span class="badge-pendiente"><i class="fas fa-exclamation-circle"></i> Pendiente</span>
                            <?php endif; ?>
                        </div>

                        <div class="tarea-meta">
                            <span><i class="far fa-calendar-alt"></i> Publicado: <?php echo htmlspecialchars($tarea['fecha_publicacion']); ?></span>
                            <span><i class="fas fa-chalkboard-teacher"></i> Profesor: <?php echo htmlspecialchars($tarea['profesor_nombre'] . ' ' . $tarea['profesor_apellidos']); ?></span>
                        </div>

                        <div class="tarea-descripcion">
                            <?php echo nl2br(htmlspecialchars($tarea['descripcion'])); ?>
                        </div>

                        <div class="entrega-section">
                            <?php if (isset($entregasPorTarea[$tarea['idTarea']])): ?>
                                <button class="btn-submit toggle-preview-btn" onclick="togglePreview(<?php echo $tarea['idTarea']; ?>)">
                                    <i class="fas fa-eye"></i> Mostrar Entrega
                                </button>

                                <div id="preview-<?php echo $tarea['idTarea']; ?>" class="preview-container" style="display: none;">
                                    <div class="archivo-entregado">
                                        <i class="fas <?php echo obtenerIconoArchivo($entregasPorTarea[$tarea['idTarea']]['rutaArchivo']); ?>"></i>
                                        <span><?php echo htmlspecialchars(basename($entregasPorTarea[$tarea['idTarea']]['rutaArchivo'])); ?></span>
                                        <a href="../entregas/<?php echo htmlspecialchars($entregasPorTarea[$tarea['idTarea']]['rutaArchivo']); ?>"
                                            class="btn-submit" download>
                                            <i class="fas fa-download"></i> Descargar
                                        </a>
                                    </div>

                                    <?php
                                    $archivo = $entregasPorTarea[$tarea['idTarea']]['rutaArchivo'];
                                    $extension = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));
                                    $rutaCompleta = "../entregas/" . $archivo;
                                    ?>

                                    <?php if ($extension === 'pdf'): ?>
                                        <div class="pdf-preview">
                                            <iframe src="../entregas/<?php echo htmlspecialchars($archivo); ?>#toolbar=0"
                                                width="100%" height="500px"
                                                style="border: 1px solid #ddd; border-radius: 5px; margin-top: 15px;">
                                            </iframe>
                                        </div>
                                    <?php elseif ($extension === 'docx' || $extension === 'doc'): ?>
                                        <div class="docx-preview">
                                            <?php
                                            $textoDocx = file_exists($rutaCompleta) ? extraerTextoDocx($rutaCompleta) : '';
                                            ?>
                                            <div class="docx-content">
                                                <h4>Vista previa del contenido:</h4>
                                                <?php if (!empty($textoDocx)): ?>
                                                    <div class="docx-text">
                                                        <?php echo nl2br(htmlspecialchars($textoDocx)); ?>
                                                    </div>
                                                <?php else: ?>
                                                    <p>No se pudo extraer el contenido del documento.</p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="docx-actions">
                                                <a href="https://view.officeapps.live.com/op/embed.aspx?src=<?php echo urlencode('http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/../entregas/' . $archivo); ?>"
                                                    class="btn-submit" target="_blank">
                                                    <i class="fas fa-external-link-alt"></i> Ver en visor online
                                                </a>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <p class="no-preview">Vista previa no disponible para este tipo de archivo</p>
                                    <?php endif; ?>

                                    <?php if (!empty($entregasPorTarea[$tarea['idTarea']]['calificacion'])): ?>
                                        <div class="calificacion-container">
                                            <div class="calificacion-titulo"><i class="fas fa-star"></i> Calificación:</div>
                                            <div class="calificacion-valor">
                                                <?php echo htmlspecialchars($entregasPorTarea[$tarea['idTarea']]['calificacion']); ?> /
                                                <?php echo htmlspecialchars($tarea['calificacion_maxima']); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($entregasPorTarea[$tarea['idTarea']]['comentarios'])): ?>
                                        <div class="comentarios-profesor">
                                            <div class="comentarios-titulo"><i class="fas fa-comment"></i> Comentarios del profesor:</div>
                                            <p><?php echo nl2br(htmlspecialchars($entregasPorTarea[$tarea['idTarea']]['comentarios'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <form action="../php/subir_tarea.php" method="post" enctype="multipart/form-data" class="form-entrega">
                                    <input type="hidden" name="idTarea" value="<?php echo $tarea['idTarea']; ?>">
                                    <input type="hidden" name="idMateria" value="<?php echo $idMateria; ?>">

                                    <div class="file-input-container">
                                        <label class="file-input-label"><i class="fas fa-paperclip"></i> Archivo de la tarea</label>
                                        <input type="file" id="file-<?php echo $tarea['idTarea']; ?>"
                                            name="archivo" class="file-input" required
                                            accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.rar,.zip">
                                        <small>Formatos aceptados: PDF, Word, Excel, PowerPoint, ZIP, RAR (Máx. 20MB)</small>
                                    </div>

                                    <button type="submit" class="btn-submit">
                                        <i class="fas fa-paper-plane"></i> Enviar tarea
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // Mostrar/ocultar el menú desplegable
        document.querySelector('.user-display').addEventListener('click', function() {
            document.querySelector('.dropdown-menu').classList.toggle('show');
        });

        // Cerrar el menú si se hace clic fuera de él
        window.addEventListener('click', function(event) {
            if (!event.target.matches('.user-display') && !event.target.closest('.user-display')) {
                const dropdowns = document.querySelectorAll('.dropdown-menu');
                dropdowns.forEach(function(dropdown) {
                    if (dropdown.classList.contains('show')) {
                        dropdown.classList.remove('show');
                    }
                });
            }
        });

        function togglePreview(tareaId) {
            const preview = document.getElementById(`preview-${tareaId}`);
            const btn = document.querySelector(`button[onclick="togglePreview(${tareaId})"]`);

            if (preview.style.display === 'none' || !preview.style.display) {
                preview.style.display = 'block';
                btn.innerHTML = '<i class="fas fa-eye-slash"></i> Ocultar Entrega';
            } else {
                preview.style.display = 'none';
                btn.innerHTML = '<i class="fas fa-eye"></i> Mostrar Entrega';
            }
        }
    </script>
</body>
</html>