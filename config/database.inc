<?php

// Incluimos fichero con las variables globales
require_once __DIR__ . '/config.inc';

// Conectamos con la base de datos
function get_db_connection() {
    try {
        // Comprobamos si existe la base de datos
        $new_db = !file_exists(DB_FILE);
    
        $db = new PDO('sqlite:' . DB_FILE);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Activamos las claves foraneas
        $db->exec("PRAGMA foreign_keys = ON;");

	// Cargamos el schema si la base de datos es nueva
	if ($new_db) {
            if (!file_exists(DB_SCHEMA)) {
                throw new Exception("No se encuentra el archivo del SCHEMA: " . DB_SCHEMA);
            }

            $schema = file_get_contents(DB_SCHEMA);
            $db->exec($schema);
        }

        return $db;
        
    } catch (Exception $e) {
        return null;
    }
}
