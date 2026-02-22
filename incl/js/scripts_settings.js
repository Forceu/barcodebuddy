function showTransferBarcodes() {
    window.open('/barcodes.php?mode=loc', '_blank');
}

function showQuantityBarcodes() {
    const startRaw = window.prompt("Starting quantity:", "1");
    if (startRaw === null) return; // user cancelled

    const endRaw = window.prompt("Ending quantity:", "10");
    if (endRaw === null) return; // user cancelled

    const startQty = parseInt(String(startRaw).trim(), 10);
    const endQty = parseInt(String(endRaw).trim(), 10);

    if (!Number.isFinite(startQty) || !Number.isFinite(endQty)) {
        window.alert("Please enter valid whole numbers for both quantities.");
        return;
    }

    if (startQty <= 0 || endQty <= 0) {
        window.alert("Quantities must be greater than 0.");
        return;
    }

    if (endQty < startQty) {
        window.alert("Ending quantity must be greater than or equal to starting quantity.");
        return;
    }

    const url = `/barcodes.php?mode=qty&startQty=${encodeURIComponent(startQty)}&endQty=${encodeURIComponent(endQty)}`;
    window.open(url, "_blank");
}