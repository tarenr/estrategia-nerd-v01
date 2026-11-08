"""CLI interface for Instagram highlights engine."""
import argparse
import json
import os
import sys
from pathlib import Path

# Insert parent directory so imports work
SCRIPT_DIR = Path(__file__).resolve().parent
PROJECT_ROOT = SCRIPT_DIR.parent.parent
STORAGE_ROOT = PROJECT_ROOT / "storage" / "instagram-highlights"

if str(SCRIPT_DIR) not in sys.path:
    sys.path.insert(0, str(SCRIPT_DIR))

from workflow import Workflow, WorkflowError
from adapter import InstagrapiAdapter

ALLOWED_ACTIONS = {"status", "job", "prepare", "step", "catalog", "reconcile"}


def read_stdin_json():
    try:
        raw = sys.stdin.read(131073).strip()
        if len(raw) > 131072:
            raise ValueError('input limit')
        if not raw:
            return {}
        return json.loads(raw)
    except Exception:
        raise WorkflowError("Formato de dados JSON inválido.")


def output_json(data, status_code=0):
    text = json.dumps(data, ensure_ascii=False, separators=(",", ":"))
    sys.stdout.write(text)
    sys.stdout.flush()
    sys.exit(status_code)


def main():
    parser = argparse.ArgumentParser(description="Instagram Highlights CLI")
    parser.add_argument("action", choices=list(ALLOWED_ACTIONS), help="Ação a executar")
    parser.add_argument("--root", help="Caminho raiz do armazenamento local", default=str(STORAGE_ROOT))
    parser.add_argument("--id", help="Identificador do job (opcional)", default=None)
    args = parser.parse_args()

    root = Path(args.root).resolve()
    session_file = root / "auth" / "session.json"
    try:
        action = args.action
        adapter = (InstagrapiAdapter(session_path=session_file)
                   if action in {'step', 'catalog', 'reconcile'} and session_file.is_file() else None)
        wf = Workflow(root, adapter=adapter)

        if action == "status":
            result = wf.status()
            output_json({"ok": True, **result})

        elif action == "job":
            payload = read_stdin_json() if not args.id else {"job_id": args.id}
            job_id = payload.get("job_id") or args.id
            if not job_id:
                raise WorkflowError("Identificador da operação não informado.")
            if not wf.job_dir(job_id).exists():
                output_json({'ok': False, 'code': 'JOB_NOT_FOUND', 'message': 'Esta revisão não foi registrada. Nenhuma etapa desta operação foi iniciada.'})
            job_data = wf.job(job_id)
            output_json({"ok": True, "job": job_data})

        elif action == "prepare":
            payload = read_stdin_json()
            job_data = wf.prepare(payload)
            output_json({"ok": True, "job": job_data})

        elif action == "step":
            payload = read_stdin_json()
            job_id = payload.get("job_id") or args.id
            if not job_id:
                raise WorkflowError("Identificador da operação não informado.")
            job_data = wf.step(job_id)
            output_json({"ok": True, "job": job_data})

        elif action == "catalog":
            cat_data = wf.catalog()
            output_json({"ok": True, **cat_data})

        elif action == "reconcile":
            payload = read_stdin_json()
            job_id = payload.get("job_id") or args.id
            remote_id = payload.get("remote_id")
            verified_visual = payload.get("verified_visual", False)
            job_data = wf.reconcile(job_id, remote_id, verified_visual)
            output_json({"ok": True, "job": job_data})

        else:
            raise WorkflowError(f"Ação '{action}' não suportada.")

    except WorkflowError as exc:
        output_json({"ok": False, "message": str(exc)}, status_code=0)
    except Exception as exc:
        output_json(
            {"ok": False, "message": "Erro operacional interno. A publicação não foi continuada."},
            status_code=0,
        )


if __name__ == "__main__":
    sys.stdin.reconfigure(encoding='utf-8')
    sys.stdout.reconfigure(encoding='utf-8')
    main()
