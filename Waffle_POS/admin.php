<?php
// admin.php  (menu management)
session_start();
if (($_SESSION['role'] ?? '') !== 'Admin') { header("Location: login.php"); exit(); }
require_once 'db.php';

use App\Core\DatabaseException;
use App\Core\Site;
use App\Services\MenuService;

$menus = new MenuService();
$show_admin_modal = false; $modal_title = ""; $modal_msg = "";

function adminAlert(&$showModal, &$modalTitle, &$modalMessage, $newTitle, $newMessage) { $showModal = true; $modalTitle = $newTitle; $modalMessage = $newMessage; }

try {
    // --- ADD PRODUCT ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
        $menus->addProduct($_POST['prod_name'] ?? '', $_POST['prod_category'] ?? '', $_POST['prod_price'] ?? 0);
        adminAlert($show_admin_modal, $modal_title, $modal_msg, "Catalog Expanded",
                   "Product added and now visible on the cashier menu.");
    }

    // --- EDIT PRODUCT (name + price) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_product'])) {
        $menus->editProduct((int)($_POST['edit_id'] ?? 0), $_POST['edit_name'] ?? '', $_POST['edit_price'] ?? 0);
        adminAlert($show_admin_modal, $modal_title, $modal_msg, "Product Updated", "Name and price were saved. Past sales keep their original prices.");
    }

    // --- SHOW / HIDE ON THE CASHIER MENU ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_product'])) {
        $nowAvailable = $menus->toggleAvailability((int)($_POST['toggle_id'] ?? 0));
        adminAlert($show_admin_modal, $modal_title, $modal_msg,
                   $nowAvailable ? "Item Available" : "Item Hidden",
                   $nowAvailable ? "This item is back on the cashier menu." : "This item is now hidden from the cashier terminal.");
    }

    // --- DELETE PRODUCT (POST only) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
        $menus->deleteProduct((int)($_POST['delete_id'] ?? 0));
        adminAlert($show_admin_modal, $modal_title, $modal_msg, "Item Deleted", "The product was permanently removed.");
    }
} catch (DatabaseException $exception) {
    // thrown by the setters of MenuItem, or by MenuService
    adminAlert($show_admin_modal, $modal_title, $modal_msg, $exception->getTitle(), $exception->getMessage());
}

$products = $menus->getAllProducts();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars(Site::getBusinessName()); ?> | Admin Console</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #faf8f5; margin: 40px; }
        .nav-link { background: #faf8f5; color: #5c3d2e; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: bold; font-size: 14px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .nav-link:hover { background: #eee; }
        .admin-flex { display: flex; gap: 40px; align-items: flex-start; }
        table { flex: 1; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden; }
        th, td { border: 1px solid #ebdcb9; padding: 16px; text-align: left; font-size: 14px; }
        th { background: #f5eedc; color: #5c3d2e; }
        input, select { padding: 16px; margin-bottom: 20px; width: 100%; font-size: 14px; box-sizing: border-box; }
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 9999; }
        .modal-box { background: white; border: 4px solid #5c3d2e; border-radius: 20px; width: 450px; padding: 40px; text-align: center; }
        .act { display: inline-block; border: none; border-radius: 6px; padding: 8px 12px; font-size: 12px; font-weight: bold; cursor: pointer; color: white; margin: 2px 0; }
        .act-edit { background: #0277bd; }
        .act-hide { background: #ef6c00; }
        .act-show { background: #2e7d32; }
        .act-del  { background: #c62828; }
        .badge { padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: bold; }
        .badge-on  { background: #c8e6c9; color: #2e7d32; }
        .badge-off { background: #ffcdd2; color: #b71c1c; }
    
        /* a roomier drop-down arrow, not stuck to the edge */
        select { -webkit-appearance: none; appearance: none; padding-right: 40px !important;
                 background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'><path d='M1 1l5 5 5-5' fill='none' stroke='%235c3d2e' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/></svg>");
                 background-repeat: no-repeat; background-position: right 16px center; background-size: 12px 8px; background-color: #fff; }
    </style>
    <script>
        function openEdit(id, name, price) {
            document.getElementById("edit_id").value = id;
            document.getElementById("edit_name").value = name;
            document.getElementById("edit_price").value = price;
            document.getElementById("editM").style.display = "flex";
        }
        function closeEdit() { document.getElementById("editM").style.display = "none"; }
    </script>
</head>
<body>
    <!-- TOP NAVIGATION HEADER WITH BACK / LOGOUT -->
    <div style="background:#5c3d2e; padding:15px 30px; margin:-40px -40px 30px -40px; display:flex; justify-content:space-between; align-items:center; box-shadow:0 4px 8px rgba(0,0,0,0.05);">
        <h2 style="color:white; margin:0; font-size:21px;"><?php echo htmlspecialchars(Site::getBusinessName()); ?> | Admin Console</h2>
        <?php include 'clock.php'; ?>
        <div><a href="staff.php" class="nav-link">Staff Accounts</a><a href="cashier_sales.php" class="nav-link" style="margin-left:10px;">Sales by Cashier</a><a href="logout.php" class="nav-link" style="margin-left:10px;">Back to Portal Gate</a></div>
    </div>

    <div class="admin-flex">
        <div style="width:30%; background:#fdfaf4; padding:40px; border-radius:16px; border:3px dashed #865d43;">
            <h3>Add Product</h3>
            <form method="POST">
                <input type="text" name="prod_name" required placeholder="Product name">
                <select name="prod_category">
                    <option value="Waffle">Waffle</option>
                    <option value="Drink">Drink</option>
                </select>
                <input type="number" step="0.01" min="0.01" name="prod_price" required placeholder="Price">
                <button type="submit" name="add_product" style="padding:15px; width:100%; background:#2e7d32; color:white; border:none; border-radius:8px; font-size:16px; font-weight:bold; cursor:pointer;">Add Product</button>
            </form>
        </div>

        <table>
            <thead><tr><th>ID</th><th>Name</th><th>Category</th><th>Price</th><th>Cashier Menu</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($products as $entry):
                    $item = $entry['item']; $avail = $entry['available']; ?>
                    <tr style="<?php echo $avail ? '' : 'background:#fafafa; color:#999;'; ?>">
                        <td>#<?php echo (int)$item->getProductId(); ?></td>
                        <td><strong><?php echo htmlspecialchars($item->getName()); ?></strong></td>
                        <td><?php echo htmlspecialchars($item->getCategory()); ?></td>
                        <td style="color:#2e7d32; font-weight:bold;">₱<?php echo number_format($item->getPrice(), 2); ?></td>
                        <td><span class="badge <?php echo $avail ? 'badge-on' : 'badge-off'; ?>"><?php echo $avail ? 'AVAILABLE' : 'HIDDEN'; ?></span></td>
                        <td>
                            <button type="button" class="act act-edit"
                                onclick='openEdit(<?php echo (int)$item->getProductId(); ?>, <?php echo json_encode($item->getName(), JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP); ?>, <?php echo json_encode((float)$item->getPrice()); ?>)'>Edit</button>

                            <form method="POST" style="display:inline; margin:0;">
                                <input type="hidden" name="toggle_id" value="<?php echo (int)$item->getProductId(); ?>">
                                <button type="submit" name="toggle_product" class="act <?php echo $avail ? 'act-hide' : 'act-show'; ?>">
                                    <?php echo $avail ? 'Hide' : 'Show'; ?>
                                </button>
                            </form>

                            <form method="POST" style="display:inline; margin:0;" onsubmit="return confirm('Permanently delete this item?');">
                                <input type="hidden" name="delete_id" value="<?php echo (int)$item->getProductId(); ?>">
                                <button type="submit" name="delete_product" class="act act-del">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- EDIT MODAL -->
    <div id="editM" class="modal-overlay">
        <div class="modal-box" style="text-align:left;">
            <h3 style="text-align:center;">Edit Product</h3>
            <form method="POST">
                <input type="hidden" name="edit_id" id="edit_id">
                <label>Name</label>
                <input type="text" name="edit_name" id="edit_name" required>
                <label>Price</label>
                <input type="number" step="0.01" min="0.01" name="edit_price" id="edit_price" required>
                <button type="submit" name="edit_product" style="background:#2e7d32; color:white; padding:14px; width:100%; border:none; border-radius:6px; font-size:14px; font-weight:bold; cursor:pointer;">Save Changes</button>
                <button type="button" onclick="closeEdit();" style="background:#777; color:white; padding:12px; width:100%; border:none; border-radius:6px; font-size:13px; cursor:pointer; margin-top:10px;">Cancel</button>
            </form>
        </div>
    </div>

    <!-- ALERT MODAL -->
    <?php if ($show_admin_modal): ?>
    <div class="modal-overlay" style="display:flex;">
        <div class="modal-box">
            <h3><?php echo htmlspecialchars($modal_title); ?></h3>
            <p style="background:#fdfaf4; padding:15px; border-left:6px solid #865d43; text-align:left;"><?php echo htmlspecialchars($modal_msg); ?></p>
            <button onclick="window.location.href='admin.php';" style="background:#5c3d2e; color:white; padding:12px; width:100%; border:none; border-radius:6px; cursor:pointer;">Acknowledge Context</button>
        </div>
    </div>
    <?php endif; ?>
<?php include 'footer.php'; ?>
</body>
</html>
