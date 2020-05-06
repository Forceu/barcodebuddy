function enableButton(idSelect, idButtonAdd, idButtonConsume)
		{

		    var oSelect = document.getElementById(idSelect);
		    var oButtonAdd = document.getElementById(idButtonAdd);
		    var oButtonConsume = document.getElementById(idButtonConsume);
		    oButtonAdd.disabled = oSelect.value == "0";
		    oButtonConsume.disabled = oSelect.value == "0";
		}

function sleep (time) {
  return new Promise((resolve) => setTimeout(resolve, time));
}


function checkAndReturn() {
		 var form1 = document.getElementById("settings1_form");
		 var form2 = document.getElementById("settings2_form");

		 var postString = serialize(form1)+'&'+serialize(form2);

		var xhr = new XMLHttpRequest();
		  xhr.onreadystatechange = function() {
		    if (this.readyState == 4 && this.status == 200) {
			window.location.href = 'settings.php';
		    }
		  };
		xhr.open("POST", 'settings.php', true);
  		xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
		xhr.send(postString);
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

function enableButtonGen(buttonId, textId, previousInput) {
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


function showQrCode(content) {
	document.getElementsByClassName("close")[0].onclick = function() {
	    document.getElementById("qrcode-modal").style.display = "none";
		document.getElementById('btn_apilinks').style.display = "block";
	}
	var qr = qrcode(0, 'L');
	qr.addData(content);
	qr.make();
	document.getElementById('btn_apilinks').style.display = "none";
	document.getElementById('placeHolder').innerHTML = qr.createImgTag(10,5);
  	document.getElementById("qrcode-modal").style.display = "block";
}
