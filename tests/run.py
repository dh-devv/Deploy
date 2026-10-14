"""Run the HTTP suite with a disposable Docker database, without changing PC MySQL."""
import os
from pathlib import Path
import secrets
import subprocess
import sys
import time
import urllib.error
import urllib.request

ROOT = Path(__file__).resolve().parent.parent
COMPOSE = ['docker', 'compose', '--env-file', '.env.test', '-f', 'compose.test.yaml', '-p', 'board-check']


def main():
    os.chdir(ROOT)
    (ROOT / '.local-tests' / 'uploads').mkdir(parents=True, exist_ok=True)
    (ROOT / '.env.test').write_text(
        f'DB_PASSWORD={secrets.token_hex(24)}\nTEST_ROOT_PASSWORD={secrets.token_hex(24)}\n', encoding='utf-8')
    try:
        subprocess.run(COMPOSE + ['down'], check=True)
        subprocess.run(COMPOSE + ['up', '--build', '-d'], check=True)
        for attempt in range(40):
            try:
                with urllib.request.urlopen('http://127.0.0.1:18082/auth.php', timeout=2) as response:
                    if response.status == 200:
                        break
            except (urllib.error.URLError, TimeoutError):
                time.sleep(0.25)
        else:
            raise RuntimeError('Test app failed to become ready.')
        env = dict(os.environ, BOARD_TEST_URL='http://127.0.0.1:18082')
        return subprocess.run([sys.executable, '-m', 'unittest', 'discover', '-s', 'tests', '-v'], env=env).returncode
    finally:
        subprocess.run(COMPOSE + ['down'], check=True)


if __name__ == '__main__':
    sys.exit(main())
