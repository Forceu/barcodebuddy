function enableButton(idSelect, idButtonAdd, idButtonConsume)
		{

		    var oSelect = document.getElementById(idSelect);
		    var oButtonAdd = document.getElementById(idButtonAdd);
		    var oButtonConsume = document.getElementById(idButtonConsume);
		    oButtonAdd.disabled = oSelect.value == "0";
		    oButtonConsume.disabled = oSelect.value == "0";
		}

function switchElements() {
	    var wsuse = document.getElementById("websocket_use");
	    var wspint = document.getElementById("websocket_port_internal");
	    var wspext = document.getElementById("websocket_port_external");
	    var wsssluse = document.getElementById("websocket_ssl_use");
	    var wssslurl = document.getElementById("websocket_ssl_url");
	    var wssfs = document.getElementById("websocket_fullscreen");

	      wspint.disabled = !wsuse.checked;
	      wspint.disabled = !wsuse.checked;
	      wssfs.disabled =  !wsuse.checked;
	      wspext.disabled = !(wsuse.checked && !wsssluse.checked);
	      wsssluse.disabled = !wsuse.checked;
	      wssslurl.disabled = !(wsuse.checked && wsssluse.checked);

	   if (wspint.disabled) {
	      wspint.parentElement.MaterialTextfield.disable()
	   } else {
	      wspint.parentElement.MaterialTextfield.enable()
	   }
	   if (wspext.disabled) {
	      wspext.parentElement.MaterialTextfield.disable()
	   } else {
	      wspext.parentElement.MaterialTextfield.enable()
	   }
	   if (wsssluse.disabled) {
	      wsssluse.parentElement.MaterialCheckbox.disable()
	   } else {
	      wsssluse.parentElement.MaterialCheckbox.enable()
	   }
	   if (wssfs.disabled) {
	      wssfs.parentElement.MaterialCheckbox.disable()
	   } else {
	      wssfs.parentElement.MaterialCheckbox.enable()
	   }
	   if (wssslurl.disabled) {
	      wssslurl.parentElement.MaterialTextfield.disable()
	   } else {
	      wssslurl.parentElement.MaterialTextfield.enable()
	   }
	}

function checkAndReturn() {
	    var wspint = document.getElementById("websocket_port_internal").value;
	    var wspint = document.getElementById("websocket_port_internal").value;
	    var crevert = document.getElementById("general_revert_min").value;

	    if (Number.isInteger(+wspint) && Number.isInteger(+wspint) && Number.isInteger(+crevert)) {
	       document.getElementById('settingsform').submit();
	    } else {
	       alert("Please only enter digits for port and minutes.");
	    }
 }

function enableButtonGen(buttonId, textId, previousInput)
		{
		    var text=document.getElementById(textId).value;
		    document.getElementById(buttonId).disabled=(text===previousInput);
		}
		function openNewTab(url, barcode) {
		    var win = window.open(url,	"New Grocy product");
		    var timer = setInterval(function() {
			if (win.closed) {
			    clearInterval(timer);
			    window.location = "index.php?refreshbarcode="+barcode;
			}
		    }, 500);
		}
