# PHP 게시판

## 로컬 실행

PC의 MySQL `board` 데이터베이스를 사용합니다. 컨테이너에서 PC에 접속할 때는 `host.docker.internal`을 사용합니다.

1. 프로젝트 루트의 `.env`에 DB 접속 정보를 설정합니다. 기존 `.env`가 있으면 그대로 사용하고, 없을 때만 `.env.example`을 복사합니다. Compose는 PHP 컨테이너의 DB 설정을 이 파일에서 직접 읽습니다. `.env`와 업로드 파일, 로컬 백업은 Git에서 제외됩니다.
2. 새 DB에는 `database/schema.sql`을 적용합니다. 기존 DB에는 먼저 백업한 뒤 `database/migrate.php`를 DB 관리자 계정으로 CLI에서 실행합니다. 이 스크립트는 `board_users` 테이블과 nullable `php_board.user_id` 열을 추가하며 게시글을 삭제하지 않습니다. CLI에는 `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` 환경변수가 필요합니다.
3. 앱 계정에는 `board_users`의 SELECT·INSERT와 `php_board`의 SELECT·INSERT·UPDATE·DELETE만 부여합니다. DB 관리자 계정은 앱 실행에 사용하지 않습니다.
4. `docker compose up --build -d`를 실행하고 <http://localhost:8082>에 접속합니다.

회원가입 후 로그인하면 글을 작성할 수 있습니다. 작성자 이름은 로그인 계정에서 결정하며 작성자만 수정·삭제할 수 있습니다. 비밀번호는 해시로 저장됩니다. 로그인은 HTTPS 환경에서 운영하세요.

기존 게시글은 소유자를 추측해 연결하지 않습니다. 기존 글을 특정 계정에 연결하려면 관리자가 작성자를 확인한 뒤 해당 글의 `user_id`를 지정하세요. 승인된 로컬 마이그레이션 전 데이터는 `.local-backups/board-before-auth.json`에 보존됩니다.

이미지(JPG·PNG·GIF·WebP), PDF, TXT만 최대 2MB까지 첨부할 수 있습니다. 파일 교체 실패 시 기존 첨부파일을 보존하며, 업로드 폴더에서는 PHP가 실행되지 않습니다.

현재 포트는 로컬 PC에서만 접속할 수 있게 바인딩되어 있습니다. 서버 배포 시에는 HTTPS 프록시와 운영 DB 주소를 설정하세요. 코드에 있던 비밀번호는 과거 Git 커밋에 남아 있으므로 공유된 자격증명은 DB 관리자가 변경해야 합니다.

## 검증

Docker와 Python이 설치된 PowerShell에서 실행합니다.

```powershell
python tests/run.py
```

PC MySQL과 분리된 Docker MySQL DB와 `18082` 포트를 사용합니다. 테스트 DB는 메모리에 생성되며 종료 시 제거됩니다. 테스트 업로드는 `.local-tests/uploads`에만 저장됩니다.

`php-board/`는 별도 Git 저장소이므로 현재 프로젝트에서 제외했습니다. 실제 앱 코드를 커밋·push할 때는 이 README가 있는 프로젝트 루트에서 실행하세요.
