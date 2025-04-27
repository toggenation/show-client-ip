<?php

require 'config.php';

$scheme = $_SERVER['REQUEST_SCHEME'];

$host = $_SERVER['HTTP_HOST'];

$script = $_SERVER['SCRIPT_NAME'];

$scriptName = $scheme . "://" . $host . $script;

$msg = [ "This is a script that echos back your client IP address.",
"use curl -4 $scriptName for your IPv4 address",
"use curl -6 $scriptName for your IPv6 address"];

header('Content-type: text/plain');
foreach($msg as $key => $m) {
	header("X-Toggen-About-$key: $m");
}

$origin = $_SERVER['HTTP_ORIGIN'] ?? null;

if (in_array($origin, $acceptedOrigins)) {
	header("Access-Control-Allow-Origin: $origin");
}

if(isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
	$ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
} elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
	$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
} else {
	$ip = $_SERVER['REMOTE_ADDR'];
}

# echo implode("\n", $msg) . str_repeat( PHP_EOL, 3 );

function getIpVersion($ip) {
	if(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false ) {
		return 4;
	}

	if(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false ) {
		return 6;
	}

	throw new Exception("Invalid IP Address");
}

$type = getIpVersion($ip);

echo "Type: IPv{$type}" . PHP_EOL . PHP_EOL;

echo $ip;



# var_dump($_SERVER);


