<?php
// Path to your Nextcloud config.php
$configPath = __DIR__ .'/config.php';

if (!file_exists($configPath)) {
    die("Error: config.php not found at $configPath\n");
}
// Backup the config
copy($configPath, $configPath . '.bak_' . date('Ymd_His'));

// Fetch Cloudflare IPs
$cf_ipv4 = file('https://www.cloudflare.com/ips-v4', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$cf_ipv6 = file('https://www.cloudflare.com/ips-v6', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

// Merge lists
$cf_ips = array_merge($cf_ipv4, $cf_ipv6);

// Load existing config safely
$config = include $configPath;

// Update trusted_proxies
$config['trusted_proxies'] = $cf_ips;

// Ensure forwarded_for_headers exist
$config['forwarded_for_headers'] = ['HTTP_X_FORWARDED_FOR','HTTP_CF_CONNECTING_IP'];

// Build the return array syntax
$content = "<?php\n\nreturn " . var_export($config, true) . ";\n";

// Write back to config.php
file_put_contents($configPath, $content);

echo "Cloudflare IPs updated successfully.\n";