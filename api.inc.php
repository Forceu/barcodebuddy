<?php



/**
 * Barcode Buddy for Grocy
 *
 * Long description for file (if any)...
 *
 * PHP version 7
 *
 * LICENSE: This source file is subject to version 3.0 of the GNU General
 * Public License v3.0 that is attached to this project.
 *
 * @author     Marc Ole Bulling
 * @copyright  2019 Marc Ole Bulling
 * @license    https://www.gnu.org/licenses/gpl-3.0.en.html  GNU GPL v3.0
 * @since      File available since Release 1.0
 */


/**
 * Helper file for Grocy API and barcode lookup
 *
 * @author     Marc Ole Bulling
 * @copyright  2019 Marc Ole Bulling
 * @license    https://www.gnu.org/licenses/gpl-3.0.en.html  GNU GPL v3.0
 * @since      File available since Release 1.0
 */


const API_PRODUCTS = API_URL . 'objects/products';
const API_STOCK    = API_URL . 'stock/products';

// Getting info of a Grocy product. If no argument is passed, all products are requested
function getProductInfo($productId = "") {
    
    if ($productId == "") {
        $apiurl = API_PRODUCTS;
    } else {
        $apiurl = API_PRODUCTS . "/" . $productId;
    }
    
    $curl = curl_init($apiurl);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array('GROCY-API-KEY: '. APIKEY));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $curl_response = curl_exec($curl);
    curl_close($curl);
    if ($curl_response === false) {
        die("Error getting product info");
    }
    
    $decoded1 = json_decode($curl_response, true);
    if (isset($decoded1->response->status) && $decoded1->response->status == 'ERROR') {
        die('Error occured: ' . $decoded1->response->errormessage);
    }
    return $decoded1;
}


// Set a Grocy product to "opened"
function openProduct($id) {
    $data = array(
        'amount' => "1"
    );
    $data_json = json_encode($data);
    
    $apiurl = API_STOCK . "/" . $id . "/open";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiurl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('GROCY-API-KEY: '. APIKEY));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json','Content-Length: '.strlen($data_json)));
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_json);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    if ($response === false) {
       die("Error opening product");
    }
}


// Add a Grocy product
function purchaseProduct($id, $amount, $bestbefore = null, $price = null) {
    $data = array(
        'amount' => $amount,
        'transaction_type' => 'purchase'
    );
    if ($price != null) {
        $data['price'] = $price;
    }
    if ($bestbefore != null) {
        $data['best_before_date'] = $bestbefore;
    } else {
        $data['best_before_date'] = getDefaultBestBefore($id);
    }
    $data_json = json_encode($data);
    
    
    $apiurl = API_STOCK . "/" . $id . "/add";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiurl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('GROCY-API-KEY: '. APIKEY));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json','Content-Length: '.strlen($data_json)));
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_json);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    if ($response === false) {
       die("Error purchasing product");
    }
}


// Consume a Grocy product
function consumeProduct($id, $amount, $spoiled = "false") {
    $data      = array(
        'amount' => $amount,
        'transaction_type' => 'consume',
        'spoiled' => $spoiled
    );
    $data_json = json_encode($data);
    
    $apiurl = API_STOCK . "/" . $id . "/consume";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiurl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('GROCY-API-KEY: '. APIKEY));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json','Content-Length: '.strlen($data_json)));
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_json);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    if ($response === false) {
       die("Error consuming product");
    }
    return $response;
    
}

// Add a barcode number to a Grocy product
function setBarcode($id, $barcode) {
    
    $data      = array('barcode' => $barcode);
    $data_json = json_encode($data);
    $apiurl = API_PRODUCTS . "/" . $id;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiurl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('GROCY-API-KEY: '. APIKEY));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json','Content-Length: '.strlen($data_json)));
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_json);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    if ($response === false) {
       die("Error setting barcode");
    }
}



// Get default best before in days from a Grocy product
function getDefaultBestBefore($id) {
    $info      = getProductInfo($id);
    $defaultBb = $info["default_best_before_days"];
    if ($defaultBb == "-1") {
        return "2999-12-31";
    } else {
        $date = date("Y-m-d");
        return date('Y-m-d', strtotime($date . " + $defaultBb days"));
    }
}


// Look up a barcode using openfoodfacts
function lookupNameByBarcode($barcode) {
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://world.openfoodfacts.org/api/v0/product/" . $barcode . ".json");
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    if ($response === false) {
       die("Error looking up barcode from OFF.");
    }
    curl_close($ch);

    if (strpos($response, 'product not found') !== false) {
        return "N/A";
    } else {
        $decoded1 = json_decode($response, true);
        if (isset($decoded1->response->status) && $decoded1->response->status == 'ERROR') {
            die('Error occured: ' . $decoded1->response->errormessage);
        }
        if (isset($decoded1["product"]["generic_name"])) {
            return sanitizeString($decoded1["product"]["generic_name"]);
        }
        if (isset($decoded1["product"]["product_name"])) {
            return sanitizeString($decoded1["product"]["product_name"]);
        }
        return "N/A";
    }
}


// Get a Grocy product by barcode
function getProductByBardcode($barcode) {
    
    $apiurl = API_STOCK . "/by-barcode/" . $barcode;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiurl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('GROCY-API-KEY: '. APIKEY));
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    if ($response === false) {
       die("Error looking up product by barcode");
    }

    $decoded1 = json_decode($response, true);
    if (isset($decoded1->response->status) && $decoded1->response->status == 'ERROR') {
        die('Error occured: ' . $decoded1->response->errormessage);
    }
    if (isset($decoded1["product"]["id"])) {
        checkIfNumeric($decoded1["product"]["id"]);
        return $decoded1["product"]["id"];
    } else {
        return null;
    }
}

?>
