"""Unit tests for Instagram Studio adapter and CLI."""
import json
import unittest
from pathlib import Path
from unittest.mock import MagicMock

import sys
STUDIO_DIR = Path(__file__).resolve().parent.parent
if str(STUDIO_DIR) not in sys.path:
    sys.path.insert(0, str(STUDIO_DIR))

from adapter import InstagramStudioAdapter, StudioError


class TestInstagramStudioAdapter(unittest.TestCase):
    def setUp(self):
        self.mock_client = MagicMock()
        self.mock_client.user_id = "12345"
        self.adapter = InstagramStudioAdapter(client=self.mock_client)

    def test_check_connection_success(self):
        info = MagicMock()
        info.username = "estrategia_nerd"
        info.full_name = "Estratégia Nerd"
        info.profile_pic_url = "https://example.com/pic.jpg"
        info.is_private = False
        self.mock_client.account_info.return_value = info

        res = self.adapter.check_connection()
        self.assertTrue(res["authenticated"])
        self.assertEqual(res["username"], "estrategia_nerd")

    def test_search_music_formats_tracks(self):
        track = MagicMock()
        track.id = "track_1"
        track.audio_asset_id = "asset_1"
        track.audio_cluster_id = "cluster_1"
        track.title = "Cyberpunk Song"
        track.subtitle = "Synthwave Artist"
        track.display_artist = "Synthwave Artist"
        track.cover_artwork_thumbnail_uri = "https://example.com/cover.jpg"
        track.progressive_download_url = "https://example.com/audio.mp3"
        track.duration_in_ms = 30000
        track.highlight_start_times_in_ms = [5000]
        track.dict.return_value = {"id": "track_1"}
        self.mock_client.search_music.return_value = [track]

        res = self.adapter.search_music("cyberpunk")
        self.assertEqual(len(res), 1)
        self.assertEqual(res[0]["title"], "Cyberpunk Song")
        self.assertEqual(res[0]["preview_url"], "https://example.com/audio.mp3")

    def test_pin_media(self):
        res = self.adapter.pin_media("12345_67890", pin=True)
        self.assertTrue(res["ok"])
        self.assertTrue(res["pinned"])
        self.mock_client.media_pin.assert_called_with("12345")

    def test_get_metrics(self):
        info = MagicMock()
        info.follower_count = 1000
        info.following_count = 200
        info.media_count = 50
        self.mock_client.user_info.return_value = info

        m1 = MagicMock()
        m1.id = "m1"
        m1.code = "code1"
        m1.media_type = 1
        m1.like_count = 50
        m1.comment_count = 10
        m1.caption_text = "Post 1"
        m1.thumbnail_url = "https://example.com/t1.jpg"

        m2 = MagicMock()
        m2.id = "m2"
        m2.code = "code2"
        m2.media_type = 2
        m2.like_count = 30
        m2.comment_count = 5
        m2.caption_text = "Post 2"
        m2.thumbnail_url = "https://example.com/t2.jpg"

        self.mock_client.user_medias.return_value = [m1, m2]

        metrics = self.adapter.get_metrics()
        self.assertTrue(metrics["ok"])
        self.assertEqual(metrics["follower_count"], 1000)
        self.assertEqual(metrics["avg_likes"], 40.0)
        self.assertEqual(len(metrics["top_posts"]), 2)
        self.assertEqual(metrics["top_posts"][0]["id"], "m1")

    def test_get_story_viewers(self):
        story = MagicMock()
        story.id = "story_123"
        story.pk = 123
        story.thumbnail_url = "https://example.com/story.jpg"

        viewer = MagicMock()
        viewer.pk = "user_999"
        viewer.username = "nerd_fan"
        viewer.full_name = "Nerd Fan"
        viewer.profile_pic_url = "https://example.com/fan.jpg"

        self.mock_client.user_stories.return_value = [story]
        self.mock_client.story_viewers.return_value = [viewer]

        res = self.adapter.get_story_viewers()
        self.assertEqual(len(res), 1)
        self.assertEqual(res[0]["id"], "story_123")
        self.assertEqual(res[0]["viewer_count"], 1)
        self.assertEqual(res[0]["viewers"][0]["username"], "nerd_fan")


if __name__ == "__main__":
    unittest.main()
