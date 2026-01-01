/**
 * StatusDialog
 *
 * Lightweight, reusable status dialog utility.
 * Supported dialog types:
 * - success
 * - error
 * - warning
 * - confirm (supports a custom cancel label and an optional danger-styled confirm button)
 */

const StatusDialog = {
    // --- 1. Style injection ---
    _injectStyles: function() {
        // Avoid injecting styles multiple times.
        if (document.getElementById('status-dialog-style')) return;

        const css = `
            @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap');
            
            :root {
                --sd-mask: rgba(0, 0, 0, 0.4);
                --sd-shadow: 0 20px 60px rgba(0,0,0,0.15);
                --sd-success: #10B981; --sd-success-bg: #D1FAE5;
                --sd-error: #EF4444;   --sd-error-bg: #FEE2E2;
                --sd-warning: #F59E0B; --sd-warning-bg: #FEF3C7;
                --sd-primary: #4F46E5; --sd-dark: #1F2937;
            }

            .sd-overlay {
                position: fixed; top: 0; left: 0; width: 100%; height: 100%;
                background: var(--sd-mask); backdrop-filter: blur(5px);
                display: flex; align-items: center; justify-content: center;
                z-index: 10000; animation: sd-in 0.2s ease;
                font-family: 'Poppins', sans-serif; color: var(--sd-dark);
            }

            .sd-card {
                background: white; padding: 35px; border-radius: 24px;
                box-shadow: var(--sd-shadow); text-align: center;
                width: 90%; max-width: 380px; border: 1px solid rgba(255,255,255,0.8);
            }

            .sd-pop { animation: sd-popIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
            .sd-shake { animation: sd-shake 0.4s ease; }

            .sd-icon {
                width: 64px; height: 64px; border-radius: 50%;
                display: flex; align-items: center; justify-content: center;
                margin: 0 auto 15px auto; font-size: 30px;
            }
            .sd-icon.success { background: var(--sd-success-bg); color: var(--sd-success); }
            .sd-icon.error { background: var(--sd-error-bg); color: var(--sd-error); }
            .sd-icon.warning { background: var(--sd-warning-bg); color: var(--sd-warning); }

            .sd-title { margin: 0 0 8px 0; font-size: 1.4rem; font-weight: 800; color: #111; }
            .sd-msg { color: #6B7280; margin-bottom: 25px; line-height: 1.5; font-size: 0.95rem; }

            .sd-btn-group { display: flex; gap: 12px; justify-content: center; }

            .sd-btn {
                flex: 1; padding: 12px; border-radius: 12px; border: none;
                font-weight: 600; font-size: 0.95rem; cursor: pointer;
                transition: transform 0.1s, opacity 0.2s;
            }
            .sd-btn:active { transform: scale(0.96); }

            /* Primary action button. */
            .sd-btn-primary { background: var(--sd-primary); color: white; box-shadow: 0 4px 15px rgba(79, 70, 229, 0.2); }
            .sd-btn-primary:hover { opacity: 0.9; }
            
            /* Danger action button (destructive confirmation). */
            .sd-btn-danger { background: var(--sd-error); color: white; box-shadow: 0 4px 15px rgba(239, 68, 68, 0.2); }
            .sd-btn-danger:hover { opacity: 0.9; }

            /* Dark button used for closing error dialogs. */
            .sd-btn-dark { background: var(--sd-dark); color: white; }
            
            .sd-btn-outline { background: white; border: 1px solid #E5E7EB; color: #4B5563; }
            .sd-btn-outline:hover { background: #F9FAFB; border-color: #D1D5DB; color: #111; }

            @keyframes sd-in { from { opacity: 0; } to { opacity: 1; } }
            @keyframes sd-popIn { from { transform: scale(0.9); opacity: 0; } to { transform: scale(1); opacity: 1; } }
            @keyframes sd-shake { 
                0%, 100% { transform: translateX(0); } 
                20%, 60% { transform: translateX(-5px); } 
                40%, 80% { transform: translateX(5px); } 
            }
        `;
        const style = document.createElement('style');
        style.id = 'status-dialog-style';
        style.textContent = css;
        document.head.appendChild(style);
    },

    // Close and remove the current dialog overlay.
    _close: function() {
        const el = document.getElementById('sd-overlay');
        if (el) {
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 200);
        }
    },

    // --- 2. Rendering ---
    _render: function(type, title, message, btnText, callback, cancelText = 'Cancel', isDanger = false) {
        this._injectStyles();
        const existing = document.getElementById('sd-overlay');
        if (existing) existing.remove();

        const overlay = document.createElement('div');
        overlay.id = 'sd-overlay';
        overlay.className = 'sd-overlay';

        let iconHtml = '';
        let iconClass = '';
        let animationClass = 'sd-pop';
        let buttonsHtml = '';

        if (type === 'success') {
            iconHtml = '✓';
            iconClass = 'success';
            buttonsHtml = `<button id="sd-confirm-btn" class="sd-btn sd-btn-primary">${btnText || 'Continue'}</button>`;
        } else if (type === 'error') {
            iconHtml = '✕';
            iconClass = 'error';
            animationClass = 'sd-shake';
            // Error dialogs use a dark close button.
            buttonsHtml = `<button id="sd-confirm-btn" class="sd-btn sd-btn-dark">${btnText || 'Close'}</button>`;
        } else if (type === 'warning') {
            iconHtml = '!';
            iconClass = 'warning';
            buttonsHtml = `<button id="sd-confirm-btn" class="sd-btn sd-btn-primary" style="background:var(--sd-warning); color:white;">${btnText || 'OK'}</button>`;
        } else if (type === 'confirm') {
            iconHtml = '?';
            iconClass = 'warning';

            // Confirm dialogs support a custom cancel label and an optional danger-styled confirm button.

            // Choose the confirm button style based on isDanger.
            const confirmBtnClass = isDanger ? 'sd-btn-danger' : 'sd-btn-primary';

            buttonsHtml = `
                <div class="sd-btn-group">
                    <button id="sd-cancel-btn" class="sd-btn sd-btn-outline">${cancelText}</button>
                    <button id="sd-confirm-btn" class="sd-btn ${confirmBtnClass}">${btnText || 'Confirm'}</button>
                </div>
            `;
        }

        overlay.innerHTML = `
            <div class="sd-card ${animationClass}">
                <div class="sd-icon ${iconClass}">${iconHtml}</div>
                <h1 class="sd-title">${title}</h1>
                <p class="sd-msg">${message}</p>
                ${buttonsHtml}
            </div>
        `;

        document.body.appendChild(overlay);

        const confirmBtn = document.getElementById('sd-confirm-btn');
        if (confirmBtn) {
            confirmBtn.onclick = () => {
                this._close();
                if (callback) callback();
            };
        }

        const cancelBtn = document.getElementById('sd-cancel-btn');
        if (cancelBtn) {
            cancelBtn.onclick = () => {
                this._close();
            };
        }
    },

    // Convenience methods
    success: function(title, message, btnText, callback) {
        this._render('success', title, message, btnText, callback);
    },

    fail: function(title, message, btnText, callback) {
        this._render('error', title, message, btnText, callback);
    },

    warning: function(title, message, btnText, callback) {
        this._render('warning', title, message, btnText, callback);
    },

    /**
     * Confirmation dialog.
     *
     * @param {string} title Dialog title
     * @param {string} message Dialog message
     * @param {string} confirmText Confirm button label
     * @param {string} cancelText Cancel button label
     * @param {Function} onConfirm Callback invoked when confirmed
     * @param {boolean} isDanger When true, use the danger-styled confirm button
     */
    confirm: function(title, message, confirmText, cancelText, onConfirm, isDanger = false) {
        // Backward-compatible argument handling.
        // If cancelText is a function, treat it as onConfirm and use the default cancel label.
        if (typeof cancelText === 'function') {
            isDanger = onConfirm || false;
            onConfirm = cancelText;
            cancelText = 'Cancel';
        }

        this._render('confirm', title, message, confirmText, onConfirm, cancelText, isDanger);
    }
};

window.StatusDialog = StatusDialog;
