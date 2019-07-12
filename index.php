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
 * Index file that receives barcodes and displays web UI
 *
 * Make sure to modify API details. This script requires php-sqlite3 and php-curl
 * 
 * @author     Marc Ole Bulling
 * @copyright  2019 Marc Ole Bulling
 * @license    https://www.gnu.org/licenses/gpl-3.0.en.html  GNU GPL v3.0
 * @since      File available since Release 1.0
 *
 * TODO:  - Disable button on web ui if "none" is selected
 *        - Refactor SQL code in this file to db.inc.php
 */





require_once "./config.php";
require_once "./api.inc.php";
require_once "./db.inc.php";
require_once "./processing.inc.php";
initDb();


testIfApiIsSet();

//If barcodes are passed through CLI or GET, process them and do not do anything else
if (isset($argv[1])) {
    processNewBarcode(sanitizeString($argv[1], true));
    die;
}
if (isset($_GET["add"])) {
    processNewBarcode(sanitizeString($_GET["add"], true));
    if (!isset($_GET["showui"])) {
       die("OK");
    }
}


?>

<!DOCTYPE html>
<html>
<head>
<title>Barcode Buddy</title>
<style>
table {
  font-family: arial, sans-serif;
  border-collapse: collapse;
  width: 100%;
}

td, th {
  border: 1px solid #dddddd;
  text-align: left;
  padding: 8px;
}

tr:nth-child(even) {
  background-color: #dddddd;
}
</style>
</head>
<body>

<h1 style="color: #5e9ca0;">Barcode Buddy</h1>
<form name="form" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" >
<table>
<thead>
<tr>
<td>Name</td>
<td>Barcode</td>
<td>Amount</td>
<td>Chosen Product</td>
<td>Apply</td>
<td>Add tags</td>
</tr>
</thead>
<tbody>

<?php

// If a button was pressed, we are processing everything here.
// Only one row can be processed at a time

if (isset($_POST["button_delete"])) {
    $id = $_POST["button_delete"];
    checkIfNumeric($id);
    deleteBarcode($id);
} else {
    if (isset($_POST["button_add"]) || isset($_POST["button_consume"])) {
        if (isset($_POST["button_consume"])) {
            $isConsume = true;
            $id        = $_POST["button_consume"];
        } else {
            $isConsume = false;
            $id        = $_POST["button_add"];
        }
        checkIfNumeric($id);
        $gidSelected = $_POST["select_" . $id];
        $res         = $db->query("SELECT * FROM Barcodes WHERE id='$id'");
        if ($gidSelected != 0 && ($row = $res->fetchArray())) {
            $barcode  = sanitizeString($row["barcode"], true);
            $amount   = $row["amount"];
            $i        = 0;
            $tagArray = array();
            checkIfNumeric($amount);
            while (isset($_POST["tags_" . $id . "_" . $i])) {
                array_push($tagArray, sanitizeString($_POST["tags_" . $id . "_" . $i]));
                $i++;
            }
            foreach ($tagArray as $tag) {
                $db->exec("INSERT INTO Tags(tag, itemId) VALUES('$tag', $gidSelected);");
            }
            $previousBarcodes = getProductInfo(sanitizeString($gidSelected)["barcode"]);
            if ($previousBarcodes == NULL) {
                setBarcode($gidSelected, $barcode);
            } else {
                setBarcode($gidSelected, $previousBarcodes . "," . $barcode);
            }
            deleteBarcode($id);
            if ($isConsume) {
                consumeProduct($gidSelected, $amount);
            } else {
                purchaseProduct($gidSelected, $amount);
            }
            
        }
    }
}

// This generates the table with all unknown barcodes
$res = $db->query('SELECT * FROM Barcodes');


while ($row = $res->fetchArray()) {
    $id                = $row['id'];
    $barcode           = $row['barcode'];
    $displayedName     = $row['name'];
    if ($displayedName == "N/A") {
        $displayedName = 'Not found. <a href="http://google.com/search?q='.$barcode.'" target="_blank">Search for barcode</a>';
    }
    echo "<tr><td>" . $displayedName . "</td><td>" . $barcode . "</td><td>" . $row['amount'] . "</td><td><select name=\"select_$id\">" . printSelections($row['possibleMatch']) . "</select>" . "</td><td><button name=\"button_add\" type=\"submit\" value=\"$id\">Add</button> <button name=\"button_consume\" type=\"submit\" value=\"$id\">Consume</button> <button name=\"button_delete\" type=\"submit\" value=\"$id\">Delete</button></td><td>" . explodeWords($row['name'], $id) . "</td></tr>";
}

?>

</tbody>
</table>
</form>
<p>&nbsp;</p>


</body>
</html>
