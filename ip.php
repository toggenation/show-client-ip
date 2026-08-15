<?php

// Security: Disable error display to prevent information leakage
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Load configuration
require 'config.php';

// Validate configuration
if (!isset($acceptedOrigins) || !is_array($acceptedOrigins)) {
	http_response_code(500);
	exit("Server configuration error");
}

// Set secure response headers
header('Content-Type: text/plain; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

/**
 * Safely get and validate $_SERVER variables
 */
function getServerVar($key, $default = '', $allowedPattern = null)
{
	if (!isset($_SERVER[$key])) {
		return $default;
	}
	$value = $_SERVER[$key];
	if ($allowedPattern && !preg_match($allowedPattern, $value)) {
		return $default;
	}
	return $value;
}

/**
 * Validate IP address format
 */
function isValidIp($ip)
{
	return filter_var($ip, FILTER_VALIDATE_IP) !== false;
}

/**
 * Get IP version (4 or 6)
 */
function getIpVersion($ip)
{
	if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
		return 4;
	}
	if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
		return 6;
	}
	return null;
}

// Get and validate scheme (HTTP or HTTPS)
$scheme = strtolower(getServerVar('REQUEST_SCHEME', 'http', '/^https?$/i'));
if (!in_array($scheme, ['http', 'https'], true)) {
	$scheme = 'http';
}

// Get and validate host (prevents header injection)
$host = getServerVar('HTTP_HOST', getServerVar('SERVER_NAME', 'localhost'));
// Only allow alphanumeric, dots, hyphens, and brackets for IPv6
if (!preg_match('/^([a-z0-9\-.]+|\[[a-f0-9:]+\])(?::\d{1,5})?$/i', $host)) {
	$host = 'localhost';
}

// Get and validate script name
$script = getServerVar('SCRIPT_NAME', '/ip.php');
// Prevent directory traversal
if (strpos($script, '..') !== false || strpos($script, "\0") !== false) {
	$script = '/ip.php';
}

// Handle CORS with proper origin validation
$origin = getServerVar('HTTP_ORIGIN', null);

if ($origin !== null && $origin !== '') {
	// Validate origin format using URL parser
	$parsed = parse_url($origin);
	if ($parsed && isset($parsed['scheme'], $parsed['host'])) {
		// Reconstruct origin to prevent injection attacks
		$validOrigin = $parsed['scheme'] . '://' . $parsed['host'];
		if (isset($parsed['port'])) {
			$validOrigin .= ':' . (int)$parsed['port'];
		}

		// Check against whitelist using strict comparison
		if (in_array($validOrigin, $acceptedOrigins, true)) {
			header("Access-Control-Allow-Origin: " . $validOrigin);
		}
	}
}

// Determine client IP address
// Priority: Direct connection > Cloudflare > X-Forwarded-For (if trusted)
$ip = null;

// Try Cloudflare header (most reliable if behind CF)
if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
	$cfIp = $_SERVER['HTTP_CF_CONNECTING_IP'];
	if (isValidIp($cfIp)) {
		$ip = $cfIp;
	}
}

// Fall back to X-Forwarded-For (can be spoofed, but useful for proxies)
if (!$ip && isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
	// Parse comma-separated list and take the last (most recent) valid IP
	$forwarded = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
	// Validate each IP in the chain
	foreach (array_reverse($forwarded) as $forwardedIp) {
		if (isValidIp($forwardedIp)) {
			$ip = $forwardedIp;
			break;
		}
	}
}

// Fall back to direct connection (most reliable)
if (!$ip && isset($_SERVER['REMOTE_ADDR'])) {
	if (isValidIp($_SERVER['REMOTE_ADDR'])) {
		$ip = $_SERVER['REMOTE_ADDR'];
	}
}

// If no valid IP found, return error
if (!$ip) {
	http_response_code(400);
	exit("Error: Could not determine a valid client IP address");
}

// Get and display IP version
try {
	$type = getIpVersion($ip);
	if ($type === null) {
		http_response_code(400);
		exit("Error: Invalid IP address detected");
	}

	echo "Type: IPv{$type}: " . $ip;
} catch (Exception $e) {
	http_response_code(500);
	exit("Error: Internal server error");
}
