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

기본 포트는 로컬 PC에서만 접속할 수 있게 바인딩되어 있습니다. `HTTP_BIND`로 접속 인터페이스를 지정할 수 있습니다. 서버 배포 시에는 HTTPS 프록시와 운영 DB 주소를 설정하세요. 코드에 있던 비밀번호는 과거 Git 커밋에 남아 있으므로 공유된 자격증명은 DB 관리자가 변경해야 합니다.

## VMware Linux VM에 배포하기

MySQL이 VM의 Linux에 직접 설치되어 있고, PHP·Nginx는 그 VM의 Docker에서 실행되는 구성입니다. Windows PC의 `.env`, MySQL 계정과 DB 변경은 VM으로 자동 복사되지 않습니다.

VM 터미널에서 다음을 실행합니다.

```bash
cd /var/www/html/Deploy
test -f .env || cp .env.example .env
nano .env
chmod 600 .env
```

`.env`에는 VM MySQL에서 사용할 실제 계정과 비밀번호를 입력하고 아래 주소·포트를 설정합니다. 예시의 자리표시자를 실제 값으로 바꿔야 합니다.

```dotenv
DB_HOST=host.docker.internal
DB_PORT=3306
DB_NAME=board
DB_USER=VM_MYSQL_APP_USER
DB_PASSWORD=VM_MYSQL_APP_PASSWORD
HTTP_BIND=0.0.0.0
HTTP_PORT=8082
```

`host.docker.internal`은 Compose의 `host-gateway` 설정을 통해 **VM의 Docker 호스트**를 가리킵니다. 컨테이너 안의 `127.0.0.1`은 PHP 컨테이너 자신이므로 VM MySQL의 주소로 사용할 수 없습니다.

MySQL이 VM의 `127.0.0.1:3306`에서만 수신하면 Docker에서 연결할 수 없습니다. VM에서 `sudo ss -lntp '( sport = :3306 )'`로 확인하고, MySQL이 Docker 호스트 게이트웨이 주소에서도 수신하도록 설정해야 합니다. 앱 계정에도 Docker 네트워크에서 접속할 권한이 필요합니다. 3306 포트는 Docker 내부에서 접근할 수 있게 제한하세요.

VM의 기존 `board` 데이터베이스를 백업한 다음 로그인 스키마를 별도로 마이그레이션해야 합니다. 로컬 Windows에서 수행한 마이그레이션은 VM에 적용되지 않습니다. 위 로컬 실행 절차의 `database/migrate.php` 또는 `database/schema.sql` 설명을 따르세요.

Linux의 업로드 폴더에는 PHP 사용자(UID 33)의 쓰기 권한과 Nginx 사용자의 읽기 권한이 필요합니다. 다음 명령은 업로드 폴더에만 적용합니다.

```bash
sudo mkdir -p src/uploads
sudo chown -R 33:33 src/uploads
sudo find src/uploads -type d -exec chmod 755 {} +
sudo find src/uploads -type f -exec chmod 644 {} +
docker compose up --build -d
hostname -I
```

Windows 브라우저에서는 `http://VM의_IP:8082`로 접속합니다. VMware가 NAT 방식이면 같은 PC에서는 VM IP로 접속하고, 다른 기기에서 접속할 때는 VMware 포트 전달 또는 브리지 네트워크 설정이 필요합니다. VM 방화벽에서도 필요한 접속 범위에 8082 포트를 허용하세요.

## 검증

Docker와 Python이 설치된 PowerShell에서 실행합니다.

```powershell
python tests/run.py
```

PC·VM MySQL과 분리된 Docker MySQL DB와 `18082` 포트를 사용합니다. 테스트 DB는 메모리에 생성되며 종료 시 제거됩니다. 테스트 업로드는 Linux 권한을 검증하기 위해 Docker 전용 볼륨에 저장되고 종료 시 제거됩니다.

`php-board/`는 별도 Git 저장소이므로 현재 프로젝트에서 제외했습니다. 실제 앱 코드를 커밋·push할 때는 이 README가 있는 프로젝트 루트에서 실행하세요.
