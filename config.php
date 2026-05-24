<?php

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'books_cms');


define('SITE_NAME', 'Education System');
define('SITE_URL',  'http://localhost/cmslibrary');
define('ADMIN_SECURE_CODE', 'educationadmincms');


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    http_response_code(500);
    die(
        '<div style="font-family:sans-serif;max-width:520px;margin:60px auto;
                     background:#fee2e2;border:1px solid #f87171;color:#991b1b;
                     padding:24px 28px;border-radius:10px;line-height:1.6;">
            <strong style="font-size:16px;">⚠ Database Connection Failed</strong><br><br>
            <code>' . mysqli_connect_error() . '</code><br><br>
            Check the credentials in <em>config.php</em> and make sure
            the MySQL server is running.
        </div>'
    );
}

mysqli_set_charset($conn, 'utf8mb4');



/**
 * Sanitize a string for safe use in queries and output.
 *
 * @param  mysqli $conn  Active connection
 * @param  string $data  Raw user input
 * @return string        Escaped, stripped string
 */
function clean($conn, $data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return mysqli_real_escape_string($conn, $data);
}

/**
 * Redirect to another page and stop execution.
 *
 * @param  string $url  Relative or absolute URL
 */
function redirect($url) {
    header('Location: ' . $url);
    exit();
}


function requireLogin() {
    if (empty($_SESSION['admin_id'])) {
        redirect('login.php');
    }
}

/**
 * Store a one-time flash message in the session.
 *
 * @param  string $type     success | error | warning | info
 * @param  string $message  Human-readable message
 */
function setFlash($type, $message) {
    $_SESSION['flash'] = [
        'type'    => $type,
        'message' => $message,
    ];
}


function showFlash() {
    if (empty($_SESSION['flash'])) {
        return;
    }

    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);

    $palette = [
        'success' => ['bg' => '#dcfce7', 'border' => '#86efac', 'text' => '#166534', 'icon' => '✓'],
        'error'   => ['bg' => '#fee2e2', 'border' => '#fca5a5', 'text' => '#991b1b', 'icon' => '✕'],
        'warning' => ['bg' => '#fef9c3', 'border' => '#fde047', 'text' => '#854d0e', 'icon' => '⚠'],
        'info'    => ['bg' => '#dbeafe', 'border' => '#93c5fd', 'text' => '#1e40af', 'icon' => 'ℹ'],
    ];

    $c = $palette[$f['type']] ?? $palette['info'];

    printf(
        '<div style="background:%s;border:1px solid %s;color:%s;
                     padding:12px 16px;border-radius:8px;margin-bottom:20px;
                     display:flex;align-items:center;gap:10px;font-size:14px;">
            <span style="font-weight:700;font-size:16px;">%s</span>
            <span>%s</span>
        </div>',
        $c['bg'], $c['border'], $c['text'],
        $c['icon'],
        htmlspecialchars($f['message'])
    );
}
