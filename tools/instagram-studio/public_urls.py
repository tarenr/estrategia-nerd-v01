"""Canonical public URLs used by Instagram publishing helpers."""

import re


PUBLIC_SITE_URL = "https://estrategianerd.com.br"


def article_url(slug: str) -> str:
    """Build a public article URL using the route implemented by the site."""
    if not re.fullmatch(r"[a-z0-9]+(?:-[a-z0-9]+)*", slug):
        raise ValueError("Slug de artigo inválido.")
    return f"{PUBLIC_SITE_URL}/post/{slug}"
