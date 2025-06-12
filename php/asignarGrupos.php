<?php
include 'conexion.php';

// Configurar para mostrar errores (solo en desarrollo)
ini_set('display_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['asignarGrupo'])) {
    // Sanitizar y validar datos
    $idMaestro = filter_input(INPUT_POST, 'maestroGrupo', FILTER_SANITIZE_NUMBER_INT);
    $grupo = trim(filter_input(INPUT_POST, 'grupo', FILTER_SANITIZE_STRING));

    // Validaciones básicas
    if (empty($idMaestro) || empty($grupo)) {
        header('Location: ../html/asignarGrupos.php?error=campos_vacios');
        exit();
    }

    // Validar formato del grupo (caracteres alfanuméricos)
    if (!preg_match('/^[A-Za-z0-9]+$/', $grupo)) {
        header('Location: ../html/asignarGrupos.php?error=formato_grupo_invalido');
        exit();
    }

    // Validar longitud del grupo (3-12 caracteres)
    if (strlen($grupo) < 3 || strlen($grupo) > 12) {
        header('Location: ../html/asignarGrupos.php?error=longitud_grupo_invalida');
        exit();
    }

    // Verificar si el maestro existe y es realmente un maestro
    $sqlCheckMaestro = "SELECT idUsuario FROM Usuarios WHERE idUsuario = ? AND rol = 'Maestro'";
    $stmtCheckMaestro = $conexion->prepare($sqlCheckMaestro);
    $stmtCheckMaestro->bind_param("i", $idMaestro);
    $stmtCheckMaestro->execute();
    
    if ($stmtCheckMaestro->get_result()->num_rows === 0) {
        header('Location: ../html/asignarGrupos.php?error=maestro_no_valido');
        exit();
    }

    // Verificar si el maestro ya está asignado a otro grupo
    $sqlCheckMaestroGrupo = "SELECT idGrupo FROM grupos WHERE id_maestro = ?";
    $stmtCheckMaestroGrupo = $conexion->prepare($sqlCheckMaestroGrupo);
    $stmtCheckMaestroGrupo->bind_param("i", $idMaestro);
    $stmtCheckMaestroGrupo->execute();
    
    if ($stmtCheckMaestroGrupo->get_result()->num_rows > 0) {
        header('Location: ../html/asignarGrupos.php?error=maestro_en_otro_grupo');
        exit();
    }

    // Verificar si el grupo ya existe
    $sqlCheckGrupo = "SELECT idGrupo FROM grupos WHERE idGrupo = ?";
    $stmtCheckGrupo = $conexion->prepare($sqlCheckGrupo);
    $stmtCheckGrupo->bind_param("s", $grupo);
    $stmtCheckGrupo->execute();
    
    if ($stmtCheckGrupo->get_result()->num_rows > 0) {
        header('Location: ../html/asignarGrupos.php?error=grupo_existente');
        exit();
    }

    // Iniciar transacción
    $conexion->begin_transaction();

    try {
        // Insertar grupo
        $sqlInsertGrupo = "INSERT INTO grupos (idGrupo, id_maestro) VALUES (?, ?)";
        $stmtInsertGrupo = $conexion->prepare($sqlInsertGrupo);
        $stmtInsertGrupo->bind_param("si", $grupo, $idMaestro);
        
        if (!$stmtInsertGrupo->execute()) {
            throw new Exception("Error al registrar el grupo: " . $stmtInsertGrupo->error);
        }

        // Actualizar usuario con el grupo asignado
        $sqlUpdateUsuario = "UPDATE Usuarios SET idGrupo = ? WHERE idUsuario = ?";
        $stmtUpdateUsuario = $conexion->prepare($sqlUpdateUsuario);
        $stmtUpdateUsuario->bind_param("si", $grupo, $idMaestro);
        
        if (!$stmtUpdateUsuario->execute()) {
            throw new Exception("Error al actualizar el usuario: " . $stmtUpdateUsuario->error);
        }

        // Confirmar transacción
        $conexion->commit();
        
        // Redirigir con éxito
        header('Location: ../php/verGrupos.php?registro=exitoso');
        exit();

    } catch (Exception $e) {
        // Revertir transacción en caso de error
        $conexion->rollback();
        error_log("Error al asignar grupo: " . $e->getMessage());
        header('Location: ../html/asignarGrupos.php?error=error_asignacion');
        exit();
    }
} else {
    header('Location: ../html/asignarGrupos.php');
    exit();
}
?>