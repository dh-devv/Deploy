"""HTTP regression tests. Run only against a disposable, empty test database."""
import http.cookiejar
import os
import re
import unittest
import urllib.error
import urllib.parse
import urllib.request
import uuid


BASE = os.environ.get('BOARD_TEST_URL', 'http://127.0.0.1:18082')


class Client:
    def __init__(self):
        self.opener = urllib.request.build_opener(
            urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

    def request(self, path='/', data=None, upload=None):
        headers = {}
        if upload:
            boundary = '----BoardTest' + uuid.uuid4().hex
            parts = []
            for key, value in data.items():
                parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n'.encode())
            name, content, mime = upload
            parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="attached_file"; filename="{name}"\r\nContent-Type: {mime}\r\n\r\n'.encode() + content + b'\r\n')
            parts.append(f'--{boundary}--\r\n'.encode())
            body = b''.join(parts)
            headers['Content-Type'] = f'multipart/form-data; boundary={boundary}'
        else:
            body = urllib.parse.urlencode(data).encode() if data is not None else None
        req = urllib.request.Request(BASE + path, data=body, headers=headers)
        try:
            response = self.opener.open(req, timeout=10)
        except urllib.error.HTTPError as error:
            response = error
        return response.status, response.read().decode('utf-8', errors='replace')

    def token(self):
        status, page = self.request()
        assert status == 200, f'Homepage returned {status}'
        match = re.search(r'name="csrf_token" value="([a-f0-9]+)"', page)
        return match.group(1) if match else ''


class BoardTests(unittest.TestCase):
    def setUp(self):
        self.client = Client()
        self.token = self.client.token()
        self.title = 'regression-' + uuid.uuid4().hex
        self.fields = dict(action='create', title=self.title, writer='tester',
                           content='test content', post_password='test-password-123',
                           csrf_token=self.token)

    def create(self, upload=None):
        status, page = self.client.request('/index.php', self.fields, upload)
        self.assertEqual(status, 200, page[:200])
        self.assertIn(self.title, page)
        ids = [int(value) for value in re.findall(r'updateboard.php\?id=(\d+)', page)]
        self.assertTrue(ids)
        return max(ids), page

    def test_get_cannot_delete(self):
        status, _ = self.client.request('/index.php?delete=0')
        self.assertEqual(status, 405)

    def test_create_requires_csrf(self):
        self.fields.pop('csrf_token')
        self.assertEqual(self.client.request('/index.php', self.fields)[0], 403)

    def test_array_input_is_validation_error(self):
        self.fields.pop('title')
        self.fields['title[]'] = 'array'
        self.assertEqual(self.client.request('/index.php', self.fields)[0], 422)

    def test_title_length_checked(self):
        self.fields['title'] = '가' * 101
        self.assertEqual(self.client.request('/index.php', self.fields)[0], 422)

    def test_empty_fields_rejected(self):
        self.fields['writer'] = '   '
        self.assertEqual(self.client.request('/index.php', self.fields)[0], 422)

    def test_password_required(self):
        self.fields.pop('post_password')
        self.assertEqual(self.client.request('/index.php', self.fields)[0], 422)

    def test_php_upload_rejected(self):
        status, _ = self.client.request('/index.php', self.fields,
                                      ('payload.php', b'<?php echo "unsafe";', 'application/x-php'))
        self.assertEqual(status, 422)

    def test_fake_image_rejected(self):
        status, _ = self.client.request('/index.php', self.fields,
                                      ('fake.png', b'<?php echo "unsafe";', 'image/png'))
        self.assertEqual(status, 422)

    def test_upload_error_not_silently_ignored(self):
        status, _ = self.client.request('/index.php', self.fields,
                                      ('large.txt', b'a' * (2 * 1024 * 1024 + 1), 'text/plain'))
        self.assertEqual(status, 422)

    def test_uploaded_scripts_are_not_executable(self):
        self.assertEqual(self.client.request('/uploads/nonexistent.php')[0], 403)

    def test_configuration_not_public(self):
        self.assertEqual(self.client.request('/config/configdb.php')[0], 403)

    def test_edit_and_delete_require_password(self):
        post_id, _ = self.create()
        other = Client()
        fields = dict(self.fields, csrf_token=other.token(), title='unauthorized', post_password='wrong-password')
        self.assertEqual(other.request(f'/updateboard.php?id={post_id}', fields)[0], 403)
        fields.update(action='delete', id=post_id)
        self.assertEqual(other.request('/index.php', fields)[0], 403)
        status, page = self.client.request()
        self.assertIn(self.title, page)

    def test_edit_then_delete_with_correct_password(self):
        post_id, _ = self.create()
        changed = self.title + '-changed'
        fields = dict(self.fields, title=changed)
        self.assertEqual(self.client.request(f'/updateboard.php?id={post_id}', fields)[0], 200)
        self.assertIn(changed, self.client.request()[1])
        fields.update(action='delete', id=post_id)
        self.assertEqual(self.client.request('/index.php', fields)[0], 200)
        self.assertNotIn(changed, self.client.request()[1])

    def test_failed_replacement_preserves_old_file(self):
        post_id, page = self.create(('old.txt', b'original attachment', 'text/plain'))
        old_path = re.search(r'href="(uploads/[^"]+)"[^>]*download="old.txt"', page).group(1)
        fields = dict(self.fields, title='   ')
        self.assertEqual(self.client.request(f'/updateboard.php?id={post_id}', fields,
                                            ('new.txt', b'replacement', 'text/plain'))[0], 422)
        self.assertEqual(self.client.request('/' + old_path), (200, 'original attachment'))
        self.assertEqual(self.client.request(f'/updateboard.php?id={post_id}', self.fields,
                                            ('replacement.php', b'<?php echo 1;', 'application/x-php'))[0], 422)
        self.assertEqual(self.client.request('/' + old_path)[0], 200)

    def test_successful_replacement_removes_old_file(self):
        post_id, page = self.create(('old.txt', b'original', 'text/plain'))
        old_path = re.search(r'href="(uploads/[^"]+)"[^>]*download="old.txt"', page).group(1)
        self.assertEqual(self.client.request(f'/updateboard.php?id={post_id}', self.fields,
                                            ('new.txt', b'replacement', 'text/plain'))[0], 200)
        self.assertEqual(self.client.request('/' + old_path)[0], 404)

    def test_output_escaped(self):
        self.fields['title'] = '<script>alert(1)</script>'
        status, page = self.client.request('/index.php', self.fields)
        self.assertEqual(status, 200)
        self.assertNotIn('<script>alert(1)</script>', page)
        self.assertIn('&lt;script&gt;alert(1)&lt;/script&gt;', page)

    def test_missing_post_is_404(self):
        self.assertEqual(self.client.request('/updateboard.php?id=2147483647')[0], 404)


if __name__ == '__main__':
    unittest.main(verbosity=2)
