# TreasureGo — Developer README

## Project summary
**TreasureGo** is a modular **PHP + static front-end** marketplace for buying and selling second-hand goods. The repository contains multiple core modules (product catalog, user management, platform governance, after-sales dispute, transactions) implemented as PHP API endpoints, paired with static HTML/CSS/JS pages and shared public assets.

## Quick start (local)
- **Prerequisites**: PHP **8.0.3** (Required).
- **Extensions**: `pdo` or `mysqli`, `mbstring`, `fileinfo`, `openssl`.
- **Mandatory**: `gd` (required for image processing).

**Serve locally (quick)**: From the repo root, run the PHP built-in server:
```bash
php -S localhost:8000
```

Then open [http://localhost:8000/](https://www.google.com/search?q=http://localhost:8000/) (the repo root contains `index.html`).

* **Config**: Module-level config files live under each module's `config/` directory (e.g., `Module_Product_Ecosystem/config/`, `Public_Api/config/`).
* **Action**: Check these before the first run.
* **Required Credentials**: MySQL credentials, `GEMINI_API_KEY`, `DEEPSEEK_API_KEY`.
* **Email**: Set `SENDGRID_API_KEY` (Example format: `SG.gpqa01DI...`) for verification codes.
* **Assets & Uploads**: Static assets reside in `Public_Assets/` and `Module_*/*/assets/`.
* **Writable Directories**: Ensure `Public_Assets/uploads/` and `Module_After_Sales_Dispute/uploads/refund_evidence/` are writable by the webserver.

## Key modules (responsibilities + representative APIs)

### Module_Product_Ecosystem
> Product catalog, upload, payments, favorites, wallet.

* `api/Get_Products.php` — List/search products
* `api/Product_Upload.php` — Product creation / image upload
* `api/Process_Payment.php`, `api/Process_Order_Payment.php` — Payment processing
* `api/Get_Categories.php`, `api/Get_Membership_Plans.php`
* `api/Update_Product.php`, `api/Toggle_Favorite.php`, `api/Session_Status.php`

### Module_User_Account_Management
> User signup, login, profile, and addresses.

* `api/signup_user.php`, `api/login_user.php`
* `api/logout.php`, `api/session_status.php`
* `api/get_profile.php`, `api/update_profile.php`, `api/upload_avatar.php`
* `api/get_addresses.php`, `api/save_address.php`, `api/verify_code.php`

### Module_Platform_Governance_AI_Services
> Reporting, support, knowledgebase, chat hooks.

* `api/admin_kb_getlist.php`, `api/admin_kb_save.php`
* `api/report_submit.php`, `api/admin_report_get.php`
* `api/support_human_chat_api.php`, `api/support_human_chat.php`
* `api/admin_chat_api.php`, `api/admin_get_support_queue.php`

### Module_After_Sales_Dispute
> Refund & dispute flows.

* `api/dispute_buyer_submit.php`, `api/dispute_seller_submit.php`
* `api/admin_dispute_list.php`, `api/admin_dispute_get.php`
* `api/Refund_Requests.php`, `api/get_dispute_timeline.php`

### Module_Transaction_Fund
> Orders and funds management.

* `api/Get_User_Orders.php`, `api/Orders_Management.php`
* `api/Transaction_Management.php`, `api/Fund_Request.php`
* `api/Refund_Actions.php`, `api/Photo_upload_proof.php`

### Public_Api
> Lightweight public checks / shared config.

* `_check.php` (Health / quick environment checks)
* `Public_Api/config/` (Shared configuration)

### Public_Assets
> Shared CSS/JS/images/uploads & PWA support.

* `manifest.json` and `sw.js` at repo root indicate PWA (Progressive Web App) support.

## Important notes for deployment

* **Document Root**: The repo places `index.html` at the root. Ensure the webserver document root points to the repo root.
* **PHP Version**: Ensure PHP 8.0.3 environment matches local dev.
* **File Uploads**: Configure `upload_max_filesize` and `post_max_size` in `php.ini`.
* **Service Worker / PWA**: `sw.js` requires HTTPS in production to function.
* **Permissions**: Do NOT use 777. Chown upload directories to your webserver user:

```bash
# Example: adjust to your system web user (www-data, www, _www)
sudo chown -R www-data:www-data Public_Assets/uploads Module_After_Sales_Dispute/uploads
```

* **Secrets**: Remove DB credentials and API keys (`SENDGRID_API_KEY`, etc.) from tracked files. Use environment variables or untracked config files in production.

## Security & operational caveats (prioritized)

* **SQL Injection**: Use prepared statements (PDO) for all DB access.
* **Session Handling**: Endpoints reference `session_status.php`. Ensure sessions are stored securely and regenerate IDs on login.
* **File Upload Validation**: Validate MIME types using `finfo`, limit extensions, and store files with randomized filenames.
* **Admin Endpoints**: Ensure `admin_*.php` APIs strictly verify admin privileges server-side.
* **Rate Limiting**: Implement throttling for `api/verify_code.php` (email sending) to prevent API Key abuse.

## Troubleshooting & common tasks

* **Blank Pages / Errors**: Check webserver logs (Apache/Nginx) or PHP `error_log`.
* **Permission Denied**: Fix upload folder permissions:

```bash
sudo chmod -R 750 Public_Assets/uploads Module_After_Sales_Dispute/uploads
```

* **DB Connection Failures**: Verify credentials in `Module_*/config/` files.
* **API Smoke Test**:

```bash
curl -i "http://localhost:8000/Module_Product_Ecosystem/api/Get_Products.php"
```
