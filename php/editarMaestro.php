<?php
session_start();
require_once '../php/Conexion.php';

if (!isset($_SESSION['idUsuario']) || $_SESSION['rol'] != 'Admin') {
    header("Location: ../html/INICIO.html");
    exit();
}
if (isset($_GET['id'])) {
    $idMaestro = $_GET['id'];

    // Obtener los datos del maestro
    $sqlMaestro = "SELECT * FROM Usuarios WHERE idUsuario = ? AND rol = 'maestro'";
    $stmtMaestro = $conexion->prepare($sqlMaestro);
    $stmtMaestro->bind_param("i", $idMaestro);
    $stmtMaestro->execute();
    $resultMaestro = $stmtMaestro->get_result();

    if ($resultMaestro->num_rows > 0) {
        $rowMaestro = $resultMaestro->fetch_assoc();
        $nombreMaestro = $rowMaestro['nombre'];
        $apellidosMaestro = $rowMaestro['apellidos'];
        $correoMaestro = $rowMaestro['correo'];
    } else {
        echo "Maestro no encontrado.";
        exit();
    }
} else {
    echo "ID de maestro no proporcionado.";
    exit();
}

// Procesar el formulario de edición
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'];
    $apellidos = $_POST['apellidos'];
    $correo = $_POST['correo'];
    $contrasena = trim($_POST['contrasena']); // Eliminar espacios en blanco al inicio y al final

    // Si el campo de contraseña no está vacío, actualizar la contraseña
    if (!empty($contrasena)) {
        $contrasenaHash = password_hash($contrasena, PASSWORD_DEFAULT); // Hash de la contraseña
        $sqlUpdate = "UPDATE Usuarios SET nombre = ?, apellidos = ?, correo = ?, contrasena = ? WHERE idUsuario = ?";
        $stmtUpdate = $conexion->prepare($sqlUpdate);
        $stmtUpdate->bind_param("ssssi", $nombre, $apellidos, $correo, $contrasenaHash, $idMaestro);
    } else {
        // Si el campo de contraseña está vacío, no actualizar la contraseña
        $sqlUpdate = "UPDATE Usuarios SET nombre = ?, apellidos = ?, correo = ? WHERE idUsuario = ?";
        $stmtUpdate = $conexion->prepare($sqlUpdate);
        $stmtUpdate->bind_param("sssi", $nombre, $apellidos, $correo, $idMaestro);
    }

    if ($stmtUpdate->execute()) {
        header('Location: verMaestros.php?success=editado');
        exit();
    } else {
        echo "Error al actualizar el maestro: " . $stmtUpdate->error;
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Maestro</title>
    <link rel="stylesheet" href="../css/editar.css">
</head>

<body>
    <div class="container">
        <h1>Editar Maestro</h1>
        <form method="POST">
            <label for="nombre">Nombre:</label>
            <input type="text" name="nombre" value="<?php echo htmlspecialchars($nombreMaestro); ?>" required>

            <label for="apellidos">Apellidos:</label>
            <input type="text" name="apellidos" value="<?php echo htmlspecialchars($apellidosMaestro); ?>" required>

            <label for="correo">Correo Electrónico:</label>
            <input type="email" name="correo" value="<?php echo htmlspecialchars($correoMaestro); ?>" required>

            <label for="contrasena">Nueva Contraseña:</label>
            <input type="password" name="contrasena" placeholder="Dejar en blanco para mantener la contraseña actual">

            <div class="botones-container">
                <button type="submit">Guardar Cambios</button>
                <button type="button" class="btn-cancelar" onclick="window.location.href='verMaestros.php'">Cancelar</button>
            </div>
        </form>
    </div>
</body>

</html>