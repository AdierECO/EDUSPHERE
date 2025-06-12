<?php
session_start();
require_once '../php/Conexion.php';

if (!isset($_SESSION['idUsuario'])) {
  header("Location: INICIO.html");
  exit();
}

// Obtener datos del padre
$idPadre = $_SESSION['idUsuario'];
$sql = "SELECT nombre, apellidos, foto_perfil FROM Usuarios WHERE idUsuario = ?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $idPadre);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
  $padre = $result->fetch_assoc();
  $nombreCompleto = $padre['nombre'] . ' ' . $padre['apellidos'];
  $fotoPerfil = !empty($padre['foto_perfil']) ? '../IMAGENES/Usuarios/' . $padre['foto_perfil'] : '../IMAGENES/Docente.jpg';
} else {
  // Datos por defecto si no encuentra al padre
  $nombreCompleto = "Usuario";
  $fotoPerfil = '../IMAGENES/Docente.jpg';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Panel Docente</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../css/Docente.css">
  <link rel="stylesheet" href="../css/botonPerfil.css">
</head>

<body>
  <header class="header">
    <img src="../IMAGENES/logo.gif" alt="EDUSPHERE" class="logo">
    <h2>DOCENTE</h2>
    <nav class="nav-bar">
      <a href="Docente.php" class="active">INICIO</a>
    </nav>
    <div class="user-info" id="userDropdown">
      <div class="user-display">
        <img src="<?= $fotoPerfil ?>" alt="Foto de perfil" class="profile-pic">
        <span><?= $nombreCompleto ?></span>
        <i class="fas fa-chevron-down dropdown-arrow"></i>
      </div>
      <div class="dropdown-menu" id="dropdownMenu">
        <a href="Perfil_docente.php"><i class="fas fa-user-circle"></i> Perfil</a>
        <a href="../php/cerrar_sesion.php"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a>
      </div>
    </div>
  </header>

  <main class="container">
    
    <section class="dashboard">
      <div class="dashboard-item">
        <img src="../IMAGENES/Tareas.png" alt="Tareas">
        <p>Tareas publicadas</p>
        <a href="tareas.php">Ver Tareas</a>
      </div>
      <div class="dashboard-item">
        <img src="../IMAGENES/Avisos.png" alt="Avisos">
        <p>Avisos</p>
        <a href="avisos.php">Ver Avisos</a>
      </div>
      <div class="dashboard-item">
        <img src="../IMAGENES/Actividades.png" alt="Asistencias">
        <p>Asistencias</p>
        <a href="asistencias.php">Registro de asistencias</a>
      </div>
      <div class="dashboard-item">
        <img src="../IMAGENES/alumno_materias.png" alt="Materias">
        <p>Materias y Alumnos</p>
        <a href="materias.php">Visualizar materias</a>
      </div>
      <div class="dashboard-item">
        <img src="../IMAGENES/Calificaciones.png" alt="Calificaciones">
        <p>Calificaciones</p>
        <a href="calificaciones.php">Registrar calificaciones</a>
      </div>
    </section>
  </main>
  <script src="../html/JS/botonPerfil.js"></script>
</body>

</html>