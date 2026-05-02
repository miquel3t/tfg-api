<?php

// Incluimos fichero con las variables globales
require_once __DIR__ . '/config.inc';

// FUNCION get_db_conection()
// Funcion para inicializar la base de datos
// Comprueba si la base de datos existe. 
// Si no, la crea y activa el uso de claves foraneas
// Devuelve la conexion a la base de datos
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


// FUNCION db_get_legoprice()
// Buscamos el precio de un set
// Valores de entrada:
//	id - identificador del set
//	site - identifidor de la tienda
//	pricedate - fecha en formato YYYYMMDD
// Salida (array):
// 	idlegoset - identificador del set
//	price - precio del set
//	site - identificador de la tienda
//	url - URL de la tienda del set
//	pricedate - fecha en formato YYYYMMDD
//	status - Disponibilidad del set 

function db_get_legoprice($db, $id, $site, $pricedate) {
    $sql = "
        SELECT idlegoset, price, site, url, pricedate, status
        FROM legoprice
        WHERE idlegoset = :id
          AND site = :site
          AND pricedate = :pricedate
        LIMIT 1
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':id' => $id,
        ':site' => $site,
        ':pricedate' => $pricedate
    ]);
    
//    return $stmt->fetch(PDO::FETCH_ASSOC);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $row['origin'] = 'database';
    }
    return $row;
}


// FUNCION db_insert_legoprice()
// Insertamos el precio de un set en la base de datos
// Valores de entrada:
// 	id - identificador del set
//	price - precio del set
//	site - identificador de la tienda
//	url - URL de la tienda del set
//	pricedate - fecha en formato YYYYMMDD
//	status - Disponibilidad del set 
// Valores de salida:
//	Resultado de la operacion de insercion
function db_insert_legoprice($db, $id, $price, $site, $url, $pricedate, $status) {
    $sql = "
        INSERT INTO legoprice (idlegoset, price, site, url, pricedate, status)
        VALUES (:id, :price, :site, :url, :pricedate, :status)
    ";

    $stmt = $db->prepare($sql);
    return $stmt->execute([
        ':id' => $id,
        ':price' => $price,
        ':site' => $site,
        ':url' => $url,
        ':pricedate' => $pricedate,
        ':status' => $status
    ]);
}
