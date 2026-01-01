/**
 * admin_report_modal.js
 * Modal logic for admin report review actions:
 * - Ban user (including custom durations)
 * - Hide product (for product-type reports)
 * - Send replies to both reporter and reported user
 *
 * Expected DB fields: Report_ID, Report_Type
 */

// Modal state
let modalReportId = null;
let modalActionType = null;

/**
 * Open the action modal.
 * @param {number} id Report ID
 * @param {string} actionType 'Resolved' or 'Dismissed'
 */
function openActionModal(id, actionType) {
    modalReportId = id;
    modalActionType = actionType;

    // 1) Get the current report object (API may return either `Report_ID` or `id`).
    const r = typeof allReports !== 'undefined'
        ? allReports.find(x => (x.Report_ID == id) || (x.id == id))
        : null;

    // 2) Grab modal DOM nodes.
    const modal = document.getElementById('action-modal');
    const title = document.getElementById('action-title');
    const textSpan = document.getElementById('action-type-text');
    const confirmBtn = document.getElementById('action-confirm-btn');

    // Section containers.
    const banSection = document.getElementById('ban-section');
    const prodSection = document.getElementById('product-section');

    // 3) Reset form state.
    // Clear both reply textareas.
    const replyReporter = document.getElementById('reply-to-reporter');
    const replyReported = document.getElementById('reply-to-reported');
    if (replyReporter) replyReporter.value = '';
    if (replyReported) replyReported.value = '';

    // Reset checkboxes.
    const banCheckbox = document.getElementById('ban-user-checkbox');
    if (banCheckbox) banCheckbox.checked = false;

    const hideCheckbox = document.getElementById('hide-product-checkbox');
    if (hideCheckbox) hideCheckbox.checked = false;

    // Reset ban options UI (checkbox-driven content).
    toggleBanOptions();

    // Reset duration chips (including custom).
    document.querySelectorAll('.duration-chip').forEach(el => el.classList.remove('active'));

    // Default selection: 3 days.
    const defaultChip = document.querySelector('.duration-chip[onclick*="3d"]');
    if(defaultChip) defaultChip.classList.add('active');

    // Reset hidden submit value.
    const durationInput = document.getElementById('selected-ban-duration');
    if(durationInput) durationInput.value = '3d';

    // Hide and clear custom input.
    const customRow = document.getElementById('custom-ban-row');
    const customInput = document.getElementById('custom-ban-input');
    if(customRow) customRow.style.display = 'none';
    if(customInput) customInput.value = '';


    // 4) Configure UI based on action type.
    if (actionType === 'Resolved') {
        title.textContent = 'Resolve Report';
        title.style.color = '#10B981'; // Success green
        textSpan.textContent = 'MARK AS RESOLVED';
        confirmBtn.textContent = 'Confirm & Resolve';
        confirmBtn.className = 'btn-confirm resolve';

        // Show the ban section.
        if (banSection) banSection.style.display = 'block';

        // Show the product section only for product reports.
        const rType = r ? (r.Report_Type || r.type || '') : '';
        if (rType.toLowerCase() === 'product' && prodSection) {
            prodSection.style.display = 'block';
        } else if (prodSection) {
            prodSection.style.display = 'none';
        }

    } else {
        // Dismiss flow.
        title.textContent = 'Dismiss Report';
        title.style.color = '#6B7280'; // Gray
        textSpan.textContent = 'DISMISS (Reject)';
        confirmBtn.textContent = 'Dismiss Report';
        confirmBtn.className = 'btn-confirm dismiss';

        // No ban/hide options on dismiss.
        if (banSection) banSection.style.display = 'none';
        if (prodSection) prodSection.style.display = 'none';
    }

    // Bind submit handler.
    confirmBtn.onclick = submitAction;

    // Show modal.
    modal.classList.add('active');
}

// Close the action modal and clear state.
function closeActionModal() {
    const modal = document.getElementById('action-modal');
    if (modal) modal.classList.remove('active');
    modalReportId = null;
}

// Toggle ban duration options (checkbox onChange handler).
function toggleBanOptions() {
    const checkbox = document.getElementById('ban-user-checkbox');
    const options = document.getElementById('ban-duration-container');
    if (checkbox && options) {
        // Use CSS grid when visible to preserve chip layout.
        options.style.display = checkbox.checked ? 'grid' : 'none';
    }
}

// Select a ban duration (duration chip click handler).
function selectDuration(element, value) {
    // 1) Update selected chip styling.
    document.querySelectorAll('.duration-chip').forEach(el => el.classList.remove('active'));
    element.classList.add('active');

    // 2) Related DOM nodes.
    const customRow = document.getElementById('custom-ban-row');
    const durationInput = document.getElementById('selected-ban-duration');
    const customInput = document.getElementById('custom-ban-input');

    // 3) Apply selection.
    if (value === 'custom') {
        // Custom: show input and keep hidden value in sync.
        if(customRow) customRow.style.display = 'block';
        if(customInput) {
            customInput.focus();
            // If a value already exists, use it; otherwise wait for user input.
            durationInput.value = customInput.value ? customInput.value : '';
        }
    } else {
        // Preset: hide custom input and set hidden value.
        if(customRow) customRow.style.display = 'none';
        durationInput.value = value;
    }
}

// Track custom duration input changes.
function updateCustomDuration(val) {
    const durationInput = document.getElementById('selected-ban-duration');
    // Only update when the input has a value.
    if (val && val.length > 0) {
        durationInput.value = val; // Store numeric string, e.g. "15"
    } else {
        durationInput.value = ''; // Clear when empty
    }
}

// Submit the selected action to the backend.
async function submitAction() {
    if (!modalReportId) return;

    const confirmBtn = document.getElementById('action-confirm-btn');

    // Read reply values.
    const replyReporterInput = document.getElementById('reply-to-reporter');
    const replyReportedInput = document.getElementById('reply-to-reported');
    const replyReporter = replyReporterInput ? replyReporterInput.value : '';
    const replyReported = replyReportedInput ? replyReportedInput.value : '';

    // Read other form values.
    const banCheckbox = document.getElementById('ban-user-checkbox');
    const hideProdCheckbox = document.getElementById('hide-product-checkbox');
    const banDurationInput = document.getElementById('selected-ban-duration');

    // Fallback duration if empty (backend can also validate).
    const banDuration = (banDurationInput && banDurationInput.value) ? banDurationInput.value : '3d';

    // Only trust checkboxes when they are visible.
    const isBanChecked = (banCheckbox && banCheckbox.offsetParent !== null) ? banCheckbox.checked : false;
    const isHideChecked = (hideProdCheckbox && hideProdCheckbox.offsetParent !== null) ? hideProdCheckbox.checked : false;

    // Build request payload.
    const requestData = {
        Report_ID: modalReportId,
        status: modalActionType,        // 'Resolved' or 'Dismissed'

        reply_to_reporter: replyReporter, // Reply shown to the reporter
        reply_to_reported: replyReported, // Reply shown to the reported user

        shouldBan: isBanChecked,
        banDuration: banDuration,       // '3d', '365d', 'forever', or a numeric string (custom)
        hideProduct: isHideChecked      // boolean
    };

    console.log("Submitting:", requestData);

    // Loading state.
    if(confirmBtn) {
        confirmBtn.disabled = true;
        confirmBtn.textContent = 'Processing...';
    }

    try {
        // Call backend endpoint.
        const response = await fetch('../api/admin_report_update.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(requestData)
        });

        const result = await response.json();

        if (result.success) {
            let msg = "Report status updated.";
            if (requestData.status === 'Resolved') {
                msg = "Report Resolved.";
            } else {
                msg = "Report Dismissed.";
            }

            if (typeof showToast === 'function') {
                showToast(msg, "success");
            } else {
                alert(msg);
            }

            closeActionModal();

            // Refresh list.
            if (typeof fetchReports === 'function') {
                fetchReports();
            } else {
                location.reload();
            }
        } else {
            if (typeof showToast === 'function') {
                showToast("Error: " + result.message, "error");
            } else {
                alert("Error: " + result.message);
            }
        }
    } catch (error) {
        console.error(error);
        if (typeof showToast === 'function') {
            showToast("Network Error", "error");
        } else {
            alert("Network Error: " + error.message);
        }
    } finally {
        if(confirmBtn) confirmBtn.disabled = false;
    }
}