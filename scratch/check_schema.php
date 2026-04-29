<?php
require_once '../marketing/config.php';
try {
    $db = new PDO("sqlite:" . MASTER_CRM_DB_PATH);
    $result = $db->query("PRAGMA table_info(customers)")->fetchAll(PDO::FETCH_ASSOC);
    print_r($result);
} catch (Exception $e) {
    echo $e->getMessage();
}
?>
