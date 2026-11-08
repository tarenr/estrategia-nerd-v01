"""Offline authentication regressions. Never contact Instagram."""
import io
import json
import sys
import tempfile
import unittest
from pathlib import Path
from types import SimpleNamespace
from unittest.mock import Mock, patch

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import auth_cli


class TestAuthentication(unittest.TestCase):
    def setUp(self):
        tmp_base = Path(__file__).resolve().parents[3] / "storage/instagram-highlights/tests"
        tmp_base.mkdir(parents=True, exist_ok=True)
        self.tmp = tempfile.TemporaryDirectory(dir=tmp_base)
        self.root = Path(self.tmp.name)
        self.client = Mock()
        self.client.login.return_value = True
        self.client.user_id = 123
        self.client.account_info.return_value = SimpleNamespace(pk=123, username="estrategia_nerd")
        self.client.user_info_v1.return_value = SimpleNamespace(pk=123, username="estrategia_nerd")
        self.client.get_settings.return_value = {"cookies": {"sessionid": "fake-session-only"}}
        self.permissions = Mock()

    def tearDown(self):
        self.tmp.cleanup()

    def run_auth(self, password=" pass with spaces ", code="123456"):
        output = io.StringIO()
        with patch.object(auth_cli, "STORAGE_ROOT", self.root), \
             patch.object(auth_cli, "SESSION_PATH", self.root / "auth/session.json"), \
             patch.object(auth_cli, "Client", return_value=self.client, create=True), \
             patch.object(auth_cli, "SingleAttemptClient", return_value=self.client, create=True), \
             patch.object(auth_cli, "restrict_permissions_windows", self.permissions), \
             patch("getpass.getpass", return_value=password), \
             patch("builtins.input", return_value=code), \
             patch("sys.stdout", output):
            try:
                result = auth_cli.main()
            except SystemExit as exc:
                result = exc.code
        return result, output.getvalue()

    def test_password_keeps_spaces_and_session_is_written_after_acl(self):
        def check_before_write(path):
            self.assertFalse((self.root / "auth/session.json").exists())
        self.permissions.side_effect = check_before_write
        status, _ = self.run_auth()
        self.assertEqual(status, 0)
        self.client.login.assert_called_once_with("estrategia_nerd", " pass with spaces ")
        self.permissions.assert_called_once_with(self.root / "auth")
        session = json.loads((self.root / "auth/session.json").read_text(encoding="utf-8"))
        self.assertEqual(session, self.client.get_settings.return_value)
        self.assertNotIn("password", session)

    def test_two_factor_uses_supported_login_parameter(self):
        self.client.login.side_effect = [auth_cli.TwoFactorRequired(), True]
        status, _ = self.run_auth()
        self.assertEqual(status, 0)
        self.client.login.assert_called_with("estrategia_nerd", " pass with spaces ", verification_code="123456")
        self.client.two_factor_login.assert_not_called()

    def test_remote_identity_failure_never_saves_session_or_verification(self):
        self.client.account_info.side_effect = RuntimeError("secret-session-value")
        self.client.user_info_v1.side_effect = RuntimeError("secret-session-value")
        status, output = self.run_auth()
        self.assertEqual(status, 1)
        self.assertNotIn("secret-session-value", output)
        self.assertFalse((self.root / "auth/session.json").exists())
        self.assertFalse((self.root / "verified.json").exists())

    def test_bound_account_id_cannot_be_overwritten(self):
        original = '{"id":"456","username":"estrategia_nerd"}'
        (self.root / "account.json").write_text(original, encoding="utf-8")
        status, _ = self.run_auth()
        self.assertEqual(status, 1)
        self.assertEqual((self.root / "account.json").read_text(encoding="utf-8"), original)
        self.assertFalse((self.root / "auth/session.json").exists())

    def test_acl_failure_is_closed_and_sanitized(self):
        self.permissions.side_effect = OSError("private-token-123")
        status, output = self.run_auth()
        self.assertEqual(status, 1)
        self.assertNotIn("private-token-123", output)
        self.assertFalse((self.root / "auth/session.json").exists())
        self.assertFalse((self.root / "verified.json").exists())

    def test_failed_login_does_not_accept_stale_client_user_id(self):
        self.client.login.return_value = False
        status, _ = self.run_auth()
        self.assertEqual(status, 1)
        self.client.account_info.assert_not_called()
        self.assertFalse((self.root / "auth/session.json").exists())

    def test_remote_uid_must_match_authenticated_client(self):
        self.client.account_info.return_value.pk = 456
        status, _ = self.run_auth()
        self.assertEqual(status, 1)
        self.assertFalse((self.root / "auth/session.json").exists())

    def test_raw_login_errors_are_not_printed(self):
        self.client.login.side_effect = RuntimeError("password=should-stay-secret")
        status, output = self.run_auth()
        self.assertEqual(status, 1)
        self.assertNotIn("should-stay-secret", output)


if __name__ == "__main__":
    unittest.main()
