<?php
// staff.php  (Admin only: register cashiers, edit names, reset passwords, activate/deactivate)
session_start();
if (($_SESSION['role'] ?? '') !== 'Admin') { header("Location: login.php"); exit(); }
require_once 'db.php';

use App\Core\DatabaseException;
use App\Core\Site;
use App\Core\OrderException;
use App\Services\StaffService;

$staffService = new StaffService();
$myId = (int)($_SESSION['staff_id'] ?? 0);
$show_modal = false; $modal_title = ""; $modal_msg = "";
function note(&$showModal, &$modalTitle, &$modalMessage, $newTitle, $newMessage) { $showModal = true; $modalTitle = $newTitle; $modalMessage = $newMessage; }

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['add_staff'])) {
            $staffService->addCashier($_POST['full_name'] ?? '', $_POST['username'] ?? '', $_POST['password'] ?? '', $_POST['password2'] ?? '');
            note($show_modal, $modal_title, $modal_msg, "Cashier Registered",
                 trim($_POST['full_name']) . " can now log in as a cashier with username \"" . trim($_POST['username']) . "\".");
        } elseif (isset($_POST['edit_staff'])) {
            $staffService->edit((int)$_POST['edit_id'], $_POST['edit_name'] ?? '', $_POST['edit_username'] ?? '');
            note($show_modal, $modal_title, $modal_msg, "Account Updated", "Changes were saved. Old sales keep pointing to this person.");
        } elseif (isset($_POST['reset_password'])) {
            $staffService->resetPassword((int)$_POST['reset_id'], $_POST['new_password'] ?? '');
            note($show_modal, $modal_title, $modal_msg, "Password Changed", "The new password works from the next login.");
        } elseif (isset($_POST['toggle_staff'])) {
            $nowActive = $staffService->toggleActive((int)$_POST['toggle_id']);
            note($show_modal, $modal_title, $modal_msg,
                 $nowActive ? "Account Active" : "Account Deactivated",
                 "A deactivated cashier can no longer log in, and any open session is signed out on the next page load. Their past sales are kept.");
        }
    }
} catch (OrderException | DatabaseException $exception) {
    note($show_modal, $modal_title, $modal_msg, $exception->getTitle(), $exception->getMessage());
}

$staff = $staffService->getAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars(Site::getBusinessName()); ?> | Staff Accounts</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #faf8f5; margin: 40px; color:#333; }
        .nav-link { background: #faf8f5; color: #5c3d2e; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: bold; font-size: 14px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-left: 10px; }
        .nav-link:hover { background: #eee; }
        h3.sec { color:#5c3d2e; font-size: 21px; margin: 35px 0 10px; }
        table { width: 100%; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden; }
        th, td { border: 1px solid #ebdcb9; padding: 12px 14px; text-align: left; font-size: 13px; }
        th { background: #f5eedc; color: #5c3d2e; }
        input { padding: 10px; font-size: 13px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 6px; }
        .box { background:#fdfaf4; padding:20px; border-radius:12px; border:3px dashed #865d43; display:flex; gap:12px; flex-wrap:wrap; align-items:end; }
        .box label { display:block; font-size:11px; color:#666; margin-bottom:4px; }
        .act { border:none; border-radius:6px; padding:8px 12px; font-size:11px; font-weight:bold; cursor:pointer; color:white; }
        .go { background:#2e7d32; } .blue { background:#0277bd; } .orange { background:#ef6c00; } .grey { background:#777; }
        .badge { padding:4px 10px; border-radius:6px; font-size:11px; font-weight:bold; }
        .on { background:#c8e6c9; color:#2e7d32; } .off { background:#ffcdd2; color:#b71c1c; }
        .role-admin { background:#e1bee7; color:#6a1b9a; } .role-owner { background:#bbdefb; color:#0d47a1; } .role-cashier { background:#ffe0b2; color:#e65100; }
        .modal-overlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); justify-content:center; align-items:center; z-index:9999; }
        .modal-box { background:white; border:4px solid #5c3d2e; border-radius:20px; width:450px; max-width:94%; padding:35px; }
        .modal-box input { width:100%; margin-bottom:12px; }
    
        /* a roomier drop-down arrow, not stuck to the edge */
        select { -webkit-appearance: none; appearance: none; padding-right: 40px !important;
                 background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'><path d='M1 1l5 5 5-5' fill='none' stroke='%235c3d2e' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/></svg>");
                 background-repeat: no-repeat; background-position: right 16px center; background-size: 12px 8px; background-color: #fff; }
    </style>
    <script>
        function openEdit(id, name, username, role) {
            document.getElementById("edit_id").value = id;
            document.getElementById("edit_name").value = name;
            var u = document.getElementById("edit_username");
            u.value = username; u.readOnly = (role !== 'Cashier'); u.style.background = (role !== 'Cashier') ? '#eee' : '#fff';
            document.getElementById("editM").style.display = "flex";
        }
        function openReset(id, name) {
            document.getElementById("reset_id").value = id;
            document.getElementById("reset_title").innerText = name;
            document.getElementById("new_password").value = "";
            document.getElementById("resetM").style.display = "flex";
        }
        function closeM(id) { document.getElementById(id).style.display = "none"; }
    </script>
</head>
<body>
    <div style="background:#5c3d2e; padding:15px 30px; margin:-40px -40px 30px -40px; display:flex; justify-content:space-between; align-items:center;">
        <h2 style="color:white; margin:0; font-size:21px;"><?php echo htmlspecialchars(Site::getBusinessName()); ?> | Staff Accounts</h2>
        <?php include 'clock.php'; ?>
        <div><a href="admin.php" class="nav-link">Menu Items</a><a href="logout.php" class="nav-link">Back to Portal Gate</a></div>
    </div>

    <h3 class="sec" style="margin-top:0;">Register a Cashier</h3>
    <form method="POST" class="box" autocomplete="off">
        <div><label>Full name</label><input type="text" name="full_name" required maxlength="100" placeholder="e.g., Rosa Villanueva"></div>
        <div><label>Username (letters, numbers, _)</label><input type="text" name="username" required maxlength="30" placeholder="e.g., rosa"></div>
        <div><label>Password (6+ characters)</label><input type="password" name="password" required minlength="6"></div>
        <div><label>Type password again</label><input type="password" name="password2" required minlength="6"></div>
        <button type="submit" name="add_staff" class="act go" style="padding:12px 20px; font-size:13px;">Register Cashier</button>
    </form>

    <h3 class="sec">All Accounts</h3>
    <table>
        <thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Status</th><th>Orders handled</th><th>Created</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($staff as $staffMember): ?>
            <tr style="<?php echo $staffMember['IsActive'] ? '' : 'background:#fafafa; color:#999;'; ?>">
                <td><strong><?php echo htmlspecialchars($staffMember['FullName']); ?></strong><?php echo (int)$staffMember['StaffID'] === $myId ? ' <em style="color:#865d43;">(you)</em>' : ''; ?></td>
                <td><?php echo htmlspecialchars($staffMember['Username']); ?></td>
                <td><span class="badge role-<?php echo strtolower(htmlspecialchars($staffMember['Role'])); ?>"><?php echo strtoupper(htmlspecialchars($staffMember['Role'])); ?></span></td>
                <td><span class="badge <?php echo $staffMember['IsActive'] ? 'on' : 'off'; ?>"><?php echo $staffMember['IsActive'] ? 'ACTIVE' : 'DEACTIVATED'; ?></span></td>
                <td><?php echo (int)$staffMember['OrderCount']; ?></td>
                <td><?php echo htmlspecialchars(substr($staffMember['CreatedAt'], 0, 10)); ?></td>
                <td style="white-space:nowrap;">
                    <button type="button" class="act blue" onclick='openEdit(<?php echo (int)$staffMember['StaffID']; ?>, <?php echo json_encode($staffMember['FullName'], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP); ?>, <?php echo json_encode($staffMember['Username'], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP); ?>, <?php echo json_encode($staffMember['Role']); ?>)'>Edit</button>
                    <button type="button" class="act grey" onclick='openReset(<?php echo (int)$staffMember['StaffID']; ?>, <?php echo json_encode($staffMember['FullName'], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP); ?>)'>Password</button>
                    <?php if ($staffMember['Role'] === 'Cashier'): ?>
                    <form method="POST" style="display:inline; margin:0;" onsubmit="return confirm('<?php echo $staffMember['IsActive'] ? 'Deactivate this cashier? They will not be able to log in.' : 'Activate this cashier again?'; ?>');">
                        <input type="hidden" name="toggle_id" value="<?php echo (int)$staffMember['StaffID']; ?>">
                        <button type="submit" name="toggle_staff" class="act <?php echo $staffMember['IsActive'] ? 'orange' : 'go'; ?>"><?php echo $staffMember['IsActive'] ? 'Deactivate' : 'Activate'; ?></button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p style="color:#777; margin-top:12px;">Accounts are deactivated, not deleted, so the sales each cashier made stay linked to their name.</p>

    <!-- EDIT MODAL -->
    <div id="editM" class="modal-overlay"><div class="modal-box">
        <h3 style="text-align:center; margin-top:0;">Edit Account</h3>
        <form method="POST">
            <input type="hidden" name="edit_id" id="edit_id">
            <label>Full name</label><input type="text" name="edit_name" id="edit_name" required maxlength="100">
            <label>Username <span style="color:#999; font-size:11px;">(only cashier usernames can change)</span></label><input type="text" name="edit_username" id="edit_username" maxlength="30">
            <button type="submit" name="edit_staff" class="act go" style="width:100%; padding:14px; font-size:14px;">Save Changes</button>
            <button type="button" class="act grey" onclick="closeM('editM')" style="width:100%; padding:12px; margin-top:8px;">Cancel</button>
        </form>
    </div></div>

    <!-- RESET PASSWORD MODAL -->
    <div id="resetM" class="modal-overlay"><div class="modal-box">
        <h3 style="text-align:center; margin-top:0;">New Password for <span id="reset_title"></span></h3>
        <form method="POST" autocomplete="off">
            <input type="hidden" name="reset_id" id="reset_id">
            <input type="password" name="new_password" id="new_password" required minlength="6" placeholder="New password (6+ characters)">
            <button type="submit" name="reset_password" class="act go" style="width:100%; padding:14px; font-size:14px;">Save New Password</button>
            <button type="button" class="act grey" onclick="closeM('resetM')" style="width:100%; padding:12px; margin-top:8px;">Cancel</button>
        </form>
    </div></div>

    <?php if ($show_modal): ?>
    <div class="modal-overlay" style="display:flex;"><div class="modal-box" style="text-align:center;">
        <h3 style="margin-top:0;"><?php echo htmlspecialchars($modal_title); ?></h3>
        <p style="background:#fdfaf4; padding:15px; border-left:6px solid #865d43; text-align:left;"><?php echo htmlspecialchars($modal_msg); ?></p>
        <button class="act" style="background:#5c3d2e; width:100%; padding:12px; font-size:13px;" onclick="window.location.href='staff.php';">Acknowledge</button>
    </div></div>
    <?php endif; ?>
<?php include 'footer.php'; ?>
</body>
</html>
