<?php
// pages/profile.php
require_once '../includes/auth.php';

// Gatekeeper: Must be logged in to view
require_login();

// Include View
require_once '../views/profile.html';
?>

<script>
  // If the view didn't include the address subview module for some reason, load it dynamically.
  (function ensureAddressSubviewScript() {
    if (typeof window.AddressSubview !== 'undefined') return;
    var s = document.createElement('script');
    s.src = '../assets/js/profile_address_subview.js';
    s.defer = true;
    document.head.appendChild(s);
  })();
</script>
