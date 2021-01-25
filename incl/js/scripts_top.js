//Overrides JSON.stringify to support associative arrays
(function () {
    // Convert array to object
    var convArrToObj = function (array) {
        var thisEleObj = new Object();
        if (typeof array == "object") {
            for (var i in array) {
                var thisEle = convArrToObj(array[i]);
                thisEleObj[i] = thisEle;
            }
        } else {
            thisEleObj = array;
        }
        return thisEleObj;
    };
    let oldJSONStringify = JSON.stringify;
    JSON.stringify = function (input) {
        if (oldJSONStringify(input) == '[]')
            return oldJSONStringify(convArrToObj(input));
        else
            return oldJSONStringify(input);
    };
})();


function generateAppQrCode(data) {
    let qr = qrcode(0, 'M');
    qr.addData(JSON.stringify(data));
    qr.make();
    document.getElementById('placeHolder').innerHTML = qr.createImgTag(8, 5);
}


function updateQrCode() {
    let qrData = [];
    qrData["issetup"] = true;
    qrData["url"] = document.getElementById("qr_url").value;
    qrData["key"] = document.getElementById("qr_key").value;
    generateAppQrCode(qrData);
}


function updateRedisCacheAndFederation(isMenu) {
    let xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function () {
        if (this.readyState === 4 && this.status === 200 && isMenu) {
            if (xhttp.responseText === "OK") {
                showToast("Cache updated");
            } else {
                showToast("Error: Could not update cache!");
            }
        }
    };
    if (isMenu)
        xhttp.open("GET", "../cron.php?ajax&force", true);
    else
        xhttp.open("GET", "./cron.php?ajax", true);
    xhttp.send();
}