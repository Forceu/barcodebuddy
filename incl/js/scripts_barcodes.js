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

function generateActionBarcodes() {
    const actions = JSON.parse(document.getElementById("action-barcodes").dataset.actions);

    Object.entries(actions).forEach(([key, action]) => {
        const elId = "#action-" + key;
        JsBarcode(elId, action.barcode, {format: "CODE128", text: action.name});
    });
}

function downloadBarcode(elId) {
    // Get the image element
    const img = document.getElementById(elId);
    const barcodeName = img.dataset.name;

    // Ask the user for confirmation before downloading
    const filename = getBarcodeFilename(barcodeName);
    if (!window.confirm(`Download "${filename}"?`)) {
        return;
    }

    const imageDataUrl = img.src;

    // Create a temporary anchor element
    const link = document.createElement('a');
    link.href = imageDataUrl;
    link.download = filename;

    // Append the link to the body (necessary for Firefox)
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function getBarcodeFilename(name) {
    return "barcode_" + name.replace(/[^a-zA-Z0-9]/g, '') + ".png";
}