function generateLocationBarcodes() {
    const locations = JSON.parse(document.getElementById("location-barcodes").dataset.locations);
    const barcodePrefix = document.getElementById("location-barcodes").dataset.barcode;

    Array.from(locations).forEach(function(location) {
        const elId = "#location-" + location.id;
        const barcode = barcodePrefix + location.id;
        JsBarcode(elId, barcode, {format: "CODE128", text: location.name});
    });
}

function generateQuantityBarcodes() {
    const barcodePrefix = document.getElementById("quantity-barcodes").dataset.barcode;
    const startQty = Number(document.getElementById("quantity-barcodes").dataset.startQty);
    const endQty = Number(document.getElementById("quantity-barcodes").dataset.endQty);

    for (let i = startQty; i <= endQty; i++) {
        const elId = "#quantity-" + i;
        const barcode = barcodePrefix + i;
        JsBarcode(elId, barcode, {format: "CODE128", text: "Qty: " + i});
    }
}