<?php
// config/db.php

// Database connection parameters
define('DB_HOST', '127.0.0.1'); // O tu host de base de datos, ej: localhost
define('DB_USER', 'root');      // Tu usuario de base de datos
define('DB_PASS', '');          // Tu contraseña de base de datos
define('DB_NAME', 'elreypapa'); // El nombre de tu base de datos

/**
 * Establishes a database connection using MySQLi.
 * @return mysqli|false A mysqli object on success, or false on failure.
 */
function conectarDB() {
    // Create connection
    $conexion = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    // Check connection
    if ($conexion->connect_error) {
        // Log error or handle it more gracefully in a real application
        error_log("Error de conexión: " . $conexion->connect_error);
        return false; // Return false if connection failed
    }

    // Set charset to utf8mb4 for proper encoding, especially with Spanish characters
    if (!$conexion->set_charset("utf8mb4")) {
        error_log("Error al establecer el charset utf8mb4: " . $conexion->error);
        // Optionally, you might still return the connection or handle this as a fatal error
    }

    return $conexion; // Return the connection object
}

/*
// Example of how to use the connection:
$db = conectarDB();
if ($db) {
    echo "Conexión exitosa a la base de datos.";
    // Perform queries here
    $db->close();
} else {
    echo "No se pudo conectar a la base de datos.";
}
*/
?>
