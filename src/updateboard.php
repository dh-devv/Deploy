<?php
require_once __DIR__ . '/config/bootstrap.php';
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) fail(405, '지원하지 않는 요청입니다.');
require_once __DIR__ . '/config/configdb.php';
$user = requireUser($pdo);
$id = postId($_GET['id'] ?? null);
$post = findPost($pdo, $id);
requireOwner($post, $user);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mutatePost($pdo, $user, 'update', $id);
    header('Location: index.php', true, 303);
    exit;
}
$isImage = $post['filepath'] && in_array(strtolower(pathinfo($post['filepath'], PATHINFO_EXTENSION)), ['jpg','jpeg','png','gif','webp'], true);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>글 수정하기</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Pretendard', sans-serif; }
        body { background-color: #f8fafc; color: #334155; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 40px 20px; }
        .edit-container { background: #fff; padding: 35px; border-radius: 16px; border: 1px solid #e2e8f0; max-width: 550px; width: 100%; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        h2 { font-size: 1.5rem; font-weight: 800; color: #0f172a; margin-bottom: 6px; }
        .subtitle { color: #94a3b8; font-size: 0.85rem; margin-bottom: 25px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 6px; }
        .form-control { width: 100%; padding: 10px 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.9rem; outline: none; }
        .form-control:focus { border-color: #4f46e5; background: #fff; }
        textarea.form-control { resize: none; }
        
        /* 💡 파일/이미지 미리보기 스타일 영역 */
        .preview-box { background: #f8fafc; padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; margin-top: 8px; }
        .current-file { font-size: 0.8rem; color: #475569; font-weight: 600; display: flex; align-items: center; gap: 5px; }
        .img-preview { max-width: 180px; max-height: 180px; border-radius: 6px; border: 1px solid #cbd5e1; margin-top: 8px; display: block; object-fit: cover; }
        
        .btn-group { display: flex; gap: 10px; margin-top: 25px; }
        .btn { padding: 12px; font-weight: 600; border: none; border-radius: 8px; cursor: pointer; text-align: center; font-size: 0.9rem; text-decoration: none; }
        .btn-submit { background: #4f46e5; color: #fff; flex: 2; }
        .btn-submit:hover { background: #4338ca; }
        .btn-cancel { background: #f1f5f9; color: #475569; flex: 1; }
        .btn-cancel:hover { background: #e2e8f0; }
    </style>
</head>
<body>

    <div class="edit-container">
        <h2>게시글 수정</h2>
        <div class="subtitle">내용과 첨부파일을 실시간으로 확인하고 수정하세요.</div>
        
        <form action="updateboard.php?id=<?= $id ?>" method="POST" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <div class="form-group">
                <label>작성자</label>
                <input type="text" name="writer" readonly value="<?= e($post['writer']) ?>" required class="form-control">
            </div>
            <div class="form-group">
                <label>제목</label>
                <input type="text" name="title" maxlength="100" value="<?= e($post['title']) ?>" required class="form-control">
            </div>
            <div class="form-group">
                <label>내용</label>
                <textarea name="content" rows="5" required class="form-control"><?= e($post['content']) ?></textarea>
            </div>
            
            <div class="form-group">
                <label>첨부파일 교체</label>
                <input type="file" name="attached_file" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.txt" class="form-control"><small>이미지·PDF·TXT, 최대 2MB</small>
                
                <?php if ($post['filename']): ?>
                    <div class="preview-box">
                        <div class="current-file">
                            📎 현재 파일: <?= e($post['filename']) ?>
                        </div>
                        <?php if ($isImage): ?>
                            <img src="<?= e($post['filepath']) ?>" alt="첨부 이미지 미리보기" class="img-preview">
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="btn-group">
                <a href="index.php" class="btn btn-cancel">취소</a>
                <button type="submit" class="btn btn-submit">수정 완료</button>
            </div>
        </form>
    </div>

</body>
</html>