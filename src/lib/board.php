<?php
declare(strict_types=1);

final class RequestError extends RuntimeException {}

function fail(int $status, string $message): never
{
    throw new RequestError($message, $status);
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function textField(array $input, string $key, int $maxChars, int $maxBytes = 65535): string
{
    $value = $input[$key] ?? null;
    if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
        fail(422, '입력값 형식이 올바르지 않습니다.');
    }
    $value = trim($value);
    if ($value === '' || mb_strlen($value, 'UTF-8') > $maxChars || strlen($value) > $maxBytes) {
        fail(422, '필수 입력값과 최대 길이를 확인하세요.');
    }
    return $value;
}

function csrfToken(): string
{
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void
{
    $token = $_POST['csrf_token'] ?? null;
    if (!is_string($token) || !hash_equals(csrfToken(), $token)) {
        fail(403, '요청이 만료되었거나 올바르지 않습니다. 페이지를 새로고침하세요.');
    }
}

function currentUser(PDO $pdo): ?array
{
    if (!isset($_SESSION['user_id'])) return null;
    $stmt = $pdo->prepare('SELECT id, username FROM board_users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user) unset($_SESSION['user_id']);
    return $user ?: null;
}

function requireUser(PDO $pdo): array
{
    return currentUser($pdo) ?? fail(401, '로그인이 필요합니다.');
}

function postId(mixed $value): int
{
    if (!is_string($value) || !ctype_digit($value) || (int)$value < 1 || (int)$value > 2147483647) {
        fail(422, '게시글 번호가 올바르지 않습니다.');
    }
    return (int)$value;
}

function findPost(PDO $pdo, int $id, bool $lock = false): array
{
    $stmt = $pdo->prepare('SELECT * FROM php_board WHERE id = ?' . ($lock ? ' FOR UPDATE' : ''));
    $stmt->execute([$id]);
    return $stmt->fetch() ?: fail(404, '존재하지 않는 게시글입니다.');
}

function requireOwner(array $post, array $user): void
{
    if ($post['user_id'] === null || (int)$post['user_id'] !== (int)$user['id']) {
        fail(403, '작성자만 수정·삭제할 수 있습니다.');
    }
}

function saveUpload(?array $file): ?array
{
    if ($file === null) return null;
    if (!isset($file['error']) || !is_int($file['error'])) fail(422, '잘못된 파일 업로드입니다.');
    if ($file['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($file['error'] !== UPLOAD_ERR_OK) fail(422, '파일 업로드에 실패했습니다. 최대 2MB까지 가능합니다.');
    if (!is_string($file['name'] ?? null) || !is_string($file['tmp_name'] ?? null)
        || !is_uploaded_file($file['tmp_name'])) fail(422, '잘못된 파일 업로드입니다.');
    $name = basename(str_replace('\\', '/', $file['name']));
    if ($name === '' || strlen($name) > 255 || !mb_check_encoding($name, 'UTF-8')) fail(422, '파일 이름이 올바르지 않습니다.');
    $size = filesize($file['tmp_name']);
    if ($size === false || $size > 2 * 1024 * 1024) fail(422, '파일은 최대 2MB까지 가능합니다.');
    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $allowed = ['jpg'=>'image/jpeg', 'jpeg'=>'image/jpeg', 'png'=>'image/png', 'gif'=>'image/gif',
        'webp'=>'image/webp', 'pdf'=>'application/pdf', 'txt'=>'text/plain'];
    if (!isset($allowed[$extension])) fail(422, '이미지, PDF, TXT 파일만 첨부할 수 있습니다.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if ($mime !== $allowed[$extension]) fail(422, '파일 확장자와 실제 형식이 일치하지 않습니다.');
    if (str_starts_with($mime, 'image/') && @getimagesize($file['tmp_name']) === false) fail(422, '올바른 이미지 파일이 아닙니다.');
    $directory = dirname(__DIR__) . '/uploads';
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('Cannot create upload directory.');
    }
    $path = 'uploads/' . bin2hex(random_bytes(16)) . '.' . $extension;
    if (!move_uploaded_file($file['tmp_name'], dirname(__DIR__) . '/' . $path)) {
        throw new RuntimeException('Cannot save uploaded file.');
    }
    return ['filename'=>$name, 'filepath'=>$path];
}

function removeAttachment(?string $path): void
{
    // Only files directly inside uploads belong to the attachment subsystem.
    if ($path === null || !preg_match('~\Auploads/[a-f0-9-]+\.[a-z0-9]+\z~iD', $path)) return;
    $absolute = dirname(__DIR__) . '/' . $path;
    if (is_file($absolute) && !@unlink($absolute)) error_log('Attachment cleanup failed.');
}

function mutatePost(PDO $pdo, array $user, string $action, ?int $id = null): void
{
    $newFile = null;
    $oldPath = null;
    $pdo->beginTransaction();
    try {
        if ($action !== 'create') {
            $post = findPost($pdo, $id, true);
            requireOwner($post, $user);
            $oldPath = $post['filepath'];
        }
        if ($action === 'delete') {
            $stmt = $pdo->prepare('DELETE FROM php_board WHERE id = ? AND user_id = ?');
            $stmt->execute([$id, $user['id']]);
        } else {
            $title = textField($_POST, 'title', 100);
            $content = textField($_POST, 'content', 65535);
            // Validate all text and permissions before changing any attachment.
            $file = $_FILES['attached_file'] ?? null;
            if ($file !== null && !is_array($file)) fail(422, '잘못된 파일 업로드입니다.');
            $newFile = saveUpload($file);
            $filename = $newFile['filename'] ?? ($post['filename'] ?? null);
            $filepath = $newFile['filepath'] ?? ($post['filepath'] ?? null);
            if ($action === 'create') {
                $stmt = $pdo->prepare('INSERT INTO php_board (title, writer, content, filename, filepath, user_id) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([$title, $user['username'], $content, $filename, $filepath, $user['id']]);
            } else {
                $stmt = $pdo->prepare('UPDATE php_board SET title = ?, content = ?, filename = ?, filepath = ? WHERE id = ? AND user_id = ?');
                $stmt->execute([$title, $content, $filename, $filepath, $id, $user['id']]);
            }
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        removeAttachment($newFile['filepath'] ?? null);
        throw $error;
    }
    // A failed DB update preserves the old file; cleanup happens after commit.
    if ($action === 'delete' || $newFile !== null) removeAttachment($oldPath);
}
