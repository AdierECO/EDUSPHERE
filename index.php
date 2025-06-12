<?php
session_start();
if (!isset($_SESSION['idEstudiante'])) {
    header("Location: html/INICIO.html");
    exit();
} else {
    header("Location: Usuario.php");
}

if (!isset($_SESSION['idUsuario'])) {
    header("Location: html/INICIO.html");
    exit();
} else {
    header("Location: Docente.php");
}

if (!isset($_SESSION['idPadre'])) {
    header("Location: html/INICIO.html");
    exit();
} else {
    header("Location: PADRES.php");
}

if (!isset($_SESSION['idUsuario'])) {
    header("Location: html/INICIO.html");
    exit();
} else {
    header("Location: Admin.php");
}


include '../php/Conexion.php';

?>