<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/board.php';
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
set_exception_handler(function (Throwable $error): void {
    $status = $error instanceof RequestError ? $error->getCode() : 500;
    if (!($error instanceof RequestError)) error_log((string)$error);
    http_response_code($status);
    header('Content-Type: text/html; charset=UTF-8');
    $message = $error instanceof RequestError ? $error->getMessage() : '서버 처리에 실패했습니다. 잠시 후 다시 시도하세요.';
    echo '<!doctype html><html lang="ko"><meta charset="UTF-8"><title>요청 오류</title><p>' . e($message) . '</p><a href="/">게시판으로 돌아가기</a></html>';
});
header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_set_cookie_params(['httponly'=>true, 'samesite'=>'Lax', 'path'=>'/',
    'secure'=>isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
session_start();
if (!isset($_SESSION['csrf_token'])) {
    session_regenerate_id(true);
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') verifyCsrf();
