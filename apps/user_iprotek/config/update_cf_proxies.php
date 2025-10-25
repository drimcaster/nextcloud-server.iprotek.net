<?php

$nextCloudDir = __DIR__ . '/../../../';
// Path to your Nextcloud config.php
$configPath = $nextCloudDir. 'config/config.php';

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

// If config.php uses `$CONFIG = array(...)`, normalize to array
if (isset($CONFIG) && is_array($CONFIG)) {
    $config = $CONFIG;
}

if(!isset($config)){
    die("Error: Config not set.\n");
}

// Compare existing vs new Cloudflare IPs
$currentProxies = $config['trusted_proxies'] ?? [];

// --- Add your custom proxy IPs here ---
$customProxies = [
    '127.0.0.1',            // Example: local OLS or Nginx reverse proxy
    '::1',
    //'192.168.1.10',         // Example: internal proxy or LAN proxy
    // Add more if needed
];

// Merge custom + Cloudflare proxies
$newProxies = array_unique(array_merge($customProxies, $cf_ips));

// Sort for consistency
sort($currentProxies);
sort($newProxies);

if ($currentProxies === $newProxies) {
    echo "No changes detected in Cloudflare IPs. Skipping update.\n";
    exit;
}

// Backup only if an update will occur
copy($configPath, $configPath . '.bak_' . date('Ymd_His'));

// Update trusted_proxies only if changed
$config['trusted_proxies'] = $newProxies;

// Ensure forwarded_for_headers exist
$config['forwarded_for_headers'] = ['HTTP_X_FORWARDED_FOR', 'HTTP_CF_CONNECTING_IP'];

// Build the return array syntax
$content = "<?php\n".'$CONFIG = ' . var_export($config, true) . ";\n";

// Write back to config.php
if (file_put_contents($configPath, $content) === false) {
    die("Error: Failed to write updated config.php\n");
}

echo "Cloudflare IPs updated successfully.\n";