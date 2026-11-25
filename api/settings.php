<?php
/**
 * ADMIN API - system_settings read/write.
 *
 * GET  api/settings.php        -> { ok, settings: {key: value} }
 * POST JSON: { key, value }    -> upsert one setting (admin)
 */
require_once __DIR__ . '/../config/config.php';

Security::apiAuth(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $rows = Database::select('SELECT setting_key, setting_value FROM system_settings');
    $map  = [];
    foreach ($rows as $r) {
        $map[$r['setting_key']] = $r['setting_value'];
    }
    Security::json(['ok' => true, 'settings' => $map]);
}

$b     = Security::jsonBody();
$key   = (string)($b['key'] ?? '');
$value = (string)($b['value'] ?? '');
$allowed = ['office_name', 'daily_reset_time', 'avg_service_min', 'next_queue_poll'];
if ($key === '' || !in_array($key, $allowed, true)) {
    Security::json(['ok' => false, 'error' => 'Unknown or disallowed setting key.'], 422);
}
Database::run(
    'INSERT INTO system_settings (setting_key, setting_value) VALUES (?,?)
     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
    [$key, mb_substr($value, 0, 250)]
);
Security::log('setting_updated', "$key = $value");
Security::json(['ok' => true, 'message' => 'Setting saved.']);
