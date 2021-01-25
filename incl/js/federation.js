function showReportFederationName(barcode, name) {
    bootbox.confirm({
        title: "Report Federation Name",
        message: "Do you want to report this name? Please <b>only</b> report this if it <b>contains offensive or malicious content</b>.<br>" +
            "Do not report this for being a different product, as in some cases barcodes can be shared by multiple products!",
        callback: function (result) {
            if (result) {
                bootbox.hideAll();
                bootbox.alert("Thank you, we will have a look at it!");
                reportName(barcode, name)
            }
        }
    });
}

function showMultipleFederationNames(barcode, namesJson) {
    let maxLength = 0;
    let names = JSON.parse(atob(namesJson));
    names.forEach(function (item, index) {
        if (item.length > maxLength)
            maxLength = item.length;
    });
    let inputOptions = [];
    names.forEach(function (name, index) {
        let paddedString = name.padEnd(maxLength + 3, ' ');
        let reportString = '<pre>  <span style="font-family: \'Courier New\', monospace;">' + paddedString + '</span><a href="#" style="color: inherit;" onclick="bootbox.hideAll(); showReportFederationName(\'' + barcode + '\', \'' + name + '\');"><span style="color: #6c757d" class="icon-flag"></span></pre></a>';
        inputOptions.push({text: reportString, value: name})
    });
    bootbox.prompt({
        title: "Multiple Names Submitted",
        message: '<p>Please select a name below:</p>',
        inputType: 'radio',
        inputOptions: inputOptions,
        callback: function (result) {
            if (result != null) {
                voteName(barcode, result);
                changeName(barcode, result);
            }
        }
    });
}

function voteName(barcode, name) {
    contactFederation("voteFederation", barcode, name, false)
}

function reportName(barcode, name) {
    contactFederation("reportFederation", barcode, name, false)
}

function changeName(barcode, name) {
    contactFederation("nameChangeFederation", barcode, name, true)
}

function contactFederation(action, barcode, name, refresh) {
    let xhr = new XMLHttpRequest();
    xhr.onreadystatechange = function () {
        if (this.readyState === 4) {
            if (this.status === 200) {
                if (refresh) {
                    location.reload();
                }
            } else {
                showToast("Error communicating with federation server");
            }
        }
    };
    xhr.open("POST", './incl/ajax.php?' + action, true);
    xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    xhr.send("barcode=" + encodeURIComponent(barcode) + "&name=" + encodeURIComponent(name));
}