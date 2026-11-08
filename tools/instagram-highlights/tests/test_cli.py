"""Unit tests for tools/instagram-highlights/cli.py."""
import io
import json
import os
import sys
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch
from PIL import Image

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import cli
from workflow import Workflow


class TestCli(unittest.TestCase):
    def setUp(self):
        local_tmp = Path(__file__).resolve().parents[3] / "storage/instagram-highlights/tests"
        local_tmp.mkdir(parents=True, exist_ok=True)
        self.tmp = tempfile.TemporaryDirectory(dir=local_tmp)
        self.root = Path(self.tmp.name)
        (self.root / "assets").mkdir()
        self.asset = "a" * 32
        Image.new("RGB", (1080, 1920), "navy").save(self.root / "assets" / (self.asset + ".jpg"))

    def tearDown(self):
        self.tmp.cleanup()

    def run_cli(self, args_list, stdin_content=""):
        old_stdout = sys.stdout
        old_stdin = sys.stdin
        sys.stdout = io.StringIO()
        sys.stdin = io.StringIO(stdin_content)
        try:
            with patch("sys.argv", ["cli.py"] + args_list):
                with self.assertRaises(SystemExit) as ctx:
                    cli.main()
            output = sys.stdout.getvalue()
            return ctx.exception.code, json.loads(output)
        finally:
            sys.stdout = old_stdout
            sys.stdin = old_stdin

    def test_status_returns_ok_and_account(self):
        code, data = self.run_cli(["status", "--root", str(self.root)])
        self.assertEqual(code, 0)
        self.assertTrue(data["ok"])
        self.assertEqual(data["account"], "estrategia_nerd")
        self.assertFalse(data["session_stored"])

    def test_missing_job_returns_explicit_code_but_partial_journal_does_not(self):
        job_id = 'd' * 32
        _, absent = self.run_cli(['job', '--root', str(self.root), '--id', job_id])
        self.assertEqual(absent['code'], 'JOB_NOT_FOUND')
        (self.root / 'jobs' / job_id).mkdir(parents=True)
        _, partial = self.run_cli(['job', '--root', str(self.root), '--id', job_id])
        self.assertFalse(partial['ok'])
        self.assertNotIn('code', partial)

    def test_offline_status_does_not_load_corrupt_session(self):
        (self.root / 'auth').mkdir()
        (self.root / 'auth/session.json').write_text('invalid', encoding='utf-8')
        with patch('cli.InstagrapiAdapter', side_effect=RuntimeError('private data')) as adapter:
            _, data = self.run_cli(['status', '--root', str(self.root)])
        self.assertTrue(data['ok'])
        adapter.assert_not_called()

    def test_remote_action_sanitizes_session_failure(self):
        (self.root / 'auth').mkdir()
        (self.root / 'auth/session.json').write_text('invalid', encoding='utf-8')
        with patch('cli.InstagrapiAdapter', side_effect=RuntimeError('private data')):
            _, data = self.run_cli(['catalog', '--root', str(self.root)])
        self.assertFalse(data['ok'])
        self.assertNotIn('private data', json.dumps(data))

    def test_prepare_and_job_flow(self):
        job_id = "b" * 32
        payload = {
            "job_id": job_id,
            "account": "estrategia_nerd",
            "items": [{"asset": self.asset, "link": "https://estrategianerd.com.br"}],
            "highlight": {"id": "", "title": "Destaque CLI", "cover_asset": ""},
        }
        code, data = self.run_cli(["prepare", "--root", str(self.root)], json.dumps(payload))
        self.assertEqual(code, 0)
        self.assertTrue(data["ok"])
        self.assertEqual(data["job"]["id"], job_id)
        self.assertEqual(data["job"]["status"], "ready")

        # Query job by id
        code_job, data_job = self.run_cli(["job", "--root", str(self.root), "--id", job_id])
        self.assertEqual(code_job, 0)
        self.assertTrue(data_job["ok"])
        self.assertEqual(data_job["job"]["id"], job_id)

    def test_step_without_session_returns_safe_error(self):
        job_id = "c" * 32
        payload = {
            "job_id": job_id,
            "account": "estrategia_nerd",
            "items": [{"asset": self.asset, "link": "https://estrategianerd.com.br"}],
            "highlight": {"id": "", "title": "Destaque CLI", "cover_asset": ""},
        }
        self.run_cli(["prepare", "--root", str(self.root)], json.dumps(payload))

        code, data = self.run_cli(["step", "--root", str(self.root), "--id", job_id])
        self.assertEqual(code, 0)
        self.assertFalse(data["ok"])
        self.assertIn("Conecte a conta", data["message"])

    def test_catalog_without_session_returns_safe_error(self):
        code, data = self.run_cli(["catalog", "--root", str(self.root)])
        self.assertEqual(code, 0)
        self.assertFalse(data["ok"])
        self.assertIn("Conecte a conta", data["message"])


if __name__ == "__main__":
    unittest.main()
