/**
 * TreasureGo - Global Authentication Modal
 *
 * Standalone, reusable modal for prompting unauthenticated users to log in.
 * Injects required CSS and HTML into the DOM on first use.
 *
 * Public API:
 * - AuthModal.show(): Ensures the modal exists, then opens it.
 * - AuthModal.close(): Closes the modal if it is open.
 */
const AuthModal = {
    /**
     * HTML template for the modal dialog.
     */
    htmlContent: `
        <dialog id="globalLoginDialog" class="tg-auth-modal">
            <div style="font-size: 40px; margin-bottom: 10px;">🔒</div>
            <h3 style="margin-bottom:10px; color: #1F2937; font-size: 1.2rem; font-family: 'Poppins', sans-serif;">Login Required</h3>
            <p style="margin-bottom:25px; color:#6B7280; font-size: 0.95rem; line-height: 1.5; font-family: 'Poppins', sans-serif;">
                You need to log in to access this feature.
            </p>
            <div style="display:flex; gap:10px; justify-content:center;">
                <button onclick="AuthModal.close()"
                        style="padding:10px 20px; border:1px solid #E5E7EB; background:white; color: #374151; border-radius:12px; cursor:pointer; font-weight: 600;">
                    Cancel
                </button>
                <button onclick="window.location.href='/Module_User_Account_Management/pages/login.php'" 
                        style="padding:10px 20px; border:none; background:#4F46E5; color:white; border-radius:12px; cursor:pointer; font-weight: 600;">
                    Go to Login
                </button>
            </div>
        </dialog>
    `,

    /**
     * Lazily initializes the modal.
     * Injects styles and HTML if the dialog is not already present.
     * Safe to call multiple times.
     */
    init: function() {
        // Prevent duplicate injection.
        if (document.getElementById('globalLoginDialog')) return;

        // Create and inject CSS styles.
        const style = document.createElement('style');
        style.innerHTML = `
            /* Backdrop styling. */
            .tg-auth-modal::backdrop { 
                background: rgba(0, 0, 0, 0.4); 
                backdrop-filter: blur(4px); 
            }

            /* Modal container styling. */
            .tg-auth-modal {
                position: fixed;
                top: 30%;
                bottom: auto;
                left: 0;
                right: 0;
                margin: 0 auto;

                border-radius: 24px;
                padding: 30px;
                box-shadow: 0 20px 50px rgba(0,0,0,0.15);
                text-align: center;
                width: 320px;
                border: none;
                outline: none;

                animation: tgSlideDown 0.4s cubic-bezier(0.25, 1, 0.5, 1);
            }

            /* Opening animation. */
            @keyframes tgSlideDown { 
                from { transform: translateY(-30px); opacity: 0; } 
                to { transform: translateY(0); opacity: 1; } 
            }
        `;
        document.head.appendChild(style);

        // Inject the dialog into the document body.
        document.body.insertAdjacentHTML('beforeend', this.htmlContent);
    },

    /**
     * Ensures the modal exists and opens it.
     */
    show: function() {
        this.init();
        const dialog = document.getElementById('globalLoginDialog');
        if (dialog) {
            dialog.showModal();
        }
    },

    /**
     * Closes the modal.
     */
    close: function() {
        const dialog = document.getElementById('globalLoginDialog');
        if (dialog) {
            dialog.close();
        }
    }
};