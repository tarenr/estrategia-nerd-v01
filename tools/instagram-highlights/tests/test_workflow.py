"""Offline behavioral tests. No Instagram credentials or requests."""
import copy
import sys
import tempfile
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from workflow import Workflow, WorkflowError, account_lock
from PIL import Image


class FakeInstagram:
    def __init__(self):
        self.username = "estrategia_nerd"
        self.uid = "42"
        self.stories = {}
        self.targets = {}
        self.uploads = 0
        self.fail_upload = False
        self.fail_create = False
        self.link_missing = False

    def identity(self):
        return {"id": self.uid, "username": self.username}

    def upload(self, path, link):
        self.uploads += 1
        sid = f"{100 + self.uploads}_42"
        self.stories[sid] = {"id": sid, "owner": "42", "links": [] if self.link_missing else [link]}
        if self.fail_upload:
            raise TimeoutError("secret-session-token must never be emitted")
        return sid

    def story_info(self, sid):
        return self.stories[sid]

    def highlights(self):
        return list(self.targets.values())

    def highlight_info(self, hid):
        return copy.deepcopy(self.targets[hid])

    def create(self, title, stories):
        hid = str(200 + len(self.targets))
        self.targets[hid] = {"id": hid, "owner": "42", "title": title, "stories": list(stories)}
        if self.fail_create:
            raise TimeoutError("secret-session-token")
        return hid

    def add(self, hid, stories):
        self.targets[hid]["stories"] += [s for s in stories if s not in self.targets[hid]["stories"]]

    def rename(self, hid, title):
        self.targets[hid]["title"] = title

    def cover(self, hid, path):
        self.targets[hid]["cover"] = str(path)


class WorkflowTests(unittest.TestCase):
    def setUp(self):
        local_tmp = Path(__file__).resolve().parents[3] / "storage/instagram-highlights/tests"
        local_tmp.mkdir(parents=True, exist_ok=True)
        self.tmp = tempfile.TemporaryDirectory(dir=local_tmp)
        self.root = Path(self.tmp.name)
        (self.root / "assets").mkdir()
        self.asset = "a" * 32
        Image.new("RGB", (1080, 1920), "navy").save(self.root / "assets" / (self.asset + ".jpg"))
        self.api = FakeInstagram()
        self.w = Workflow(self.root, self.api)
        self.manifest = {"job_id": "b" * 32, "account": "estrategia_nerd", "items": [{"asset": self.asset, "link": "https://example.com/product"}], "highlight": {"id": "", "title": "PC R$400", "cover_asset": ""}}

    def tearDown(self):
        self.tmp.cleanup()

    def finish(self):
        for _ in range(10):
            job = self.w.step(self.manifest["job_id"])
            if job["status"] == "completed":
                return job
        self.fail("Workflow did not finish")

    def test_prepare_is_offline_and_repeated_request_is_same_job(self):
        job = self.w.prepare(self.manifest)
        self.assertEqual(job["status"], "ready")
        self.assertEqual(self.w.prepare(self.manifest)["id"], job["id"])
        self.assertEqual(self.api.uploads, 0)

    def test_create_preserves_story_order_and_repeat_does_not_publish_twice(self):
        self.manifest["items"].append({"asset": self.asset, "link": "https://example.com/other"})
        self.w.prepare(self.manifest)
        job = self.finish()
        self.w.step(job["id"])
        self.assertEqual(self.api.uploads, 2)
        self.assertEqual(self.api.targets[job["highlight_id"]]["stories"], ["101_42", "102_42"])

    def test_wrong_account_prevents_mutation(self):
        self.w.prepare(self.manifest)
        self.api.username = "someone_else"
        with self.assertRaises(WorkflowError):
            self.w.step(self.manifest["job_id"])
        self.assertEqual(self.api.uploads, 0)

    def test_root_link_normalized_by_instagram_is_equivalent(self):
        self.api.stories['101_42'] = {'id': '101_42', 'owner': '42', 'links': ['https://example.com/']}
        self.w.own_story('101_42', '42', 'https://example.com')

    def test_instagram_redirect_and_tracking_are_equivalent_to_requested_link(self):
        wrapped = (
            'https://l.instagram.com/?u=https%3A%2F%2Fexample.com%2Fpost%2Farticle%3Ffbclid%3Dtracking'
            '&e=opaque'
        )
        self.api.stories['101_42'] = {'id': '101_42', 'owner': '42', 'links': [wrapped]}
        self.w.own_story('101_42', '42', 'https://example.com/post/article')

    def test_wrong_highlight_id_is_rejected_even_with_same_owner(self):
        self.api.targets['200'] = {'id': '201', 'owner': '42', 'title': 'PC', 'stories': []}
        with self.assertRaises(WorkflowError):
            self.w.own_target('200', '42')

    def test_same_job_with_changed_link_is_rejected(self):
        self.w.prepare(self.manifest)
        self.manifest["items"][0]["link"] = "https://example.com/different"
        with self.assertRaises(WorkflowError):
            self.w.prepare(self.manifest)

    def test_changed_asset_cannot_be_published_under_old_confirmation(self):
        self.w.prepare(self.manifest)
        Image.new("RGB", (1080, 1920), "red").save(self.root / "assets" / (self.asset + ".jpg"))
        with self.assertRaises(WorkflowError):
            self.w.step(self.manifest["job_id"])
        self.assertEqual(self.api.uploads, 0)

    def test_upload_timeout_blocks_retry_and_sanitizes_error(self):
        self.w.prepare(self.manifest)
        self.api.fail_upload = True
        job = self.w.step(self.manifest["job_id"])
        self.assertEqual(job["status"], "unknown")
        self.assertNotIn("secret", str(job))
        self.w.step(job["id"])
        self.assertEqual(self.api.uploads, 1)

    def test_reconciliation_requires_visual_confirmation_and_verified_remote_owner(self):
        self.w.prepare(self.manifest)
        self.api.fail_upload = True
        self.w.step(self.manifest["job_id"])
        with self.assertRaises(WorkflowError):
            self.w.reconcile(self.manifest["job_id"], "101_42", False)
        self.api.stories["101_42"]["owner"] = "99"
        with self.assertRaises(WorkflowError):
            self.w.reconcile(self.manifest["job_id"], "101_42", True)
        self.api.stories["101_42"]["owner"] = "42"
        self.api.fail_upload = False
        self.w.reconcile(self.manifest["job_id"], "101_42", True)
        self.finish()
        self.assertEqual(self.api.uploads, 1)

    def test_create_timeout_can_resume_with_confirmed_highlight(self):
        self.w.prepare(self.manifest)
        self.w.step(self.manifest["job_id"])
        self.api.fail_create = True
        job = self.w.step(self.manifest["job_id"])
        self.assertEqual(job["status"], "unknown")
        self.w.reconcile(job["id"], "200", True)
        self.assertEqual(self.finish()["status"], "completed")
        self.assertEqual(len(self.api.targets), 1)

    def test_missing_link_after_upload_blocks_highlight_creation(self):
        self.w.prepare(self.manifest)
        self.api.link_missing = True
        job = self.w.step(self.manifest["job_id"])
        self.assertEqual(job["status"], "unknown")
        self.assertEqual(len(self.api.targets), 0)

    def test_existing_highlight_preserves_omitted_title_and_deduplicates_stories(self):
        self.api.stories["103_42"] = {"id": "103_42", "owner": "42", "links": []}
        self.api.targets["201"] = {"id": "201", "owner": "42", "title": "Original", "stories": ["103_42"]}
        self.manifest["items"] = [{"story_id": "103_42"}]
        self.manifest["highlight"] = {"id": "201", "title": "", "cover_asset": ""}
        self.w.prepare(self.manifest)
        self.finish()
        self.assertEqual(self.api.targets["201"]["title"], "Original")
        self.assertEqual(self.api.targets["201"]["stories"], ["103_42"])

    def test_foreign_highlight_rejected_before_upload(self):
        self.api.targets["201"] = {"id": "201", "owner": "99", "title": "Other", "stories": []}
        self.manifest["highlight"]["id"] = "201"
        self.w.prepare(self.manifest)
        with self.assertRaises(WorkflowError):
            self.w.step(self.manifest["job_id"])
        self.assertEqual(self.api.uploads, 0)

    def test_invalid_paths_urls_and_payloads_are_rejected(self):
        for invalid in ["javascript:alert(1)", "https://user:pass@example.com", "https://127.0.0.1", "https://example.com/\n"]:
            manifest = copy.deepcopy(self.manifest)
            manifest["items"][0]["link"] = invalid
            with self.subTest(url=invalid), self.assertRaises(WorkflowError):
                self.w.prepare(manifest)
        self.manifest["items"][0]["asset"] = "../../secret"
        with self.assertRaises(WorkflowError):
            self.w.prepare(self.manifest)

    def test_exclusive_lock_prevents_parallel_mutation_and_releases_after_error(self):
        self.w.prepare(self.manifest)
        with account_lock(self.root):
            with self.assertRaises(WorkflowError):
                self.w.step(self.manifest["job_id"])
        self.finish()


if __name__ == "__main__":
    unittest.main()
