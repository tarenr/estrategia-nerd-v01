#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Radar de Conteúdo, Pesquisa e Transcrições — YouTube (FEAT-017)
Executa busca sem consumo de cota via InnerTube API e extração de transcrições completas.
"""

import sys
import os
import json
import argparse
import urllib.request
import urllib.parse
import re

# Força UTF-8 na saída padrão
if sys.platform == "win32":
    import io
    sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')
    sys.stderr = io.TextIOWrapper(sys.stderr.buffer, encoding='utf-8')

USER_AGENT = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36"

def parse_runs(runs):
    if not runs or not isinstance(runs, list):
        return ""
    return "".join([r.get("text", "") for r in runs])

def search_innertube(query, filter_type="all", limit=24):
    url = "https://www.youtube.com/youtubei/v1/search"
    search_query = query
    if filter_type == "shorts" and "#shorts" not in query.lower() and "shorts" not in query.lower():
        search_query = f"{query} shorts"

    payload = {
        "context": {
            "client": {
                "clientName": "WEB",
                "clientVersion": "2.20261020.00.00",
                "hl": "pt-BR",
                "gl": "BR"
            }
        },
        "query": search_query
    }

    req = urllib.request.Request(
        url,
        data=json.dumps(payload).encode("utf-8"),
        headers={
            "Content-Type": "application/json",
            "User-Agent": USER_AGENT
        }
    )

    items = []
    seen_ids = set()

    try:
        with urllib.request.urlopen(req, timeout=12) as resp:
            data = json.loads(resp.read().decode("utf-8"))
            
            sections = (
                data.get("contents", {})
                .get("twoColumnSearchResultsRenderer", {})
                .get("primaryContents", {})
                .get("sectionListRenderer", {})
                .get("contents", [])
            )
            
            for sec in sections:
                item_section = sec.get("itemSectionRenderer", {})
                contents = item_section.get("contents", [])
                for c in contents:
                    # Prateleira de Shorts Moderna (gridShelfViewModel / shortsLockupViewModel)
                    if "gridShelfViewModel" in c:
                        gsvm = c["gridShelfViewModel"]
                        for s_item in gsvm.get("contents", []):
                            if "shortsLockupViewModel" in s_item:
                                slvm = s_item["shortsLockupViewModel"]
                                entity_id = slvm.get("entityId", "")
                                vid_id = entity_id.replace("shorts-shelf-item-", "").strip()
                                if not vid_id or vid_id in seen_ids:
                                    continue
                                
                                title = (
                                    slvm.get("overlayMetadata", {})
                                    .get("primaryText", {})
                                    .get("content", "")
                                    or slvm.get("accessibilityText", "")
                                )
                                views = (
                                    slvm.get("overlayMetadata", {})
                                    .get("secondaryText", {})
                                    .get("content", "")
                                )
                                
                                sources = (
                                    slvm.get("thumbnailViewModel", {})
                                    .get("image", {})
                                    .get("sources", [])
                                )
                                thumb_url = sources[-1].get("url") if sources else f"https://i.ytimg.com/vi/{vid_id}/hqdefault.jpg"
                                
                                if filter_type != "videos":
                                    seen_ids.add(vid_id)
                                    items.append({
                                        "id": vid_id,
                                        "type": "short",
                                        "title": title,
                                        "url": f"https://www.youtube.com/shorts/{vid_id}",
                                        "channel": "YouTube Shorts",
                                        "views": views or "Em alta",
                                        "published": "Viral / Recente",
                                        "duration": "Short (<60s)",
                                        "thumbnail": thumb_url
                                    })

                    # Vídeos normais
                    elif "videoRenderer" in c:
                        vr = c["videoRenderer"]
                        vid_id = vr.get("videoId")
                        if not vid_id or vid_id in seen_ids:
                            continue
                        
                        title = parse_runs(vr.get("title", {}).get("runs", []))
                        channel = parse_runs(vr.get("ownerText", {}).get("runs", []))
                        views = vr.get("viewCountText", {}).get("simpleText", "")
                        published = vr.get("publishedTimeText", {}).get("simpleText", "")
                        duration = vr.get("lengthText", {}).get("simpleText", "")
                        
                        thumbs = vr.get("thumbnail", {}).get("thumbnails", [])
                        thumb_url = thumbs[-1].get("url") if thumbs else f"https://i.ytimg.com/vi/{vid_id}/hqdefault.jpg"
                        
                        is_short = False
                        if "#shorts" in title.lower() or "#short" in title.lower():
                            is_short = True
                        elif duration and ":" in duration:
                            parts = duration.split(":")
                            if len(parts) == 2 and int(parts[0]) == 0 and int(parts[1]) <= 60:
                                is_short = True
                                
                        if filter_type == "shorts" and not is_short:
                            continue
                        if filter_type == "videos" and is_short:
                            continue
                            
                        seen_ids.add(vid_id)
                        items.append({
                            "id": vid_id,
                            "type": "short" if is_short else "video",
                            "title": title,
                            "url": f"https://www.youtube.com/watch?v={vid_id}",
                            "channel": channel,
                            "views": views,
                            "published": published,
                            "duration": duration or "Short",
                            "thumbnail": thumb_url
                        })
                        
                    if len(items) >= limit:
                        break
                if len(items) >= limit:
                    break
    except Exception as e:
        return {"ok": False, "error": str(e), "items": []}

    return {"ok": True, "query": query, "total": len(items), "items": items}

def get_transcript(video_id):
    video_id = video_id.strip()
    # Limpa caso passem URL completa
    if "v=" in video_id:
        video_id = video_id.split("v=")[1].split("&")[0]
    elif "shorts/" in video_id:
        video_id = video_id.split("shorts/")[1].split("?")[0].split("/")[0]

    try:
        from youtube_transcript_api import YouTubeTranscriptApi
        api = YouTubeTranscriptApi()
        
        # Tenta primeiro português, depois inglês, espanhol ou qualquer idioma disponível
        transcript_data = None
        lang_used = ""
        
        try:
            transcript_data = api.fetch(video_id, languages=['pt', 'pt-BR'])
            lang_used = "Português"
        except Exception:
            try:
                transcript_data = api.fetch(video_id, languages=['en', 'en-US'])
                lang_used = "Inglês"
            except Exception:
                try:
                    # Busca lista de transcrições disponíveis
                    t_list = api.list(video_id)
                    for tr in t_list:
                        transcript_data = tr.fetch()
                        lang_used = tr.language
                        break
                except Exception as list_err:
                    pass

        if not transcript_data:
            return {
                "ok": False,
                "video_id": video_id,
                "error": "Nenhuma transcrição ou legenda disponível para este vídeo."
            }

        segments = []
        full_text_list = []

        for item in transcript_data:
            text = (getattr(item, 'text', '') or (item.get('text', '') if isinstance(item, dict) else '')).strip()
            if not text:
                continue
            start = float(getattr(item, 'start', 0) if hasattr(item, 'start') else item.get('start', 0))
            duration = float(getattr(item, 'duration', 0) if hasattr(item, 'duration') else item.get('duration', 0))
            
            m = int(start // 60)
            s = int(start % 60)
            time_str = f"{m:02d}:{s:02d}"
            
            segments.append({
                "time": time_str,
                "start": start,
                "duration": duration,
                "text": text
            })
            full_text_list.append(text)

        full_text = " ".join(full_text_list)

        return {
            "ok": True,
            "video_id": video_id,
            "language": lang_used,
            "total_segments": len(segments),
            "segments": segments,
            "full_text": full_text
        }

    except Exception as e:
        return {
            "ok": False,
            "video_id": video_id,
            "error": f"Falha ao obter transcrição: {str(e)}"
        }

def main():
    parser = argparse.ArgumentParser(description="Radar de Conteúdo YouTube")
    parser.add_argument("--action", required=True, choices=["search", "transcript", "trending"])
    parser.add_argument("--query", default="")
    parser.add_argument("--type", default="all", choices=["all", "shorts", "videos"])
    parser.add_argument("--video-id", default="")
    parser.add_argument("--limit", type=int, default=24)

    args = parser.parse_args()

    if args.action == "search":
        q = args.query.strip() or "setup gamer"
        result = search_innertube(q, args.type, args.limit)
        print(json.dumps(result, ensure_ascii=False, indent=2))
    elif args.action == "transcript":
        if not args.video_id:
            print(json.dumps({"ok": False, "error": "Informe o --video-id"}))
            sys.exit(1)
        result = get_transcript(args.video_id)
        print(json.dumps(result, ensure_ascii=False, indent=2))
    elif args.action == "trending":
        result = search_innertube(args.query.strip() or "tecnologia curiosidades virais", "shorts", args.limit)
        print(json.dumps(result, ensure_ascii=False, indent=2))

if __name__ == "__main__":
    main()
