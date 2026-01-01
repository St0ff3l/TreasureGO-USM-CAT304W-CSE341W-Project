/**
 * Admin Guard Script
 * Protects admin pages from unauthorized access.
 * Checks session status and role.
 * Includes a minimum 2s loading delay for UX.
 */

(async function() {
    // 1. Create a loading overlay immediately
    const overlay = document.createElement('div');
    overlay.id = 'admin-guard-overlay';
    overlay.style.cssText = `
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: #F3F6F9; z-index: 99999; display: flex;
        align-items: center; justify-content: center; flex-direction: column;
    `;
    overlay.innerHTML = `
        <div style="font-size: 3rem; margin-bottom: 20px;">🔒</div>
        <div style="font-family: sans-serif; color: #4B5563; font-weight: 600;">Verifying Access...</div>
    `;
    document.documentElement.appendChild(overlay);

    try {
        // Run a short delay and the session request in parallel.

        // Two async tasks:
        // 1) A small forced delay to ensure the loading overlay is visible.
        const delayPromise = new Promise(resolve => setTimeout(resolve, 100));

        // 2) The session status request.
        const fetchPromise = fetch('../../Module_User_Account_Management/api/session_status.php');

        // Wait for both tasks to complete.
        // The result is an array: [delayResult, fetchResponse].
        const [_, res] = await Promise.all([delayPromise, fetchPromise]);

        const data = await res.json();

        if (data.is_logged_in && data.user && data.user.role === 'admin') {
            // Access granted
            const overlay = document.getElementById('admin-guard-overlay');
            if (overlay) {
                // Fade out the overlay for a smoother transition.
                overlay.style.opacity = '0';
                overlay.style.transition = 'opacity 0.5s ease';
                setTimeout(() => overlay.remove(), 500);
            }
        } else {
            // Access denied
            showAccessDenied();
        }
    } catch (error) {
        console.error('Admin guard error:', error);
        // On request errors (e.g., offline), show the blocked state immediately.
        showAccessDenied();
    }

    function showAccessDenied() {
        const overlay = document.getElementById('admin-guard-overlay');
        if (overlay) {
            overlay.innerHTML = `
                <div style="font-size: 4rem; margin-bottom: 20px;">🚫</div>
                <h1 style="font-family: sans-serif; color: #1F2937; margin-bottom: 10px;">Access Denied</h1>
                <p style="font-family: sans-serif; color: #6B7280; margin-bottom: 30px;">You do not have permission to view this page.</p>
                <button onclick="window.location.href='../../index.html'" 
                        style="padding: 12px 24px; background: #4F46E5; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-family: sans-serif; transition: 0.2s;">
                    Go to Home
                </button>
            `;
            // Add a simple hover effect to the button.
            const btn = overlay.querySelector('button');
            btn.onmouseover = () => btn.style.background = '#4338CA';
            btn.onmouseout = () => btn.style.background = '#4F46E5';
        } else {
            // Fallback when the overlay does not exist.
            document.body.innerHTML = 'Access Denied';
            window.location.href = '../../index.html';
        }
    }
})();