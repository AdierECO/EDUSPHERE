<?php
session_start();

if (!isset($_SESSION['idEstudiante'])) {
    header("Location: ../html/loginEstudiante.html");
    exit();
}

include '../php/Conexion.php';

if (!isset($_GET['idTarea'])) {
    header("Location: ../html/Usuario.php");
    exit();
}

$idTarea = $_GET['idTarea'];
$idEstudiante = $_SESSION['idEstudiante'];

// Obtener información de la tarea (incluyendo fecha_limite)
$sqlTarea = "SELECT t.idTarea, t.titulo, t.descripcion, t.idMateria, t.fecha_limite, m.nombreMateria
             FROM tareas t
             JOIN materias m ON t.idMateria = m.idMateria
             WHERE t.idTarea = ?";
$stmtTarea = $conexion->prepare($sqlTarea);
$stmtTarea->bind_param("i", $idTarea);
$stmtTarea->execute();
$resultTarea = $stmtTarea->get_result();

if ($resultTarea->num_rows == 0) {
    header("Location: Usuario.php");
    exit();
}

$tarea = $resultTarea->fetch_assoc();

// Verificar si ya existe entrega
$sqlVerificar = "SELECT idEntrega, rutaArchivo FROM entregas WHERE idTarea = ? AND idEstudiante = ?";
$stmtVerificar = $conexion->prepare($sqlVerificar);
$stmtVerificar->bind_param("ii", $idTarea, $idEstudiante);
$stmtVerificar->execute();
$resultVerificar = $stmtVerificar->get_result();

$yaEntregado = $resultVerificar->num_rows > 0;
$entregaActual = $yaEntregado ? $resultVerificar->fetch_assoc() : null;

// Verificar si la fecha límite ha pasado (solo comparando fechas, no horas)
$fechaLimitePasada = false;
if ($tarea['fecha_limite']) {
    $fechaLimite = new DateTime($tarea['fecha_limite']);
    $hoy = new DateTime();
    // Comparar solo las fechas (ignorando la hora)
    $fechaLimite->setTime(0, 0, 0);
    $hoy->setTime(0, 0, 0);
    $fechaLimitePasada = $hoy > $fechaLimite;
}

// Obtener el nombre, apellido, foto de perfil y grupo del estudiante
$sqlEstudiante = "SELECT nombre, apellidos, foto_perfil, idGrupo FROM estudiantes WHERE idEstudiante = ?";
$stmtEstudiante = $conexion->prepare($sqlEstudiante);
$stmtEstudiante->bind_param("i", $idEstudiante);
$stmtEstudiante->execute();
$resultEstudiante = $stmtEstudiante->get_result();

$nombre = 'Usuario';
$apellidos = '';
$foto_perfil = 'default.png';
$idGrupo = null;
if ($resultEstudiante->num_rows > 0) {
    $rowEstudiante = $resultEstudiante->fetch_assoc();
    $nombre = $rowEstudiante['nombre'];
    $apellidos = $rowEstudiante['apellidos'];
    $foto_perfil = $rowEstudiante['foto_perfil'];
    $idGrupo = $rowEstudiante['idGrupo'];
}

// Consulta para contar notificaciones no leídas
if ($idGrupo) {
    $sqlNotificacionesNoLeidas = "SELECT (
                                    SELECT COUNT(*) FROM (
                                        SELECT t.idTarea FROM tareas t
                                        WHERE t.idGrupo = ?
                                        AND NOT EXISTS (
                                            SELECT 1 FROM notificaciones_vistas nv
                                            WHERE nv.idEstudiante = ?
                                            AND nv.tipo_notificacion = 'tarea'
                                            AND nv.id_notificacion = t.idTarea
                                        )
                                        
                                        UNION ALL
                                        
                                        SELECT a.id FROM avisos a
                                        WHERE a.idGrupo = ?
                                        AND NOT EXISTS (
                                            SELECT 1 FROM notificaciones_vistas nv
                                            WHERE nv.idEstudiante = ?
                                            AND nv.tipo_notificacion = 'aviso'
                                            AND nv.id_notificacion = a.id
                                        )
                                    ) AS notificaciones_no_leidas
                                  ) AS total";

    $stmtNotificaciones = $conexion->prepare($sqlNotificacionesNoLeidas);
    $stmtNotificaciones->bind_param("sisi", $idGrupo, $idEstudiante, $idGrupo, $idEstudiante);
    $stmtNotificaciones->execute();
    $resultNotificaciones = $stmtNotificaciones->get_result();
    $numNoLeidas = $resultNotificaciones->fetch_assoc()['total'] ?? 0;
    $stmtNotificaciones->close();
} else {
    $numNoLeidas = 0;
}

// Procesar formulario
$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($fechaLimitePasada) {
        $error = "No se puede entregar la tarea porque la fecha límite ha pasado.";
    } elseif (isset($_FILES['archivo']) && $_FILES['archivo']['error'] == UPLOAD_ERR_OK) {
        // Extensiones permitidas (incluyendo PDF)
        $extensionesPermitidas = ['docx', 'xlsx', 'pptx', 'zip', 'rar', 'pdf'];
        $nombreArchivo = $_FILES['archivo']['name'];
        $extension = strtolower(pathinfo($nombreArchivo, PATHINFO_EXTENSION));
        $tamanoArchivo = $_FILES['archivo']['size']; // Tamaño en bytes

        // Verificar extensión
        if (!in_array($extension, $extensionesPermitidas)) {
            $error = "Tipo de archivo no permitido. Formatos aceptados: DOCX, XLSX, PPTX, ZIP, RAR, PDF";
        }
        // Verificar tamaño máximo para ZIP/RAR (20MB = 20971520 bytes)
        elseif (($extension == 'zip' || $extension == 'rar') && $tamanoArchivo > 20971520) {
            $error = "Los archivos ZIP/RAR no deben exceder los 20MB";
        }
        // Verificar tamaño general del archivo (por ejemplo, 50MB para otros tipos)
        elseif ($tamanoArchivo > 52428800) { // 50MB
            $error = "El archivo es demasiado grande. Tamaño máximo permitido: 50MB";
        } else {
            // Crear directorio de entregas si no existe
            $directorioEntregas = '../entregas/';
            if (!file_exists($directorioEntregas)) {
                mkdir($directorioEntregas, 0777, true);
            }

            // Eliminar archivo anterior si existe
            if ($yaEntregado && !empty($entregaActual['rutaArchivo'])) {
                $rutaAnterior = $directorioEntregas . $entregaActual['rutaArchivo'];
                if (file_exists($rutaAnterior)) {
                    unlink($rutaAnterior);
                }
            }

            $nuevoNombre = uniqid() . '_' . $idEstudiante . '_' . $idTarea . '.' . $extension;
            $rutaDestino = $directorioEntregas . $nuevoNombre;

            if (move_uploaded_file($_FILES['archivo']['tmp_name'], $rutaDestino)) {
                if ($yaEntregado) {
                    // Actualizar entrega existente
                    $sqlUpdate = "UPDATE entregas SET rutaArchivo = ?, fechaEntrega = NOW(), comentarios = ?
                                  WHERE idTarea = ? AND idEstudiante = ?";
                    $stmtUpdate = $conexion->prepare($sqlUpdate);
                    $comentarios = $_POST['comentarios'] ?? '';
                    $stmtUpdate->bind_param("ssii", $nuevoNombre, $comentarios, $idTarea, $idEstudiante);

                    if ($stmtUpdate->execute()) {
                        $mensaje = "¡Tarea actualizada correctamente!";
                        header("refresh:2;url=ver_entrega.php?idTarea=" . $idTarea);
                    } else {
                        $error = "Error al actualizar la entrega en la base de datos: " . $conexion->error;
                        unlink($rutaDestino); // Eliminar el archivo si falla la actualización en la BD
                    }
                } else {
                    // Insertar nueva entrega
                    $sqlInsert = "INSERT INTO entregas (idTarea, idEstudiante, rutaArchivo, fechaEntrega, comentarios)
                                  VALUES (?, ?, ?, NOW(), ?)";
                    $stmtInsert = $conexion->prepare($sqlInsert);
                    $comentarios = $_POST['comentarios'] ?? '';
                    $stmtInsert->bind_param("iiss", $idTarea, $idEstudiante, $nuevoNombre, $comentarios);

                    if ($stmtInsert->execute()) {
                        $mensaje = "¡Tarea entregada correctamente!";
                        header("refresh:2;url=ver_entrega.php?idTarea=" . $idTarea);
                    } else {
                        $error = "Error al guardar la entrega en la base de datos: " . $conexion->error;
                        unlink($rutaDestino); // Eliminar el archivo si falla la inserción en la BD
                    }
                }
            } else {
                $error = "Error al subir el archivo. Verifica los permisos del directorio.";
            }
        }
    } else {
        $error = "No se ha seleccionado ningún archivo para la entrega.";
    }
}

$stmtVerificar->close();
$stmtTarea->close();
$stmtEstudiante->close();
$conexion->close();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tarea - <?php echo htmlspecialchars($tarea['titulo']); ?></title>
    <link rel="stylesheet" href="../css/entregar_tarea.css">
    <link rel="stylesheet" href="../css/botonPerfil.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .notification-badge {
            background-color: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 3px 8px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 5px;
            animation: pulse 1.5s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
    </style>
    <script defer src="../js/botonPerfil.js"></script>
</head>

<body>
    <header class="header">
        <img src="../IMAGENES/logo.gif" alt="EDUSPHERE" class="logo">
        <h2>SUBIR TAREA</h2>
        <nav class="nav-bar">
            <a href="../html/Usuario.php"><i class="fas fa-home"></i> INICIO</a>
            <a href="../html/Notificaciones.php"><i class="fas fa-bell"></i> NOTIFICACIONES
                <?php if ($numNoLeidas > 0): ?>
                    <span class="notification-badge"><?= $numNoLeidas ?></span>
                <?php endif; ?>
            </a>
        </nav>
        <div class="user-info" id="userDropdown">
            <div class="user-display">
                <span><?php echo htmlspecialchars($nombre . ' ' . $apellidos); ?></span>
                <img src="../IMAGENES/estudiantes/<?php echo htmlspecialchars($foto_perfil); ?>" alt="Foto perfil" class="profile-pic">
                <i class="fas fa-chevron-down dropdown-arrow"></i>
            </div>
            <div class="dropdown-menu" id="dropdownMenu">
                <a href="../html/Perfil.php"><i class="fas fa-user-circle"></i> Perfil</a>
                <form action="../php/cerrar_sesion.php" method="post">
                    <button type="submit" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</button>
                </form>
            </div>
        </div>
    </header>

    <main class="container">
        <div class="card">
            <div class="card-header" style="background-color: #0A2A4D;">
                <div class="header-info">
                    <h3>Tarea: <?php echo htmlspecialchars($tarea['titulo']); ?></h3>
                    <span>Materia: <?php echo htmlspecialchars($tarea['nombreMateria']); ?></span>
                </div>
                <a href="../html/tareas_alumno.php?idMateria=<?php echo $tarea['idMateria']; ?>" class="btn-regresar">
                    <i class="fas fa-arrow-left"></i> Regresar
                </a>
            </div>

            <div class="card-body">
                <?php if (!empty($mensaje)): ?>
                    <div class="resultado success">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($mensaje); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="resultado error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <div class="tarea-info">
                    <h4>Descripción de la tarea:</h4>
                    <p><?php echo nl2br(htmlspecialchars($tarea['descripcion'])); ?></p>

                    <?php if ($tarea['fecha_limite']): ?>
                        <div class="fecha-info">
                            <p><strong>Fecha límite:</strong> <?php echo date('d/m/Y', strtotime($tarea['fecha_limite'])); ?></p>
                            <?php if ($fechaLimitePasada): ?>
                                <div class="resultado error">
                                    <i class="fas fa-exclamation-circle"></i>
                                    La fecha límite para esta tarea ha pasado y no se pueden realizar más entregas.
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!$fechaLimitePasada): ?>
                <form id="formEntrega" action="entregar_tarea.php?idTarea=<?php echo $idTarea; ?>" method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="archivo">Archivo de la tarea:</label>
                        <input type="file" id="archivo" name="archivo" required class="file-input">
                        <small class="file-info">Formatos aceptados: DOCX, XLSX, PPTX, ZIP (max 20MB), RAR (max 20MB), PDF</small>
                        <?php if ($yaEntregado): ?>
                            <p class="entrega-actual">
                                <i class="fas fa-file-alt"></i> Entrega actual:
                                <span><?php echo htmlspecialchars($entregaActual['rutaArchivo']); ?></span>
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="comentarios">Comentarios (opcional):</label>
                        <textarea id="comentarios" name="comentarios" rows="3" placeholder="Agrega cualquier comentario sobre tu entrega..."><?php
                            echo $yaEntregado ? htmlspecialchars($entregaActual['comentarios'] ?? '') : '';
                        ?></textarea>
                    </div>

                    <div class="form-actions">
                        <a href="../html/tareas_alumno.php?idMateria=<?php echo $tarea['idMateria']; ?>" class="btn-cancelar">
                            <i class="fas fa-times"></i> Cancelar
                        </a>
                        <button type="button" id="btnConfirmar" class="btn-entregar">
                            <i class="fas fa-upload"></i> <?php echo $yaEntregado ? 'Actualizar entrega' : 'Entregar tarea'; ?>
                        </button>
                    </div>
                </form>
                <?php else: ?>
                <div class="resultado error">
                    <i class="fas fa-exclamation-circle"></i>
                    No se pueden realizar entregas después de la fecha límite.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <?php if (!$fechaLimitePasada): ?>
    <!-- Modal de confirmación -->
    <div id="confirmModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Confirmación</h3>
            </div>
            <div class="modal-body">
                <p>¿Estás seguro de <?php echo $yaEntregado ? 'actualizar' : 'enviar'; ?> tu tarea?</p>
            </div>
            <div class="modal-footer">
                <button id="btnAceptar" class="modal-btn aceptar">Sí, Continuar</button>
                <button id="btnCancelar" class="modal-btn cancelar">No, Cancelar</button>
            </div>
        </div>
    </div>

    <!-- Modal de alerta -->
    <div id="alertModal" class="modal">
        <div class="modal-content alert">
            <div class="modal-header">
                <h3>Alerta</h3>
            </div>
            <div class="modal-body">
                <p id="alertMessage">No se ha seleccionado ningún archivo para la entrega.</p>
            </div>
            <div class="modal-footer alert">
                <button id="btnCerrarAlerta" class="modal-btn aceptar">Aceptar</button>
            </div>
        </div>
    </div>

    <script>
        // Mostrar modal de confirmación al hacer clic en el botón de entregar
        document.getElementById('btnConfirmar').addEventListener('click', function() {
            // Validar que se haya seleccionado un archivo primero
            const archivoInput = document.getElementById('archivo');
            if (archivoInput.files.length === 0) {
                document.getElementById('alertMessage').textContent = 'No se ha seleccionado ningún archivo para la entrega.';
                document.getElementById('alertModal').style.display = 'block';
                return;
            }

            // Mostrar el modal si hay un archivo seleccionado
            document.getElementById('confirmModal').style.display = 'block';
        });

        // Manejar botón de cancelar en el modal
        document.getElementById('btnCancelar').addEventListener('click', function() {
            document.getElementById('confirmModal').style.display = 'none';
        });

        // Manejar botón de aceptar en el modal
        document.getElementById('btnAceptar').addEventListener('click', function() {
            document.getElementById('formEntrega').submit();
        });

        // Manejar botón de cerrar alerta
        document.getElementById('btnCerrarAlerta').addEventListener('click', function() {
            document.getElementById('alertModal').style.display = 'none';
        });

        // Cerrar modales si se hace clic fuera de ellos
        window.addEventListener('click', function(event) {
            if (event.target == document.getElementById('confirmModal')) {
                document.getElementById('confirmModal').style.display = 'none';
            }
            if (event.target == document.getElementById('alertModal')) {
                document.getElementById('alertModal').style.display = 'none';
            }
        });

        // Temporizador de inactividad
        let inactivityTime = 180000; // 3 minutos
        let timeout;

        function resetTimer() {
            clearTimeout(timeout);
            timeout = setTimeout(logout, inactivityTime);
        }

        function logout() {
            window.location.href = '../php/cerrar_sesion.php';
        }

        // Eventos que reinician el temporizador
        window.onload = resetTimer;
        window.onmousemove = resetTimer;
        window.onmousedown = resetTimer;
        window.onclick = resetTimer;
        window.onscroll = resetTimer;
        window.onkeypress = resetTimer;

        // Menú desplegable del usuario
        document.querySelector('.user-display').addEventListener('click', function() {
            document.querySelector('.dropdown-menu').classList.toggle('show');
        });

        // Cerrar menú al hacer clic fuera
        window.addEventListener('click', function(event) {
            if (!event.target.matches('.user-display') && !event.target.closest('.user-display')) {
                const dropdowns = document.querySelectorAll('.dropdown-menu');
                dropdowns.forEach(dropdown => {
                    if (dropdown.classList.contains('show')) {
                        dropdown.classList.remove('show');
                    }
                });
            }
        });
    </script>
    <?php endif; ?>
</body>

</html>