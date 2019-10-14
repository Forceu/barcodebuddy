function enableButton(idSelect, idButtonAdd, idButtonConsume)
		{

		    var oSelect = document.getElementById(idSelect);
		    var oButtonAdd = document.getElementById(idButtonAdd);
		    var oButtonConsume = document.getElementById(idButtonConsume);
		    oButtonAdd.disabled = oSelect.value == "0";
		    oButtonConsume.disabled = oSelect.value == "0";
		}

function testWebsocket(server, isHttps) {
    
    var notification = document.querySelector('.mdl-js-snackbar');

    var wsuse = document.getElementById("websocket_use");
    if (!wsuse.checked) {
		notification.MaterialSnackbar.showSnackbar(
		  {
		    message: 'Please enable websockets first',
	  	    timeout: 2000
		  }
		);
	return;
    }

    var url = null;
    var wspint = document.getElementById("websocket_port_internal");
    var wspext = document.getElementById("websocket_port_external");
    var wsssluse = document.getElementById("websocket_ssl_use");
    var wssslurl = document.getElementById("websocket_ssl_url");

    if (wsssluse.checked) {
	url = wssslurl.value;
    } else {
	if (isHttps) {
		url = "wss://"+server+":"+wspext.value;
	} else {
		url = "ws://"+server+":"+wspext.value;
	}
    }
  
    var ws = new WebSocket(url);

	var progressbar = document.getElementById("progressbar");
	progressbar.style.display = 'block'; 
 	ws.onclose = function (event) {
		progressbar.style.display = 'none'; 
		notification.MaterialSnackbar.showSnackbar(
		  {
		    message: 'Error! Could not connect to websocket! Please check your configuration.',
	  	    timeout: 8000
		  }
		);
		ws.close();
	};
        ws.onopen = function() {
		progressbar.style.display = 'none'; 
		notification.MaterialSnackbar.showSnackbar(
		  {
		    message: 'Sucessfully connected! You can use all websocket features.',
	  	    timeout: 5000
		  }
		);
		ws.onclose = null;
		ws.close();
      };
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
	try {
	   if (wsssluse.disabled) {
	      wsssluse.parentElement.MaterialCheckbox.disable();
	   } else {
	      wsssluse.parentElement.MaterialCheckbox.enable();
	   }
	   if (wssfs.disabled) {
	      wssfs.parentElement.MaterialCheckbox.disable();
	   } else {
	      wssfs.parentElement.MaterialCheckbox.enable();
	   }
	   if (wssslurl.disabled) {
	      wssslurl.parentElement.MaterialTextfield.disable();
	   } else {
	      wssslurl.parentElement.MaterialTextfield.enable();
	   }

	   if (wspint.disabled) {
	      wspint.parentElement.MaterialTextfield.disable();
	   } else {
	      wspint.parentElement.MaterialTextfield.enable();
	   }
	   if (wspext.disabled) {
	      wspext.parentElement.MaterialTextfield.disable();
	   } else {
	      wspext.parentElement.MaterialTextfield.enable();
	   }
	}
	catch(error) {	}
}

function checkAndReturn() {
	    var wspint = document.getElementById("websocket_port_internal").value;
	    var wspint = document.getElementById("websocket_port_internal").value;
	    var crevert = document.getElementById("general_revert_min").value;

	    if (Number.isInteger(+wspint) && Number.isInteger(+wspint) && Number.isInteger(+crevert)) {
		 var form1 = document.getElementById("settingsform_1");
		 var form2 = document.getElementById("settingsform_2");
		 var form3 = document.getElementById("settingsform_3");
		 var form4 = document.getElementById("settingsform_4");

		 var postString = serialize(form1)+'&'+serialize(form2)+'&'+serialize(form3)+'&'+serialize(form4);

		var xhr = new XMLHttpRequest();
		  xhr.onreadystatechange = function() {
		    if (this.readyState == 4 && this.status == 200) {
			window.location.href = 'settings.php';
		    }
		  };
		xhr.open("POST", 'settings.php', true);
  		xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
		xhr.send(postString);

	    } else {
	       alert("Please only enter digits for port and minutes.");
	    }
 }


/*!
 * Serialize all form data into a query string
 * (c) 2018 Chris Ferdinandi, MIT License, https://gomakethings.com
 * @param  {Node}   form The form to serialize
 * @return {String}      The serialized form data
 */
var serialize = function (form) {

	// Setup our serialized data
	var serialized = [];

	// Loop through each field in the form
	for (var i = 0; i < form.elements.length; i++) {

		var field = form.elements[i];

		// Don't serialize fields without a name, submits, buttons, file and reset inputs, and disabled fields
		if (!field.name || field.disabled || field.type === 'file' || field.type === 'reset' || field.type === 'submit' || field.type === 'button') continue;

		// If a multi-select, get all selections
		if (field.type === 'select-multiple') {
			for (var n = 0; n < field.options.length; n++) {
				if (!field.options[n].selected) continue;
				serialized.push(encodeURIComponent(field.name) + "=" + encodeURIComponent(field.options[n].value));
			}
		}

		// Convert field data to a query string
		else if ((field.type !== 'checkbox' && field.type !== 'radio') || field.checked) {
			serialized.push(encodeURIComponent(field.name) + "=" + encodeURIComponent(field.value));
		}
	}

	return serialized.join('&');

};

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
