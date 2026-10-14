<?php
    require_once 'config/configdb.php';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
        $title = trim($_POST['title']);
        $writer = trim($_POST['writer']);
        $content = trim($_POST['content']);

        $filename = null;
        $filepath = null;

        if(isset($_FILES['attached_file']) && $_FILES['attached_file']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['attached_file']['tmp_name'];
            $originalName = $_FILES['attached_file']['name'];
            $fileExtension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

            $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );

            $newFileName = $uuid . '.' . $fileExtension;
            $uploadFileDir = __DIR__ . '/uploads/';
            $dest_path = $uploadFileDir . $newFileName;

            if (!is_dir($uploadFileDir)) {
                mkdir($uploadFileDir, 0777, true);
            }

            if(move_uploaded_file($fileTmpPath, $dest_path)) {
                $filename = $originalName;
                $filepath = 'uploads/' . $newFileName;
            }
        }

        if ($title && $writer && $content) {
            $stmt = $pdo->prepare("INSERT INTO php_board (title, writer, content, filename, filepath) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$title, $writer, $content, $filename, $filepath]);
            header("Location: index.php");
            exit;
        }
    }

    if (isset($_GET['delete'])) {
        $id = (int)$_GET['delete'];

        $stmt = $pdo->prepare("SELECT filepath FROM php_board WHERE id = ?");
        $stmt->execute([$id]);
        $post = $stmt->fetch();

        if ($post && $post['filepath'] && file_exists(__DIR__ . '/' . $post['filepath'])) {
            unlink(__DIR__ . '/' . $post['filepath']); 
        }

        $stmt = $pdo->prepare("DELETE FROM php_board WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: index.php");
        exit;
    }

    $stmt = $pdo->query("SELECT * FROM php_board ORDER BY id DESC");
    $posts = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
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
        </header>

        <div class="main-grid">
            <div class="card">
                <div class="card-title">작성하기</div>
                <form action="index.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="create">
                    <div class="form-group">
                        <label>작성자</label>
                        <input type="text" name="writer" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label>제목</label>
                        <input type="text" name="title" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label>내용</label>
                        <textarea name="content" rows="4" required class="form-control"></textarea>
                    </div>
                    <div class="form-group">
                        <label>첨부파일</label>
                        <input type="file" name="attached_file" class="form-control">
                    </div>
                    <button type="submit" class="btn">등록하기</button>
                </form>
            </div>

            <div>
                <div class="post-list-title">게시글 (<?= count($posts) ?>)</div>
                
                <?php if (empty($posts)): ?>
                    <div class="empty-state">등록된 게시글이 없습니다.</div>
                <?php else: ?>
                    <?php foreach ($posts as $post): ?>
                        <div class="post-card">
                            <div class="post-header">
                                <div class="post-title"><?= htmlspecialchars($post['title']) ?></div>
                                <div class="post-writer"><?= htmlspecialchars($post['writer']) ?></div>
                            </div>
                            <div class="post-body"><?= htmlspecialchars($post['content']) ?></div>
                            
                            <?php if ($post['filename'] && $post['filepath']): ?>
                                <div class="file-box">
                                    첨부파일: <a href="<?= htmlspecialchars($post['filepath']) ?>" download="<?= htmlspecialchars($post['filename']) ?>" class="file-link">
                                        <?= htmlspecialchars($post['filename']) ?>
                                    </a>
                                </div>
                            <?php endif; ?>

                            <div class="post-footer">
                                <div><?= $post['created_at'] ?></div>
                                <div class="post-actions">
                                    <a href="updateboard.php?id=<?= $post['id'] ?>" class="action-edit">수정</a>
                                    <a href="index.php?delete=<?= $post['id'] ?>" onclick="return confirm('삭제하시겠습니까?')" class="action-delete">삭제</a>
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