/**
 * TreasureGo - Global Auth Modal API
 * * A reusable component to prompt users to login.
 * Usage: AuthModal.show()
 */
const AuthModal = {
    /**
     * The HTML template for the modal dialog.
     * @type {string}
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
     * Initializes the modal by injecting CSS and HTML into the DOM.
     * Idempotent: safe to call multiple times.
     */
    init: function() {
        if (document.getElementById('globalLoginDialog')) return;

        // 1. Inject CSS Styles
        const style = document.createElement('style');
        style.innerHTML = `
            .tg-auth-modal::backdrop { 
                background: rgba(0, 0, 0, 0.4); 
                backdrop-filter: blur(4px); 
            }
            .tg-auth-modal {
                margin: 0 auto; 
                margin-top: 120px; /* Top positioning */
                border-radius: 24px; 
                padding: 30px; 
                box-shadow: 0 20px 50px rgba(0,0,0,0.15); 
                text-align: center; 
                width: 320px; 
                border: none; 
                outline: none;
                animation: tgSlideDown 0.4s cubic-bezier(0.25, 1, 0.5, 1);
            }
            @keyframes tgSlideDown { 
                from { transform: translateY(-50px); opacity: 0; } 
                to { transform: translateY(0); opacity: 1; } 
            }
        `;
        document.head.appendChild(style);

        // 2. Inject HTML Content
        document.body.insertAdjacentHTML('beforeend', this.htmlContent);
    },

    /**
     * Shows the login modal.
     */
    show: function() {
        this.init(); // Ensure elements exist
        const dialog = document.getElementById('globalLoginDialog');
        if (dialog) {
            dialog.showModal();
        }
    },

    /**
     * Closes the login modal.
     */
    close: function() {
        const dialog = document.getElementById('globalLoginDialog');
        if (dialog) {
            dialog.close();
        }
    }
};