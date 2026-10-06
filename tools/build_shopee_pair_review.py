"""Build protected Owner candidate review with verified covers and explicit choice export."""

import argparse
import base64
import csv
import hashlib
import html
import json
from pathlib import Path


def build(review_path, covers_path, output):
    private = (Path.cwd() / "storage/app/private").resolve()
    if any(private not in p.resolve().parents for p in (review_path, covers_path, output)) or output.exists():
        raise ValueError("Use private inputs and a new output directory")
    review = json.loads(review_path.read_text(encoding="utf8"))
    covers = json.loads(covers_path.read_text(encoding="utf8"))
    pairs = json.loads((review_path.parent / "pairs.json").read_text(encoding="utf8"))
    if pairs.get("schema") != "qammaris-pairs-v1" or pairs.get("provenance") != review.get("provenance"):
        raise ValueError("Source provenance mismatch")
    if review.get("schema") != "qammaris-shopee-pair-review-v1" or len(review["data"]) != 56:
        raise ValueError("Expected the 56 Owner candidate rows")
    by_id = {r["id"]: r for r in covers}
    if set(by_id) != {r["shopee_id"] for r in review["data"]}:
        raise ValueError("Cover cohort mismatch")
    esc = lambda value: html.escape(str(value or ""), quote=True)
    articles, export_data = [], []
    for row in review["data"]:
        cover = by_id[row["shopee_id"]]
        if cover["mime"] not in {"image/jpeg", "image/png", "image/webp"}:
            raise ValueError("Unsupported cover MIME")
        image = base64.b64decode(cover["bytes"], validate=True)
        if len(image) > 8 * 1024 * 1024 or hashlib.sha256(image).hexdigest() != cover["sha256"]:
            raise ValueError("Cover integrity mismatch")
        options = ['<option value="">Belum dipilih</option>']
        items, targets = [], []
        for index, c in enumerate(row["candidates"]):
            blocked = bool(c.get("issue") or c.get("hidden") or c.get("publication_status") != "draft")
            conflict = bool(c.get("existing_shopee_id") and c["existing_shopee_id"] != row["shopee_id"])
            issue = c.get("issue") or ("Sumber hidden" if c.get("hidden") else "Bukan draft" if c.get("publication_status") != "draft" else
                    "Sudah terpasang ke Shopee lain; perlu koreksi" if conflict else "")
            label = f'{c.get("name", "")} · {c["sku"]}'
            options.append(f'<option value="{index}" {"disabled" if blocked else ""}>{esc(label)}{esc(" · "+issue) if issue else ""}</option>')
            items.append(f'<li><strong>{esc(c.get("name"))}</strong><br>{esc(c.get("brand"))} · SKU {esc(c["sku"])} · ID website {esc(c.get("website_id"))}<br>{esc(issue) or "Bisa dipilih"} · skor sumber {esc(c["score"])}<br><small>UUID {esc(c.get("uuid"))}</small></li>')
            targets.append({**c, "blocked": blocked, "requires_conflict_review": conflict})
        export_data.append({"shopee_id": row["shopee_id"], "shopee_name": row["shopee_name"], "status": row["status"], "candidates": targets})
        code = row["shopee_id"]
        articles.append(f'''<article data-code="{esc(code)}" data-status="{esc(row['status'])}" data-search="{esc(row['shopee_name']+' '+' '.join(c.get('name','')+' '+c['sku'] for c in row['candidates']))}">
<img src="data:{cover['mime']};base64,{cover['bytes']}" width="200" height="200" alt="Foto Shopee {esc(row['shopee_name'])}" loading="lazy">
<div><p class="brand">Shopee {esc(code)} · {esc(row['status'])}</p><h2>{esc(row['shopee_name'])}</h2>
<label for="pair-{esc(code)}">Pilih pasangan untuk {esc(row['shopee_name'])}</label><select id="pair-{esc(code)}" class="choice">{''.join(options)}</select>
<details><summary>Bandingkan {len(targets)} kandidat</summary><ul>{''.join(items)}</ul></details>
<details><summary>Baca deskripsi Shopee</summary><p class="copy">{esc(row['description'])}</p></details></div></article>''')
    payload = json.dumps({"schema": "qammaris-shopee-owner-choices-v1", "provenance": review["provenance"],
        "capture_sha256": review["capture_sha256"], "captured_at": review["captured_at"], "data": export_data}, ensure_ascii=False).replace("<", "\\u003c").replace(">", "\\u003e").replace("&", "\\u0026")
    output.mkdir()
    with (output / "unmatched.csv").open("w", encoding="utf-8-sig", newline="") as stream:
        writer = csv.writer(stream)
        writer.writerow(["shopee_id", "shopee_name", "status"])
        for row in pairs["data"]:
            if row["status"] == "tidak_ketemu":
                values = [row["shopee_id"], row["shopee_name"], row["status"]]
                writer.writerow(["'" + v if v.lstrip().startswith(("=", "+", "-", "@")) else v for v in values])
    (output / "index.html").write_text('''<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Pilih pasangan Shopee · Qammaris</title><link rel="stylesheet" href="review.css"><script defer src="review.js"></script></head><body><a class="skip" href="#products">Lewati ke pasangan</a><main><header><p class="brand">Qammaris · Review Owner</p><h1>Pilih pasangan Shopee</h1><p>56 produk: 26 perlu cek dan 30 ambigu. Pilih berdasarkan nama, merek dan ukuran. Belum diterapkan atau dipublish.</p><details><summary>Cara menyimpan pilihan</summary><p>Pilih pasangan, lalu unduh pilihan dan kirim berkasnya. Pilihan belum mengubah katalog; kami cek ulang pasangan sebelum menerapkannya. Pilihan hanya bertahan selama halaman ini terbuka. Unduh sebelum menutup atau memuat ulang.</p><p>Pasangan yang sudah digunakan boleh dipilih sebagai usulan koreksi. Konfliknya harus diselesaikan sebelum diterapkan; identitas tidak dipindahkan otomatis.</p><a href="unmatched.csv" download>17 produk tidak ketemu untuk upload manual</a> · <a href="../p8-06-wave-one/">Review gelombang pertama</a></details></header><form id="filters"><label for="search">Cari nama atau SKU<input id="search" type="search" autocomplete="off"></label><label for="status">Status<select id="status"><option value="all">Semua 56 produk</option><option value="perlu_cek">Perlu cek · 26</option><option value="ambigu">Ambigu · 30</option><option value="unselected">Belum dipilih</option><option value="selected">Sudah dipilih</option></select></label><button type="reset">Reset filter</button></form><div class="actions"><button id="export" disabled>Unduh pilihan</button><button id="clear" disabled>Kosongkan pilihan</button></div><p id="count" role="status" aria-live="polite">56 produk · 0 dipilih</p><p id="feedback" role="status" aria-live="polite"></p><p id="empty" hidden>Tidak ada pasangan yang cocok. Ubah pencarian atau reset filter.</p><section id="products" aria-label="Pasangan Shopee">'''+"".join(articles)+'''</section><footer>Hanya pilihan yang diunduh untuk proses berikutnya. Tidak ada perubahan produk atau publikasi dari halaman ini.</footer></main><script id="review-data" type="application/json">'''+payload+'''</script></body></html>''', encoding="utf8")
    (output / "review.css").write_text('''*{box-sizing:border-box}body{margin:0;background:#f9f9f7;color:#1a1a1a;font:16px/1.55 Arial,sans-serif}main{max-width:1100px;margin:auto;padding:24px}h1{font-size:32px;margin:8px 0}h2{font-size:21px;line-height:1.35;margin:6px 0;overflow-wrap:anywhere}p{margin:8px 0}.brand,small{font-size:14px;color:#565656}a{color:#0d3f33;text-underline-offset:3px}form{display:flex;gap:16px;align-items:end;margin:24px 0}label{display:block}form label{flex:1}input,select,button,summary{font:inherit}input,select{display:block;width:100%}input,select,button{min-height:44px;padding:8px 12px;border:1px solid #777;border-radius:3px;background:#fff;color:#1a1a1a;max-width:100%}button,summary{cursor:pointer}button:disabled{color:#666;background:#eee;cursor:default}summary{padding:10px 0;min-height:44px}.actions{display:flex;gap:12px;flex-wrap:wrap}article{display:grid;grid-template-columns:200px minmax(0,1fr);gap:28px;padding:28px 0;border-top:1px solid #d7d7d1}img{width:200px;height:200px;object-fit:contain;background:white}details{border-top:1px solid #e5e5df;margin-top:10px}.copy{white-space:pre-line;overflow-wrap:anywhere}li{margin:12px 0;overflow-wrap:anywhere}footer{padding:24px 0;border-top:1px solid #d7d7d1}.skip{position:absolute;left:-9999px}.skip:focus{left:16px;top:10px;background:white;padding:8px;z-index:1}:focus-visible{outline:3px solid #0d3f33;outline-offset:3px}[hidden]{display:none!important}@media(max-width:650px){main{padding:16px}h1{font-size:27px}form{display:grid;gap:12px;margin:18px 0}article{grid-template-columns:minmax(0,1fr);gap:12px;padding:22px 0}img{width:180px;height:180px;justify-self:center}select{font-size:15px}.actions button{flex:1}}''', encoding="utf8")
    (output / "review.js").write_text('''"use strict";
const payload=JSON.parse(document.querySelector("#review-data").textContent),rows=[...document.querySelectorAll("article")],search=document.querySelector("#search"),status=document.querySelector("#status"),exportButton=document.querySelector("#export"),clearButton=document.querySelector("#clear"),feedback=document.querySelector("#feedback");
function selections(){return rows.filter(r=>r.querySelector("select").value!=="");}
function update(){const q=search.value.toLocaleLowerCase("id").trim(),filter=status.value;let visible=0;for(const row of rows){const picked=row.querySelector("select").value!=="";row.hidden=!(row.dataset.search.toLocaleLowerCase("id").includes(q)&&(filter==="all"||filter===row.dataset.status||filter==="selected"&&picked||filter==="unselected"&&!picked));if(!row.hidden)visible++;}const picked=selections().length;document.querySelector("#count").textContent=`${visible} produk · ${picked} dipilih`;document.querySelector("#empty").hidden=visible>0;exportButton.disabled=clearButton.disabled=picked===0;}
search.addEventListener("input",update);status.addEventListener("change",update);document.querySelector("#filters").addEventListener("reset",()=>requestAnimationFrame(update));
for(const row of rows)row.querySelector("select").addEventListener("change",()=>{feedback.textContent="Pilihan belum diterapkan. Unduh sebelum menutup halaman.";update();});
clearButton.addEventListener("click",()=>{for(const row of rows)row.querySelector("select").value="";feedback.textContent="Pilihan dikosongkan.";update();});
exportButton.addEventListener("click",()=>{try{const chosen=selections().map(row=>{const source=payload.data.find(r=>r.shopee_id===row.dataset.code),candidate=source.candidates[Number(row.querySelector("select").value)];if(!candidate||candidate.blocked||!candidate.uuid||!candidate.website_id)throw Error();return {shopee_id:source.shopee_id,shopee_name:source.shopee_name,majoo_sku:candidate.sku,uuid:candidate.uuid,website_id:candidate.website_id,expected_fingerprint:candidate.fingerprint,requires_conflict_review:candidate.requires_conflict_review,existing_shopee_id:candidate.existing_shopee_id};});if(new Set(chosen.map(r=>r.uuid)).size!==chosen.length){feedback.textContent="Satu produk website dipilih lebih dari sekali. Koreksi pasangan sebelum mengunduh.";return;}const content={schema:payload.schema,provenance:payload.provenance,capture_sha256:payload.capture_sha256,captured_at:payload.captured_at,data:chosen};const url=URL.createObjectURL(new Blob([JSON.stringify(content,null,2)],{type:"application/json"})),a=document.createElement("a");a.href=url;a.download="qammaris-shopee-owner-choices.json";a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);feedback.textContent=`${chosen.length} pilihan diunduh. Katalog tetap belum diubah.`;}catch{feedback.textContent="Pilihan tidak valid atau unduhan gagal. Periksa pasangan lalu coba lagi.";}});
update();''', encoding="utf8")
    return {"rows": len(articles), "covers": len(by_id), "candidate_references": sum(len(r["candidates"]) for r in review["data"])}


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    for name in ("review", "covers", "output"):
        parser.add_argument(name, type=Path)
    args = parser.parse_args()
    print(json.dumps(build(args.review, args.covers, args.output)))
