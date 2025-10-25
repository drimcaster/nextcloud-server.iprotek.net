<?php
// Path to your Nextcloud config.php
$configPath = __DIR__ . '/config.php';

// Optional: Lock updates (set in your environment, e.g., export LOCK_CF_UPDATE=true)
//if (getenv('LOCK_CF_UPDATE')) {
    //die("Update disabled: keeping existing Nextcloud proxies unchanged.\n");
//}

// Check config exists
if (!file_exists($configPath)) {
    die("Error: config.php not found at $configPath\n");
}


// Fetch Cloudflare IPs
$cf_ipv4 = @file('https://www.cloudflare.com/ips-v4', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$cf_ipv6 = @file('https://www.cloudflare.com/ips-v6', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

if (!$cf_ipv4 || !$cf_ipv6) {
    die("Error: Failed to fetch Cloudflare IPs.\n");
}

// Merge IPv4 + IPv6
$cf_ips = array_merge($cf_ipv4, $cf_ipv6);

// Load existing config safely
$config = include $configPath;

// Compare existing vs new Cloudflare IPs
$currentProxies = $config['trusted_proxies'] ?? [];

sort($currentProxies);
sort($cf_ips);

if ($currentProxies === $cf_ips) {
    echo "No changes detected in Cloudflare IPs. Skipping update.\n";
    exit;
}

// Backup only if an update will occur
copy($configPath, $configPath . '.bak_' . date('Ymd_His'));

// Update trusted_proxies only if changed
$config['trusted_proxies'] = $cf_ips;

// Ensure forwarded_for_headers exist
$config['forwarded_for_headers'] = ['HTTP_X_FORWARDED_FOR', 'HTTP_CF_CONNECTING_IP'];

// Build the return array syntax
$content = "<?php\n\nreturn " . var_export($config, true) . ";\n";

// Write back to config.php
if (file_put_contents($configPath, $content) === false) {
    die("Error: Failed to write updated config.php\n");
}

echo "Cloudflare IPs updated successfully.\n";