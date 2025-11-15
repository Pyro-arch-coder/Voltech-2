<?php
// Debug endpoint to inspect session, cookies and request headers.
// Use only temporarily for debugging on the hosted environment.
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$out = [
    'success' => true,
    'server_time' => date('c'),
    'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null,
    'session_id' => session_id(),
    'cookies' => $_COOKIE,
    'session' => $_SESSION,
    'headers' => function_exists('getallheaders') ? getallheaders() : null,
];

// Log for server-side inspection
error_log('[debug_session] ' . json_encode([ 'session_id' => $out['session_id'], 'cookies' => $_COOKIE, 'remote' => $out['remote_addr'] ]));

echo json_encode($out);
exit;
