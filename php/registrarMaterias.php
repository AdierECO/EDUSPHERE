<?php
include 'Conexion.php';

// Configurar para mostrar errores (solo en desarrollo)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Procesar el formulario de registro
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar y sanitizar los datos de entrada
    $nombreMateria = filter_input(INPUT_POST, 'nombreMateria', FILTER_SANITIZE_STRING);
    $idInstitucion = filter_input(INPUT_POST, 'idInstitucion', FILTER_VALIDATE_INT);
    $idGrupo = filter_input(INPUT_POST, 'idGrupo', FILTER_SANITIZE_STRING);
    $id_maestro = filter_input(INPUT_POST, 'id_maestro', FILTER_VALIDATE_INT);

    // Validaciones básicas
    if (empty($nombreMateria) || empty($idInstitucion) || empty($idGrupo) || empty($id_maestro)) {
        header('Location: ../html/registrarMaterias.php?error=campos_vacios');
        exit();
    }

    // Validar longitud del nombre
    if (strlen($nombreMateria) < 3 || strlen($nombreMateria) > 100) {
        header('Location: ../html/registrarMaterias.php?error=nombre_invalido');
        exit();
    }

    // Verificar si la combinación materia-grupo ya existe
    $sqlCheck = "SELECT idMateria FROM Materias WHERE nombreMateria = ? AND idGrupo = ?";
    $stmtCheck = $conexion->prepare($sqlCheck);
    $stmtCheck->bind_param("ss", $nombreMateria, $idGrupo);
    $stmtCheck->execute();
    
    if ($stmtCheck->get_result()->num_rows > 0) {
        header('Location: ../html/registrarMaterias.php?error=materia_existente');
        exit();
    }

    // Verificar que la institución existe
    $sqlCheckInstitucion = "SELECT idinstitucion FROM Instituciones WHERE idinstitucion = ?";
    $stmtCheckInstitucion = $conexion->prepare($sqlCheckInstitucion);
    $stmtCheckInstitucion->bind_param("i", $idInstitucion);
    $stmtCheckInstitucion->execute();
    
    if ($stmtCheckInstitucion->get_result()->num_rows === 0) {
        header('Location: ../html/registrarMaterias.php?error=institucion_no_existe');
        exit();
    }

    // Verificar que el grupo existe
    $sqlCheckGrupo = "SELECT idGrupo FROM Grupos WHERE idGrupo = ?";
    $stmtCheckGrupo = $conexion->prepare($sqlCheckGrupo);
    $stmtCheckGrupo->bind_param("s", $idGrupo);
    $stmtCheckGrupo->execute();
    
    if ($stmtCheckGrupo->get_result()->num_rows === 0) {
        header('Location: ../html/registrarMaterias.php?error=grupo_no_existe');
        exit();
    }

    // Verificar que el maestro existe y tiene rol correcto
    $sqlCheckMaestro = "SELECT idUsuario FROM Usuarios WHERE idUsuario = ? AND rol = 'Maestro'";
    $stmtCheckMaestro = $conexion->prepare($sqlCheckMaestro);
    $stmtCheckMaestro->bind_param("i", $id_maestro);
    $stmtCheckMaestro->execute();
    
    if ($stmtCheckMaestro->get_result()->num_rows === 0) {
        header('Location: ../html/registrarMaterias.php?error=maestro_no_valido');
        exit();
    }

    // Consulta SQL para insertar una nueva materia
    $sql = "INSERT INTO Materias (nombreMateria, idInstitucion, idGrupo, id_maestro) VALUES (?, ?, ?, ?)";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("sisi", $nombreMateria, $idInstitucion, $idGrupo, $id_maestro);

    try {
        if ($stmt->execute()) {
            header('Location: ../php/verMaterias.php?registro=exitoso');
        } else {
            header('Location: ../html/registrarMaterias.php?error=error_registro');
        }
    } catch (mysqli_sql_exception $e) {
        error_log("Error al registrar materia: " . $e->getMessage());
        header('Location: ../html/registrarMaterias.php?error=error_bd');
    }
    exit();
}