<?php
/**
 * Leads Module - Main View & Logic
 */

// Handle Actions
$action = $_GET['action'] ?? null;

if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $contact_person = trim($_POST['name'] ?? '');
    $company_name = trim($_POST['company'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $lead_source = $_POST['source'] ?? 'Manual';

    if (!empty($contact_person) && !empty($email)) {
        try {
            // Generate Master CRM ID (Matching Order Manager format)
            $customer_id = 'CUST-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

            $stmt = $crmDb->prepare("INSERT INTO customers (customer_id, contact_person, company_name, email, lead_source, account_status) VALUES (?, ?, ?, ?, ?, 'Lead')");
            $stmt->execute([$customer_id, $contact_person, $company_name, $email, $lead_source]);
            
            $newId = $customer_id;
            
            // Log to Audit
            log_marketing_audit($marketingDb, 'Lead', $newId, 'CREATED', "Manually added lead via Marketing: $contact_person ($company_name)");
            
            header("Location: ?page=leads&success=1");
            exit;
        } catch (Exception $e) {
            $error = "Failed to add lead: " . $e->getMessage();
        }
    } else {
        $error = "Name and Email are required.";
    }
}
?>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">Lead synced to Master CRM successfully!</div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>


<header class="page-header">
    <h1>Lead Management</h1>
    <p>Track and manage your B2B prospects synced with the Master CRM.</p>
</header>

<div class="dashboard-grid">
    <!-- NEW LEAD FORM -->
    <section class="card">
        <h2>Capture New Lead</h2>
        <form action="?page=leads&action=add" method="POST" class="standard-form">
            <div class="form-group">
                <label for="name">Contact Name</label>
                <input type="text" name="name" id="name" required placeholder="e.g. John Doe">
            </div>
            <div class="form-group">
                <label for="company">Company</label>
                <input type="text" name="company" id="company" placeholder="e.g. Acme Corp">
            </div>
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" name="email" id="email" required placeholder="john@example.com">
            </div>
            <div class="form-group">
                <label for="source">Lead Source</label>
                <select name="source" id="source">
                    <option value="Marketing Hub">Marketing Hub</option>
                    <option value="LinkedIn">LinkedIn</option>
                    <option value="Email Outreach">Email Outreach</option>
                    <option value="Referral">Referral</option>
                </select>
            </div>
            <button type="submit" class="btn-action">Add to CRM</button>
        </form>
    </section>

    <!-- RECENT LEADS TABLE -->
    <section class="card" style="grid-column: span 2;">
        <h2>Recent Leads (Synced with Master)</h2>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Company</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $leads = $crmDb->query("SELECT * FROM customers WHERE account_status = 'Lead' ORDER BY created_at DESC LIMIT 10")->fetchAll();
                    if (empty($leads)):
                    ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 2rem; color: var(--text-dim);">No leads found in Master CRM.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($leads as $lead): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($lead['contact_person']); ?></strong></td>
                            <td><?php echo htmlspecialchars($lead['company_name'] ?? '—'); ?></td>
                            <td><span class="badge badge-new"><?php echo $lead['account_status']; ?></span></td>
                            <td><?php echo date('M j, Y', strtotime($lead['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>



