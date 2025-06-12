<?php
session_start();

if (!isset($_SESSION['idEstudiante'])) {
    header("Location: ../html/loginEstudiante.html");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../html/Usuario.php");
    exit();
}

require_once 'Conexion.php';

$idTarea = $_POST['idTarea'];
$idEstudiante = $_POST['idEstudiante'];

// Verificar si la tarea aún está dentro del plazo
$sqlFecha = "SELECT fecha_limite FROM tareas WHERE idTarea = ?";
$stmtFecha = $conexion->prepare($sqlFecha);
$stmtFecha->bind_param("i", $idTarea);
$stmtFecha->execute();
$resultFecha = $stmtFecha->get_result();

if ($resultFecha->num_rows == 0) {
    header("Location: ../html/tareas_alumno.php?error=1");
    exit();
}

$tarea = $resultFecha->fetch_assoc();
$fecha_limite = new DateTime($tarea['fecha_limite']);
$hoy = new DateTime();

if ($hoy > $fecha_limite) {
    header("Location: ../html/ver_entrega.php?idTarea=$idTarea&error=2");
    exit();
}

// Procesar el archivo
if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
    header("Location: ../html/ver_entrega.php?idTarea=$idTarea&error=3");
    exit();
}

$nombreArchivo = $_FILES['archivo']['name'];
$tipoArchivo = $_FILES['archivo']['type'];
$tamanoArchivo = $_FILES['archivo']['size'];
$archivoTemp = $_FILES['archivo']['tmp_name'];

// Validar el archivo (tamaño máximo 5MB)
$tamanoMaximo = 5 * 1024 * 1024; // 5MB
if ($tamanoArchivo > $tamanoMaximo) {
    header("Location: ../html/ver_entrega.php?idTarea=$idTarea&error=4");
    exit();
}

// Generar un nombre único para el archivo
$extension = pathinfo($nombreArchivo, PATHINFO_EXTENSION);
$nombreUnico = "entrega_" . $idEstudiante . "_" . $idTarea . "_" . time() . "." . $extension;
$rutaDestino = "../entregas/" . $nombreUnico;

// Mover el archivo a la carpeta de entregas
if (!move_uploaded_file($archivoTemp, $rutaDestino)) {
    header("Location: ../html/ver_entrega.php?idTarea=$idTarea&error=5");
    exit();
}

// Actualizar la entrega en la base de datos
$sqlUpdate = "UPDATE entregas 
              SET rutaArchivo = ?, fechaEntrega = NOW(), calificacion = NULL, comentarios = NULL
              WHERE idTarea = ? AND idEstudiante = ?";
$stmtUpdate = $conexion->prepare($sqlUpdate);
$stmtUpdate->bind_param("sii", $nombreUnico, $idTarea, $idEstudiante);
$stmtUpdate->execute();

if ($stmtUpdate->affected_rows === 0) {
    // Si no había entrega previa, insertar una nueva
    $sqlInsert = "INSERT INTO entregas (idTarea, idEstudiante, rutaArchivo, fechaEntrega)
                  VALUES (?, ?, ?, NOW())";
    $stmtInsert = $conexion->prepare($sqlInsert);
    $stmtInsert->bind_param("iis", $idTarea, $idEstudiante, $nombreUnico);
    $stmtInsert->execute();
}

header("Location: ../html/ver_entrega.php?idTarea=$idTarea&success=1");
exit();
?>