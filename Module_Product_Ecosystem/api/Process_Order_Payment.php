<?php
// api/Process_Order_Payment.php

error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config/treasurego_db_config.php';
session_start();

$response = ['success' => false, 'msg' => 'Unknown error'];

// 1. Verify Login
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'msg' => 'User not logged in']);
    exit;
}
$buyerId = $_SESSION['user_id'];

// 2. Get frontend data
$input = json_decode(file_get_contents('php://input'), true);
$totalAmount = isset($input['total_amount']) ? floatval($input['total_amount']) : 0.00;
$productId   = isset($input['product_id']) ? intval($input['product_id']) : 0;
$shippingType = isset($input['shipping_type']) ? $input['shipping_type'] : 'meetup';
$addressId = isset($input['address_id']) && $input['address_id'] !== '' ? intval($input['address_id']) : null;

// NEW: Get payment PIN
$pinCode = isset($input['payment_pin']) ? $input['payment_pin'] : '';

if ($totalAmount <= 0 || $productId === 0) {
    echo json_encode(['success' => false, 'msg' => 'Invalid payment data']);
    exit;
}
if (empty($pinCode)) {
    echo json_encode(['success' => false, 'msg' => 'Payment PIN is required']);
    exit;
}

try {
    $conn = getDatabaseConnection();

    // =================================================================
    // STEP A: Payment PIN Verification (Must be executed before starting transaction)
    // =================================================================
    $stmtUser = $conn->prepare("SELECT User_Payment_PIN_Hash, User_PIN_Retry_Count, User_PIN_Locked_Until FROM User WHERE User_ID = :uid");
    $stmtUser->execute([':uid' => $buyerId]);
    $userInfo = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$userInfo) {
        throw new Exception("User not found");
    }

    // Check lock
    if ($userInfo['User_PIN_Locked_Until'] && strtotime($userInfo['User_PIN_Locked_Until']) > time()) {
        $waitMinutes = ceil((strtotime($userInfo['User_PIN_Locked_Until']) - time()) / 60);
        throw new Exception("Wallet locked. Try again in $waitMinutes minutes.");
    }

    // Verify password
    if (!password_verify($pinCode, $userInfo['User_Payment_PIN_Hash'])) {
        $newRetry = $userInfo['User_PIN_Retry_Count'] + 1;
        $lockUntil = null;
        $errorMsg = "Incorrect PIN. Attempts remaining: " . (5 - $newRetry);

        if ($newRetry >= 5) {
            $lockUntil = date('Y-m-d H:i:s', strtotime('+15 minutes'));
            $newRetry = 0;
            $errorMsg = "Too many failed attempts. Wallet locked for 15 minutes.";
        }

        $updateStmt = $conn->prepare("UPDATE User SET User_PIN_Retry_Count = :retry, User_PIN_Locked_Until = :lock WHERE User_ID = :uid");
        $updateStmt->execute([':retry' => $newRetry, ':lock' => $lockUntil, ':uid' => $buyerId]);

        throw new Exception($errorMsg);
    }

    // Reset error count
    if ($userInfo['User_PIN_Retry_Count'] > 0) {
        $conn->prepare("UPDATE User SET User_PIN_Retry_Count = 0 WHERE User_ID = :uid")->execute([':uid' => $buyerId]);
    }

    // =================================================================
    // STEP B: Core Transaction
    // =================================================================
    $conn->beginTransaction();

    // Verify address
    if ($shippingType === 'shipping') {
        if (!$addressId) {
            throw new Exception('Shipping address is required');
        }
        $stmtAddr = $conn->prepare("SELECT Address_ID FROM Address WHERE Address_ID = :aid AND Address_User_ID = :uid");
        $stmtAddr->execute([':aid' => $addressId, ':uid' => $buyerId]);
        if (!$stmtAddr->fetch(PDO::FETCH_ASSOC)) {
            throw new Exception('Invalid shipping address');
        }
    } else {
        $addressId = null;
    }

    // 3. Get product info (Lock)
    $sqlProduct = "SELECT User_ID AS Seller_ID, Product_Price, Product_Status, Product_Title 
                   FROM Product 
                   WHERE Product_ID = :pid 
                   FOR UPDATE";
    $stmtProd = $conn->prepare($sqlProduct);
    $stmtProd->execute([':pid' => $productId]);
    $productInfo = $stmtProd->fetch(PDO::FETCH_ASSOC);

    if (!$productInfo) throw new Exception("Product not found");
    if ($productInfo['Product_Status'] !== 'Active') throw new Exception("Product is unavailable");
    if ($productInfo['Seller_ID'] == $buyerId) throw new Exception("You cannot buy your own product");

    $sellerId = $productInfo['Seller_ID'];
    $productPrice = floatval($productInfo['Product_Price']);

    // 4. Check balance
    $sqlCheck = "SELECT Balance_After FROM Wallet_Logs WHERE User_ID = :uid ORDER BY Log_ID DESC LIMIT 1 FOR UPDATE";
    $stmtCheck = $conn->prepare($sqlCheck);
    $stmtCheck->execute([':uid' => $buyerId]);
    $walletResult = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    $currentBalance = $walletResult ? (float)$walletResult['Balance_After'] : 0.00;

    if ($currentBalance < $totalAmount) {
        throw new Exception("Insufficient balance");
    }

    // ----------------------------------------------------------------
    // 5. Create Order first (Orders) - So that Order_ID is available for Wallet Logs
    // ----------------------------------------------------------------
    // Calculate platform fee
    $sqlMembership = "SELECT mp.Membership_Tier FROM Memberships m JOIN Membership_Plans mp ON m.Plan_ID = mp.Plan_ID WHERE m.User_ID = :seller_id AND m.Memberships_Start_Date <= NOW() AND m.Memberships_End_Date > NOW() ORDER BY mp.Membership_Price DESC LIMIT 1";
    $stmtMembership = $conn->prepare($sqlMembership);
    $stmtMembership->execute([':seller_id' => $sellerId]);
    $membership = $stmtMembership->fetch(PDO::FETCH_ASSOC);

    $sellerTier = $membership ? $membership['Membership_Tier'] : 'Free';
    $waiveFeeTiers = ['VIP', 'SVIP'];
    $platformFee = in_array($sellerTier, $waiveFeeTiers) ? 0.00 : $productPrice * 0.02;

    $sqlOrder = "INSERT INTO Orders (
                    Orders_Buyer_ID, Orders_Seller_ID, Product_ID,
                    Orders_Total_Amount, Orders_Platform_Fee, 
                    Orders_Status, Orders_Created_AT, Address_ID
                ) VALUES (
                    :buyer_id, :seller_id, :product_id,
                    :total_amount, :platform_fee, 
                    'Paid', NOW(), :address_id
                )";

    $stmtOrder = $conn->prepare($sqlOrder);
    $stmtOrder->execute([
        ':buyer_id' => $buyerId,
        ':seller_id' => $sellerId,
        ':product_id' => $productId,
        ':total_amount' => $totalAmount,
        ':platform_fee' => $platformFee,
        ':address_id' => $addressId
    ]);

    // Get the newly generated Order ID
    $newOrderId = $conn->lastInsertId();

    // ----------------------------------------------------------------
    // 6. Execute Deduction (Wallet_Logs) - Link Order ID
    // ----------------------------------------------------------------
    $newBalance = $currentBalance - $totalAmount;
    $negativeAmount = -1 * $totalAmount;
    $walletDesc = "Payment for Order: " . $productInfo['Product_Title'];

    // Update: Added Reference_ID and Reference_Type
    $sqlInsertWallet = "INSERT INTO Wallet_Logs 
                  (User_ID, Amount, Balance_After, Description, Reference_Type, Reference_ID, Created_AT) 
                  VALUES 
                  (:uid, :amount, :balance_after, :desc, 'order_payment', :ref_id, NOW())";

    $stmtWallet = $conn->prepare($sqlInsertWallet);
    $stmtWallet->execute([
        ':uid' => $buyerId,
        ':amount' => $negativeAmount,
        ':balance_after' => $newBalance,
        ':desc' => $walletDesc,
        ':ref_id' => $newOrderId // Link the Order ID generated just now
    ]);

    // ----------------------------------------------------------------
    // 7. Update product status
    // ----------------------------------------------------------------
    $sqlUpdateProd = "UPDATE Product SET Product_Status = 'Sold' WHERE Product_ID = :pid";
    $stmtUpdateProd = $conn->prepare($sqlUpdateProd);
    $stmtUpdateProd->execute([':pid' => $productId]);

    // === Commit Transaction ===
    $conn->commit();

    $response['success'] = true;
    $response['msg'] = 'Payment successful';

} catch (Exception $e) {
    if (isset($conn)) { $conn->rollBack(); } // Rollback on error
    $response['msg'] = $e->getMessage();
}

echo json_encode($response);
?>