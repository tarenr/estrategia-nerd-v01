"""Durable, one-operation-at-a-time workflow for the separate Instagram module."""
import hashlib
import ipaddress
import json
import os
import re
import tempfile
from contextlib import contextmanager
from datetime import datetime, timezone
from pathlib import Path
from urllib.parse import parse_qs, parse_qsl, urlencode, urlsplit, urlunsplit

ACCOUNT = "estrategia_nerd"
HEX_ID = re.compile(r"^[a-f0-9]{32}$")
STORY_ID = re.compile(r"^\d{1,30}_\d{1,30}$")
NUM_ID = re.compile(r"^\d{1,30}$")


class WorkflowError(Exception):
    """Only intentionally safe, user-facing messages belong in this exception."""


def now():
    return datetime.now(timezone.utc).isoformat()


def atomic_json(path, value):
    path.parent.mkdir(parents=True, exist_ok=True)
    fd, name = tempfile.mkstemp(prefix=".write-", dir=path.parent)
    try:
        with os.fdopen(fd, "w", encoding="utf-8") as stream:
            json.dump(value, stream, ensure_ascii=False, separators=(",", ":"))
            stream.flush()
            os.fsync(stream.fileno())
        os.replace(name, path)
    finally:
        if os.path.exists(name):
            os.unlink(name)


def read_json(path):
    try:
        return json.loads(path.read_text(encoding="utf-8"))
    except (OSError, ValueError):
        raise WorkflowError("Registro local indisponível. Confira a instalação; nenhuma nova publicação foi iniciada.") from None


@contextmanager
def account_lock(root):
    root.mkdir(parents=True, exist_ok=True)
    with (root / "account.lock").open("a+b") as stream:
        stream.seek(0, 2)
        if stream.tell() == 0:
            stream.write(b"0")
            stream.flush()
        stream.seek(0)
        locked = False
        try:
            try:
                if os.name == "nt":
                    import msvcrt
                    msvcrt.locking(stream.fileno(), msvcrt.LK_NBLCK, 1)
                else:
                    import fcntl
                    fcntl.flock(stream.fileno(), fcntl.LOCK_EX | fcntl.LOCK_NB)
                locked = True
            except OSError:
                raise WorkflowError("Já existe uma operação em andamento nesta integração. Aguarde e atualize o histórico.") from None
            yield
        finally:
            if locked:
                stream.seek(0)
                if os.name == "nt":
                    import msvcrt
                    msvcrt.locking(stream.fileno(), msvcrt.LK_UNLCK, 1)
                else:
                    import fcntl
                    fcntl.flock(stream.fileno(), fcntl.LOCK_UN)


def valid_link(value):
    if not isinstance(value, str) or len(value) > 2048 or any(ord(c) < 32 for c in value):
        raise WorkflowError("O link deve ser uma URL HTTPS válida, com até 2048 caracteres.")
    if not value:
        return ""
    try:
        parsed = urlsplit(value)
        host = parsed.hostname or ""
        if parsed.scheme != "https" or not host or parsed.username or parsed.password or parsed.port not in (None, 443):
            raise ValueError()
        if host == "localhost" or host.endswith((".local", ".localhost")) or "." not in host:
            raise ValueError()
        try:
            address = ipaddress.ip_address(host)
        except ValueError:
            address = None
        if address is not None and not address.is_global:
            raise ValueError()
    except ValueError:
        raise WorkflowError("Use um link HTTPS público, sem usuário ou senha na URL.") from None
    return value


def comparable_story_link(value):
    """Normalize Instagram's redirect wrapper and volatile tracking params."""
    parsed = urlsplit(value)
    if (parsed.hostname or "").lower() == "l.instagram.com":
        wrapped = parse_qs(parsed.query).get("u", [])
        if wrapped:
            parsed = urlsplit(wrapped[0])
    query = urlencode(sorted(
        (key, item)
        for key, item in parse_qsl(parsed.query, keep_blank_values=True)
        if key.lower() != "fbclid"
    ))
    host = (parsed.hostname or "").lower()
    if parsed.port not in (None, 443):
        host = f"{host}:{parsed.port}"
    path = parsed.path or "/"
    if path != "/":
        path = path.rstrip("/")
    return urlunsplit((parsed.scheme.lower(), host, path, query, ""))


class Workflow:
    def __init__(self, root, adapter=None):
        self.root = Path(root)
        self.api = adapter

    def asset(self, asset_id, kind="story"):
        if not isinstance(asset_id, str) or not HEX_ID.fullmatch(asset_id):
            raise WorkflowError("Identificador da imagem inválido.")
        assets = (self.root / "assets").resolve()
        path = assets / (asset_id + ".jpg")
        if not path.is_file() or path.is_symlink() or path.resolve().parent != assets:
            raise WorkflowError("Imagem não encontrada na biblioteca privada.")
        if not 0 < path.stat().st_size <= 8 * 1024 * 1024:
            raise WorkflowError("Cada imagem deve ter no máximo 8 MB.")
        from PIL import Image
        try:
            with Image.open(path) as image:
                dimensions = image.size
                is_jpeg = image.format == "JPEG"
                image.verify()
            valid_size = dimensions == (1080, 1920) if kind == "story" else dimensions[0] == dimensions[1] and 320 <= dimensions[0] <= 2160
            if not is_jpeg or not valid_size:
                raise ValueError()
        except Exception:
            raise WorkflowError("Use JPEG 1080×1920 para stories e JPEG quadrado de 320 a 2160 px para capa.") from None
        return path, hashlib.sha256(path.read_bytes()).hexdigest()

    def normalize(self, data):
        if not isinstance(data, dict) or data.get("account") != ACCOUNT:
            raise WorkflowError("Esta integração está vinculada ao @estrategia_nerd.")
        job_id = data.get("job_id", "")
        if not isinstance(job_id, str) or not HEX_ID.fullmatch(job_id):
            raise WorkflowError("Identificador da operação inválido.")
        items = data.get("items")
        if not isinstance(items, list) or not 1 <= len(items) <= 20:
            raise WorkflowError("Adicione de 1 a 20 stories por operação.")
        clean = []
        seen = set()
        for item in items:
            if not isinstance(item, dict):
                raise WorkflowError("Story inválido.")
            if item.get("story_id"):
                sid = item["story_id"]
                if not isinstance(sid, str) or not STORY_ID.fullmatch(sid) or item.get("asset") or item.get("link") or sid in seen:
                    raise WorkflowError("Informe um ID completo e único para cada story existente, sem imagem ou link novo.")
                seen.add(sid)
                clean.append({"story_id": sid})
            else:
                _, digest = self.asset(item.get("asset"))
                clean.append({"asset": item["asset"], "sha256": digest, "link": valid_link(item.get("link", ""))})
        target = data.get("highlight")
        if not isinstance(target, dict):
            raise WorkflowError("Escolha um destaque existente ou informe o nome do novo destaque.")
        hid, title, cover = target.get("id", ""), target.get("title", ""), target.get("cover_asset", "")
        if not all(isinstance(x, str) for x in (hid, title, cover)) or (hid and not NUM_ID.fullmatch(hid)):
            raise WorkflowError("Destaque inválido.")
        title = title.strip()
        if len(title) > 15 or any(ord(c) < 32 for c in title) or (not hid and not title):
            raise WorkflowError("O novo destaque precisa de um nome com até 15 caracteres.")
        normalized_target = {"id": hid, "title": title, "cover_asset": cover}
        if cover:
            _, normalized_target["cover_sha256"] = self.asset(cover, "cover")
        return {"version": 1, "job_id": job_id, "account": ACCOUNT, "items": clean, "highlight": normalized_target}

    def job_dir(self, job_id):
        if not isinstance(job_id, str) or not HEX_ID.fullmatch(job_id):
            raise WorkflowError("Identificador da operação inválido.")
        return self.root / "jobs" / job_id

    def load(self, job_id):
        path = self.job_dir(job_id)
        return read_json(path / "manifest.json"), read_json(path / "state.json")

    def save(self, manifest, state):
        state["updated_at"] = now()
        atomic_json(self.job_dir(manifest["job_id"]) / "state.json", state)

    def prepare(self, data):
        with account_lock(self.root):
            manifest = self.normalize(data)
            path = self.job_dir(manifest["job_id"])
            if (path / "manifest.json").exists():
                old, state = self.load(manifest["job_id"])
                if old != manifest:
                    raise WorkflowError("Esta operação já foi revisada com outro conteúdo. Crie uma nova revisão.")
                return self.public(old, state)
            atomic_json(path / "manifest.json", manifest)
            state = {"status": "ready", "created_at": now(), "updated_at": now(), "results": {}, "pending": None, "message": "Revisão pronta. Nenhum conteúdo publicado."}
            self.save(manifest, state)
            return self.public(manifest, state)

    def actions(self, manifest):
        actions = [{"key": f"story:{i}", "type": "upload", "index": i} for i, item in enumerate(manifest["items"]) if "asset" in item]
        target = manifest["highlight"]
        actions.append({"key": "target", "type": "add" if target["id"] else "create"})
        if target["id"] and target["title"]:
            actions.append({"key": "title", "type": "rename"})
        if target["cover_asset"]:
            actions.append({"key": "cover", "type": "cover"})
        return actions

    def public(self, manifest, state):
        actions = self.actions(manifest)
        remaining = [a for a in actions if a["key"] not in state["results"]]
        status = state["status"]
        # A process may have died after a remote write. Pending is never retryable.
        if state.get("pending") and status == "running":
            status = "unknown"
        return {"id": manifest["job_id"], "status": status, "created_at": state["created_at"], "updated_at": state["updated_at"], "completed_steps": len(actions) - len(remaining), "total_steps": len(actions), "message": state["message"], "highlight_id": str(state["results"].get("target", manifest["highlight"]["id"])), "items": manifest["items"], "highlight": manifest["highlight"], "next_action": remaining[0]["type"] if remaining else "done"}

    def job(self, job_id):
        return self.public(*self.load(job_id))

    def status(self):
        jobs = []
        for path in sorted((self.root / "jobs").glob("*/state.json"), key=lambda p: p.stat().st_mtime, reverse=True)[:50]:
            try:
                jobs.append(self.job(path.parent.name))
            except WorkflowError:
                continue
        verified = self.root / "verified.json"
        return {"account": ACCOUNT, "runtime_ready": True, "session_stored": (self.root / "auth/session.json").is_file(), "verified_at": read_json(verified).get("verified_at") if verified.exists() else None, "jobs": jobs}

    def identity(self):
        if self.api is None:
            raise WorkflowError("Conecte a conta pelo terminal antes de consultar ou publicar.")
        identity = self.api.identity()
        if identity.get("username") != ACCOUNT or not NUM_ID.fullmatch(str(identity.get("id", ""))):
            raise WorkflowError("A sessão conectada não corresponde ao @estrategia_nerd. Nenhuma publicação iniciada.")
        bound = self.root / "account.json"
        if bound.exists() and read_json(bound).get("id") != str(identity["id"]):
            raise WorkflowError("O identificador da conta mudou. A operação foi bloqueada.")
        atomic_json(bound, {"id": str(identity["id"]), "username": ACCOUNT})
        atomic_json(self.root / "verified.json", {"verified_at": now()})
        return str(identity["id"])

    def own_story(self, sid, uid, link=""):
        story = self.api.story_info(sid)
        if str(story.get("owner")) != uid or str(story.get("id")) != sid:
            raise WorkflowError("O story informado não pertence à conta conectada.")
        if link:
            if comparable_story_link(link) not in [comparable_story_link(url) for url in story.get("links", [])]:
                raise WorkflowError("O Instagram não confirmou o link do story. Confira no aplicativo antes de continuar.")
        return story

    def own_target(self, hid, uid, stories=None):
        target = self.api.highlight_info(hid)
        if str(target.get("owner")) != uid or str(target.get("id")) != hid:
            raise WorkflowError("O destaque não pertence à conta conectada.")
        if stories is not None and not set(stories).issubset(set(target.get("stories", []))):
            raise WorkflowError("O Instagram ainda não confirmou todos os stories no destaque.")
        return target

    def story_ids(self, manifest, state):
        return [item.get("story_id") or state["results"].get(f"story:{i}") for i, item in enumerate(manifest["items"])]

    def step(self, job_id):
        with account_lock(self.root):
            manifest, state = self.load(job_id)
            if state["status"] == "completed":
                return self.public(manifest, state)
            if state.get("pending"):
                state.update(status="unknown", message="Resultado não confirmado. Confira no Instagram e reconcilie antes de continuar.")
                self.save(manifest, state)
                return self.public(manifest, state)
            for path in (self.root / "jobs").glob("*/state.json"):
                if path.parent.name != job_id and read_json(path).get("pending"):
                    raise WorkflowError("Existe outra operação sem confirmação. Resolva-a no histórico antes de publicar.")
            if self.normalize(manifest) != manifest:
                raise WorkflowError("Uma imagem foi alterada depois da revisão. Faça uma nova revisão.")
            uid = self.identity()
            target = manifest["highlight"]
            if target["id"]:
                self.own_target(target["id"], uid)
            for i, item in enumerate(manifest["items"]):
                sid = item.get("story_id") or state["results"].get(f"story:{i}")
                if sid:
                    self.own_story(sid, uid, item.get("link", ""))
            actions = [a for a in self.actions(manifest) if a["key"] not in state["results"]]
            if not actions:
                state.update(status="completed", message="Stories e destaque confirmados pelo Instagram.")
                self.save(manifest, state)
                return self.public(manifest, state)
            action = actions[0]
            state.update(status="running", pending=action, message="Operação enviada. Aguarde a confirmação.")
            self.save(manifest, state)
            try:
                result = self.execute(action, manifest, state, uid)
            except Exception:
                state.update(status="unknown", message="Não foi possível confirmar a operação. Não reenvie: confira no Instagram e reconcilie este registro.")
                self.save(manifest, state)
                return self.public(manifest, state)
            state["results"][action["key"]] = result
            state.update(pending=None, status="completed" if len(actions) == 1 else "ready", message="Stories e destaque confirmados pelo Instagram." if len(actions) == 1 else "Etapa confirmada. Pronto para continuar.")
            self.save(manifest, state)
            return self.public(manifest, state)

    def execute(self, action, manifest, state, uid):
        target = manifest["highlight"]
        kind = action["type"]
        if kind == "upload":
            item = manifest["items"][action["index"]]
            sid = str(self.api.upload(self.asset(item["asset"])[0], item["link"]))
            self.own_story(sid, uid, item["link"])
            return sid
        ids = self.story_ids(manifest, state)
        if kind == "create":
            hid = str(self.api.create(target["title"], ids))
            self.own_target(hid, uid, ids)
            return hid
        hid = str(state["results"].get("target") or target["id"])
        current = self.own_target(hid, uid)
        if kind == "add":
            missing = [sid for sid in ids if sid not in current.get("stories", [])]
            if missing:
                self.api.add(hid, missing)
            self.own_target(hid, uid, ids)
            return hid
        if kind == "rename":
            if current["title"] != target["title"]:
                self.api.rename(hid, target["title"])
            if self.own_target(hid, uid)["title"] != target["title"]:
                raise WorkflowError("Nome ainda não confirmado.")
            return True
        if kind == "cover":
            self.api.cover(hid, self.asset(target["cover_asset"], "cover")[0])
            return True
        raise WorkflowError("Operação inválida.")

    def reconcile(self, job_id, remote_id, verified_visual):
        if verified_visual is not True:
            raise WorkflowError("Confira visualmente o resultado no Instagram antes de confirmar a reconciliação.")
        with account_lock(self.root):
            manifest, state = self.load(job_id)
            action = state.get("pending")
            if not action:
                raise WorkflowError("Esta operação não precisa de reconciliação.")
            uid = self.identity()
            kind = action["type"]
            if kind == "upload":
                if not isinstance(remote_id, str) or not STORY_ID.fullmatch(remote_id):
                    raise WorkflowError("Informe o ID completo do story que você conferiu no Instagram.")
                item = manifest["items"][action["index"]]
                self.own_story(remote_id, uid, item["link"])
                if remote_id in state["results"].values():
                    raise WorkflowError("Esse story já foi associado a outra etapa.")
                result = remote_id
            else:
                hid = str(state["results"].get("target") or manifest["highlight"]["id"] or remote_id)
                if not NUM_ID.fullmatch(hid):
                    raise WorkflowError("Informe o ID do destaque que você conferiu no Instagram.")
                target = self.own_target(hid, uid, self.story_ids(manifest, state))
                if kind in ("create", "rename") and target["title"] != manifest["highlight"]["title"]:
                    raise WorkflowError("O nome do destaque ainda não corresponde ao resultado esperado.")
                result = hid if kind in ("create", "add") else True
            state["results"][action["key"]] = result
            state.update(pending=None, status="ready", message="Resultado conferido e conciliado. Pode continuar.")
            self.save(manifest, state)
            return self.public(manifest, state)

    def catalog(self):
        with account_lock(self.root):
            uid = self.identity()
            return {"verified_at": now(), "highlights": [{"id": str(h["id"]), "title": h["title"], "media_count": len(h.get("stories", []))} for h in self.api.highlights() if str(h.get("owner")) == uid]}
