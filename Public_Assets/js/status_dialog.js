/**
 * StatusDialog.js
 * TreasureGO 通用状态弹窗框架 v2.0
 * 新增：StatusDialog.confirm() 二次确认功能
 */

const StatusDialog = {
    // --- 1. 样式配置 (自动注入) ---
    _injectStyles: function() {
        if (document.getElementById('status-dialog-style')) return;

        const css = `
            @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap');
            
            :root {
                --sd-mask: rgba(0, 0, 0, 0.4); /*稍微深一点的遮罩*/
                --sd-shadow: 0 20px 60px rgba(0,0,0,0.15);
                --sd-success: #10B981; --sd-success-bg: #D1FAE5;
                --sd-error: #EF4444;   --sd-error-bg: #FEE2E2;
                --sd-warning: #F59E0B; --sd-warning-bg: #FEF3C7; /* 新增警告色 */
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

            /* 动画 */
            .sd-pop { animation: sd-popIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
            .sd-shake { animation: sd-shake 0.4s ease; }

            /* 图标 */
            .sd-icon {
                width: 64px; height: 64px; border-radius: 50%;
                display: flex; align-items: center; justify-content: center;
                margin: 0 auto 15px auto; font-size: 30px;
            }
            .sd-icon.success { background: var(--sd-success-bg); color: var(--sd-success); }
            .sd-icon.error { background: var(--sd-error-bg); color: var(--sd-error); }
            .sd-icon.warning { background: var(--sd-warning-bg); color: var(--sd-warning); } /* 新增警告图标样式 */

            /* 文字 */
            .sd-title { margin: 0 0 8px 0; font-size: 1.4rem; font-weight: 800; color: #111; }
            .sd-msg { color: #6B7280; margin-bottom: 25px; line-height: 1.5; font-size: 0.95rem; }

            /* 按钮容器 */
            .sd-btn-group { display: flex; gap: 12px; justify-content: center; }

            /* 按钮通用 */
            .sd-btn {
                flex: 1; padding: 12px; border-radius: 12px; border: none;
                font-weight: 600; font-size: 0.95rem; cursor: pointer;
                transition: transform 0.1s, opacity 0.2s;
            }
            .sd-btn:active { transform: scale(0.96); }

            /* 按钮变体 */
            .sd-btn-primary { background: var(--sd-primary); color: white; box-shadow: 0 4px 15px rgba(79, 70, 229, 0.2); }
            .sd-btn-primary:hover { opacity: 0.9; }
            
            .sd-btn-danger { background: var(--sd-dark); color: white; }
            
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

    _close: function() {
        const el = document.getElementById('sd-overlay');
        if (el) {
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 200);
        }
    },

    // --- 2. 核心渲染逻辑 ---
    _render: function(type, title, message, btnText, callback, cancelText = 'Cancel') {
        this._injectStyles();
        const existing = document.getElementById('sd-overlay');
        if (existing) existing.remove();

        const overlay = document.createElement('div');
        overlay.id = 'sd-overlay';
        overlay.className = 'sd-overlay';

        // 配置图标和动画
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
            buttonsHtml = `<button id="sd-confirm-btn" class="sd-btn sd-btn-danger">${btnText || 'Close'}</button>`;
        } else if (type === 'confirm') {
            // 问号图标
            iconHtml = '?';
            iconClass = 'warning';
            // 双按钮布局
            buttonsHtml = `
                <div class="sd-btn-group">
                    <button id="sd-cancel-btn" class="sd-btn sd-btn-outline">${cancelText}</button>
                    <button id="sd-confirm-btn" class="sd-btn sd-btn-primary">${btnText || 'Confirm'}</button>
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

        // 绑定事件
        const confirmBtn = document.getElementById('sd-confirm-btn');
        if (confirmBtn) {
            confirmBtn.onclick = () => {
                this._close();
                if (callback) callback(); // 执行确认回调
            };
        }

        const cancelBtn = document.getElementById('sd-cancel-btn');
        if (cancelBtn) {
            cancelBtn.onclick = () => {
                this._close();
                // 可以在这里加 cancel callback，如果需要的话
            };
        }
    },

    /**
     * [1] 成功弹窗
     */
    success: function(title, message, btnText, callback) {
        this._render('success', title, message, btnText, callback);
    },

    /**
     * [2] 失败弹窗
     */
    fail: function(title, message, btnText, callback) {
        this._render('error', title, message, btnText, callback);
    },

    /**
     * [3] 二次确认弹窗 (新增)
     * @param {string} title - 标题
     * @param {string} message - 内容
     * @param {string} confirmText - 确认按钮文字 (如 "Confirm")
     * @param {Function} onConfirm - 点击确认后的回调函数
     */
    confirm: function(title, message, confirmText, onConfirm) {
        this._render('confirm', title, message, confirmText, onConfirm);
    }
};

window.StatusDialog = StatusDialog;