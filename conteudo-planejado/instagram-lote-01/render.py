"""Renderiza os cards locais com Chrome headless, sem publicar ou acessar o banco."""
import json, html, pathlib, subprocess, concurrent.futures, struct, sys
ROOT = pathlib.Path(__file__).resolve().parent
CHROME = pathlib.Path(r"C:\Program Files\Google\Chrome\Application\chrome.exe")
POSTS = json.loads((ROOT / "conteudo.json").read_text(encoding="utf-8-sig"))
BRAND = ROOT.parent.parent / "public/assets/brand/logo-symbol.png"
def esc(x): return html.escape(str(x))
def lines(x): return esc(x).replace("\n","<br>")
def diagram(p,idx,s):
    if s.get("steps"):
        return '<div class="steps">'+''.join(f'<div class="step"><b>{i+1:02}</b><span>{esc(t)}</span></div>' for i,t in enumerate(s["steps"]))+'</div>'
    if p["slug"].startswith("09") and idx in (1,2,3):
        path = {1:"M40 175 L170 160 L300 135 L440 108 L590 80 L760 50",
                2:"M40 175 L200 145 L280 65 L350 65 L410 125 L570 90 L760 50",
                3:"M40 175 L200 145 L280 55 L320 55 L350 135 L570 90 L760 50"}[idx]
        return f'<div class="chart"><div class="chart-label">{esc(s["big"])}</div><svg viewBox="0 0 800 220"><path d="M30 15V200H780" stroke="#718092" stroke-width="2" fill="none"/><path d="{path}" stroke="var(--accent)" stroke-width="9" fill="none" stroke-linecap="round"/></svg><small>Esquema de sensação • não é curva medida de um modelo</small></div>'
    if s.get("big"):
        return '<div class="big">'+esc(s["big"])+'</div>'
    return '<div class="visual-panel"><img src="arte-base.png"><div class="visual-shade"></div><span>'+esc(s["tag"])+'</span></div>'

def page(p,idx):
    s=p["slides"][idx];total=len(p["slides"]);cover=idx==0;last=idx==total-1
    quiz=s.get("quiz") or s.get("answer")
    cls="cover" if cover else "quiz" if quiz else "last" if last else "inner"
    title=lines(s["title"])
    body=lines(s["body"])
    art=s.get("art","arte-base.png")
    if cover:
        content=f'<img class="hero" src="{art}"><div class="hero-shade"></div><div class="cover-copy"><div class="eyebrow">{esc(s["tag"])}</div><h1>{title}</h1><p>{body}</p></div>'
    elif quiz:
        content=f'<div class="quiz-copy"><div class="eyebrow">{esc(s["tag"])}</div><h1>{title}</h1></div><div class="quiz-art"><img src="{art}"></div><p class="quiz-body">{body}</p>'
    else:
        content=f'<div class="copy"><div class="eyebrow">{esc(s["tag"])}</div><h1>{title}</h1><p>{body}</p></div><div class="diagram">{diagram(p,idx,s)}</div>'
    note=f'<div class="note">{esc(s["note"])}</div>' if s.get("note") else ''
    footer="COMENTE SUA RESPOSTA" if last else "DESLIZE PARA CONTINUAR"
    marks=''.join('<i class="'+('active' if n==idx else '')+'"></i>' for n in range(total))
    return f"""<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>{esc(p["title"])} — {idx+1}</title>
<style>
*{{box-sizing:border-box}}html,body{{margin:0;width:1080px;height:1350px;overflow:hidden;background:#07101e;color:#f6f7fa;font-family:'Bahnschrift','Arial',sans-serif}}
:root{{--accent:{p["color"]}}}
body{{position:relative;background:radial-gradient(ellipse at 95% 68%,color-mix(in srgb,var(--accent) 14%,transparent),transparent 60%),#07101e}}
.grid{{position:absolute;inset:0;opacity:.13;background-image:linear-gradient(#547082 1px,transparent 1px),linear-gradient(90deg,#547082 1px,transparent 1px);background-size:90px 90px;mask-image:linear-gradient(transparent,#000)}}
.frame{{position:absolute;inset:29px;border:1px solid #8aa5b530;pointer-events:none;z-index:6}}
.frame:before,.frame:after{{content:'';position:absolute;width:42px;height:42px;border-color:var(--accent);border-style:solid}}
.frame:before{{left:-1px;top:-1px;border-width:3px 0 0 3px}}.frame:after{{right:-1px;bottom:-1px;border-width:0 3px 3px 0}}
header{{position:absolute;top:64px;left:72px;right:72px;display:flex;align-items:center;justify-content:space-between;z-index:5}}
.brand{{display:flex;align-items:center;gap:13px;font-weight:700;font-size:24px;letter-spacing:2px}}
.brand img{{width:49px;height:58px;object-fit:contain}}.category{{font-size:20px;letter-spacing:2px;color:var(--accent);text-align:right}}
.hero{{position:absolute;width:1080px;height:1350px;object-fit:cover;top:0;left:0}}
.hero-shade{{position:absolute;inset:0;background:linear-gradient(#07101e77 0%,transparent 23%,transparent 39%,#07101edb 67%,#07101e 95%)}}
.cover-copy{{position:absolute;left:76px;right:76px;bottom:205px;z-index:3}}
.eyebrow{{font-size:23px;letter-spacing:2px;font-weight:600;color:var(--accent);margin-bottom:26px}}
h1{{font-family:Impact,'Arial Black',sans-serif;font-weight:400;font-size:112px;line-height:1.06;letter-spacing:1px;margin:0;text-shadow:0 5px 0 #0005;overflow-wrap:normal}}
.cover h1{{font-size:132px;line-height:1.01}}p{{font-size:37px;line-height:1.4;color:#e0e8ef;margin:30px 0 0;white-space:normal}}
.copy{{position:absolute;left:76px;right:76px;top:196px;z-index:3}}.inner h1,.last h1{{font-size:96px}}.copy p{{font-size:39px;line-height:1.42;margin-top:32px}}
.diagram{{position:absolute;left:76px;right:76px;top:775px;height:355px;display:flex;align-items:center;justify-content:center}}
.big{{font-family:Impact,'Arial Black',sans-serif;font-size:158px;line-height:1;color:var(--accent);letter-spacing:3px;text-align:center;width:100%;border-top:1px solid #ffffff28;border-bottom:1px solid #ffffff28;padding:52px 0;text-shadow:0 0 60px color-mix(in srgb,var(--accent) 18%,transparent)}}
.steps{{width:100%;display:grid;gap:16px}}.step{{display:flex;align-items:center;gap:26px;padding:22px 28px;background:#152336;border-left:4px solid var(--accent);border-radius:0 12px 12px 0;font-size:33px;line-height:1.15}}
.step b{{font-size:30px;color:var(--accent)}}.visual-panel{{width:100%;height:355px;position:relative;overflow:hidden;border-radius:16px;border:1px solid #ffffff22}}
.visual-panel img{{width:100%;height:100%;object-fit:cover;object-position:50% 26%;opacity:.82}}
.visual-shade{{position:absolute;inset:0;background:linear-gradient(transparent,#07101e)}}.visual-panel span{{position:absolute;bottom:26px;left:30px;font-size:23px;letter-spacing:3px;color:var(--accent)}}
.chart{{width:100%;padding:20px;background:#132032;border:1px solid #ffffff20;border-radius:18px}}.chart-label{{color:var(--accent);font-size:34px;font-weight:600}}.chart svg{{width:100%;height:220px}}.chart small{{font-size:20px;color:#c6d4df}}
.note{{position:absolute;bottom:174px;left:76px;right:76px;font-size:22px;line-height:1.3;color:#adbfcd;z-index:4}}
footer{{position:absolute;bottom:63px;left:76px;right:76px;border-top:1px solid #ffffff25;padding-top:22px;display:flex;justify-content:space-between;align-items:center;z-index:5}}
footer strong{{display:block;font-size:22px;font-weight:500;letter-spacing:1px}}footer small{{display:block;font-size:18px;color:#aebdc9;margin-top:9px;letter-spacing:1px}}
.page-no{{font-size:28px;color:var(--accent);font-weight:700}}.progress{{display:flex;gap:8px;margin-top:13px}}.progress i{{height:4px;width:30px;background:#526576}}.progress .active{{background:var(--accent)}}
.quiz-copy{{position:absolute;top:184px;left:76px;right:76px}}.quiz h1{{font-size:100px}}
.quiz-art{{position:absolute;left:150px;right:150px;top:465px;height:490px;border-radius:20px;background:#a8d5e5;overflow:hidden}}
.quiz-art img{{width:100%;height:100%;object-fit:contain}}
.quiz-body{{position:absolute;top:965px;left:76px;right:76px;font-size:36px;margin:0}}
.last .big{{font-size:160px}}.last .copy h1{{color:var(--accent)}}
</style></head><body class="{cls}">
<div class="grid"></div>{content}{note}<div class="frame"></div>
<header><div class="brand"><img src="{BRAND.as_uri()}"><span>ESTRATÉGIA<br>NERD</span></div><div class="category">{esc(p["cat"])}</div></header>
<footer><div><strong>{footer} {"→" if not last else "↓"}</strong><small>@estrategia_nerd</small></div><div><div class="page-no">{idx+1:02} / {total:02}</div><div class="progress">{marks}</div></div></footer>
<script>
document.fonts.ready.then(()=>{{
for(const el of document.querySelectorAll('h1')){{
let size=parseFloat(getComputedStyle(el).fontSize);
while(el.scrollWidth>el.clientWidth+1&&size>72){{size-=2;el.style.fontSize=size+'px'}}
}}
const fit=document.querySelector('.copy');
if(fit){{
const title=fit.querySelector('h1');const para=fit.querySelector('p');
let size=parseFloat(getComputedStyle(title).fontSize);
while(fit.getBoundingClientRect().bottom>750&&size>76){{size-=2;title.style.fontSize=size+'px'}}
size=parseFloat(getComputedStyle(para).fontSize);
while(fit.getBoundingClientRect().bottom>750&&size>34){{size-=1;para.style.fontSize=size+'px'}}
}}
let issues=[];
for(const el of document.querySelectorAll('h1,p,.eyebrow,.step,.note')){{
const r=el.getBoundingClientRect();
if(r.left<50||r.right>1030||r.bottom>1190)issues.push(el.textContent);
if(el.scrollWidth>el.clientWidth+1)issues.push('overflow:'+el.textContent);
}}
const copy=document.querySelector('.copy');
if(copy&&copy.getBoundingClientRect().bottom>755)issues.push('copy-overlap');
document.body.setAttribute('data-qa',JSON.stringify(issues));
}});
</script></body></html>"""

def prepare():
    jobs=[]
    for p in POSTS:
        d=ROOT/p["slug"];d.mkdir(exist_ok=True)
        (d/"legenda.txt").write_text(p["caption"]+"\n",encoding="utf-8")
        sources="# Fontes e notas editoriais\n\nConsulta: 14/09/2026.\n\n"
        sources+="\n\n".join(f'- [{s["title"]}]({s["url"]})\n  - Apoia: {s["note"]}' for s in p["sources"])
        if not p["sources"]: sources+="Conteúdo autoral: perguntas, situações cotidianas ou regras de brincadeira. Não apresenta fatos de franquias ou recomendações técnicas específicas."
        sources+="\n\nAs avaliações de personagens e seleções são editoriais. As artes são ilustrações produzidas para esta leva, não capturas dos jogos nem fotografias oficiais.\n"
        (d/"fontes.md").write_text(sources,encoding="utf-8")
        roteiro=f'# {p["title"]}\n\nFormato: 1080 × 1350 px (4:5). Ordem: slide-01.png em diante.\n\n'
        for i,s in enumerate(p["slides"]):
            roteiro+=f'## Slide {i+1:02}\n\n{s["tag"]}\n\n{s["title"]}\n\n{s["body"]}\n\n'
            if s.get("note"): roteiro+=s["note"]+"\n\n"
        roteiro+="## Variações de abertura\n\n"+"\n".join("- "+h for h in p["hooks"])+"\n"
        (d/"roteiro.md").write_text(roteiro,encoding="utf-8")
        for i in range(len(p["slides"])):
            f=d/f"slide-{i+1:02}.html"; f.write_text(page(p,i),encoding="utf-8")
            jobs.append((f,f.with_suffix(".png")))
    return jobs

def render(job):
    f,out=job
    cmd=[str(CHROME),"--headless=new","--disable-gpu","--hide-scrollbars","--no-first-run","--no-default-browser-check","--disable-background-networking","--disable-extensions","--allow-file-access-from-files","--force-device-scale-factor=1","--window-size=1080,1350","--virtual-time-budget=1500",f"--screenshot={out}","--dump-dom",f.as_uri()]
    done=subprocess.run(cmd,capture_output=True,timeout=60,creationflags=subprocess.CREATE_NO_WINDOW)
    dom=done.stdout.decode("utf-8",errors="replace")
    if not out.exists(): raise RuntimeError(f"Falha de render: {f}\n"+done.stderr.decode(errors="replace")[-500:])
    raw=out.read_bytes()
    w,h=struct.unpack(">II",raw[16:24])
    import re
    qa=re.search(r'data-qa="([^"]*)"',dom)
    issues=json.loads(html.unescape(qa.group(1))) if qa else ["QA DOM não retornado"]
    return {"file":str(out.relative_to(ROOT)),"width":w,"height":h,"bytes":len(raw),"issues":issues}

if __name__=="__main__":
    jobs=prepare()
    if "--prepare" in sys.argv:
        print(json.dumps({"html":len(jobs),"posts":len(POSTS)}));sys.exit()
    if "--sample" in sys.argv: jobs=[jobs[0],jobs[6],jobs[8]]
    if "--available" in sys.argv: jobs=[j for j in jobs if (j[0].parent/'arte-base.png').exists() and not j[0].parent.name.startswith('10-')]
    if "--only" in sys.argv:
        target=sys.argv[sys.argv.index("--only")+1]
        jobs=[j for j in jobs if target in str(j[0])]
    results=[]
    # Chromium serial screenshot invocations avoid shared-profile contention.
    for job in jobs:
        r=render(job);results.append(r);print(json.dumps(r,ensure_ascii=False),flush=True)
    (ROOT/"verificacao.json").write_text(json.dumps(results,ensure_ascii=False,indent=2),encoding="utf-8")
