<?php

header("Content-Type: application/json");

// Incluimos el fichero de configuracion database.inc
require_once __DIR__ . '/../config/database.inc';

$site = "Alcampo";
$pricedate = date("Ymd");


// VALIDACIONES INICIALES
// Comprobamos que llega el parametro set
if (!isset($_GET['set'])) {
    echo json_encode(["error" => "Falta el parametro 'set'"]);
    exit;
}

// Seguridad: eliminamos espacios y caracteres invisibles
$set_raw = trim($_GET['set']);

// Seguridad: comprobamos el formato de set_raw
if (!preg_match('/^[0-9]+$/', $set_raw)) {
    echo json_encode([
        "error" => "Formato invalido. Solo se permiten numeros"]);
    exit;
}

// Convertimos a numero entero
$id = intval($set_raw);


// INTENTAMOS OBTENER EL PRECIO DESDE LA BASE DE DATOS

$db = get_db_connection();
$set_legoprice = db_get_legoprice($db, $id, $site, $pricedate);

// Tenemos el precio en la base de datos
if ($set_legoprice) {
    echo json_encode($set_legoprice);
    exit;
}

// EL PRECIO NO ESTA EN LA BASE DE DATOS: BUSCAMOS EN ALCAMPO
// Construimos la peticioin URL con cUrl
$searchUrl = "https://www.compraonline.alcampo.es/search?q=lego%20" . urlencode($id);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $searchUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

curl_setopt($ch, CURLOPT_USERAGENT,
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36"
);

$html = curl_exec($ch);
curl_close($ch);

// Si no hay respuesta de Alcampo, mostramos un error
if (!$html) {
    die(json_encode([
        "idlegoset" => $id,
        "error" => "No se han podido obtener datos de Alcampo",
        "site" => $site,
        "pricedate" => $pricedate
    ]));
}

// Parseamos el HTML recibido
$dom = new DOMDocument();
libxml_use_internal_errors(true);
$dom->loadHTML($html);
libxml_clear_errors();

$xpath = new DOMXPath($dom);

// Buscamos los DIV con el valor product-card-container en el atributo class
$products = $xpath->query("//div[contains(@class,'product-card-container')]");

// Si no hay ninguno, no hemos encontrado el Lego 
if ($products->length === 0) {
    echo json_encode([
        "idlegoset" => $id,
        "error" => "No se ha encontrado el set indicado",
        "site" => $site,
        "pricedate" => $pricedate
    ]);
    exit;
}

// Revisamos los sets encontrados
foreach ($products as $product) {

    // Buscamos el nombre del Lego 
    $nameNode = $xpath->query(".//h3[@data-test='fop-title']", $product);

    // Si no lo encuentra, saltamos al siguiente
    if ($nameNode->length === 0) continue;

    // Extraemos el titulo del set
    $titulo = trim($nameNode->item(0)->textContent);

    // Si el id del set no esta contenido en $titulo, saltamos al siguiente
    if (!preg_match("/\b" . preg_quote($id, '/') . "\b/", $titulo)) {
        continue;
    }

    $priceNode = $xpath->query(".//span[@data-test='fop-price']", $product);

    // Formateamos el precio para eliminar el simbolo de euro, espacios y poner . como semparador de decimales
    if ($priceNode->length > 0) {
        $price = trim($priceNode->item(0)->textContent);
        $price = str_replace(['€', ' ', "\xc2\xa0"], '', $price);
        $price = str_replace(',', '.', $price);
    } else {
        $price = 0;
    }

    // Buscamos la URL del set
    $hrefNode = $xpath->query(".//a[@data-test='fop-product-link']", $product);
    if ($hrefNode->length === 0) continue;

    $href = $hrefNode->item(0)->getAttribute("href");
    $productUrl = "https://www.compraonline.alcampo.es" . $href;

    // Comprobamos si hay disponibilidad
    $outOfStockNode = $xpath->query(".//span[@data-test='product-card-out-of-stock-badge']", $product);

    if ($outOfStockNode->length > 0) {
        $status = "Agotado";
    } else {
        $status = ($price > 0) ? "Disponible" : "Agotado";
    }

    // Si hemos llegado hasta aqui, insertamos la informacion en la base de datos
    db_insert_legoprice($db, $id, $price, $site, $productUrl, $pricedate, $status);

    // Y mostramos el JSON con los resultados
    echo json_encode([
        "idlegoset" => $id,
        "price" => $price,
        "site" => $site,
        "url" => $productUrl,
        "pricedate" => $pricedate,
        "status" => $status,
        "origin" => "Alcampo"
    ]);
    exit;
}

// Si no ha habido coincidencia mostramos un error
echo json_encode([
    "idlegoset" => $id,
    "error" => "No se ha encontrado coincidencia",
    "site" => $site,
    "pricedate" => $pricedate
]);
?>
