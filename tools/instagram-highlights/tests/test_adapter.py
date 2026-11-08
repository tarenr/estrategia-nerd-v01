"""Unit tests for InstagrapiAdapter and SingleAttemptClient."""
import sys
import unittest
from pathlib import Path
from unittest.mock import MagicMock, patch
from types import SimpleNamespace
from pydantic import HttpUrl

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from adapter import InstagrapiAdapter, SingleAttemptClient
from workflow import WorkflowError


class TestAdapter(unittest.TestCase):
    def test_retries_stay_disabled_after_loading_settings(self):
        client = SingleAttemptClient(private_transport="requests")
        self.assertEqual(client.private.adapters['https://'].max_retries.total, 0)
        settings = client.get_settings()
        settings['session_retry_total'] = 3
        client.set_settings(settings)
        self.assertEqual(client.private.adapters['https://'].max_retries.total, 0)

    def test_real_url_types_and_fresh_story_identity(self):
        self.mock_client.story_info.return_value = SimpleNamespace(
            id='777666_123456', user=SimpleNamespace(pk='123456'),
            links=[SimpleNamespace(webUri=HttpUrl('https://example.com/product'))])
        result = self.adapter.story_info('777666_123456')
        self.assertEqual(result['links'], ['https://example.com/product'])
        self.mock_client.story_info.assert_called_once_with('777666_123456', use_cache=False)

    def test_missing_highlight_owner_is_not_assumed(self):
        self.mock_client.highlight_info.return_value = SimpleNamespace(pk='55', title='PC', items=[])
        self.assertEqual(self.adapter.highlight_info('55')['owner'], '')

    def test_cover_without_confirmation_is_rejected(self):
        self.mock_client.highlight_change_cover.return_value = None
        with self.assertRaises(WorkflowError):
            self.adapter.cover('55', Path('cover.jpg'))

    def setUp(self):
        self.mock_client = MagicMock()
        self.mock_client.user_id = "123456"
        self.adapter = InstagrapiAdapter(client=self.mock_client)

    def test_identity_matching_account(self):
        mock_info = MagicMock()
        mock_info.username = "estrategia_nerd"
        mock_info.pk = '123456'
        self.mock_client.account_info.return_value = mock_info

        ident = self.adapter.identity()
        self.assertEqual(ident, {"id": "123456", "username": "estrategia_nerd"})

    def test_identity_mismatched_account_raises(self):
        mock_info = MagicMock()
        mock_info.username = "outro_usuario"
        self.mock_client.account_info.return_value = mock_info

        with self.assertRaises(WorkflowError) as ctx:
            self.adapter.identity()
        self.assertIn("não corresponde", str(ctx.exception))

    def test_upload_story_calls_single_attempt(self):
        self.mock_client.photo_rupload.return_value = ("999888", 1080, 1920)
        self.mock_client.photo_configure_to_story.return_value = {"media": {"pk": "777666"}}

        sid = self.adapter.upload(Path("fake.jpg"), link="https://estrategianerd.com.br")
        self.assertEqual(sid, "777666_123456")
        self.mock_client.photo_configure_to_story.assert_called_once()
        args, kwargs = self.mock_client.photo_configure_to_story.call_args
        self.assertEqual(kwargs.get("upload_id"), "999888")
        links = kwargs.get("links", [])
        self.assertEqual(len(links), 1)
        self.assertEqual(str(links[0].webUri).rstrip('/'), "https://estrategianerd.com.br")
        self.assertEqual(links[0].x, 0.5)
        self.assertEqual(links[0].y, 0.70)
        self.assertEqual(links[0].width, 0.38)
        self.assertEqual(links[0].height, 0.07)

    def test_upload_timeout_translates_to_safe_workflow_error(self):
        from instagrapi.exceptions import ClientRequestTimeout
        self.mock_client.photo_rupload.side_effect = ClientRequestTimeout()

        with self.assertRaises(WorkflowError) as ctx:
            self.adapter.upload(Path("fake.jpg"))
        self.assertIn("incerto", str(ctx.exception).lower())

    def test_story_info_normalizes_owner_and_links(self):
        mock_story = MagicMock()
        mock_story.user.pk = "123456"
        mock_story.id = '777666_123456'
        sticker = MagicMock()
        sticker.story_link.url = "https://estrategianerd.com.br/post"
        mock_story.story_link_stickers = [sticker]
        mock_story.links = []
        self.mock_client.story_info.return_value = mock_story

        res = self.adapter.story_info("777666_123456")
        self.assertEqual(res["id"], "777666_123456")
        self.assertEqual(res["owner"], "123456")
        self.assertIn("https://estrategianerd.com.br/post", res["links"])

    def test_highlights_normalizes_items_and_media_ids(self):
        mock_h = MagicMock()
        mock_h.pk = "555444"
        mock_h.title = "Destaque Teste"
        mock_h.user.pk = "123456"
        item = MagicMock()
        item.pk = "777666"
        mock_h.items = [item]
        self.mock_client.user_highlights_v1.return_value = [mock_h]

        hls = self.adapter.highlights()
        self.assertEqual(len(hls), 1)
        self.assertEqual(hls[0]["id"], "555444")
        self.assertEqual(hls[0]["title"], "Destaque Teste")
        self.assertEqual(hls[0]["stories"], ["777666_123456"])

    def test_create_and_edit_methods(self):
        mock_hl = MagicMock()
        mock_hl.pk = "999111"
        self.mock_client.highlight_create.return_value = mock_hl

        hid = self.adapter.create("Novo Destaque", ["777666_123456"])
        self.assertEqual(hid, "999111")

        self.adapter.add("999111", ["888999_123456"])
        self.mock_client.highlight_add_stories.assert_called_once()

        self.adapter.rename("999111", "Renomeado")
        self.mock_client.highlight_change_title.assert_called_once()

        self.mock_client.highlight_change_cover.return_value = SimpleNamespace(
            pk='999111', user=SimpleNamespace(pk='123456'), cover_media={'upload_id': '789'})
        self.adapter.cover("999111", Path("cover.jpg"))
        self.mock_client.highlight_change_cover.assert_called_once()


if __name__ == "__main__":
    unittest.main()
