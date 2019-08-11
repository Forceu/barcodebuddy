<?php
/**
 * Barcode Buddy for Grocy
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
 *
 * TODO: Versions newer than 2.4.4 have easier API for shoppinglist and add product (BB-date)
 */


const API_PRODUCTS = 'objects/products';
const API_SHOPPINGLIST = 'objects/shopping_list';
const API_CHORES = 'objects/chores';
const API_STOCK    = 'stock/products';
const API_CHORE_EXECUTE    = 'chores/';
const API_SYTEM_INFO    = 'system/info';

// Getting info of a Grocy product. If no argument is passed, all products are requested
function getProductInfo($productId = "") {
    global $BBCONFIG;

    if ($productId == "") {
        $apiurl = $BBCONFIG["GROCY_API_URL"].API_PRODUCTS;
    } else {
        $apiurl = $BBCONFIG["GROCY_API_URL"].API_PRODUCTS . "/" . $productId;
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiurl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('GROCY-API-KEY: '. $BBCONFIG["GROCY_API_KEY"]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $curl_response = curl_exec($ch);
    curl_close($ch);
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
    global $BBCONFIG;
    $data = array(
        'amount' => "1"
    );
    $data_json = json_encode($data);
    
    $apiurl = $BBCONFIG["GROCY_API_URL"].API_STOCK . "/" . $id . "/open";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiurl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('GROCY-API-KEY: '. $BBCONFIG["GROCY_API_KEY"],'Content-Type: application/json','Content-Length: '.strlen($data_json)));
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_json);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    if ($response === false) {
       die("Error opening product");
    }
}



// Check if API details are correct
function checkApiConnection($givenurl, $apikey) {
    $apiurl = $givenurl.API_SYTEM_INFO;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiurl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('GROCY-API-KEY: '. $apikey));
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    if ($response === false) {
       return false;
    }
    $decoded1 = json_decode($response, true);
    if (isset($decoded1->response->status) && $decoded1->response->status == 'ERROR') {
        die('Error occured: ' . $decoded1->response->errormessage);
    }
    return (isset($decoded1["grocy_version"]["Version"]));
}


// Add a Grocy product. Return: false if default best before date not set
function purchaseProduct($id, $amount, $bestbefore = null, $price = null) {
    global $BBCONFIG;

    $daysBestBefore = 0;
    $data = array(
        'amount' => $amount,
        'transaction_type' => 'purchase'
    );
    if ($price != null) {
        $data['price'] = $price;
    }
    if ($bestbefore != null) {
        $daysBestBefore           = $bestbefore;
        $data['best_before_date'] = $bestbefore;
    } else {
        $daysBestBefore           = getDefaultBestBeforeDays($id);
        $data['best_before_date'] = formatBestBeforeDays($daysBestBefore);
    }
    $data_json = json_encode($data);
    
    
    $apiurl = $BBCONFIG["GROCY_API_URL"].API_STOCK . "/" . $id . "/add";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiurl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('GROCY-API-KEY: '. $BBCONFIG["GROCY_API_KEY"],'Content-Type: application/json','Content-Length: '.strlen($data_json)));
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_json);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    if ($response === false) {
       die("Error purchasing product");
    }

    if ($BBCONFIG["SHOPPINGLIST_REMOVE"]) {
        removeFromShoppinglist($id,$amount);
    }
    return ($daysBestBefore != 0);
}


function removeFromShoppinglist($productid, $amount) {
$items = getShoppingList();
 foreach ($items as $item) {
	$deleteItem = false;
	if (isset($item["product_id"]) && $item["product_id"]==$productid) {
		$modified = true;
		$remaining = ($item["amount"] - $amount);
		if ($remaining <1) {
			deleteShoppingListItem($item["id"]);
		} else {
			setShoppingListItemAmount($item["id"],$remaining);
		}
	}
}
}

function setShoppingListItemAmount($itemid, $remaining) {
 global $BBCONFIG;
     $data      = array(
        'amount' => $remaining
    );
    $data_json = json_encode($data);
    $apiurl = $BBCONFIG["GROCY_API_URL"].API_SHOPPINGLIST."/".$itemid;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiurl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('GROCY-API-KEY: '. $BBCONFIG["GROCY_API_KEY"],'Content-Type: application/json','Content-Length: '.strlen($data_json)));
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_json);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    if ($response === false) {
       die("Error setting barcode");
    }
}

function deleteShoppingListItem($itemid) {
  global $BBCONFIG;

        $apiurl = $BBCONFIG["GROCY_API_URL"].API_SHOPPINGLIST."/".$itemid;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiurl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('GROCY-API-KEY: '. $BBCONFIG["GROCY_API_KEY"]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    $curl_response = curl_exec($ch);
    curl_close($ch);
    if ($curl_response === false) {
        die("Error deleting shopping list item");
    }
}

function getShoppingList() {
    global $BBCONFIG;

        $apiurl = $BBCONFIG["GROCY_API_URL"].API_SHOPPINGLIST;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiurl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('GROCY-API-KEY: '. $BBCONFIG["GROCY_API_KEY"]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $curl_response = curl_exec($ch);
    curl_close($ch);
    if ($curl_response === false) {
        die("Error getting shoppinglist");
    }
    
    $decoded1 = json_decode($curl_response, true);
    if (isset($decoded1->response->status) && $decoded1->response->status == 'ERROR') {
        die('Error occured: ' . $decoded1->response->errormessage);
    }
    return $decoded1;
}


// Consume a Grocy product
function consumeProduct($id, $amount, $spoiled = "false") {
    global $BBCONFIG;
    $data      = array(
        'amount' => $amount,
        'transaction_type' => 'consume',
        'spoiled' => $spoiled
    );
    $data_json = json_encode($data);
    
    $apiurl = $BBCONFIG["GROCY_API_URL"].API_STOCK . "/" . $id . "/consume";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiurl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('GROCY-API-KEY: '. $BBCONFIG["GROCY_API_KEY"],'Content-Type: application/json','Content-Length: '.strlen($data_json)));
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
    global $BBCONFIG;
    
    $data      = array('barcode' => $barcode);
    $data_json = json_encode($data);
    $apiurl = $BBCONFIG["GROCY_API_URL"].API_PRODUCTS . "/" . $id;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiurl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('GROCY-API-KEY: '. $BBCONFIG["GROCY_API_KEY"],'Content-Type: application/json','Content-Length: '.strlen($data_json)));
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_json);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    if ($response === false) {
       die("Error setting barcode");
    }
}



// Formats the amount of days into future date
function formatBestBeforeDays($days) {
    if ($days == "-1") {
        return "2999-12-31";
    } else {
        $date = date("Y-m-d");
        return date('Y-m-d', strtotime($date . " + $days days"));
    }
}

// Get default best before in days from a Grocy product
function getDefaultBestBeforeDays($id) {
    $info      = getProductInfo($id);
    $days      = $info["default_best_before_days"];
    checkIfNumeric($days);
    return $days;
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
        if (isset($decoded1["product"]["generic_name"]) && $decoded1["product"]["generic_name"]!="") {
            return sanitizeString($decoded1["product"]["generic_name"]);
        }
        if (isset($decoded1["product"]["product_name"])&& $decoded1["product"]["product_name"]!="") {
            return sanitizeString($decoded1["product"]["product_name"]);
        }
        return "N/A";
    }
}


// Get a Grocy product by barcode
function getProductByBardcode($barcode) {
    global $BBCONFIG;
    
    $apiurl = $BBCONFIG["GROCY_API_URL"].API_STOCK . "/by-barcode/" . $barcode;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiurl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('GROCY-API-KEY: '. $BBCONFIG["GROCY_API_KEY"]));
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
        $resultArray = array();
	$resultArray["id"]=$decoded1["product"]["id"];
	$resultArray["name"]=sanitizeString($decoded1["product"]["name"]);
	$resultArray["unit"]=sanitizeString($decoded1["quantity_unit_stock"]["name"]);
	return $resultArray;
    } else {
        return null;
    }
}



// Getting info of a Grocy chore. If no argument is passed, all products are requested
function getChoresInfo($choreId = "") {
    global $BBCONFIG;
    
    if ($choreId == "") {
        $apiurl = $BBCONFIG["GROCY_API_URL"].API_CHORES;
    } else {
        $apiurl = $BBCONFIG["GROCY_API_URL"].API_CHORES . "/" . $choreId;
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiurl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('GROCY-API-KEY: '. $BBCONFIG["GROCY_API_KEY"]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $curl_response = curl_exec($ch);
    curl_close($ch);
    if ($curl_response === false) {
        die("Error getting chore info");
    }
    
    $decoded1 = json_decode($curl_response, true);
    if (isset($decoded1->response->status) && $decoded1->response->status == 'ERROR') {
        die('Error occured: ' . $decoded1->response->errormessage);
    }
    return $decoded1;
}


// Getting info of a Grocy chore. If no argument is passed, all products are requested
function executeChore($choreId) {
    global $BBCONFIG;
    
    $apiurl = $BBCONFIG["GROCY_API_URL"].API_CHORE_EXECUTE . $choreId. "/execute" ;
    $data      = array('tracked_time' => "", 'done_by' => "");
    $data_json = json_encode($data);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiurl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('GROCY-API-KEY: '. $BBCONFIG["GROCY_API_KEY"],'Content-Type: application/json','Content-Length: '.strlen($data_json)));
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_json);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $curl_response = curl_exec($ch);
    curl_close($ch);
    if ($curl_response === false) {
        die("Error getting chore info");
    }
    
    $decoded1 = json_decode($curl_response, true);
    if (isset($decoded1->response->status) && $decoded1->response->status == 'ERROR') {
        die('Error occured: ' . $decoded1->response->errormessage);
    }
    return $decoded1;
}


?>
