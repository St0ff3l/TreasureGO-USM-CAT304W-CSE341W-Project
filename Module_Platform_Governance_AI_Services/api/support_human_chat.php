<?php
// Redirect endpoint for the human support chat page.
//
// This file lives under the API folder but acts as a protected entrypoint:
// - Requires an authenticated user session.
// - Redirects the browser to the human support chat UI.

require_once __DIR__ . '/../../Module_User_Account_Management/includes/auth.php';
require_login();

// Send the user to the human support chat UI.
header('Location: /Module_Platform_Governance_AI_Services/pages/support_human_chat.html');
exit;
?>
