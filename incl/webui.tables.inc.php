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




function printMainTables() {
global $productinfo;
echo '
      <main class="mdl-layout__content" style="flex: 1 0 auto;">
        <div class="mdl-layout__tab-panel is-active" id="overview">
       <section class="section--center mdl-grid--no-spacing mdl-grid mdl-shadow--2dp">
            <div class="mdl-card mdl-cell  mdl-cell--12-col">
              <div class="mdl-card__supporting-text" style="overflow-x: auto; ">
                <h4>New Barcodes</h4><br>';
		$barcodes = getStoredBarcodes();
		if (sizeof($barcodes['known']) > 0 || sizeof($barcodes['unknown']) > 0) {
		    $productinfo = getProductInfo();
		}
		echo generateTableMain($barcodes, true);
		echo '</div>
            </div>
            <button class="mdl-button mdl-js-button mdl-js-ripple-effect mdl-button--icon" id="btn1">
              <i class="material-icons">more_vert</i>
            </button>
            <ul class="mdl-menu mdl-js-menu mdl-menu--bottom-right" for="btn1">
              <li class="mdl-menu__item" onclick="window.location.href=\''.$_SERVER['PHP_SELF'].'?delete=known\'">Delete all</li>
            </ul>
          </section>
       <section class="section--center mdl-grid mdl-grid--no-spacing mdl-shadow--2dp">
            <div class="mdl-card mdl-cell mdl-cell--12-col">
              <div class="mdl-card__supporting-text" style="overflow-x: auto;" >
                <h4>Unknown Barcodes</h4>';
		echo generateTableMain($barcodes, false);
		echo '              </div>
            </div>
            <button class="mdl-button mdl-js-button mdl-js-ripple-effect mdl-button--icon" id="btn2">
              <i class="material-icons">more_vert</i>
            </button>
            <ul class="mdl-menu mdl-js-menu mdl-menu--bottom-right" for="btn2">
              <li class="mdl-menu__item" onclick="window.location.href=\''.$_SERVER['PHP_SELF'].'?delete=unknown\'">Delete all</li>
            </ul>
          </section>
          <section class="section--center mdl-grid mdl-grid--no-spacing mdl-shadow--2dp">
            <div class="mdl-card mdl-cell mdl-cell--12-col">
              <div class="mdl-card__supporting-text">
                <h4>Processed Barcodes</h4>';
		printLog();
		echo'              </div>
            </div>
            <button class="mdl-button mdl-js-button mdl-js-ripple-effect mdl-button--icon" id="btn3">
              <i class="material-icons">more_vert</i>
            </button>
            <ul class="mdl-menu mdl-js-menu mdl-menu--bottom-right" for="btn3">
              <li class="mdl-menu__item" onclick="window.location.href=\''.$_SERVER['PHP_SELF'].'?delete=log\'">Clear log</li>
            </ul>

          </section>
          <section class="section--footer mdl-grid">
          </section>
        </div>';
}

//Generate the table with barcodes
function generateTableMain($barcodes, $isKnown) {
    if ($isKnown) {
        if (sizeof($barcodes['known']) == 0) {
            return "No known barcodes yet.";
        } else {
            $returnString = '<form name="form" method="post" action="' . $_SERVER['PHP_SELF'] . '" >
                <table class="mdl-data-table mdl-js-data-table mdl-cell " >
                 <thead>
                    <tr>
                      <th class="mdl-data-table__cell--non-numeric">Name</th>
                      <th class="mdl-data-table__cell--non-numeric">Barcode</th>
                      <th>Quantity</th>
                      <th class="mdl-data-table__cell--non-numeric">Product</th>
                      <th class="mdl-data-table__cell--non-numeric">Action</th>
                      <th class="mdl-data-table__cell--non-numeric">Tags</th>
                      <th class="mdl-data-table__cell--non-numeric">Delete</th>
                    </tr>
                  </thead>
                  <tbody>';
            
            $returnString = $returnString . generateTableRowMain($barcodes, true) . '</tbody>
                </table>
                </form>';
            return $returnString;
        }
    } else {
        if (sizeof($barcodes['unknown']) == 0) {
            return "No unknown barcodes yet.";
        } else {
            $returnString = '<form name="form" method="post" action="' . $_SERVER['PHP_SELF'] . '" >
                <table class="mdl-data-table mdl-js-data-table mdl-cell " >
                 <thead>
                    <tr>
                      <th class="mdl-data-table__cell--non-numeric">Barcode</th>
                      <th class="mdl-data-table__cell--non-numeric">Look up</th>
                      <th>Quantity</th>
                      <th class="mdl-data-table__cell--non-numeric">Product</th>
                      <th class="mdl-data-table__cell--non-numeric">Action</th>
                      <th class="mdl-data-table__cell--non-numeric">Delete</th>
                    </tr>
                  </thead>
                  <tbody>';
            
            $returnString = $returnString . generateTableRowMain($barcodes, false) . '</tbody>
                </table>
                </form>';
            return $returnString;
        }
    }
}


//generate each row for the table
function generateTableRowMain($barcodes, $isKnown) {
    global $productinfo;
    $returnString = "";
    if ($isKnown) {
        foreach ($barcodes['known'] as $item) {
            $isDisabled="disabled";
	    if ($item['match']!=0) {
		$isDisabled="";
	    }
            $itemId=$item['id'];
            $returnString = $returnString . '<tr>
        <td class="mdl-data-table__cell--non-numeric">' . $item['name'] . '</td>
              <td class="mdl-data-table__cell--non-numeric">' . $item['barcode'] . '</td>
              <td>' . $item['amount'] . '</td>
              <td class="mdl-data-table__cell--non-numeric"><select  onchange=\'enableButton("select_' . $itemId . '", "button_add_'.$item['id'].'", "button_consume_'.$item['id'].'")\'  name="select_' . $itemId . '")" id="select_' . $itemId . '" name="select_' . $itemId . '">' . printSelections($item['match'], $productinfo) . '</select></td>
        <td><button '.$isDisabled.' class="mdl-button mdl-js-button mdl-button--raised mdl-js-ripple-effect mdl-button--accent" id="button_add_'.$item['id'].'" name="button_add" type="submit"  value="' . $itemId . '">Add</button> <button '.$isDisabled.' class="mdl-button mdl-js-button mdl-button--raised mdl-js-ripple-effect mdl-button--accent" id="button_consume_'.$item['id'].'" name="button_consume" type="submit" value="' . $itemId . '">Consume</button> </td>
        <td>' . explodeWords($item['name'], $itemId) . '</td>
        <td><button name="button_delete" type="submit" class="mdl-button mdl-js-button mdl-js-ripple-effect" value="' . $itemId . '">Delete</button></td></tr>';
        }
    } else {
        foreach ($barcodes['unknown'] as $item) {
            $itemId=$item['id'];
            $returnString = $returnString . '<tr>
    <td class="mdl-data-table__cell--non-numeric">' . $item['barcode'] . '</td>
          <td class="mdl-data-table__cell--non-numeric"><a href="http://google.com/search?q=' . $item['barcode'] . '" target="_blank">Search for barcode</a></td>
          <td>' . $item['amount'] . '</td>
          <td class="mdl-data-table__cell--non-numeric"><select onchange=\'enableButton("select_' . $itemId . '", "button_add_'.$item['id'].'", "button_consume_'.$item['id'].'")\' id="select_' . $itemId . '" name="select_' . $itemId . '">' . printSelections($item['match'], $productinfo) . '</select></td>
        <td><button disabled class="mdl-button mdl-js-button mdl-button--raised mdl-js-ripple-effect mdl-button--accent" id="button_add_'.$item['id'].'" name="button_add" type="submit"  value="' . $itemId . '">Add</button> <button disabled class="mdl-button mdl-js-button mdl-button--raised mdl-js-ripple-effect mdl-button--accent" id="button_consume_'.$item['id'].'" name="button_consume" type="submit" value="' . $itemId . '">Consume</button> </td>
        <td><button name="button_delete" type="submit" class="mdl-button mdl-js-button mdl-js-ripple-effect" value="' . $itemId . '">Delete</button></td></tr>';
        }
    }
    return $returnString;
}



function printSettingsTagTable() {
echo ' <main class="mdl-layout__content" style="flex: 1 0 auto;">
        <div class="mdl-layout__tab-panel is-active" id="overview">
       <section class="section--center mdl-grid--no-spacing mdl-grid mdl-shadow--2dp">
            <div class="mdl-card mdl-cell  mdl-cell--12-col">
              <div class="mdl-card__supporting-text" style="overflow-x: auto; ">
                <h4>Stored Tags</h4><br>';

$tags = getAllTags();
		 echo  generateTableSettingsTag($tags);

echo'            </div>
        </div>
          </section>
          <section class="section--footer mdl-grid">
          </section>
        </div>';
}


function generateTableSettingsTag($tags) {
 if (sizeof($tags) == 0) {
            return "No tags yet.";
        } else {
            $returnString = '<form name="form" method="post" action="' . $_SERVER['PHP_SELF'] . '" >
                <table class="mdl-data-table mdl-js-data-table mdl-cell " >
                 <thead>
                    <tr>
                      <th class="mdl-data-table__cell--non-numeric">Tag</th>
                      <th class="mdl-data-table__cell--non-numeric">Product</th>
                      <th class="mdl-data-table__cell--non-numeric">Action</th>
                    </tr>
                  </thead>
                  <tbody>';
            
            $returnString = $returnString . generateTableRowSettingsTag($tags) . '</tbody>
                </table>
                </form>';
            return $returnString;
}
}


//generate each row for the table
function generateTableRowSettingsTag($tags) {
    $returnString = "";
        foreach ($tags as $tag) {
            $returnString = $returnString . '<tr>
        <td class="mdl-data-table__cell--non-numeric">' . $tag['name'] . '</td>
              <td class="mdl-data-table__cell--non-numeric">' . $tag['item'] . '</td>
        <td><button name="button_delete" type="submit" class="mdl-button mdl-js-button mdl-js-ripple-effect" value="' . $tag['id'] . '">Delete</button></td></tr>';
        }
    return $returnString;
}




function printSettingsChoresTable() {
echo ' <main class="mdl-layout__content" style="flex: 1 0 auto;">
        <div class="mdl-layout__tab-panel is-active" id="overview">
       <section class="section--center mdl-grid--no-spacing mdl-grid mdl-shadow--2dp">
            <div class="mdl-card mdl-cell  mdl-cell--12-col">
              <div class="mdl-card__supporting-text" style="overflow-x: auto; ">
                <h4>Chores</h4><br>';

$chores = getAllChores();
		 echo  generateTableSettingsChores($chores);

echo'            </div>
        </div>
          </section>
          <section class="section--footer mdl-grid">
          </section>
        </div>';
}


function generateTableSettingsChores($chores) {
 if (sizeof($chores) == 0) {
            return "No chores yet.";
        } else {
            $returnString = '<form name="form" method="post" action="' . $_SERVER['PHP_SELF'] . '" >
                <table class="mdl-data-table mdl-js-data-table mdl-cell " >
                 <thead>
                    <tr>
                      <th class="mdl-data-table__cell--non-numeric">Chore</th>
                      <th class="mdl-data-table__cell--non-numeric">Barcode</th>
                      <th class="mdl-data-table__cell--non-numeric">Action</th>
                    </tr>
                  </thead>
                  <tbody>';
            
            $returnString = $returnString . generateTableRowSettingsChores($chores) . '</tbody>
                </table>
                </form>';
            return $returnString;
}
}


//generate each row for the table
function generateTableRowSettingsChores($chores) {
    $returnString = "";
        foreach ($chores as $tag) {
            $editText = "Enter new barcode";
	    $editValue= "";
	    $buttonText= "Add";
            $labelId = "barcode_".$tag['id'];
            $buttonId = "button_".$tag['id'];
            if ($tag['barcode']!=null) {
               $editText = "Barcode will be deleted";
	       $buttonText= "Edit";
               $editValue=$tag['barcode'];
	    }
            $returnString = $returnString . '<tr>
              <td class="mdl-data-table__cell--non-numeric">' . $tag['name'] . '</td>
              <td class="mdl-data-table__cell--non-numeric"> <div class="mdl-textfield mdl-js-textfield"> 
               <input class="mdl-textfield__input"  onKeyUp="enableButtonGen(\''.$buttonId.'\', \''.$labelId.'\', \''.$editValue.'\')" type="text" value="'.$editValue.'" name="'.$labelId.'" id="'.$labelId.'">
               <label class="mdl-textfield__label" for="'.$labelId.'">'. $editText .'</label></div></td>
        <td><button name="button_edit" id="'.$buttonId.'" disabled type="submit" class="mdl-button mdl-js-button mdl-button--raised mdl-js-ripple-effect mdl-button--accent" value="' . $tag['id'] . '">'.$buttonText.'</button></td></tr>';
        }
    return $returnString;
}


function setCheckedIfConfigTrue($value) {
    if ($value=="1") {
        return "checked";
    }
    return "";
}

function printSettingsGeneralSettingsTable() {
    global $BBCONFIG;

    echo'<main class="mdl-layout__content" style="flex: 1 0 auto;">
        <div class="mdl-layout__tab-panel is-active" id="overview">
	<form name="settingsform" onsubmit="return integerCheck(this)" id="settingsform" method="post" action="' . $_SERVER['PHP_SELF'] . '" >
        <section class="section--center mdl-grid--no-spacing mdl-grid mdl-shadow--2dp">
            <div class="mdl-card mdl-cell  mdl-cell--12-col">
              <div class="mdl-card__supporting-text" style="overflow-x: auto; ">
                <h4>General Settings</h4><br>
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
		</label>
		</div>
            </div>
          </section>
          <section class="section--center mdl-grid--no-spacing mdl-grid mdl-shadow--2dp">
            <div class="mdl-card mdl-cell  mdl-cell--12-col">
              <div class="mdl-card__supporting-text" style="overflow-x: auto; ">
                <h4>Grocy API</h4><br>
		<div class="mdl-textfield mdl-js-textfield mdl-textfield--floating-label">
		    <input class="mdl-textfield__input" pattern="https://.*/api/|http://.*/api/|https://.*/api|http://.*/api" size="60" value="'.$BBCONFIG["GROCY_API_URL"].'" name="GROCY_API_URL" type="text" id="grocy_url">
		    <label class="mdl-textfield__label" for="grocy_url">Grocy API URL</label>
		  </div><br>
		<div class="mdl-textfield mdl-js-textfield mdl-textfield--floating-label">
		    <input class="mdl-textfield__input" pattern="[A-Za-z0-9]{50}"   size="60" value="'.$BBCONFIG["GROCY_API_KEY"].'" name="GROCY_API_KEY"  type="text" id="grocy_api_key">
		    <label class="mdl-textfield__label" for="grocy_api_key">Grocy API Key</label>
		  </div>
		</div>
            </div>
          </section>
          <section class="section--center mdl-grid--no-spacing mdl-grid mdl-shadow--2dp">
            <div class="mdl-card mdl-cell  mdl-cell--12-col">
              <div class="mdl-card__supporting-text" style="overflow-x: auto; ">
                <h4>Websockets</h4><br>
                   <label class="mdl-checkbox mdl-js-checkbox mdl-js-ripple-effect" for="websocket_use">
		  <input type="checkbox" value="1" name="WS_USE" onChange="switchElements()"  id="websocket_use" class="mdl-checkbox__input" '.setCheckedIfConfigTrue($BBCONFIG["WS_USE"]).'>
		  <span class="mdl-checkbox__label">Use websockets</span>
		</label><br>
 <div class="mdl-textfield mdl-js-textfield mdl-textfield--floating-label">
    <input class="mdl-textfield__input" type="text" onkeypress="return (event.charCode == 8 || event.charCode == 0) ? null : event.charCode >= 48 && event.charCode <= 57" pattern="-?[0-9]*(\.[0-9]+)?" value="'.$BBCONFIG["WS_PORT"].'" id="websocket_port_internal" name="WS_PORT">
    <label class="mdl-textfield__label" for="websocket_port_internal">Websocket Port</label>
  </div>
 <div class="mdl-textfield mdl-js-textfield mdl-textfield--floating-label">
    <input class="mdl-textfield__input" type="text" onkeypress="return (event.charCode == 8 || event.charCode == 0) ? null : event.charCode >= 48 && event.charCode <= 57" pattern="-?[0-9]*(\.[0-9]+)?" value="'.$BBCONFIG["WS_PORT_EXT"].'" id="websocket_port_external" name="WS_PORT_EXT">
    <label class="mdl-textfield__label" for="websocket_port_external">Websocket Port External</label>
  </div><br><br>
  <label class="mdl-checkbox mdl-js-checkbox mdl-js-ripple-effect" for="websocket_ssl_use">
		  <input type="checkbox" value="1" name="WS_SSL_USE" onChange="switchElements()" id="websocket_ssl_use" class="mdl-checkbox__input" '.setCheckedIfConfigTrue($BBCONFIG["WS_SSL_USE"]).'>
		  <span class="mdl-checkbox__label">Use SSL proxy</span>
		</label><br>
 <div class="mdl-textfield mdl-js-textfield mdl-textfield--floating-label">
    <input class="mdl-textfield__input" type="text" value="'.$BBCONFIG["WS_SSL_URL"].'" name="WS_SSL_URL" id="websocket_ssl_url">
    <label class="mdl-textfield__label" for="websocket_ssl_url">External websocket URL</label>
  </div>
<input type="hidden" value="0" name="REVERT_SINGLE_hidden"/>
<input type="hidden" value="0" name="MORE_VERBOSE_hidden"/>
<input type="hidden" value="0" name="WS_USE_hidden"/>
<input type="hidden" value="0" name="WS_SSL_USE_hidden"/>
		</div>
            </div>
          </section></form>
          <section class="section--footer mdl-grid">
          </section>
        </div>';
}


function printSetupTable($failed) {

    echo'<main class="mdl-layout__content" style="flex: 1 0 auto;">
        <div class="mdl-layout__tab-panel is-active" id="overview">
	<form name="settingsform" id="settingsform" method="post" action="' . $_SERVER['PHP_SELF'] . '" >
        <section class="section--center mdl-grid--no-spacing mdl-grid mdl-shadow--2dp">
            <div class="mdl-card mdl-cell  mdl-cell--12-col">
              <div class="mdl-card__supporting-text" style="overflow-x: auto; ">
                <h3>Setup</h3>
                Welcome to Barcode Buddy! Please enter your Grocy API details below. For more information, please visit the <a target="_blank" href="https://github.com/Forceu/barcodebuddy/wiki/2.0-Installation">wiki</a>.<br><br><br>
		<div class="mdl-textfield mdl-js-textfield mdl-textfield--floating-label">
		    <input pattern="https://.*/api/|http://.*/api/|https://.*/api|http://.*/api" class="mdl-textfield__input" size="60" ';
if (isset($_POST["GROCY_API_URL"])) { echo 'value="'.$_POST["GROCY_API_URL"].'" '; } echo' placeholder="e.g. https://your.grocy.com/api/" name="GROCY_API_URL" type="text" id="grocy_url">
		    <label class="mdl-textfield__label" for="grocy_url">Grocy API URL</label>
		  </div><br>
		<div class="mdl-textfield mdl-js-textfield mdl-textfield--floating-label">
		    <input pattern="[A-Za-z0-9]{50}" class="mdl-textfield__input"  size="60" ';
if (isset($_POST["GROCY_API_KEY"])) { echo 'value="'.$_POST["GROCY_API_KEY"].'" '; } echo'name="GROCY_API_KEY"  type="text" id="grocy_api_key">
		    <label class="mdl-textfield__label" for="grocy_api_key">Grocy API Key</label>
		  </div><br>';
if ($failed) {
	echo '<font color="red">Unable to connect to API! Please double check input and try again.</font>';
}
echo'<br>
<button type="submit" class="mdl-button mdl-js-button mdl-button--raised mdl-js-ripple-effect mdl-button--accent">
  Save
</button>
		</div>
            </div>
          </section>
         </form>
          <section class="section--footer mdl-grid">
          </section>
        </div>';
}


?>
