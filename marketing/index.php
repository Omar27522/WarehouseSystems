<?php
require_once 'config.php';
require_once INCLUDES_PATH . '/db.php';

$marketingDb = get_marketing_db();
$labelsDb = get_labels_db();
$crmDb = get_master_crm_db();

// Fetch real stats from Master CRM
$leadCount = $crmDb->query("SELECT COUNT(*) FROM customers WHERE account_status = 'Lead'")->fetchColumn() ?: 0;
$campaignCount = $marketingDb->query("SELECT COUNT(*) FROM campaigns WHERE status = 'Active'")->fetchColumn() ?: 0;

// Fetch inventory for summary (items with qty > 10)
if ($labelsDb) {
    $inventoryCount = $labelsDb->query("SELECT COUNT(DISTINCT model) FROM items WHERE status = 'In Warehouse'")->fetchColumn() ?: 0;
}

include_once INCLUDES_PATH . '/header.php';

// Simple Router
$page = $_GET['page'] ?? 'dashboard';
$module_path = MODULES_PATH . '/' . $page . '/index.php';

echo '<main id="main-content">';

if ($page === 'dashboard') {
    // Load Dashboard Stats (inline for now as the default view)
    ?>
    <header class="page-header">
        <h1>Welcome to <?php echo APP_NAME; ?></h1>
        <p>Your modular marketing command center.</p>
    </header>

    <div class="dashboard-grid">
        <section class="card lead-summary">
            <h2>Lead Statistics</h2>
            <div class="stat"><?php echo $leadCount; ?> Leads Tracked</div>
        </section>

        <section class="card campaign-summary">
            <h2>Active Campaigns</h2>
            <div class="stat"><?php echo $campaignCount; ?> Active</div>
        </section>

        <section class="card inventory-summary">
            <h2>Marketable Stock</h2>
            <div class="stat"><?php echo $inventoryCount; ?> Models in Bulk</div>
        </section>
    </div>
    <?php
} elseif (file_exists($module_path)) {
    include_once $module_path;
} else {
    echo '<section class="card"><h2>404</h2><p>Module "' . htmlspecialchars($page) . '" not found.</p></section>';
}

echo '</main>';

include_once INCLUDES_PATH . '/footer.php';
?>
