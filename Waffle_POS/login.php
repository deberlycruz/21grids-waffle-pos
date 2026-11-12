<?php
// login.php  (checks the Staff table; passwords are verified against bcrypt hashes)
session_start();
require_once 'db.php';

use App\Core\Site;
use App\Services\AuthService;

$error_message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth = new AuthService();
    $staff = $auth->login($_POST['username'] ?? '', $_POST['password'] ?? '', $_POST['role'] ?? '');

    if ($staff) {
        session_regenerate_id(true);
        $_SESSION['staff_id']  = (int)$staff['StaffID'];
        $_SESSION['role']      = $staff['Role'];
        $_SESSION['full_name'] = $staff['FullName'];

        $destinations = ['Cashier' => 'cashier.php', 'Owner' => 'owner.php', 'Admin' => 'admin.php'];
        header("Location: " . $destinations[$staff['Role']]);
        exit();
    }
    $error_message = "Invalid username, password, or role.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars(Site::getBusinessName()); ?></title>
    <link rel="icon" href="assets/logo.png">
    <style>
        body { font-family: "Segoe UI", sans-serif; background: #faf8f5; display: flex; flex-direction: column; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; box-sizing: border-box; }
        .login-card { background: white; border: 4px solid #e8dec9; border-radius: 20px; width: 880px; max-width: 100%; display: flex; box-shadow: 0 12px 24px rgba(0,0,0,0.05); overflow: hidden; }
        .brand { flex: 0 0 42%; background: #fffdf8; border-right: 3px solid #e8dec9; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 30px; text-align: center; }
        .brand img { width: 100%; max-width: 250px; height: auto; }
        .brand .site { margin-top: 18px; font-size: 16px; font-weight: bold; color: #5c3d2e; letter-spacing: 1px; }
        .brand .addr { color: #777; font-size: 11px; margin-top: 4px; }
        .form-side { flex: 1; padding: 44px 46px; text-align: center; }
        select, input, button { padding: 16px; width: 100%; border-radius: 10px; border: 2px solid #ccc; font-size: 14px; box-sizing: border-box; margin-bottom: 16px; }
        button { background: #5c3d2e; color: white; font-weight: bold; cursor: pointer; }
        @media (max-width: 720px) { .login-card { flex-direction: column; } .brand { border-right: 0; border-bottom: 3px solid #e8dec9; } }
    
        /* a roomier drop-down arrow, not stuck to the edge */
        select { -webkit-appearance: none; appearance: none; padding-right: 40px !important;
                 background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'><path d='M1 1l5 5 5-5' fill='none' stroke='%235c3d2e' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/></svg>");
                 background-repeat: no-repeat; background-position: right 16px center; background-size: 12px 8px; background-color: #fff; }
    </style>
</head>
<body>
<div class="login-card">
    <div class="brand">
        <?php if (file_exists(__DIR__ . '/assets/logo.png')): ?>
            <img src="assets/logo.png" alt="<?php echo htmlspecialchars(Site::getBusinessName()); ?>">
        <?php else: ?>
            <div style="font-size:21px; font-weight:bold; color:#5c3d2e;"><?php echo htmlspecialchars(Site::getBusinessName()); ?></div>
        <?php endif; ?>
    </div>
    <div class="form-side">
        <h2 style="color:#5c3d2e; margin-top:0;">Staff Login</h2>
        <?php if ($error_message): ?><div style="color:red; font-weight:bold; margin-bottom:15px;"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>
        <form method="POST">
            <select name="role">
                <option value="Cashier">Cashier Counter Terminal</option>
                <option value="Owner">Store Owner Dashboard</option>
                <option value="Admin">System Admin Console</option>
            </select>
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Log In</button>
        </form>
    </div>
</div>
<div style="position:fixed; top:10px; right:16px;"><span class="live-clock" style="color:#5c3d2e; font-size:11px;"></span></div>
<?php $clockScriptOnly = true; include 'clock.php'; ?>
<?php include 'footer.php'; ?>
</body>
</html>
