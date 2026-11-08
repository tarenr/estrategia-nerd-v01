"""Regression tests for public URLs used by Instagram publishing."""

import importlib.util
import unittest
from pathlib import Path


SCRIPT_PATH = Path(__file__).resolve().parents[2] / "instagram-studio" / "public_urls.py"
SPEC = importlib.util.spec_from_file_location("instagram_public_urls", SCRIPT_PATH)
MODULE = importlib.util.module_from_spec(SPEC)
assert SPEC is not None and SPEC.loader is not None
SPEC.loader.exec_module(MODULE)


class EchoPublicationUrlTests(unittest.TestCase):
    def test_article_url_uses_public_post_route(self):
        self.assertEqual(
            MODULE.article_url("conheca-o-echo-assistente-estrategia-nerd"),
            "https://estrategianerd.com.br/post/conheca-o-echo-assistente-estrategia-nerd",
        )

    def test_article_url_rejects_invalid_slug(self):
        with self.assertRaises(ValueError):
            MODULE.article_url("../artigo/errado")


if __name__ == "__main__":
    unittest.main()
