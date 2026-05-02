<?php

// Cargamos el fichero de configuracion
require_once __DIR__ . '/config/config.inc';

// Obtenemos la URL
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Base de la API
$base = '/api';
$endpoint = substr($path, strlen($base));

switch (true) {

    //Endpoint bestprice: busca el mejor precio en todas las tiendas
    case preg_match('#^/bestprice/([^/]+)$#', $endpoint, $matches):
        $_GET['set'] = $matches[1];
        require API_ROOT . '/endpoints/bestprice.php';
        break;

    //Endpoint abacus: busca un set en Abacus
    case preg_match('#^/abacus/([^/]+)$#', $endpoint, $matches):
        $_GET['set'] = $matches[1];
        require API_ROOT . '/endpoints/abacus.php';
        break;
        
    //Endpoint alcampo: busca un set en Alcampo
    case preg_match('#^/alcampo/([^/]+)$#', $endpoint, $matches):
        $_GET['set'] = $matches[1];
        require API_ROOT . '/endpoints/alcampo.php';
        break;

    //Endpoint brickoutique: busca un set en Brickoutique
    case preg_match('#^/brickoutique/([^/]+)$#', $endpoint, $matches):
        $_GET['set'] = $matches[1];
        require API_ROOT . '/endpoints/brickoutique.php';
        break;

    //Endpoint setinfo: muestra informacion de un set
    case preg_match('#^/setinfo/([^/]+)$#', $endpoint, $matches):
        $_GET['set'] = $matches[1];
        require API_ROOT . '/endpoints/setinfo.php';
        break;

    // Endpoint health: comprobamos el estado de la bdd
    case $endpoint === '/health':
        require API_ROOT . '/endpoints/health.php';
        break;
        
    // No tenemos el endpoint definido: Error 404 (Not found)
    default:
        header('Content-Type: application/json');
        http_response_code(404);
        echo json_encode([
            "error" => "Endpoint not found"
        ]);
}
