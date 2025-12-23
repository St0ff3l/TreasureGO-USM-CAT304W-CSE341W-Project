/*
 * TreasureGO Headerbar Component (Navbar Only)
 * 更新说明：已同步 Profile 页面的头像样式，并精简了下拉菜单（只保留 Log Out）
 */

(function (global) {
    'use strict';

    // --- 1. 配置常量 ---
    const TG_HEADERBAR_STYLE_ID = 'tg-headerbar-style';
    const TG_HEADERBAR_FONTS_LINK_ID = 'tg-headerbar-fonts';

    // --- 2. 样式定义 ---
    const EMBEDDED_HEADERBAR_CSS = `
    /* ================= CSS Variables ================= */
    :root {
        --primary: #4F46E5;
        --primary-hover: #4338CA;
        --text-dark: #1F2937;
        --text-gray: #6B7280;
        --glass-bg: rgba(255, 255, 255, 0.95);
    }

    /* ================= Navbar Styles ================= */
    .navbar {
        font-family: 'Poppins', 'Noto Sans SC', sans-serif;
        background: var(--glass-bg);
        backdrop-filter: blur(12px);
        padding: 1rem 5%;
        display: flex;
        justify-content: space-between;
        align-items: center;
        
        /* Sticky 吸顶设置 */
        position: sticky; 
        top: 0;
        z-index: 1000;
        
        border-bottom: 1px solid rgba(255,255,255,0.5);
    }

    .logo {
        font-weight: 800; font-size: 1.5rem; color: var(--primary);
        display: flex; align-items: center; gap: 10px; text-decoration: none;
    }
    .logo span { color: var(--text-dark); }
    
    .logo-img {
        width: 40px; height: 40px; border-radius: 8px; object-fit: cover;
        animation: glowAnimation 3s infinite alternate;
    }
    @keyframes glowAnimation {
        0% { box-shadow: 0 0 5px rgba(245, 158, 11, 0.2), 0 0 10px rgba(245, 158, 11, 0.1); }
        100% { box-shadow: 0 0 15px rgba(245, 158, 11, 0.8), 0 0 25px rgba(245, 158, 11, 0.5); }
    }

    .nav-actions { display: flex; align-items: center; gap: 20px; }
    
    .nav-btn {
        border: none; background: transparent; font-weight: 600; color: var(--text-gray);
        padding: 0.6rem 0.5rem; cursor: pointer; transition: color 0.2s; font-size: 1rem;
    }
    .nav-btn:hover { color: var(--text-dark); }
    
    .btn-primary {
        border: none; background-color: var(--text-dark); color: white;
        font-weight: 600; padding: 0.7rem 1.8rem; border-radius: 12px;
        cursor: pointer; transition: all 0.2s; font-size: 1rem;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    .btn-primary:hover { transform: translateY(-2px); background-color: #000; }

    /* ================= Dropdown Menu & Avatar Styles ================= */
    .menu-container { position: relative; display: inline-block; }
    
    /* 头像按钮样式 (Profile 页面风格) */
    .dots-btn {
        width: 40px; height: 40px; 
        background: #EEF2FF;      
        color: var(--primary);    
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 1rem; 
        font-weight: bold; 
        cursor: pointer; 
        border: 2px solid white;  
        box-shadow: 0 4px 10px rgba(79, 70, 229, 0.2); 
        transition: 0.2s; 
    }
    
    .dots-btn:hover { 
        transform: scale(1.05); 
    }
    
    .dropdown-content {
        display: none; position: absolute; right: 0;
        top: 100%; margin-top: 10px;
        background-color: white; min-width: 160px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.12);
        border-radius: 16px; z-index: 1001; padding: 8px;
        animation: fadeIn 0.2s ease;
    }
    .dropdown-content::before {
        content: ""; position: absolute; top: -20px; left: 0;
        width: 100%; height: 20px; background: transparent;
    }
    .menu-container:hover .dropdown-content { display: block; }
    
    .dropdown-item {
        color: var(--text-dark); padding: 12px 16px; text-decoration: none;
        display: block; font-size: 14px; font-weight: 500; border-radius: 10px;
    }
    .dropdown-item:hover { background-color: #f3f4f6; color: var(--primary); }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }

    /* Mobile Responsive (Navbar only) */
    @media (max-width: 768px) {
        .navbar { padding: 15px; }
        .nav-actions { gap: 10px; }
        .nav-btn { display: none; } 
    }
  `;

    // --- 3. 辅助函数 ---
    function ensureAssets() {
        if(!document.getElementById(TG_HEADERBAR_FONTS_LINK_ID)) {
            const link = document.createElement('link');
            link.id = TG_HEADERBAR_FONTS_LINK_ID; link.rel = 'stylesheet';
            link.href = 'https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@400;700&family=Poppins:wght@400;600;800&display=swap';
            document.head.appendChild(link);
        }
        if(!document.getElementById(TG_HEADERBAR_STYLE_ID)) {
            const style = document.createElement('style');
            style.id = TG_HEADERBAR_STYLE_ID;
            style.textContent = EMBEDDED_HEADERBAR_CSS;
            document.head.appendChild(style);
        }
    }

    function getBasePath(options) {
        const basePath = (options && options.basePath) ? String(options.basePath).replace(/\/$/, '') : '';
        return basePath ? (basePath + '/') : '';
    }

    // --- 4. HTML 构建 (已修改) ---
    function getNavbarHtml(p) {
        return `
    <nav class="navbar" data-component="tg-headerbar">
      <a href="${p}index.html" class="logo">
        <img src="${p}Public_Assets/images/TreasureGo_Logo.png" alt="Logo" class="logo-img">
        Treasure<span>Go</span>
      </a>

      <div class="nav-actions">
        <button class="nav-btn" onclick="window.location.href='${p}Module_Transaction_Fund/pages/Fund_Request.html'">Top Up</button>
        <button id="nav-admin-btn" class="nav-btn" style="display: none;" onclick="window.location.href='${p}Module_User_Account_Management/pages/admin_dashboard.php'">Admin Dashboard</button>
        <button class="nav-btn" onclick="window.location.href='${p}Module_Transaction_Fund/pages/Orders_Management.html'">Orders</button>

        <button id="nav-login-btn" class="btn-primary" onclick="window.location.href='${p}Module_User_Account_Management/pages/login.php'">Login</button>

        <div id="nav-user-menu" class="menu-container" style="display: none;">
          <div id="nav-avatar" class="dots-btn" onclick="window.location.href='${p}Module_User_Account_Management/pages/profile.php'">👤</div>
          <div class="dropdown-content">
            <a href="${p}Module_User_Account_Management/api/logout.php" class="dropdown-item" style="color: #ef4444;">Log Out</a>
          </div>
        </div>
      </div>
    </nav>`.trim();
    }

    // --- 5. Session 逻辑 ---
    async function checkSession(p) {
        const apiUrl = `${p}Module_User_Account_Management/api/session_status.php`;

        const loginBtn = document.getElementById('nav-login-btn');
        const userMenu = document.getElementById('nav-user-menu');
        const avatarBtn = document.getElementById('nav-avatar');
        const adminBtn = document.getElementById('nav-admin-btn');

        console.log('[Headerbar] Checking session...');
        console.log('[Headerbar] BasePath:', p);
        console.log('[Headerbar] API URL:', apiUrl);
        console.log('[Headerbar] Elements found:', {
            loginBtn: !!loginBtn,
            userMenu: !!userMenu,
            avatarBtn: !!avatarBtn,
            adminBtn: !!adminBtn
        });

        if (!loginBtn || !userMenu) {
            console.error('[Headerbar] Required DOM elements not found!');
            return;
        }

        try {
            // 修改点：带上 credentials，设置 Accept 并关闭缓存，检测重定向与非 JSON 响应
            const res = await fetch(apiUrl, {
                method: 'GET',
                credentials: 'include', // <- 关键：让浏览器发送 cookie（HttpOnly 会话 cookie）
                headers: { 'Accept': 'application/json' },
                cache: 'no-cache'
            });
            console.log('[Headerbar] Response status:', res.status, 'redirected:', res.redirected);

            // 如果被重定向，很可能服务器返回了 HTML 登录页（没有携带会话）
            if (res.redirected) {
                console.warn('[Headerbar] Request was redirected — likely not authenticated or wrong endpoint.');
                throw new Error('Redirected to non-API endpoint');
            }

            // 确保返回的是 JSON，再去解析
            const contentType = res.headers.get('content-type') || '';
            if (!contentType.includes('application/json')) {
                console.warn('[Headerbar] session_status did not return JSON. Content-Type:', contentType);
                throw new Error('Invalid response content-type: ' + contentType);
            }

            if (!res.ok) {
                throw new Error(`HTTP error! status: ${res.status}`);
            }

            const data = await res.json();
            console.log('[Headerbar] Session data received:', data);

            if (data.is_logged_in) {
                console.log('[Headerbar] User is logged in');
                loginBtn.style.display = 'none';
                userMenu.style.display = 'inline-block';

                if (data.user) {
                    console.log('[Headerbar] User data:', {
                        username: data.user.username,
                        role: data.user.role,
                        avatar_url: data.user.avatar_url
                    });

                    if (avatarBtn) {
                        if (data.user.avatar_url) {
                            console.log('[Headerbar] Setting avatar image');
                            avatarBtn.innerHTML = `<img src="${data.user.avatar_url}" style="width:100%; height:100%; border-radius:50%; object-fit:cover;">`;
                            avatarBtn.style.background = 'transparent';
                        }
                        else if (data.user.username) {
                            console.log('[Headerbar] Setting avatar initial');
                            avatarBtn.innerText = data.user.username.charAt(0).toUpperCase();
                        }
                    }

                    if (adminBtn && data.user.role === 'admin') {
                        console.log('[Headerbar] User is admin, showing admin button');
                        adminBtn.style.display = 'inline-block';
                    } else if (adminBtn) {
                        adminBtn.style.display = 'none';
                    }
                }
            } else {
                console.log('[Headerbar] User is not logged in');
                loginBtn.style.display = 'inline-block';
                userMenu.style.display = 'none';
                if (adminBtn) adminBtn.style.display = 'none';
            }
        } catch (err) {
            console.error('[Headerbar] Session check failed:', err);
            console.error('[Headerbar] Error details:', {
                message: err.message,
                stack: err.stack
            });
            // 发生错误时显示登录按钮
            loginBtn.style.display = 'inline-block';
            userMenu.style.display = 'none';
            if (adminBtn) adminBtn.style.display = 'none';
        }
    }

    // --- 6. 挂载函数 ---
    function mount(options) {
        ensureAssets();
        const basePath = getBasePath(options);

        const wrapper = document.createElement('div');
        wrapper.setAttribute('data-tg-headerbar-mount', '1');
        wrapper.innerHTML = getNavbarHtml(basePath);
        wrapper.style.display = 'contents';

        if (document.body.firstChild) {
            document.body.insertBefore(wrapper, document.body.firstChild);
        } else {
            document.body.appendChild(wrapper);
        }

        checkSession(basePath);
        return wrapper;
    }

    global.TreasureGoHeaderbar = { mount };

})(window);