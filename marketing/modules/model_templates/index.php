<?php
/**
 * Model Templates Module - Master Content Library
 */

// Handle Actions
$action = $_GET['action'] ?? null;

if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $model_name = trim($_POST['model_name'] ?? '');
    $category = $_POST['category'] ?? 'Laptop';
    $base_specs = trim($_POST['base_specs'] ?? '');
    $marketing_copy = trim($_POST['marketing_copy'] ?? '');

    if (!empty($model_name)) {
        try {
            $stmt = $marketingDb->prepare("INSERT INTO model_templates (model_name, category, base_specs, marketing_copy) VALUES (?, ?, ?, ?)");
            $stmt->execute([$model_name, $category, $base_specs, $marketing_copy]);
            
            $newId = $marketingDb->lastInsertId();
            log_marketing_audit($marketingDb, 'Template', $newId, 'CREATED', "Created marketing template for: $model_name");
            
            header("Location: ?page=model_templates&success=1");
            exit;
        } catch (Exception $e) {
            $error = "Failed to create template: " . $e->getMessage();
        }
    } else {
        $error = "Model Name is required.";
    }
}
?>

<header class="page-header">
    <h1>Model Template Library</h1>
    <p>Create master marketing copy and specs for high-volume inventory.</p>
</header>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">Template created successfully!</div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<div class="dashboard-grid">
    <!-- NEW TEMPLATE FORM -->
    <section class="card">
        <h2>Create New Template</h2>
        <form action="?page=model_templates&action=add" method="POST" class="standard-form">
            <div class="form-group">
                <label for="model_name">Model Name (Unique Identifier)</label>
                <input type="text" name="model_name" id="model_name" required placeholder="e.g. Dell Latitude 5490">
            </div>
            <div class="form-group">
                <label for="category">Category</label>
                <select name="category" id="category">
                    <option value="Laptop">Laptop</option>
                    <option value="Desktop">Desktop</option>
                    <option value="Server">Server</option>
                    <option value="Part">Component / Part</option>
                </select>
            </div>
            <div class="form-group">
                <label for="base_specs">Standard Specifications</label>
                <textarea name="base_specs" id="base_specs" rows="4" placeholder="i5-8350U, 8GB RAM, 256GB SSD..."></textarea>
            </div>
            <div class="form-group">
                <label for="marketing_copy">Marketing Copy (The Pitch)</label>
                <textarea name="marketing_copy" id="marketing_copy" rows="6" placeholder="Write the ad description here..."></textarea>
            </div>
            <button type="submit" class="btn-action">Save Template</button>
        </form>
    </section>

    <!-- TEMPLATE LIST -->
    <section class="card" style="grid-column: span 2;">
        <h2>Existing Templates</h2>
        <div class="template-list">
            <?php
            $templates = $marketingDb->query("SELECT * FROM model_templates ORDER BY model_name ASC")->fetchAll();
            if (empty($templates)):
            ?>
                <div style="text-align: center; padding: 3rem; color: var(--text-dim);">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">📚</div>
                    <p>Your library is empty. Templates help you generate ads instantly from warehouse stock.</p>
                </div>
            <?php else: ?>
                <div class="template-grid">
                    <?php foreach ($templates as $tmpl): ?>
                        <div class="template-card">
                            <div class="tmpl-header">
                                <h3><?php echo htmlspecialchars($tmpl['model_name']); ?></h3>
                                <span class="tmpl-badge"><?php echo $tmpl['category']; ?></span>
                            </div>
                            <div class="tmpl-body">
                                <p class="tmpl-specs"><strong>Specs:</strong> <?php echo htmlspecialchars(mb_strimwidth($tmpl['base_specs'], 0, 100, "...")); ?></p>
                                <p class="tmpl-copy"><?php echo htmlspecialchars(mb_strimwidth($tmpl['marketing_copy'], 0, 150, "...")); ?></p>
                            </div>
                            <div class="tmpl-footer">
                                <a href="?page=model_templates&action=edit&id=<?php echo $tmpl['id']; ?>" class="btn-small">Edit</a>
                                <a href="?page=ad_generator&template_id=<?php echo $tmpl['id']; ?>" class="btn-small btn-highlight">Create Ad</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>


