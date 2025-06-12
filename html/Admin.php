<?php
session_start();
require_once '../php/Conexion.php';

if (!isset($_SESSION['idUsuario']) || $_SESSION['rol'] != 'Admin') {
    header("Location: INICIO.html");
    exit();
}

$idPadre = $_SESSION['idUsuario'];
$sql = "SELECT nombre, apellidos, foto_perfil FROM Usuarios WHERE idUsuario = ?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $idPadre);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $padre = $result->fetch_assoc();
    $nombreCompleto = $padre['nombre'] . ' ' . $padre['apellidos'];
    $fotoPerfil = !empty($padre['foto_perfil']) ? '../IMAGENES/Usuarios/' . $padre['foto_perfil'] : '../IMAGENES/Admin.jpg';
} else {
    // Datos por defecto si no encuentra al padre
    $nombreCompleto = "Usuario";
    $fotoPerfil = '../IMAGENES/Admin.jpg';
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administración - Edusphere</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/botonPerfil.css">
    <link rel="stylesheet" href="../css/Admin.css">
</head>

<body>
    <header class="header">
        <img src="../IMAGENES/logo.gif" alt="EDUSPHERE" class="logo">
        <h2>ADMINISTRADOR</h2>
        <nav class="nav-bar">
            <a href="" class="active">Inicio</a>
        </nav>
        <div class="user-info" id="userDropdown">
            <div class="user-display">
                <img src="<?= $fotoPerfil ?>" alt="Foto de perfil" class="profile-pic">
                <span><?= $nombreCompleto ?></span>
                <i class="fas fa-chevron-down dropdown-arrow"></i>
            </div>
            <div class="dropdown-menu" id="dropdownMenu">
                <a href="perfilUsuario.php"><i class="fas fa-user-circle"></i> Perfil</a>
                <a href="../php/cerrar_sesion.php"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a>
            </div>
        </div>
    </header>

    <main class="container">
        <section class="dashboard">
            <div class="dashboard-item">
                <img src="../IMAGENES/DOCENTE.png" alt="Maestros">
                <p>Maestros</p>
                <a href="../php/verMaestros.php">Registrar</a>
            </div>
            <div class="dashboard-item">
                <img src="../IMAGENES/Padres de familia.png" alt="Maestros">
                <p>Padres</p>
                <a href="../php/verPadres.php">Registrar</a>
            </div>
            <div class="dashboard-item">
                <img src="../IMAGENES/Estudiante.png" alt="Alumnos">
                <p>Alumnos</p>
                <a href="../php/verEstudiantes.php">Registrar</a>
            </div>
            <div class="dashboard-item">
                <img src="../IMAGENES/Grupos.png" alt="Grupos">
                <p>Grupos</p>
                <a href="../php/verGrupos.php">Asignar</a>
            </div>
            <div class="dashboard-item">
                <img src="../IMAGENES/Tareas.png" alt="Grupos">
                <p>Materias</p>
                <a href="../php/verMaterias.php">Crear</a>
            </div>
        </section>
    </main>
    <script src="../html/JS/botonPerfil.js"></script>
</body>

</html>