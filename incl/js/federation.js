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

// Escapes a string for safe insertion as HTML *attribute* content (e.g. inside data-foo="...").
// Note: names/barcodes here are already HTML-escaped once by BBuddy's own sanitizeString()
// upstream. That is NOT enough on its own to safely embed them into a newly-built
// onclick="..." attribute string, because the browser HTML-decodes an attribute's value
// before treating it as JS source - so an already-escaped quote (e.g. "&#039;") would be
// turned back into a real "'" and could break out of the inline JS string. To avoid that
// entirely, we never rebuild inline JS from untrusted text at all: values are only ever
// passed via data-* attributes and read back out through .dataset, which never gets
// interpreted as code.
function escapeHtmlAttribute(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

// Delegated handler: bound once, works for links added later via bootbox's dynamically
// inserted HTML. Reads barcode/name back out of data-* attributes, which are always plain
// data and are never parsed as HTML or JS, so no re-encoding pitfalls are possible here.
document.addEventListener('click', function (event) {
    let link = event.target.closest('.js-federation-report-link');
    if (!link)
        return;
    event.preventDefault();
    bootbox.hideAll();
    showReportFederationName(link.dataset.barcode, link.dataset.name);
});

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
        let reportString = '<pre>  <span style="font-family: \'Courier New\', monospace;">' + paddedString + '</span><a href="#" style="color: inherit;" class="js-federation-report-link" data-barcode="' + escapeHtmlAttribute(barcode) + '" data-name="' + escapeHtmlAttribute(name) + '"><span style="color: #6c757d" class="icon-flag"></span></pre></a>';
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