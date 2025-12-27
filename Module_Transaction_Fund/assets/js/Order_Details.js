document.addEventListener('DOMContentLoaded', () => {
    // 1. Mount Headerbar
    if (window.TreasureGoHeaderbar) {
        TreasureGoHeaderbar.mount({
            basePath: '../../'
        });
    }

    // 2. Initialize Order Details
    if (window.OrderDetailsOrder) {
        if (typeof window.OrderDetailsOrder.bindSafetyNet === 'function') {
            window.OrderDetailsOrder.bindSafetyNet();
        }
        if (typeof window.OrderDetailsOrder.init === 'function') {
            window.OrderDetailsOrder.init();
        }
    }
});