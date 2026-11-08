"""Interactive, terminal-only login for the separate Instagram integration."""
import getpass
import os
import re
import subprocess
import sys
from pathlib import Path

from instagrapi.exceptions import (
    BadPassword,
    ChallengeRequired,
    ClientForbiddenError,
    ClientLoginRequired,
    FeedbackRequired,
    PleaseWaitFewMinutes,
    TwoFactorRequired,
)

from adapter import SingleAttemptClient
from workflow import ACCOUNT, NUM_ID, WorkflowError, account_lock, atomic_json, now, read_json

PROJECT_ROOT = Path(__file__).resolve().parents[2]
STORAGE_ROOT = PROJECT_ROOT / "storage" / "instagram-highlights"
SESSION_PATH = STORAGE_ROOT / "auth" / "session.json"

# Pass the path as environment data; never interpolate it into shell code.
# Replace the whole DACL, including any pre-existing explicit grants.
WINDOWS_ACL_SCRIPT = r"""
$ErrorActionPreference = 'Stop'
$directory = $env:INSTAGRAM_AUTH_DIRECTORY
$sid = [System.Security.Principal.WindowsIdentity]::GetCurrent().User
$system = [System.Security.Principal.SecurityIdentifier]::new('S-1-5-18')
$allowed = @($sid.Value, $system.Value) | Select-Object -Unique
$targets = @(Get-Item -LiteralPath $directory -Force)
$session = Join-Path $directory 'session.json'
if (Test-Path -LiteralPath $session) { $targets += Get-Item -LiteralPath $session -Force }
foreach ($target in $targets) {
    if ($target.Attributes -band [System.IO.FileAttributes]::ReparsePoint) { throw 'Invalid path' }
    $accessSection = [System.Security.AccessControl.AccessControlSections]::Access
    $acl = $target.GetAccessControl($accessSection)
    $acl.SetAccessRuleProtection($true, $false)
    $currentRules = $acl.GetAccessRules($true, $true, [System.Security.Principal.SecurityIdentifier])
    foreach ($r in $currentRules) {
        $acl.PurgeAccessRules($r.IdentityReference)
    }
    $inheritance = if ($target.PSIsContainer) {
        [System.Security.AccessControl.InheritanceFlags]'ContainerInherit,ObjectInherit'
    } else {
        [System.Security.AccessControl.InheritanceFlags]::None
    }
    foreach ($identity in $allowed) {
        $principal = [System.Security.Principal.SecurityIdentifier]::new($identity)
        $rule = [System.Security.AccessControl.FileSystemAccessRule]::new(
            $principal, [System.Security.AccessControl.FileSystemRights]::FullControl,
            $inheritance, [System.Security.AccessControl.PropagationFlags]::None,
            [System.Security.AccessControl.AccessControlType]::Allow)
        $acl.AddAccessRule($rule)
    }
    $target.SetAccessControl($acl)
    $actual = $target.GetAccessControl($accessSection)
    $rules = @($actual.GetAccessRules($true, $true, [System.Security.Principal.SecurityIdentifier]))
    if (-not $actual.AreAccessRulesProtected -or $rules.Count -ne $allowed.Count) { throw 'ACL verification failed' }
    foreach ($rule in $rules) {
        if ($rule.IdentityReference.Value -notin $allowed -or $rule.IsInherited -or
            $rule.AccessControlType -ne [System.Security.AccessControl.AccessControlType]::Allow -or
            $rule.FileSystemRights -ne [System.Security.AccessControl.FileSystemRights]::FullControl) {
            throw 'ACL verification failed'
        }
    }
}
"""


def restrict_permissions_windows(path: Path):
    """Protect the isolated auth directory before creating any session bytes."""
    if path.is_symlink() or path.is_junction():
        raise WorkflowError("A pasta de autenticação deve ser uma pasta local privada.")
    path.mkdir(parents=True, exist_ok=True)
    if os.name == "nt":
        env = dict(os.environ, INSTAGRAM_AUTH_DIRECTORY=str(path.resolve()))
        subprocess.run(
            ["powershell.exe", "-NoProfile", "-NonInteractive", "-Command", WINDOWS_ACL_SCRIPT],
            env=env, stdin=subprocess.DEVNULL, stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL, timeout=20, check=True, shell=False,
            creationflags=subprocess.CREATE_NO_WINDOW,
        )
    else:
        path.chmod(0o700)
        session = path / "session.json"
        if session.is_symlink():
            raise WorkflowError("O arquivo da sessão deve ser privado e local.")
        if session.exists():
            session.chmod(0o600)


def authenticate():
    """Hold the shared account lock throughout login, identity checks and save."""
    with account_lock(STORAGE_ROOT):
        # Fail before login as well as persistence when private storage is unavailable.
        restrict_permissions_windows(SESSION_PATH.parent)
        client = SingleAttemptClient()

        def on_challenge_code(username, choice=None):
            method_desc = f" via {choice}" if choice else ""
            print(f"\n[Verificação de Segurança] O Instagram enviou um código{method_desc}.")
            return input("Digite o código de verificação recebido (SMS/E-mail): ").strip()

        client.challenge_code_handler = on_challenge_code

        password = getpass.getpass(f"Digite a senha da conta @{ACCOUNT}: ")
        if not password:
            raise WorkflowError("Senha vazia. Operação cancelada.")
        try:
            try:
                logged_in = client.login(ACCOUNT, password)
            except TwoFactorRequired:
                code = input("Digite o código 2FA de 6 dígitos: ").strip()
                if not re.fullmatch(r"[0-9]{6}", code):
                    raise WorkflowError("Código 2FA inválido. Operação cancelada.")
                logged_in = client.login(ACCOUNT, password, verification_code=code)
            except ChallengeRequired:
                print("\n[Desafio Detectado] Resolvendo desafio de segurança do Instagram...")
                try:
                    client.challenge_resolve(client.last_json)
                    logged_in = True
                except Exception as c_exc:
                    raise WorkflowError(f"Não foi possível concluir o desafio de segurança: {c_exc}") from c_exc

            if not logged_in:
                raise WorkflowError("Login não concluído. Confira o aplicativo oficial.")

            remote = client.account_info()
            uid = str(remote.pk)
            if (str(remote.username).lower() != ACCOUNT or not NUM_ID.fullmatch(uid)
                    or uid != str(client.user_id)):
                raise WorkflowError("A conta autenticada não corresponde ao @estrategia_nerd.")
            bound_path = STORAGE_ROOT / "account.json"
            if bound_path.exists():
                bound = read_json(bound_path)
                if not isinstance(bound, dict) or bound.get("id") != uid or bound.get("username") != ACCOUNT:
                    raise WorkflowError("O identificador da conta mudou. A sessão existente foi preservada.")

            # get_settings() in the pinned version contains session/device data,
            # never the client's in-memory password or verification code.
            settings = client.get_settings()
            if not isinstance(settings, dict) or "password" in settings or "verification_code" in settings:
                raise WorkflowError("Formato de sessão inesperado. Nada foi persistido.")
            atomic_json(bound_path, {"id": uid, "username": ACCOUNT})
            atomic_json(SESSION_PATH, settings)
            atomic_json(STORAGE_ROOT / "verified.json", {"verified_at": now()})
        finally:
            password = None
            client.password = None


def main():
    print(f"Autenticação pelo terminal — @{ACCOUNT}")
    print("A senha não será salva. A sessão ficará em armazenamento privado local.")
    try:
        authenticate()
    except BadPassword:
        print("\n❌ Senha incorreta. Confira suas credenciais no aplicativo oficial do Instagram.")
        return 1
    except ChallengeRequired:
        print("\n⚠️ Desafio solicitado. Confira o aplicativo oficial do Instagram no celular.")
        return 1
    except TwoFactorRequired:
        print("\n❌ O código 2FA informado não concluiu o login. Confira o código no aplicativo.")
        return 1
    except FeedbackRequired as exc:
        print(f"\n⚠️ O Instagram solicitou confirmação de atividade (Feedback Required): {getattr(exc, 'message', str(exc))}")
        print("👉 Abra o aplicativo oficial do Instagram no celular e toque em 'Fui eu' para autorizar o acesso deste computador.")
        return 1
    except PleaseWaitFewMinutes:
        print("\n⏳ O Instagram solicitou aguardar alguns minutos devido a tentativas recentes. Aguarde de 5 a 10 minutos antes de tentar novamente.")
        return 1
    except ClientLoginRequired as exc:
        print(f"\n⚠️ Login requerido: {exc}")
        return 1
    except ClientForbiddenError as exc:
        print(f"\n🚫 Acesso recusado pelo Instagram: {exc}")
        return 1
    except WorkflowError as exc:
        print(f"\n❌ {str(exc)}")
        return 1
    except (KeyboardInterrupt, EOFError):
        print("\nAutenticação cancelada.")
        return 1
    except Exception:
        print("\nNão foi possível concluir a autenticação e o armazenamento privado. Nenhuma verificação foi confirmada nesta tentativa.")
        return 1
    print("\n✅ Sessão privada salva com sucesso! Identidade da conta confirmada pelo Instagram.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
