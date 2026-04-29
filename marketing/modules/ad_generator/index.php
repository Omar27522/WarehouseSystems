<?php
/**
 * Ad Generator Module - The "Manifest Script"
 * Cross-references Warehouse stock with Marketing templates.
 */

// 1. Fetch available models from Labels DB
$modelsInStock = [];
if ($labelsDb) {
    $stmt = $labelsDb->query("
        SELECT brand, model, COUNT(*) as qty 
        FROM items 
        WHERE status = 'In Warehouse' 
        GROUP BY brand, model 
        ORDER BY qty DESC
    ");
    $modelsInStock = $stmt->fetchAll();
}

// 2. Handle Ad Generation Logic
$selectedModel = $_GET['model'] ?? null;
$generatedAd = null;
$matchingTemplate = null;

if ($selectedModel) {
    // Find template in Marketing DB
    $stmt = $marketingDb->prepare("SELECT * FROM model_templates WHERE model_name = ?");
    $stmt->execute([$selectedModel]);
    $matchingTemplate = $stmt->fetch();

    if ($matchingTemplate) {
        // Calculate QTY again for the specific ad
        $qty = 0;
        foreach($modelsInStock as $m) {
            if ($m['model'] === $selectedModel) {
                $qty = $m['qty'];
                break;
            }
        }

        // COMPILE THE AD
        $generatedAd = "🔥 INVENTORY ALERT: " . strtoupper($matchingTemplate['model_name']) . " 🔥\n\n";
        $generatedAd .= "We have just processed a batch of " . $qty . " units, now ready for immediate fulfillment!\n\n";
        $generatedAd .= "📍 SPECIFICATIONS:\n" . $matchingTemplate['base_specs'] . "\n\n";
        $generatedAd .= "📝 OVERVIEW:\n" . $matchingTemplate['marketing_copy'] . "\n\n";
        $generatedAd .= "DM for pricing and bulk manifest. #WarehouseDeals #IQAInventory #RefurbishedTech";
        
        log_marketing_audit($marketingDb, 'AdGenerator', $selectedModel, 'GENERATED', "Generated ad manifest for $selectedModel (Qty: $qty)");
    }
}
?>

<header class="page-header">
    <h1>Inventory-to-Ad Generator</h1>
    <p>Convert real-time warehouse stock into high-performance marketing copy.</p>
</header>

<div class="dashboard-grid">
    <!-- STOCK SELECTOR -->
    <section class="card">
        <h2>1. Select Stock from Warehouse</h2>
        <div class="stock-list">
            <?php if (empty($modelsInStock)): ?>
                <p style="color: var(--text-dim);">No stock found in Labels database.</p>
            <?php else: ?>
                <div class="stock-grid">
                    <?php foreach ($modelsInStock as $stock): ?>
                        <a href="?page=ad_generator&model=<?php echo urlencode($stock['model']); ?>" 
                           class="stock-item <?php echo ($selectedModel === $stock['model']) ? 'active' : ''; ?>">
                            <div class="stock-qty"><?php echo $stock['qty']; ?> Units</div>
                            <div class="stock-name"><?php echo htmlspecialchars($stock['brand'] . ' ' . $stock['model']); ?></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- AD PREVIEW -->
    <section class="card" style="grid-column: span 2;">
        <h2>2. Generated Ad Manifest</h2>
        <?php if ($selectedModel && !$matchingTemplate): ?>
            <div class="alert alert-danger">
                No marketing template found for <strong><?php echo htmlspecialchars($selectedModel); ?></strong>. 
                <a href="?page=model_templates" style="color: white; text-decoration: underline;">Create one here</a> to generate an ad.
            </div>
        <?php elseif ($generatedAd): ?>
            <div class="ad-preview-container">
                <textarea id="adOutput" readonly><?php echo htmlspecialchars($generatedAd); ?></textarea>
                <div class="ad-actions">
                    <button onclick="copyAdToClipboard()" class="btn-action">📋 Copy to Clipboard</button>
                    <p style="font-size: 0.8rem; color: var(--text-dim); margin-top: 1rem;">
                        This ad is optimized for LinkedIn and Email outreach.
                    </p>
                </div>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 4rem; color: var(--text-dim);">
                <div style="font-size: 3rem; margin-bottom: 1rem;">⚡</div>
                <p>Select a model from the left to generate its marketing manifest.</p>
            </div>
        <?php endif; ?>
    </section>
</div>

<script>
function copyAdToClipboard() {
    const copyText = document.getElementById("adOutput");
    copyText.select();
    copyText.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(copyText.value);
    
    const btn = document.querySelector('.btn-action');
    const originalText = btn.innerHTML;
    btn.innerHTML = "✅ Copied!";
    btn.style.background = "var(--accent-blue)";
    
    setTimeout(() => {
        btn.innerHTML = originalText;
        btn.style.background = "";
    }, 2000);
}
</script>

<style>
.stock-grid {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}
.stock-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 0.75rem;
    text-decoration: none;
    color: var(--text-main);
    transition: all 0.2s;
}
.stock-item:hover {
    border-color: var(--accent-purple);
    background: rgba(255, 255, 255, 0.1);
}
.stock-item.active {
    border-color: var(--accent-purple);
    background: rgba(168, 85, 247, 0.1);
}
.stock-qty {
    font-weight: 800;
    font-size: 0.8rem;
    background: var(--accent-purple);
    padding: 0.2rem 0.6rem;
    border-radius: 2rem;
}
.stock-name {
    font-weight: 600;
}
.ad-preview-container {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}
#adOutput {
    width: 100%;
    height: 400px;
    background: rgba(0, 0, 0, 0.2);
    border: 1px solid var(--glass-border);
    border-radius: 1rem;
    padding: 1.5rem;
    color: #4ade80;
    font-family: 'Courier New', Courier, monospace;
    font-size: 0.95rem;
    line-height: 1.6;
    resize: none;
    outline: none;
}
</style>
