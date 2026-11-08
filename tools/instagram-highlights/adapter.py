"""Instagrapi adapter with single-attempt protections against duplicate mutations."""
import logging
import os
import re
import time
from pathlib import Path
from typing import Any, Dict, List, Optional

try:
    from instagrapi import Client
    from instagrapi.exceptions import (
        ClientError,
        ClientIncompleteReadError,
        ClientRequestTimeout,
        PhotoConfigureStoryError,
    )
    from instagrapi.types import StoryLink
except ImportError:  # pragma: no cover
    Client = object  # type: ignore
    ClientError = Exception  # type: ignore
    ClientIncompleteReadError = Exception  # type: ignore
    ClientRequestTimeout = Exception  # type: ignore
    PhotoConfigureStoryError = Exception  # type: ignore
    StoryLink = None  # type: ignore

from workflow import ACCOUNT, NUM_ID, WorkflowError

logger = logging.getLogger("instagram_highlights.adapter")


class SingleAttemptClient(Client if Client is not object else object):  # type: ignore
    """Subclass of instagrapi.Client preventing blind retry loops on mutations."""

    def __init__(self, *args, **kwargs):
        if Client is not object:
            kwargs['session_retry_total'] = 0
            kwargs['with_challenge_flow'] = False
            super().__init__(*args, **kwargs)

    def init(self):
        # Client.init and load_settings both restore retry settings from disk.
        self.settings['session_retry_total'] = 0
        return super().init()

    def private_request(
        self,
        endpoint,
        data=None,
        params=None,
        login=False,
        with_signature=True,
        headers=None,
        extra_sig=None,
        domain: str = None,
    ):
        """Prevent auto-retry loops on mutation endpoints after timeout/network drop."""
        is_mutation = bool(data is not None or "configure" in endpoint or "create" in endpoint or "edit" in endpoint)
        if not is_mutation:
            return super().private_request(
                endpoint,
                data=data,
                params=params,
                login=login,
                with_signature=with_signature,
                headers=headers,
                extra_sig=extra_sig,
                domain=domain,
            )

        # For mutations, execute directly without blind retry loops
        if self.authorization:
            if not headers:
                headers = {}
            if "authorization" not in headers:
                headers.update({"Authorization": self.authorization})
        kwargs = dict(
            data=data,
            params=params,
            login=login,
            with_signature=with_signature,
            headers=headers,
            extra_sig=extra_sig,
            domain=domain,
        )
        self.private_requests_count += 1
        try:
            self._send_private_request(endpoint, **kwargs)
            return self.last_json
        except (ClientRequestTimeout, ClientIncompleteReadError) as exc:
            # Under uncertain network drops during a mutation, DO NOT retry blindly.
            # Propagate error so workflow records status=unknown for visual human reconciliation.
            raise WorkflowError(
                "A conexão com o Instagram expirou durante a gravação. Não reenvie: confira no aplicativo e reconcilie."
            ) from exc


class InstagrapiAdapter:
    """Production adapter wrapping instagrapi with safety guarantees."""

    def __init__(self, session_path: Optional[Path] = None, client: Optional[Any] = None):
        self.session_path = Path(session_path) if session_path else None
        if client is not None:
            self.client = client
        else:
            self.client = SingleAttemptClient()
            if self.session_path and self.session_path.is_file():
                self._load_session()

    def _load_session(self) -> None:
        try:
            self.client.load_settings(self.session_path)
        except Exception as exc:
            raise WorkflowError(
                "Não foi possível carregar a sessão salva. Reconecte a conta pelo terminal."
            ) from exc

    def identity(self) -> Dict[str, str]:
        if not hasattr(self.client, "user_id") or not self.client.user_id:
            raise WorkflowError("Conecte a conta pelo terminal antes de consultar ou publicar.")
        try:
            uid = str(self.client.user_id)
            info = self.client.account_info()
            username = str(info.username).lower()
            if username != ACCOUNT or str(info.pk) != uid:
                raise WorkflowError(
                    f"A conta conectada (@{username}) não corresponde ao @{ACCOUNT}."
                )
            return {"id": uid, "username": username}
        except WorkflowError:
            raise
        except Exception as exc:
            raise WorkflowError("Não foi possível verificar a conta conectada no Instagram.") from exc

    def upload(self, path: Path, link: str = "") -> str:
        """Upload a photo story with single attempt and explicit link coordinates."""
        try:
            path = Path(path).resolve()
            upload_id = str(int(time.time() * 1000))
            upload_id, width, height = self.client.photo_rupload(
                path, upload_id=upload_id, for_story=True, resize_mode="fill"
            )

            story_links = []
            if link and StoryLink is not None:
                # Centered above the artwork CTA, within the 1080x1920 safe area.
                story_links.append(
                    StoryLink(
                        webUri=link,
                        x=0.5,
                        y=0.70,
                        width=0.38,
                        height=0.07,
                    )
                )

            # Single configure attempt: no 10-attempt loop
            configured = self.client.photo_configure_to_story(
                upload_id=upload_id,
                width=width,
                height=height,
                caption="",
                links=story_links,
            )
            if not configured:
                raise WorkflowError(
                    "O Instagram não confirmou a publicação do story. Confira no aplicativo antes de continuar."
                )

            media = configured.get("media", configured)
            pk = media.get("pk") or media.get("id")
            uid = self.client.user_id
            if not pk:
                raise WorkflowError("Story publicado sem identificador retornado.")

            sid = f"{pk}_{uid}" if "_" not in str(pk) else str(pk)
            return sid
        except WorkflowError:
            raise
        except (ClientRequestTimeout, ClientIncompleteReadError) as exc:
            raise WorkflowError(
                "Tempo esgotado ao publicar story. O resultado é incerto: confira no app e reconcilie."
            ) from exc
        except Exception as exc:
            raise WorkflowError(
                "Falha ao enviar story para o Instagram. Confira a sessão e tente novamente."
            ) from exc

    def story_info(self, sid: str) -> Dict[str, Any]:
        """Fetch story information and verify links and ownership."""
        try:
            info = self.client.story_info(sid, use_cache=False)
            owner = str(getattr(getattr(info, "user", None), "pk", ""))
            links = []
            if hasattr(info, "story_link_stickers") and info.story_link_stickers:
                for sticker in info.story_link_stickers:
                    url = getattr(getattr(sticker, "story_link", None), "url", "")
                    if url:
                        links.append(str(url))
            elif hasattr(info, "links") and info.links:
                for l in info.links:
                    url = getattr(l, "webUri", "") or getattr(l, "url", "")
                    if url:
                        links.append(str(url))
            return {"id": str(info.id), "owner": owner, "links": links}
        except Exception as exc:
            raise WorkflowError(
                f"Story {sid} não encontrado ou expirado no Instagram."
            ) from exc

    def highlights(self) -> List[Dict[str, Any]]:
        """List active user highlights."""
        try:
            uid = self.client.user_id
            items = self.client.user_highlights_v1(uid)
            result = []
            for h in items:
                stories = []
                if hasattr(h, "items") and h.items:
                    stories = [f"{getattr(item, 'pk', '')}_{uid}" for item in h.items]
                elif hasattr(h, "media_ids") and h.media_ids:
                    stories = [f"{pk}_{uid}" for pk in h.media_ids]
                result.append(
                    {
                        "id": str(h.pk),
                        "title": str(h.title or ""),
                        "owner": str(getattr(getattr(h, "user", None), "pk", "")),
                        "stories": stories,
                    }
                )
            return result
        except Exception as exc:
            raise WorkflowError("Não foi possível consultar os destaques no Instagram.") from exc

    def highlight_info(self, hid: str) -> Dict[str, Any]:
        """Get detail for a specific highlight."""
        try:
            pk = hid.split(":")[-1] if ":" in hid else hid
            h = self.client.highlight_info(pk)
            uid = self.client.user_id
            stories = []
            if hasattr(h, "items") and h.items:
                stories = [f"{getattr(item, 'pk', '')}_{uid}" for item in h.items]
            elif hasattr(h, "media_ids") and h.media_ids:
                stories = [f"{p}_{uid}" for p in h.media_ids]
            return {
                "id": str(h.pk),
                "title": str(h.title or ""),
                "owner": str(getattr(getattr(h, "user", None), "pk", "")),
                "stories": stories,
            }
        except Exception as exc:
            raise WorkflowError(f"Destaque {hid} não encontrado no Instagram.") from exc

    def create(self, title: str, story_ids: List[str]) -> str:
        """Create highlight with given stories."""
        try:
            # instagrapi expects full story IDs or PKs
            highlight = self.client.highlight_create(title=title, story_ids=story_ids)
            return str(highlight.pk)
        except Exception as exc:
            raise WorkflowError("Falha ao criar o destaque no Instagram.") from exc

    def add(self, hid: str, story_ids: List[str]) -> None:
        """Add stories to existing highlight."""
        try:
            pk = hid.split(":")[-1] if ":" in hid else hid
            self.client.highlight_add_stories(highlight_pk=pk, added_media_ids=story_ids)
        except Exception as exc:
            raise WorkflowError("Falha ao adicionar stories ao destaque existente.") from exc

    def rename(self, hid: str, title: str) -> None:
        """Rename existing highlight."""
        try:
            pk = hid.split(":")[-1] if ":" in hid else hid
            self.client.highlight_change_title(highlight_pk=pk, title=title)
        except Exception as exc:
            raise WorkflowError("Falha ao renomear o destaque no Instagram.") from exc

    def cover(self, hid: str, path: Path) -> None:
        """Change cover of existing highlight."""
        try:
            pk = hid.split(":")[-1] if ":" in hid else hid
            confirmed = self.client.highlight_change_cover(highlight_pk=pk, cover_path=Path(path))
            if (not confirmed or str(confirmed.pk) != pk
                    or str(getattr(getattr(confirmed, 'user', None), 'pk', '')) != str(self.client.user_id)
                    or not isinstance(getattr(confirmed, 'cover_media', None), dict)
                    or not confirmed.cover_media):
                raise WorkflowError("A alteração da capa não foi confirmada pelo Instagram.")
        except Exception as exc:
            raise WorkflowError("Falha ao atualizar a capa do destaque no Instagram.") from exc
