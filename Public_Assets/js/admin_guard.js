/**
 * Admin Guard Script
 * Protects admin pages from unauthorized access.
 * Checks session status and role.
 */

(async function() {
    // Create a loading overlay immediately to hide content
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
        // Check session status
        // Adjust path if necessary based on where this script is included
        // Assuming this script is in Module_Transaction_Fund/assets/
        // and API is in Module_User_Account_Management/api/
        const res = await fetch('../../Module_User_Account_Management/api/session_status.php');
        const data = await res.json();

        if (data.is_logged_in && data.user && data.user.role === 'admin') {
            // Access granted
            const overlay = document.getElementById('admin-guard-overlay');
            if (overlay) {
                overlay.style.opacity = '0';
                overlay.style.transition = 'opacity 0.5s';
                setTimeout(() => overlay.remove(), 500);
            }
        } else {
            // Access denied
            showAccessDenied();
        }
    } catch (error) {
        console.error('Admin guard error:', error);
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
                        style="padding: 12px 24px; background: #4F46E5; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-family: sans-serif;">
                    Go to Home
                </button>
            `;
        } else {
            document.body.innerHTML = `
                <div style="height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; background: #F3F6F9;">
                    <div style="font-size: 4rem; margin-bottom: 20px;">🚫</div>
                    <h1 style="font-family: sans-serif; color: #1F2937; margin-bottom: 10px;">Access Denied</h1>
                    <p style="font-family: sans-serif; color: #6B7280; margin-bottom: 30px;">You do not have permission to view this page.</p>
                    <button onclick="window.location.href='../../index.html'" 
                            style="padding: 12px 24px; background: #4F46E5; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-family: sans-serif;">
                        Go to Home
                    </button>
                </div>
            `;
        }
    }
})();

