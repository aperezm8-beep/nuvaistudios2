<?php
require_once 'includes/db.php';

try {
    $db = getDB();
    // Asignamos el IdRol 1, que suele ser el de administrador
    $stmt = $db->prepare("UPDATE m_usuarios SET IdRol = 1 WHERE IdUsuario = 8");
    $stmt->execute();
    
    echo "¡Rol actualizado con éxito!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>