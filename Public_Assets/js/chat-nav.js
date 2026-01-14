/*
 * Shared chat navigation helper
 * Purpose:
 * - Provide a single, reusable function for jumping into chat with login check.
 * - Keep behavior consistent across modules (Product Detail, Order Details, etc.).
 *
 * Usage:
 *   window.goToOrderChat({ contactId, productId, orderId })
 */

(function (global) {
  'use strict';

  async function checkLoginAndProceed(targetUrl) {
    try {
      const response = await fetch('../../Module_User_Account_Management/api/session_status.php', { credentials: 'include' });
      const result = await response.json();

      if (result && result.is_logged_in) {
        global.location.href = targetUrl;
        return true;
      }

      // If AuthModal exists (some pages), prefer it.
      if (typeof global.AuthModal !== 'undefined' && global.AuthModal && typeof global.AuthModal.show === 'function') {
        global.AuthModal.show();
      } else {
        global.location.href = '../../Module_User_Account_Management/pages/login.php';
      }
      return false;
    } catch (e) {
      // Most conservative fallback.
      global.location.href = '../../Module_User_Account_Management/pages/login.php';
      return false;
    }
  }

  /**
   * Navigate to chat.php.
   * @param {{contactId: string|number, productId?: string|number, orderId?: string|number}} params
   */
  function goToOrderChat(params) {
    const contactId = params && (params.contactId ?? params.contact_id);
    if (!contactId) {
      alert('Unable to open chat: missing user id');
      return;
    }

    const productId = params && (params.productId ?? params.product_id ?? '');
    const chatUrl = `../../Module_User_Account_Management/pages/chat.php?contact_id=${encodeURIComponent(String(contactId))}&product_id=${encodeURIComponent(String(productId || ''))}`;

    // Fire-and-forget; order-details already does session caching, but other pages may not.
    void checkLoginAndProceed(chatUrl);
  }

  // Expose globals for HTML/inline onclick
  global.goToOrderChat = goToOrderChat;

})(window);

