"""CLI interface for Instagram Studio engine."""
import argparse
import json
import os
import sys
from pathlib import Path

if sys.platform == "win32":
    try:
        sys.stdout.reconfigure(encoding="utf-8")
        sys.stderr.reconfigure(encoding="utf-8")
        sys.stdin.reconfigure(encoding="utf-8")
    except Exception:
        pass

SCRIPT_DIR = Path(__file__).resolve().parent
PROJECT_ROOT = SCRIPT_DIR.parent.parent
STORAGE_ROOT = PROJECT_ROOT / "storage" / "instagram-highlights"
SESSION_FILE = STORAGE_ROOT / "auth" / "session.json"

if str(SCRIPT_DIR) not in sys.path:
    sys.path.insert(0, str(SCRIPT_DIR))

from adapter import InstagramStudioAdapter, StudioError

ALLOWED_ACTIONS = {
    "status",
    "search_music",
    "trending_music",
    "upload_feed",
    "get_profile",
    "update_profile",
    "pin_media",
    "send_dm",
    "search_viral",
    "metrics",
    "story_viewers"
}


def read_stdin_json():
    try:
        raw = sys.stdin.read(131073).strip()
        if len(raw) > 131072:
            raise ValueError('Input too large')
        if not raw:
            return {}
        return json.loads(raw)
    except Exception:
        raise StudioError("Formato de entrada JSON inválido.")


def output_json(data, status_code=0):
    text = json.dumps(data, ensure_ascii=False, separators=(",", ":"), default=str)
    sys.stdout.write(text)
    sys.stdout.flush()
    sys.exit(status_code)


def main():
    parser = argparse.ArgumentParser(description="Instagram Studio CLI")
    parser.add_argument("action", choices=list(ALLOWED_ACTIONS), help="Ação a executar")
    parser.add_argument("--root", help="Caminho raiz do armazenamento local", default=str(STORAGE_ROOT))
    args = parser.parse_args()

    root = Path(args.root).resolve()
    session_file = root / "auth" / "session.json"

    try:
        action = args.action
        adapter = InstagramStudioAdapter(session_path=session_file)

        if action == "status":
            result = adapter.check_connection()
            output_json({"ok": True, **result})

        elif action == "search_music":
            payload = read_stdin_json()
            query = payload.get("query", "")
            tracks = adapter.search_music(query)
            output_json({"ok": True, "tracks": tracks})

        elif action == "trending_music":
            tracks = adapter.trending_music()
            output_json({"ok": True, "tracks": tracks})

        elif action == "upload_feed":
            payload = read_stdin_json()
            media_type = payload.get("media_type", "photo")  # 'photo' or 'album'
            caption = payload.get("caption", "")
            track = payload.get("track")  # track dict
            start_time_ms = int(payload.get("start_time_ms", 0))

            if media_type == "album":
                paths = [Path(p) for p in payload.get("paths", [])]
                res = adapter.upload_album(paths, caption=caption, track_dict=track, start_time_ms=start_time_ms)
            else:
                path = Path(payload.get("path", ""))
                res = adapter.upload_photo(path, caption=caption, track_dict=track, start_time_ms=start_time_ms)

            output_json({"ok": True, **res})

        elif action == "get_profile":
            res = adapter.get_profile()
            output_json({"ok": True, **res})

        elif action == "update_profile":
            payload = read_stdin_json()
            bio = payload.get("biography")
            url = payload.get("external_url")
            res = adapter.update_profile(biography=bio, external_url=url)
            output_json({"ok": True, **res})

        elif action == "pin_media":
            payload = read_stdin_json()
            media_id = payload.get("media_id", "")
            pin = bool(payload.get("pin", True))
            res = adapter.pin_media(media_id=media_id, pin=pin)
            output_json({"ok": True, **res})

        elif action == "send_dm":
            payload = read_stdin_json()
            username = payload.get("username", "")
            text = payload.get("text", "")
            res = adapter.send_direct(username=username, text=text)
            output_json({"ok": True, **res})

        elif action == "search_viral":
            payload = read_stdin_json()
            target = payload.get("target", "")
            is_hashtag = bool(payload.get("is_hashtag", False))
            amount = int(payload.get("amount", 9))
            posts = adapter.search_viral(target=target, is_hashtag=is_hashtag, amount=amount)
            output_json({"ok": True, "posts": posts})

        elif action == "metrics":
            res = adapter.get_metrics()
            output_json({"ok": True, "metrics": res})

        elif action == "story_viewers":
            viewers = adapter.get_story_viewers()
            output_json({"ok": True, "stories": viewers})

        else:
            raise StudioError(f"Ação '{action}' desconhecida.")

    except StudioError as exc:
        output_json({"ok": False, "message": str(exc)}, status_code=0)
    except Exception as exc:
        output_json({"ok": False, "message": f"Erro interno: {str(exc)}"}, status_code=0)


if __name__ == "__main__":
    main()
