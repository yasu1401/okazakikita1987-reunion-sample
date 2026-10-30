<?php
declare(strict_types=1);
// CLI only. This is a dated upstream-version check, not a vulnerability scanner.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
function reunionRuntimeFloor(string $version): ?bool {
    $floors = ['8.2'=>'8.2.35', '8.3'=>'8.3.36', '8.4'=>'8.4.27', '8.5'=>'8.5.12'];
    $branch = implode('.', array_slice(explode('.', $version), 0, 2));
    if (!isset($floors[$branch]) || preg_match('/(?:alpha|beta|RC|dev)/i', $version)) return null;
    return version_compare($version, $floors[$branch], '>=');
}
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== __FILE__) return;
$required = ['PDO','session','filter','fileinfo','mbstring','gd','exif'];
$missing = array_values(array_filter($required, fn($name)=>!extension_loaded($name)));
$drivers = class_exists('PDO') ? PDO::getAvailableDrivers() : [];
$patched = reunionRuntimeFloor(PHP_VERSION);
echo json_encode([
    'checked_policy_date'=>'2026-10-30', 'php_version'=>PHP_VERSION, 'sapi'=>PHP_SAPI,
    'target_cves'=>['CVE-2026-109618','CVE-2026-109619'],
    'upstream_version_floor'=>$patched === true ? 'meets' : ($patched === false ? 'below' : 'unreviewed'),
    'missing_extensions'=>$missing, 'pdo_drivers'=>$drivers,
    'session_upload_progress'=>ini_get('session.upload_progress.enabled'),
    'session_serialize_handler'=>ini_get('session.serialize_handler'),
    'note'=>'CLIのみの確認。Web側のPHPと設定、ベンダーのバックポート、今後のCVEは別途確認してください。',
], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), PHP_EOL;
exit($patched === true && !$missing && (in_array('sqlite',$drivers,true)||in_array('mysql',$drivers,true)) ? 0 : 1);
