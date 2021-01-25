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

function showMultipleFederationNames(names) {
    bootbox.prompt({
        title: "Multiple Names Submitted",
        message: '<p>Please select a name below:</p>',
        inputType: 'radio',
        inputOptions: [
            {
                text: '<span style="font-family: \'Courier New\', monospace;">Choice One &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span><span class="icon-flag"></span>',
                value: '1',
            },
            {
                text: '<span style="font-family: \'Courier New\', monospace;">Choice Two &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span><span class="icon-flag"></span>',
                value: '2',
            },
            {
                text: '<span style="font-family: \'Courier New\', monospace;">Choice Three &nbsp;&nbsp;&nbsp;&nbsp;</span><span class="icon-flag"></span>',
                value: '3',
            }
        ],
        callback: function (result) {
            console.log(result);
        }
    });
}

function voteName(barcode, name) {
    contactFederation("voteFederation", barcode, name)
}

function reportName(barcode, name) {
    contactFederation("reportFederation", barcode, name)
}

function contactFederation(action, barcode, name) {
    let xhr = new XMLHttpRequest();
    xhr.onreadystatechange = function () {
        if (this.readyState === 4) {
            if (this.status !== 200) {
                showToast("Error communicating with federation server");
            }
        }
    };
    xhr.open("POST", './incl/ajax.php?' + action, true);
    xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    xhr.send("barcode=" + encodeURIComponent(barcode) + "&name=" + encodeURIComponent(name));
}