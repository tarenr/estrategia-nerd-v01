"""Monta o texto da legenda do Castlevania (0–44,57 s): texto oficial da locução + tempo de cada palavra medido pelo Whisper.
Cópia de manifesto-estrategia-nerd/ferramentas/montar_legenda_texto.py, com os caminhos e o texto deste vídeo.
Lê audio/whisper_palavras_locucao_corrigida.json e grava v2/legenda/legenda_texto.json (blocos de uma linha).
Palavras do roteiro sem par no áudio são interpoladas e listadas como AVISO.
"""
import difflib
import json
import re
import unicodedata
from pathlib import Path

AQUI = Path(__file__).resolve().parent
ENT = AQUI.parent.parent / "audio" / "whisper_palavras_locucao_corrigida.json"
SAI = AQUI / "legenda_texto.json"
TEXTO = json.loads(ENT.read_text(encoding="utf-8"))["texto_oficial"]
DESTAQUE = {"castlevania", "2d", "3d", "segredo", "richter", "castelo", "invertido", "metroidvania", "comentários"}


def norm(s):
    s = unicodedata.normalize("NFD", s.lower())
    return re.sub(r"[^a-z0-9]", "", "".join(c for c in s if unicodedata.category(c) != "Mn"))


def main():
    ws = json.loads(ENT.read_text(encoding="utf-8"))["palavras"]
    ref = TEXTO.split()
    a = [norm(w["palavra"]) for w in ws]
    b = [norm(w) for w in ref]
    sm = difflib.SequenceMatcher(None, a, b, autojunk=False)
    tempos = [None] * len(ref)
    avisos = []
    for tag, i1, i2, j1, j2 in sm.get_opcodes():
        if tag == "equal":
            for k in range(j2 - j1):
                tempos[j1 + k] = (ws[i1 + k]["ini"], ws[i1 + k]["fim"])
        elif tag == "replace" and (i2 - i1) > 0:
            ini, fim = ws[i1]["ini"], ws[i2 - 1]["fim"]
            n = j2 - j1
            for k in range(n):
                tempos[j1 + k] = (ini + (fim - ini) * k / n, ini + (fim - ini) * (k + 1) / n)
            avisos.append(f"áudio '{' '.join(w['palavra'] for w in ws[i1:i2])}' -> roteiro '{' '.join(ref[j1:j2])}'")
        elif tag == "insert":
            avisos.append(f"SEM ÁUDIO para o roteiro '{' '.join(ref[j1:j2])}'")
        elif tag == "delete":
            avisos.append(f"áudio sem roteiro: '{' '.join(w['palavra'] for w in ws[i1:i2])}'")
    for k in range(len(tempos)):  # interpola faltantes
        if tempos[k] is None:
            ant = next((tempos[j][1] for j in range(k - 1, -1, -1) if tempos[j]), 0.0)
            pos = next((tempos[j][0] for j in range(k + 1, len(tempos)) if tempos[j]), ant + 0.3)
            tempos[k] = (ant, pos)
    # blocos de UMA linha com letra grande: no máximo MAXC letras por bloco
    MAXC = 14
    blocos, atual = [], []
    for k, w in enumerate(ref):
        if atual and len(" ".join(ref[j] for j in atual + [k])) > MAXC:
            # evita terminar o bloco numa palavrinha solta ("a", "e", "o", "de", "na"...): ela passa para o próximo
            if len(atual) > 1 and len(ref[atual[-1]]) <= 2:
                blocos.append(atual[:-1])
                atual = [atual[-1]]
            else:
                blocos.append(atual)
                atual = []
        atual.append(k)
        fim_frase = bool(re.search(r"[.,:?!]$", w))
        pausa = k + 1 < len(ref) and tempos[k + 1][0] - tempos[k][1] > 0.25
        if fim_frase or pausa:
            blocos.append(atual)
            atual = []
    if atual:
        blocos.append(atual)
    out = []
    for n, bl in enumerate(blocos):
        ini = tempos[bl[0]][0]
        prox = tempos[blocos[n + 1][0]][0] if n + 1 < len(blocos) else tempos[bl[-1]][1] + 0.6
        fim = min(prox, tempos[bl[-1]][1] + 0.6)
        out.append({"ini": round(ini, 3), "fim": round(fim, 3),
                    "palavras": [{"p": ref[j], "ini": round(tempos[j][0], 3), "fim": round(tempos[j][1], 3),
                                  "destaque": norm(ref[j]) in {norm(x) for x in DESTAQUE}} for j in bl]})
    SAI.write_text(json.dumps({"fonte_tempos": ENT.name, "blocos": out}, ensure_ascii=False, indent=1), encoding="utf-8")
    print(f"blocos: {len(out)}  palavras: {len(ref)}")
    for b_ in out:
        print(f"{b_['ini']:6.2f}–{b_['fim']:6.2f}  " + " ".join(p["p"] for p in b_["palavras"]))
    print("AVISOS:")
    for a_ in avisos:
        print("  ", a_)


if __name__ == "__main__":
    main()
