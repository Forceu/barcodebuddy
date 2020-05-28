
//Overrides JSON.stringify to support associative arrays
(function(){
    // Convert array to object
    var convArrToObj = function(array){
        var thisEleObj = new Object();
        if(typeof array == "object"){
            for(var i in array){
                var thisEle = convArrToObj(array[i]);
                thisEleObj[i] = thisEle;
            }
        }else {
            thisEleObj = array;
        }
        return thisEleObj;
    };
    var oldJSONStringify = JSON.stringify;
    JSON.stringify = function(input){
        if(oldJSONStringify(input) == '[]')
            return oldJSONStringify(convArrToObj(input));
        else
            return oldJSONStringify(input);
    };
})();


function generateAppQrCode(data) {
	var qr = qrcode(0, 'M');
    qr.addData(JSON.stringify(data));
    qr.make();
    document.getElementById('placeHolder').innerHTML = qr.createImgTag(8,5);
}


function updateQrCode() {
	var qrData =[];
	qrData["issetup"] = true;
	qrData["url"] = document.getElementById("qr_url").value;
	qrData["key"] = document.getElementById("qr_key").value;
	generateAppQrCode(qrData);
}


function addCollapsables() {
    var coll = document.getElementsByClassName("collapsible");
    var i;

    for (i = 0; i < coll.length; i++) {
        coll[i].addEventListener("click", function() {
            this.classList.toggle("fl-hidden"); //originally "active", so that it is still visible
            var content = this.nextElementSibling;
            if (content.style.maxHeight){
                content.style.maxHeight = null;
            } else {
                content.style.maxHeight = content.scrollHeight + "px";
            }
        });
    }
}