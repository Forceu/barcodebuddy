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
 * @since      File available since Release 1.2
 */


/**
 * tables for web ui
 * 
 * @author     Marc Ole Bulling
 * @copyright  2019 Marc Ole Bulling
 * @license    https://www.gnu.org/licenses/gpl-3.0.en.html  GNU GPL v3.0
 * @since      File available since Release 1.2
 *
 */




class TableGenerator {
    private $htmlOutput = "";
    
    function __construct($tableHeadItems) {
        $this->htmlOutput  = '<table class="mdl-data-table mdl-js-data-table mdl-cell">
                 <thead>
                    <tr>';
        foreach ($tableHeadItems as $item) {
                $this->htmlOutput = $this->htmlOutput . '<th class="mdl-data-table__cell--non-numeric">' . $item . '</th>';
        }
        $this->htmlOutput = $this->htmlOutput . '    </tr>
                  </thead>
                  <tbody>';
    }
    
    
    function startRow() {
        $this->htmlOutput = $this->htmlOutput . '<tr>';
    }

    function addCell($html) {
            $this->htmlOutput = $this->htmlOutput . '<td class="mdl-data-table__cell--non-numeric">' . $html . '</td>';
    }

    function endRow() {
        $this->htmlOutput = $this->htmlOutput . '</tr>';
    }
    
    function getHtml() {
        return $this->htmlOutput . '</tbody></table>';
    }
    
}


//outputs stored logs to the textarea
function getHtmlLogTextArea() {
    global $db;
    $logs = $db->getLogs();
    if (sizeof($logs) == 0) {
        return "No barcodes processed yet.";
    } else {
        $returnString = '<textarea readonly class="mdl-textfield__input" rows="15">';
        foreach ($logs as $log) {
            $returnString = $returnString . $log . "\r\n";
        }
        return $returnString . '</textarea>';
    }
}

//Generate the table with barcodes
function getHtmlMainMenuTableKnown($barcodes) {
    global $productinfo;
    global $BBCONFIG;
    if (sizeof($barcodes['known']) == 0) {
        return "No known barcodes yet.";
    } else {
        $table        = new TableGenerator(array(
            "Name",
            "Barcode",
            "Quantity",
            "Product",
            "Action",
            "Tags",
            "Create",
            "Remove"
        ));
        $returnString = '<form name="form" method="post" action="' . $_SERVER['PHP_SELF'] . '" >';
        foreach ($barcodes['known'] as $item) {
            $isDisabled = "disabled";
            if ($item['match'] != 0) {
                $isDisabled = "";
            }
            $itemId = $item['id'];
            $table->startRow();
            $table->addCell($item['name']);
            $table->addCell($item['barcode']);
            $table->addCell($item['amount']);
            $table->addCell('<select  onchange=\'enableButton("select_' . $itemId . '", "button_add_' . $item['id'] . '", "button_consume_' . $item['id'] . '")\' id="select_' . $itemId . '" name="select_' . $itemId . '">' . printSelections($item['match'], $productinfo) . '</select>');
            $table->addCell('<button ' . $isDisabled . ' class="mdl-button mdl-js-button mdl-button--raised mdl-js-ripple-effect mdl-button--accent" id="button_add_' . $item['id'] . '" name="button_add" type="submit"  value="' . $itemId . '">Add</button> <button ' . $isDisabled . ' class="mdl-button mdl-js-button mdl-button--raised mdl-js-ripple-effect mdl-button--accent" id="button_consume_' . $item['id'] . '" name="button_consume" type="submit" value="' . $itemId . '">Consume</button>');
            $table->addCell(explodeWords($item['name'], $itemId));
            $table->addCell('<input type="button" onclick="openNewTab(\''.$BBCONFIG["GROCY_BASE_URL"].'product/new?closeAfterCreation&prefillname='.rawurlencode($item['name']).'&prefillbarcode='.$item['barcode'].'\', \''.$item['barcode'].'\')" name="button_createproduct" class="mdl-button mdl-js-button mdl-js-ripple-effect" value="Create Product"/>');
            $table->addCell('<button name="button_delete" type="submit" class="mdl-button mdl-js-button mdl-js-ripple-effect" value="' . $itemId . '">Remove</button>');
            $table->endRow();
        }
        return $returnString . $table->getHtml() . "</form>";
    }
}


//Generate the table with barcodes
function getHtmlMainMenuTableUnknown($barcodes) {
    global $BBCONFIG;
    global $productinfo;
    if (sizeof($barcodes['unknown']) == 0) {
        return "No unknown barcodes yet.";
    } else {
        $table        = new TableGenerator(array(
            "Barcode",
            "Look up",
            "Quantity",
            "Product",
            "Action",
            "Create",
            "Remove"
        ));
        $returnString = '<form name="form" method="post" action="' . $_SERVER['PHP_SELF'] . '" >';
        foreach ($barcodes['unknown'] as $item) {
            $isDisabled = "disabled";
            if ($item['match'] != 0) {
                $isDisabled = "";
            }
            $itemId = $item['id'];
            $table->startRow();
            $table->addCell($item['barcode']);
            $table->addCell('<a href="http://google.com/search?q=' . $item['barcode'] . '" target="_blank">Search for barcode</a>');
            $table->addCell($item['amount']);
            $table->addCell('<select onchange=\'enableButton("select_' . $itemId . '", "button_add_' . $item['id'] . '", "button_consume_' . $item['id'] . '")\' id="select_' . $itemId . '" name="select_' . $itemId . '">' . printSelections($item['match'], $productinfo) . '</select>');
            $table->addCell('<button ' . $isDisabled . ' class="mdl-button mdl-js-button mdl-button--raised mdl-js-ripple-effect mdl-button--accent" id="button_add_' . $item['id'] . '" name="button_add" type="submit"  value="' . $itemId . '">Add</button> <button ' . $isDisabled . ' class="mdl-button mdl-js-button mdl-button--raised mdl-js-ripple-effect mdl-button--accent" id="button_consume_' . $item['id'] . '" name="button_consume" type="submit" value="' . $itemId . '">Consume</button>');
            $table->addCell('<input type="button" onclick="openNewTab(\''.$BBCONFIG["GROCY_BASE_URL"].'product/new?closeAfterCreation&prefillbarcode='.$item['barcode'].'\', \''.$item['barcode'].'\')" name="button_createproduct" class="mdl-button mdl-js-button mdl-js-ripple-effect" value="Create Product"/>');
            $table->addCell('<button name="button_delete" type="submit" class="mdl-button mdl-js-button mdl-js-ripple-effect" value="' . $itemId . '">Remove</button>');
            $table->endRow();
        }
        return $returnString . $table->getHtml() . "</form>";
    }
}




function getHtmlTagTable() {
    $tags = getAllTags();
    if (sizeof($tags) == 0) {
        return "No tags yet.";
    } else {
        $returnString = '<form name="form" method="post" action="' . $_SERVER['PHP_SELF'] . '" >';
        $table        = new TableGenerator(array(
            "Tag",
            "Product",
            "Action"
        ));
        
        foreach ($tags as $tag) {
            $table->startRow();
            $table->addCell($tag['name']);
            $table->addCell($tag['item']);
            $table->addCell('<button name="button_delete" type="submit" class="mdl-button mdl-js-button mdl-js-ripple-effect" value="' . $tag['id'] . '">Delete</button>');
            $table->endRow();
        }
        return $returnString . $table->getHtml() . "</form>";
    }
}





function getHtmlChoreTable() {
    $chores = getAllChores();
    if (sizeof($chores) == 0) {
        return "No chores yet.";
    } else {
        $returnString = '<form name="form" method="post" action="' . $_SERVER['PHP_SELF'] . '" >';
        $table        = new TableGenerator(array(
            "Chore",
            "Barcode",
            "Action"
        ));
        
        foreach ($chores as $chore) {
            $editText   = "Enter new barcode";
            $editValue  = "";
            $buttonText = "Add";
            $labelId    = "barcode_" . $chore['id'];
            $buttonId   = "button_" . $chore['id'];
            if ($chore['barcode'] != null) {
                $editText   = "Barcode will be deleted";
                $buttonText = "Edit";
                $editValue  = $chore['barcode'];
            }
            
            $table->startRow();
            $table->addCell($chore['name']);
            $table->addCell('<div class="mdl-textfield mdl-js-textfield"> 
               <input class="mdl-textfield__input"  onKeyUp="enableButtonGen(\'' . $buttonId . '\', \'' . $labelId . '\', \'' . $editValue . '\')" type="text" value="' . $editValue . '" name="' . $labelId . '" id="' . $labelId . '">
               <label class="mdl-textfield__label" for="' . $labelId . '">' . $editText . '</label></div>');
            $table->addCell('<button name="button_edit" id="' . $buttonId . '" disabled type="submit" class="mdl-button mdl-js-button mdl-button--raised mdl-js-ripple-effect mdl-button--accent" value="' . $chore['id'] . '">' . $buttonText . '</button>');
            $table->endRow();
        }
        return $returnString . $table->getHtml() . '</form>';
    }
}


function getHtmlSettingsGeneral() {
    global $BBCONFIG;
return '<form name="settingsform_1" id="settingsform_1">

		<div class="mdl-textfield mdl-js-textfield mdl-textfield--floating-label">
		    <input class="mdl-textfield__input" value="'.$BBCONFIG["BARCODE_C"].'" type="text" name="BARCODE_C" id="barcode_c">
		    <label class="mdl-textfield__label" for="barcode_c">Barcode: Consume</label>
		  </div>
		<div class="mdl-textfield mdl-js-textfield mdl-textfield--floating-label">
		    <input class="mdl-textfield__input" value="'.$BBCONFIG["BARCODE_CS"].'" type="text" name="BARCODE_CS" id="barcode_cs">
		    <label class="mdl-textfield__label" for="barcode_cs">Barcode: Consume (spoiled)</label>
		  </div><br>
		<div class="mdl-textfield mdl-js-textfield mdl-textfield--floating-label">
		    <input class="mdl-textfield__input" value="'.$BBCONFIG["BARCODE_P"].'" type="text" name="BARCODE_P" id="barcode_p">
		    <label class="mdl-textfield__label" for="barcode_p">Barcode: Purchase</label>
		  </div>
		<div class="mdl-textfield mdl-js-textfield mdl-textfield--floating-label">
		    <input class="mdl-textfield__input" value="'.$BBCONFIG["BARCODE_O"].'" type="text" name="BARCODE_O" id="barcode_o">
		    <label class="mdl-textfield__label" for="barcode_o">Barcode: Open</label>
		  </div><br>
		<div class="mdl-textfield mdl-js-textfield mdl-textfield--floating-label">
		    <input class="mdl-textfield__input" value="'.$BBCONFIG["BARCODE_GS"].'" type="text" name="BARCODE_GS" id="barcode_gs">
		    <label class="mdl-textfield__label" for="barcode_gs">Barcode: Inventory</label>
		  </div>
		<div class="mdl-textfield mdl-js-textfield mdl-textfield--floating-label">
		    <input class="mdl-textfield__input" value="'.$BBCONFIG["BARCODE_Q"].'" type="text" name="BARCODE_Q" id="barcode_q">
		    <label class="mdl-textfield__label" for="barcode_q">Barcode: Quantitiy</label>
		  </div><br>
		<div class="mdl-textfield mdl-js-textfield mdl-textfield--floating-label">
		    <input class="mdl-textfield__input" value="'.$BBCONFIG["BARCODE_AS"].'" type="text" name="BARCODE_AS" id="barcode_as">
		    <label class="mdl-textfield__label" for="barcode_as">Barcode: Add to shopping list</label>
		  </div><br>
<div class="mdl-textfield mdl-js-textfield mdl-textfield--floating-label">
    <input class="mdl-textfield__input" type="text" onkeypress="return (event.charCode == 8 || event.charCode == 0) ? null : event.charCode >= 48 && event.charCode <= 57" pattern="-?[0-9]*(\.[0-9]+)?" value="'.$BBCONFIG["REVERT_TIME"].'" id="general_revert_min"  name="REVERT_TIME">
    <label class="mdl-textfield__label" for="general_revert_min">Revert state to &quot;Purchase&quot; after time passed in minutes</label>
  </div><br>
  <label class="mdl-checkbox mdl-js-checkbox mdl-js-ripple-effect" for="general_revert">
		  <input type="checkbox" value="1" name="REVERT_SINGLE" id="general_revert" class="mdl-checkbox__input" '.setCheckedIfConfigTrue($BBCONFIG["REVERT_SINGLE"]).'>
		  <span class="mdl-checkbox__label">Revert after single item scan in &quot;Open&quot; or &quot;Spoiled&quot; mode</span>
		</label><br>
  <label class="mdl-checkbox mdl-js-checkbox mdl-js-ripple-effect" for="general_verbose">
		  <input type="checkbox" value="1" name="MORE_VERBOSE" id="general_verbose" class="mdl-checkbox__input" '.setCheckedIfConfigTrue($BBCONFIG["MORE_VERBOSE"]).'>
		  <span class="mdl-checkbox__label">More verbose logs</span>
		</label><br>
<label class="mdl-checkbox mdl-js-checkbox mdl-js-ripple-effect" for="general_checkshoppinglist">
		  <input type="checkbox" value="1" name="SHOPPINGLIST_REMOVE" id="general_checkshoppinglist" class="mdl-checkbox__input" '.setCheckedIfConfigTrue($BBCONFIG["SHOPPINGLIST_REMOVE"]).'>
		  <span class="mdl-checkbox__label">Remove purchased items from shoppinglist</span>
		</label><br>
<label class="mdl-checkbox mdl-js-checkbox mdl-js-ripple-effect" for="websocket_fullscreen">
		  <input type="checkbox" value="1" name="WS_FULLSCREEN"  id="websocket_fullscreen" class="mdl-checkbox__input" '.setCheckedIfConfigTrue($BBCONFIG["WS_FULLSCREEN"]).'>
		  <span class="mdl-checkbox__label">Show Screen module in fullscreen</span>
		</label><br></form>';
}
function getHtmlSettingsGrocyApi() {
    global $BBCONFIG;
return '<form name="settingsform_2" id="settingsform_2">
<div class="mdl-textfield mdl-js-textfield mdl-textfield--floating-label">
		    <input class="mdl-textfield__input" pattern="https://.*/api/|http://.*/api/|https://.*/api|http://.*/api" size="60" value="'.$BBCONFIG["GROCY_API_URL"].'" name="GROCY_API_URL" type="text" id="grocy_url">
		    <label class="mdl-textfield__label" for="grocy_url">Grocy API URL</label>
		  </div><br>
		<div class="mdl-textfield mdl-js-textfield mdl-textfield--floating-label">
		    <input class="mdl-textfield__input" pattern="[A-Za-z0-9]{50}"   size="60" value="'.$BBCONFIG["GROCY_API_KEY"].'" name="GROCY_API_KEY"  type="text" id="grocy_api_key">
		    <label class="mdl-textfield__label" for="grocy_api_key">Grocy API Key</label>
		  </div></form>';
}
function getHtmlSettingsWebsockets() {
    global $BBCONFIG;
return  '<form name="settingsform_3" id="settingsform_3">

	<label class="mdl-checkbox mdl-js-checkbox mdl-js-ripple-effect" for="websocket_use">
		  <input type="checkbox" value="1" name="WS_USE" onChange="switchWebsocketCheckboxes()"  id="websocket_use" class="mdl-checkbox__input" '.setCheckedIfConfigTrue($BBCONFIG["WS_USE"] || IS_DOCKER).'>
		  <span class="mdl-checkbox__label">Use websockets</span>
		</label> <br>
 <div class="mdl-textfield mdl-js-textfield mdl-textfield--floating-label">
    <input class="mdl-textfield__input" type="text" onkeypress="return (event.charCode == 8 || event.charCode == 0) ? null : event.charCode >= 48 && event.charCode <= 57" pattern="-?[0-9]*(\.[0-9]+)?" value="'.$BBCONFIG["WS_PORT_EXT"].'" id="websocket_port_external" name="WS_PORT_EXT">
    <label class="mdl-textfield__label" for="websocket_port_external">Websocket Port External</label>
  </div><br><br>
  <label class="mdl-checkbox mdl-js-checkbox mdl-js-ripple-effect" for="websocket_ssl_use">
		  <input type="checkbox" value="1" name="WS_SSL_USE" onChange="switchWebsocketCheckboxes()" id="websocket_ssl_use" class="mdl-checkbox__input" '.setCheckedIfConfigTrue($BBCONFIG["WS_SSL_USE"]).'>
		  <span class="mdl-checkbox__label">Use SSL proxy</span>
		</label><br>
 <div class="mdl-textfield mdl-js-textfield mdl-textfield--floating-label">
    <input class="mdl-textfield__input" type="text" value="'.$BBCONFIG["WS_SSL_URL"].'" name="WS_SSL_URL" id="websocket_ssl_url">
    <label class="mdl-textfield__label" for="websocket_ssl_url">External websocket URL</label>
  </div><br>
<div id="progressbar" style="display:none" class="mdl-progress mdl-js-progress mdl-progress__indeterminate"></div></form>
    <script> var IS_DOCKER = ' . var_export(IS_DOCKER, true) . ' </script>
';
}

function getHtmlSettingsHiddenValues() {
return '<form name="settingsform_4" id="settingsform_4">
<input type="hidden" value="0" name="REVERT_SINGLE_hidden"/>
<input type="hidden" value="0" name="MORE_VERBOSE_hidden"/>
<input type="hidden" value="0" name="SHOPPINGLIST_REMOVE_hidden"/>
<input type="hidden" value="0" name="WS_USE_hidden"/>
<input type="hidden" value="0" name="WS_FULLSCREEN_hidden"/>
<input type="hidden" value="0" name="WS_SSL_USE_hidden"/>
<input type="hidden" value="1" name="isSaved"/>
</form>';
}

function setCheckedIfConfigTrue($value) {
    if ($value=="1") {
        return "checked";
    }
    return "";
}


function getHtmlSetupTable($result) {
    global $BBCONFIG;
    $returnString = '<form name="settingsform" id="settingsform" method="post" action="' . $_SERVER['PHP_SELF'] . '" >
        Welcome to Barcode Buddy! Please enter your Grocy API details below. For more information, please visit the <a target="_blank" href="https://github.com/Forceu/barcodebuddy/wiki/2.0-Installation">wiki</a>.<br><br><br>
        <div class="mdl-textfield mdl-js-textfield mdl-textfield--floating-label">
            <input pattern="https://.*/api/|http://.*/api/|https://.*/api|http://.*/api" class="mdl-textfield__input" size="60" ';

    if (isset($_POST["GROCY_API_URL"])) {
        $returnString = $returnString . 'value="' . $_POST["GROCY_API_URL"] . '" ';
    } else {   
       if ($BBCONFIG["GROCY_API_URL"] != null) {
           $returnString = $returnString . 'value="' . $BBCONFIG["GROCY_API_URL"] . '" ';
       }
    }
     $returnString = $returnString . ' placeholder="e.g. https://your.grocy.com/api/" name="GROCY_API_URL" type="text" id="grocy_url">
            <label class="mdl-textfield__label" for="grocy_url">Grocy API URL</label>
          </div><br>
        <div class="mdl-textfield mdl-js-textfield mdl-textfield--floating-label">
            <input pattern="[A-Za-z0-9]{50}" class="mdl-textfield__input"  size="60" ';
    if (isset($_POST["GROCY_API_KEY"])) {
         $returnString = $returnString . 'value="' . $_POST["GROCY_API_KEY"] . '" ';
    }
     $returnString = $returnString . 'name="GROCY_API_KEY"  type="text" id="grocy_api_key">
            <label class="mdl-textfield__label" for="grocy_api_key">Grocy API Key</label>
          </div><br>';
    if ($result !== true) {
         $returnString = $returnString . '<font color="red">Unable to connect to API: '.$result.'</font>';
    }
     $returnString = $returnString . '<br>
	<button type="submit" class="mdl-button mdl-js-button mdl-button--raised mdl-js-ripple-effect mdl-button--accent">
	  Save
	</button></form>';
    return $returnString;
}

function formatExtentioncolor($name, $installed, $req) {
    if ($installed) {
    return 'Installed: <span style="color:green">' . $name . '</span><br>';
    } else {
        if ($req) {
   	 return '<b>Not installed: <span style="color:red">' . $name . ' (required)</span></b><br>';
        } else {
   	 return 'Not installed: <span style="color:#d0a42c">' . $name . ' (optional)</span><br>';
        }
    }
}

function getHtmlSetupExtMissing() {
$returnString ='<form name="settingsform" id="settingsform" method="post" action="' . $_SERVER['PHP_SELF'] . '" >
		Welcome to Barcode Buddy! Please make sure the following extensios are installed and enabled:<br><br>'; 
		$extensions = checkExtensionsInstalled();
		foreach ($extensions["req"] as $ext=>$installed) {
			$returnString=$returnString.formatExtentioncolor($ext,$installed,true);
		}
		foreach ($extensions["opt"] as $ext=>$installed) {
			$returnString=$returnString.formatExtentioncolor($ext,$installed,false);
		}
		$returnString=$returnString.'<br><br>
		<button type="submit" class="mdl-button mdl-js-button mdl-button--raised mdl-js-ripple-effect mdl-button--accent">
		  Refresh
		</button></form>';
return $returnString;
}



function printSettingsQuantity() {
//TODO
}

function printSettingsQuantityTable(){
    global $db;
    $quantities = $db->getQuantities();
     if (sizeof($quantities) == 0) {
        return "No saved quantities yet.";
    } else {
        $returnString = '<form name="form" method="post" action="' . $_SERVER['PHP_SELF'] . '" >';
        $table        = new TableGenerator(array(
            "Product",
            "Barcode",
            "Quantitiy",
            "Action"
        ));
        
        foreach ($quantities as $quantity) {
            $table->startRow();
            $table->addCell($quantity['product']);
            $table->addCell($quantity['barcode']);
            $table->addCell($quantity['quantitiy']);
            $table->addCell('<button name="button_delete" type="submit" class="mdl-button mdl-js-button mdl-js-ripple-effect" value="' . $quantity['id'] . '">Delete</button>');
            $table->endRow();
        }
        return $returnString . $table->getHtml() . "</form>";
    }

}



?>
