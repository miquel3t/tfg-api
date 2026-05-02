<?php

header("Content-Type: application/json");

// Incluimos el fichero de configuracion database.inc
require_once __DIR__ . '/../config/database.inc';

$site = "Brickoutique";
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
    // Mostramos el JSON con los resultados
    echo json_encode($set_legoprice);
    exit;
}

// EL PRECIO NO ESTA EN LA BASE DE DATOS: BUSCAMOS EN BRICKOUTIQUE

// Construimos la peticioin URL con cUrl
$searchUrl = "https://brickoutique.com/?s=" . urlencode($id);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $searchUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

curl_setopt($ch, CURLOPT_USERAGENT,
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36"
);

$html = curl_exec($ch);
curl_close($ch);

// Si no hay respuesta de Brickoutique, mostramos un error
if (!$html) {
    die(json_encode([
        "idlegoset" => $id,
        "error" => "No se han podido obtener datos de Brickoutique",
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

// Buscamos en los UL con la clase 'products', los LI que tambien tengan
// la clase 'product'
$products = $xpath->query("//ul[contains(@class,'products')]//li[contains(normalize-space(@class),'product')]");

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

// Si no hay ninguno, no hemos encontrado el Lego 
foreach ($products as $product) {

    // Buscamos la URL del set
    $hrefNode = $xpath->query(".//a[contains(@class,'woocommerce-LoopProduct-link')]", $product);

    if ($hrefNode->length === 0) continue;

    $productUrl = $hrefNode->item(0)->getAttribute("href");

    // Comprobamos si el id del set esta contenido en la URL
    if (strpos($productUrl, (string)$id) === false) {
        continue;
    }
    
    // Buscamos el precio
    $priceNode = $xpath->query(".//span[contains(@class,'price')]//ins//bdi", $product);

    if ($priceNode->length === 0) {
        // Precio sin descuento. No tiene la etiqueta INS
        $priceNode = $xpath->query(".//span[contains(@class,'price')]//bdi", $product);
    }

    // Formateamos el precio para eliminar el simbolo de euro, espacios y poner . como semparador de decimales
    if ($priceNode->length > 0) {
        $price = trim($priceNode->item(0)->textContent);
        $price = str_replace(['€', ' ', "\xc2\xa0"], '', $price);
        $price = str_replace(',', '.', $price);
    } else {
        $price = 0;
    }

    // Comprobamos la disponibilidad. Hay un enlace A HREF con la clase 'add_to_cart_button'
    $addToCart = $xpath->query(".//a[contains(@class,'add_to_cart_button')]", $product);

    if ($addToCart->length > 0) {
        $status = "Disponible";
    } else {
        $status = "Agotado";
    }

    // Si hemos llegado hasta aqui, insertamos la informacion en la base de datos
    db_insert_legoprice($db, $id, $price, $site, $productUrl, $pricedate, $status);

    echo json_encode([
        "idlegoset" => $id,
        "price" => $price,
        "site" => $site,
        "url" => $productUrl,
        "pricedate" => $pricedate,
        "status" => $status,
        "origin" => "Brickoutique"
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
