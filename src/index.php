<?php
require_once __DIR__ . '/config/bootstrap.php';
if (isset($_GET['delete'])) {
    header('Allow: POST');
    fail(405, '삭제는 POST 요청으로만 가능합니다.');
}
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) fail(405, '지원하지 않는 요청입니다.');
require_once __DIR__ . '/config/configdb.php';
$user = currentUser($pdo);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!in_array($action, ['create', 'delete'], true)) fail(422, '잘못된 요청입니다.');
    $user = requireUser($pdo);
    $id = $action === 'delete' ? postId($_POST['id'] ?? null) : null;
    mutatePost($pdo, $user, $action, $id);
    header('Location: index.php', true, 303);
    exit;
}
$posts = $pdo->query('SELECT id, title, writer, content, filename, filepath, user_id, created_at FROM php_board ORDER BY id DESC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>리눅스게시판실습</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Pretendard', sans-serif; }
        body { background-color: #f8fafc; color: #334155; line-height: 1.6; padding: 50px 20px; }
        .container { max-width: 1000px; margin: 0 auto; }
        header { text-align: center; margin-bottom: 40px; }
        header h1 { font-size: 2.2rem; color: #0f172a; margin-bottom: 8px; font-weight: 800; }
        header p { color: #64748b; font-size: 0.95rem; }
        .main-grid { display: grid; grid-template-columns: 1fr; gap: 30px; }
        @media (min-width: 768px) { .main-grid { grid-template-columns: 350px 1fr; } }
        .card { background: #fff; padding: 25px; border-radius: 16px; border: 1px solid #e2e8f0; }
        .card-title { font-size: 1.2rem; font-weight: 700; color: #0f172a; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 6px; }
        .form-control { width: 100%; padding: 10px 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.9rem; outline: none; }
        .form-control:focus { border-color: #4f46e5; background: #fff; }
        .btn { display: inline-block; width: 100%; padding: 12px; background: #4f46e5; color: #fff; font-weight: 600; border: none; border-radius: 8px; cursor: pointer; text-align: center; font-size: 0.9rem; text-decoration: none; }
        .btn:hover { background: #4338ca; }
        .post-list-title { font-size: 1.2rem; font-weight: 700; color: #0f172a; margin-bottom: 20px; }
        .empty-state { text-align: center; padding: 50px 20px; border: 2px dashed #cbd5e1; border-radius: 16px; color: #94a3b8; }
        .post-card { background: #fff; padding: 25px; border-radius: 16px; border: 1px solid #e2e8f0; margin-bottom: 20px; }
        .post-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
        .post-title { font-size: 1.15rem; font-weight: 700; color: #0f172a; }
        .post-writer { font-size: 0.75rem; background: #f1f5f9; color: #475569; padding: 4px 8px; border-radius: 6px; }
        .post-body { color: #475569; font-size: 0.95rem; white-space: pre-line; margin-bottom: 15px; }
        
        .file-box { background: #f8fafc; padding: 10px 15px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 15px; font-size: 0.85rem; }
        .file-link { color: #4f46e5; text-decoration: none; font-weight: 600; }
        .file-link:hover { text-decoration: underline; }

        .post-footer { display: flex; justify-content: space-between; align-items: center; padding-top: 15px; border-top: 1px solid #f1f5f9; font-size: 0.75rem; color: #94a3b8; }
        .post-actions a { margin-left: 10px; font-weight: 600; text-decoration: none; }
        .action-edit { color: #64748b; } .action-edit:hover { color: #4f46e5; }
        .action-delete { color: #f43f5e; } .action-delete:hover { color: #be123c; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>자유게시판</h1>
            <?php if ($user): ?>
                <p><?= e($user['username']) ?>님</p>
                <form method="POST" action="auth.php" style="margin-top:12px">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="action" value="logout">
                    <button type="submit">로그아웃</button>
                </form>
            <?php else: ?>
                <p><a href="auth.php">로그인</a> · <a href="auth.php?mode=register">회원가입</a></p>
            <?php endif; ?>
        </header>

        <div class="main-grid">
            <div class="card">
                <div class="card-title">작성하기</div>
                <?php if ($user): ?><form action="index.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="create"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <div class="form-group">
                        <label>작성자</label>
                        <input type="text" name="writer" value="<?= e($user['username']) ?>" readonly class="form-control">
                    </div>
                    <div class="form-group">
                        <label>제목</label>
                        <input type="text" name="title" maxlength="100" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label>내용</label>
                        <textarea name="content" rows="4" required class="form-control"></textarea>
                    </div>
                    <div class="form-group">
                        <label>첨부파일</label>
                        <input type="file" name="attached_file" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.txt" class="form-control"><small>이미지·PDF·TXT, 최대 2MB</small>
                    </div>
                    <button type="submit" class="btn">등록하기</button>
                </form><?php else: ?><p>글을 작성하려면 <a href="auth.php">로그인</a>하세요.</p><?php endif; ?>
            </div>

            <div>
                <div class="post-list-title">게시글 (<?= count($posts) ?>)</div>
                
                <?php if (empty($posts)): ?>
                    <div class="empty-state">등록된 게시글이 없습니다.</div>
                <?php else: ?>
                    <?php foreach ($posts as $post): ?>
                        <div class="post-card">
                            <div class="post-header">
                                <div class="post-title"><?= e($post['title']) ?></div>
                                <div class="post-writer"><?= e($post['writer']) ?></div>
                            </div>
                            <div class="post-body"><?= e($post['content']) ?></div>
                            
                            <?php if ($post['filename'] && $post['filepath']): ?>
                                <div class="file-box">
                                    첨부파일: <a href="<?= e($post['filepath']) ?>" download="<?= e($post['filename']) ?>" class="file-link">
                                        <?= e($post['filename']) ?>
                                    </a>
                                </div>
                            <?php endif; ?>

                            <div class="post-footer">
                                <div><?= $post['created_at'] ?></div>
                                <div class="post-actions">
                                    <?php if ($user && $post['user_id'] !== null && (int)$post['user_id'] === (int)$user['id']): ?>
                                    <a href="updateboard.php?id=<?= (int)$post['id'] ?>" class="action-edit">수정</a>
                                    <form method="POST" action="index.php" style="display:inline" onsubmit="return confirm('삭제하시겠습니까?')">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int)$post['id'] ?>">
                                        <button type="submit" class="action-delete">삭제</button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>