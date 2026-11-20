"""Instagram Studio Adapter with full instagrapi capabilities and safety guarantees."""
import logging
import os
import re
import sys
import time
from pathlib import Path
from typing import Any, Dict, List, Optional, Union

try:
    from instagrapi import Client
    from instagrapi.exceptions import (
        ClientError,
        ClientIncompleteReadError,
        ClientRequestTimeout,
        LoginRequired,
        FeedbackRequired,
    )
    from instagrapi.types import StoryLink, Track
except ImportError:  # pragma: no cover
    Client = object  # type: ignore
    ClientError = Exception  # type: ignore
    ClientIncompleteReadError = Exception  # type: ignore
    ClientRequestTimeout = Exception  # type: ignore
    LoginRequired = Exception  # type: ignore
    FeedbackRequired = Exception  # type: ignore
    StoryLink = None  # type: ignore
    Track = None  # type: ignore

ACCOUNT = "estrategia_nerd"
logger = logging.getLogger("instagram_studio.adapter")


class StudioError(Exception):
    """Custom error for studio actions."""
    pass


class SingleAttemptClient(Client if Client is not object else object):  # type: ignore
    """Subclass of instagrapi.Client preventing blind retry loops on mutations."""

    def __init__(self, *args, **kwargs):
        if Client is not object:
            kwargs['session_retry_total'] = 0
            kwargs['with_challenge_flow'] = False
            super().__init__(*args, **kwargs)

    def init(self):
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
            raise StudioError(
                "A conexão com o Instagram expirou durante a gravação. Não reenvie: confira no aplicativo."
            ) from exc


class InstagramStudioAdapter:
    """Production adapter providing music-backed feed posts, profile management,
    direct messages and discovery tools while preserving SingleAttempt safety.
    """

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
            raise StudioError(
                "Não foi possível carregar a sessão salva. Reconecte a conta pelo terminal."
            ) from exc

    def check_connection(self) -> Dict[str, Any]:
        """Verify session connection and return basic account metadata."""
        if not hasattr(self.client, "user_id") or not self.client.user_id:
            return {"authenticated": False, "message": "Conta não conectada no terminal."}
        try:
            uid = str(self.client.user_id)
            info = self.client.account_info()
            username = str(getattr(info, "username", "")).lower()
            return {
                "authenticated": True,
                "user_id": uid,
                "username": username,
                "full_name": getattr(info, "full_name", ""),
                "profile_pic_url": str(getattr(info, "profile_pic_url", "") or ""),
                "is_private": getattr(info, "is_private", False),
            }
        except LoginRequired:
            return {"authenticated": False, "message": "Sessão expirada. Execute auth_cli.py para renovar."}
        except Exception as exc:
            return {"authenticated": False, "message": f"Erro de validação: {str(exc)}"}

    # -------------------------------------------------------------------------
    # 1. Music Search & Trending
    # -------------------------------------------------------------------------
    @staticmethod
    def _format_track(raw: Any) -> Optional[Dict[str, Any]]:
        """Extract and format track metadata safely from dict or object."""
        if not raw:
            return None
        if isinstance(raw, dict):
            t = raw.get("track", raw)
        elif hasattr(raw, "title"):
            t = raw
        else:
            t = getattr(raw, "track", raw)

        def get_val(key: str, default: Any = "") -> Any:
            if isinstance(t, dict):
                val = t.get(key, default)
            elif hasattr(t, key):
                val = getattr(t, key)
            else:
                val = default
            if callable(val) and type(val).__name__ != "MagicMock":
                return ""
            return val if val is not None else default

        title_raw = get_val("title", "")
        title = str(title_raw).strip() if title_raw is not None else ""
        if not title or title.lower() in ("sem título", "none"):
            san = get_val("sanitized_title", "")
            if san:
                title = str(san).strip()
        if not title or title.lower() in ("sem título", "none"):
            return None

        artist = str(get_val("display_artist", "") or get_val("subtitle", "") or "").strip()
        cover = str(get_val("cover_artwork_thumbnail_uri", "") or get_val("cover_artwork_uri", "") or "").strip()
        preview = str(
            get_val("progressive_download_url", "")
            or get_val("fast_start_progressive_download_url", "")
            or get_val("web_30s_preview_download_url", "")
            or ""
        ).strip()
        audio_cluster = str(get_val("audio_cluster_id", "") or "").strip()
        audio_asset = str(get_val("audio_asset_id", "") or get_val("id", "") or audio_cluster or "").strip()

        raw_dict = t if isinstance(t, dict) else (t.dict() if hasattr(t, "dict") and callable(t.dict) else {})

        highlight_times = get_val("highlight_start_times_in_ms", [0])
        if not isinstance(highlight_times, list):
            highlight_times = [0]

        try:
            dur_val = get_val("duration_in_ms", 30000)
            dur = int(dur_val) if dur_val is not None else 30000
        except (ValueError, TypeError):
            dur = 30000

        return {
            "id": audio_asset or audio_cluster,
            "audio_asset_id": audio_asset,
            "audio_cluster_id": audio_cluster,
            "title": title,
            "subtitle": artist or "Instagram Audio",
            "display_artist": artist or "Instagram Audio",
            "cover_url": cover,
            "preview_url": preview,
            "duration_ms": dur,
            "highlight_start_times_ms": highlight_times,
            "raw_dict": raw_dict
        }

    def search_music(self, query: str) -> List[Dict[str, Any]]:
        """Search Instagram's music library and format tracks for UI."""
        clean_q = (query or "").strip()
        if len(clean_q) < 2:
            return []
        try:
            raw_items = []
            if hasattr(self.client, "music_search_v2"):
                try:
                    res = self.client.music_search_v2(clean_q, product="music_in_feed")
                    if isinstance(res, dict) and "items" in res and res["items"]:
                        raw_items = res["items"]
                except Exception as e:
                    logger.warning("music_search_v2 falhou, tentando fallback: %s", e)

            if not raw_items and hasattr(self.client, "music_keyword_search"):
                try:
                    res = self.client.music_keyword_search(clean_q)
                    if isinstance(res, dict) and "items" in res and res["items"]:
                        raw_items = res["items"]
                except Exception:
                    pass

            if not raw_items and hasattr(self.client, "search_music"):
                try:
                    legacy_res = self.client.search_music(clean_q)
                    if isinstance(legacy_res, list):
                        raw_items = legacy_res
                except Exception:
                    pass

            results = []
            for item in (raw_items or []):
                formatted = self._format_track(item)
                if formatted:
                    results.append(formatted)
            return results
        except Exception as exc:
            logger.error("Erro ao buscar músicas: %s", exc)
            return []

    def trending_music(self) -> List[Dict[str, Any]]:
        """Fetch trending or top trend audio tracks from Instagram."""
        try:
            raw_items = []
            if hasattr(self.client, "music_top_trends"):
                try:
                    res = self.client.music_top_trends()
                    if isinstance(res, dict) and "items" in res:
                        raw_items = res["items"]
                    elif isinstance(res, list):
                        raw_items = res
                except Exception:
                    pass

            if not raw_items and hasattr(self.client, "music_trending"):
                try:
                    res = self.client.music_trending()
                    if isinstance(res, dict) and "items" in res:
                        raw_items = res["items"]
                    elif isinstance(res, list):
                        raw_items = res
                except Exception:
                    pass

            results = []
            for item in (raw_items or []):
                formatted = self._format_track(item)
                if formatted:
                    results.append(formatted)
            return results
        except Exception as exc:
            logger.error("Erro ao buscar músicas em alta: %s", exc)
            return []

    # -------------------------------------------------------------------------
    # 2. Feed Publishing (Photos & Albums with Music)
    # -------------------------------------------------------------------------
    def upload_photo(
        self,
        path: Path,
        caption: str = "",
        track_dict: Optional[Dict[str, Any]] = None,
        start_time_ms: int = 0
    ) -> Dict[str, Any]:
        """Upload a photo to feed with optional attached music track."""
        path = Path(path).resolve()
        if not path.is_file():
            raise StudioError(f"Arquivo de imagem não encontrado: {path}")

        try:
            if track_dict:
                media = self.client.photo_upload_with_music(
                    path,
                    caption=caption,
                    track=track_dict,
                    audio_asset_start_time=start_time_ms,
                    overlap_duration=30000
                )
            else:
                media = self.client.photo_upload(path, caption=caption)

            pk = getattr(media, "pk", "") or getattr(media, "id", "")
            code = getattr(media, "code", "")
            return {
                "ok": True,
                "id": str(pk),
                "code": code,
                "url": f"https://www.instagram.com/p/{code}/" if code else ""
            }
        except (ClientRequestTimeout, ClientIncompleteReadError) as exc:
            raise StudioError("Tempo esgotado na comunicação. Confira o app antes de reenviar.") from exc
        except Exception as exc:
            raise StudioError(f"Falha ao publicar foto no feed: {str(exc)}") from exc

    def upload_album(
        self,
        paths: List[Path],
        caption: str = "",
        track_dict: Optional[Dict[str, Any]] = None,
        start_time_ms: int = 0
    ) -> Dict[str, Any]:
        """Upload a carousel album to feed with optional attached music track."""
        resolved = [Path(p).resolve() for p in paths]
        for p in resolved:
            if not p.is_file():
                raise StudioError(f"Slide de carrossel não encontrado: {p}")
        if len(resolved) < 2:
            raise StudioError("Carrossel requer no mínimo 2 imagens.")
        if len(resolved) > 10:
            raise StudioError("Carrossel suporta no máximo 10 imagens.")

        try:
            if track_dict:
                media = self.client.album_upload_with_music(
                    resolved,
                    caption=caption,
                    track=track_dict,
                    audio_asset_start_time=start_time_ms,
                    overlap_duration=30000
                )
            else:
                media = self.client.album_upload(resolved, caption=caption)

            pk = getattr(media, "pk", "") or getattr(media, "id", "")
            code = getattr(media, "code", "")
            return {
                "ok": True,
                "id": str(pk),
                "code": code,
                "url": f"https://www.instagram.com/p/{code}/" if code else ""
            }
        except (ClientRequestTimeout, ClientIncompleteReadError) as exc:
            raise StudioError("Tempo esgotado na comunicação do carrossel. Confira o app antes de reenviar.") from exc
        except Exception as exc:
            raise StudioError(f"Falha ao publicar carrossel no feed: {str(exc)}") from exc

    # -------------------------------------------------------------------------
    # 3. Profile & Bio Management
    # -------------------------------------------------------------------------
    def get_profile(self) -> Dict[str, Any]:
        """Fetch full profile data including bio, links and pinned media."""
        try:
            uid = self.client.user_id
            info = self.client.user_info(uid)
            recent_medias = self.client.user_medias(uid, amount=9)
            posts = []
            for m in (recent_medias or []):
                code = getattr(m, "code", "")
                thumb = getattr(m, "thumbnail_url", "")
                if not thumb and hasattr(m, "resources") and m.resources:
                    thumb = getattr(m.resources[0], "thumbnail_url", "")
                posts.append({
                    "id": str(getattr(m, "id", "") or getattr(m, "pk", "")),
                    "code": code,
                    "media_type": getattr(m, "media_type", 1),
                    "like_count": getattr(m, "like_count", 0),
                    "comment_count": getattr(m, "comment_count", 0),
                    "thumbnail_url": str(thumb or ""),
                    "caption": (getattr(m, "caption_text", "") or "")[:100]
                })

            return {
                "ok": True,
                "username": getattr(info, "username", ""),
                "full_name": getattr(info, "full_name", ""),
                "biography": getattr(info, "biography", ""),
                "external_url": getattr(info, "external_url", ""),
                "media_count": getattr(info, "media_count", 0),
                "follower_count": getattr(info, "follower_count", 0),
                "following_count": getattr(info, "following_count", 0),
                "profile_pic_url": str(getattr(info, "profile_pic_url", "") or ""),
                "recent_posts": posts
            }
        except Exception as exc:
            raise StudioError(f"Não foi possível consultar perfil: {str(exc)}") from exc

    def update_profile(
        self,
        biography: Optional[str] = None,
        external_url: Optional[str] = None
    ) -> Dict[str, Any]:
        """Update biography and/or external link in bio."""
        try:
            res = {}
            if biography is not None:
                self.client.account_set_biography(biography.strip())
                res["biography_updated"] = True
            if external_url is not None:
                if hasattr(self.client, "account_set_url"):
                    self.client.account_set_url(external_url.strip())
                res["url_updated"] = True
            return {"ok": True, **res}
        except Exception as exc:
            raise StudioError(f"Falha ao atualizar perfil: {str(exc)}") from exc

    def pin_media(self, media_id: str, pin: bool = True) -> Dict[str, Any]:
        """Pin or unpin media in profile grid."""
        try:
            pk = media_id.split("_")[0]
            if pin:
                self.client.media_pin(pk)
            else:
                self.client.media_unpin(pk)
            return {"ok": True, "pinned": pin}
        except Exception as exc:
            raise StudioError(f"Falha ao alterar fixação do post: {str(exc)}") from exc

    # -------------------------------------------------------------------------
    # 4. Direct Messages
    # -------------------------------------------------------------------------
    def send_direct(self, username: str, text: str) -> Dict[str, Any]:
        """Send a direct message to a user safely without spamming."""
        username = username.strip().lstrip("@")
        if not username:
            raise StudioError("Nome de usuário não informado.")
        if not text.strip():
            raise StudioError("Mensagem não informada.")

        try:
            uid = self.client.user_id_from_username(username)
            self.client.direct_send(text.strip(), user_ids=[uid])
            return {"ok": True, "recipient": username, "delivered": True}
        except Exception as exc:
            raise StudioError(f"Falha ao enviar DM para @{username}: {str(exc)}") from exc

    # -------------------------------------------------------------------------
    # 5. Viral & Trends Discovery
    # -------------------------------------------------------------------------
    def search_viral(self, target: str, is_hashtag: bool = False, amount: int = 9) -> List[Dict[str, Any]]:
        """Fetch top media from a hashtag or user profile for benchmarking."""
        clean_target = target.strip().lstrip("@").lstrip("#")
        if not clean_target:
            return []

        try:
            if is_hashtag:
                medias = self.client.hashtag_medias_top(clean_target, amount=amount)
            else:
                uid = self.client.user_id_from_username(clean_target)
                medias = self.client.user_medias(uid, amount=amount)

            results = []
            for m in (medias or []):
                thumb = getattr(m, "thumbnail_url", "")
                if not thumb and hasattr(m, "resources") and m.resources:
                    thumb = getattr(m.resources[0], "thumbnail_url", "")
                code = getattr(m, "code", "")
                likes = getattr(m, "like_count", 0)
                comms = getattr(m, "comment_count", 0)
                caption = getattr(m, "caption_text", "") or ""
                results.append({
                    "id": str(getattr(m, "id", "") or getattr(m, "pk", "")),
                    "code": code,
                    "url": f"https://www.instagram.com/p/{code}/" if code else "",
                    "thumbnail_url": str(thumb or ""),
                    "like_count": likes,
                    "comment_count": comms,
                    "caption": caption[:140] + ("..." if len(caption) > 140 else ""),
                    "media_type": getattr(m, "media_type", 1)
                })

            results.sort(key=lambda x: x["like_count"] + (x["comment_count"] * 2), reverse=True)
            return results
        except Exception as exc:
            logger.error("Erro ao minerar conteúdos virais: %s", exc)
            return []

    # -------------------------------------------------------------------------
    # 6. Metrics & Story Viewers
    # -------------------------------------------------------------------------
    def get_metrics(self) -> Dict[str, Any]:
        """Fetch live account telemetry: engagement, top posts, format averages."""
        try:
            uid = self.client.user_id
            info = self.client.user_info(uid)
            followers = int(getattr(info, "follower_count", 0))
            following = int(getattr(info, "following_count", 0))
            total_media = int(getattr(info, "media_count", 0))

            medias = self.client.user_medias(uid, amount=24)
            post_count = len(medias or [])
            total_likes = 0
            total_comments = 0

            format_data = {
                "photo": {"count": 0, "likes": 0, "comments": 0},
                "album": {"count": 0, "likes": 0, "comments": 0},
                "video": {"count": 0, "likes": 0, "comments": 0}
            }

            all_posts = []
            for m in (medias or []):
                likes = int(getattr(m, "like_count", 0) or 0)
                comms = int(getattr(m, "comment_count", 0) or 0)
                total_likes += likes
                total_comments += comms

                mtype_raw = getattr(m, "media_type", 1)
                mtype = "album" if mtype_raw == 8 else ("video" if mtype_raw == 2 else "photo")

                format_data[mtype]["count"] += 1
                format_data[mtype]["likes"] += likes
                format_data[mtype]["comments"] += comms

                code = getattr(m, "code", "")
                thumb = getattr(m, "thumbnail_url", "")
                if not thumb and hasattr(m, "resources") and m.resources:
                    thumb = getattr(m.resources[0], "thumbnail_url", "")

                all_posts.append({
                    "id": str(getattr(m, "id", "") or getattr(m, "pk", "")),
                    "code": code,
                    "url": f"https://www.instagram.com/p/{code}/" if code else "",
                    "thumbnail_url": str(thumb or ""),
                    "like_count": likes,
                    "comment_count": comms,
                    "engagement_score": likes + (comms * 2),
                    "caption": (getattr(m, "caption_text", "") or "")[:120],
                    "media_type": mtype
                })

            avg_likes = round(total_likes / post_count, 1) if post_count > 0 else 0
            avg_comments = round(total_comments / post_count, 1) if post_count > 0 else 0
            engagement_rate = round(((avg_likes + avg_comments) / followers) * 100, 2) if followers > 0 else 0.0

            format_comparison = {}
            for fmt, val in format_data.items():
                c = val["count"]
                format_comparison[fmt] = {
                    "count": c,
                    "avg_likes": round(val["likes"] / c, 1) if c > 0 else 0,
                    "avg_comments": round(val["comments"] / c, 1) if c > 0 else 0,
                }

            all_posts.sort(key=lambda x: x["engagement_score"], reverse=True)
            top_posts = all_posts[:5]

            return {
                "ok": True,
                "follower_count": followers,
                "following_count": following,
                "media_count": total_media,
                "sample_posts_count": post_count,
                "avg_likes": avg_likes,
                "avg_comments": avg_comments,
                "engagement_rate": engagement_rate,
                "format_comparison": format_comparison,
                "top_posts": top_posts
            }
        except Exception as exc:
            raise StudioError(f"Não foi possível compilar métricas: {str(exc)}") from exc

    def get_story_viewers(self) -> List[Dict[str, Any]]:
        """Fetch active stories and their nominal viewers."""
        try:
            uid = self.client.user_id
            stories = self.client.user_stories(uid)
            result = []
            for s in (stories or []):
                sid = str(getattr(s, "id", "") or getattr(s, "pk", ""))
                thumb = getattr(s, "thumbnail_url", "")
                viewers = []
                try:
                    raw_viewers = self.client.story_viewers(s.pk)
                    for v in (raw_viewers or []):
                        viewers.append({
                            "pk": str(getattr(v, "pk", "")),
                            "username": getattr(v, "username", ""),
                            "full_name": getattr(v, "full_name", ""),
                            "profile_pic_url": getattr(v, "profile_pic_url", "")
                        })
                except Exception:
                    pass

                result.append({
                    "id": sid,
                    "thumbnail_url": str(thumb or ""),
                    "viewer_count": len(viewers),
                    "viewers": viewers[:30]
                })
            return result
        except Exception as exc:
            logger.error("Erro ao buscar visualizadores de story: %s", exc)
            return []
