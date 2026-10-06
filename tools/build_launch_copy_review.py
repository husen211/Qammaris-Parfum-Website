"""Build a private, offline Owner review from curated copy and verified website covers."""

import argparse
import base64
import hashlib
import html
import json
import shutil
from pathlib import Path


def build(wave_path, capture_path, output):
    private = (Path.cwd() / "storage/app/private").resolve()
    paths = [p.resolve() for p in (wave_path, capture_path, output)]
    if any(private not in p.parents for p in paths) or output.exists():
        raise ValueError("Use new private output and private source paths")
    wave = json.loads(wave_path.read_text(encoding="utf8"))
    capture = json.loads(capture_path.read_text(encoding="utf8"))
    if wave.get("schema") != "qammaris-wave-one-v1" or len(wave["data"]) != 113:
        raise ValueError("Expected reviewed wave-one cohort")
    covers = {r["id"]: r for r in capture["covers"]}
    if len(covers) != 113 or set(covers) != {r["product_id"] for r in wave["data"]}:
        raise ValueError("Cover cohort mismatch")
    escape = lambda text: html.escape(str(text or ""), quote=True)
    articles = []
    for i, row in enumerate(wave["data"]):
        cover = covers[row["product_id"]]
        if cover["mime"] not in ("image/jpeg", "image/png", "image/webp"):
            raise ValueError("Invalid cover MIME")
        image = base64.b64decode(cover["bytes"], validate=True)
        if len(image) > 5242880 or hashlib.sha256(image).hexdigest() != cover["sha256"]:
            raise ValueError("Cover integrity mismatch")
        decision = row["audience_decision"]
        status = "Peruntukan jelas" if decision == "clear" else "Peruntukan bertentangan" if decision == "conflict" else "Peruntukan belum diketahui"
        evidence = "".join(f'<li>{escape(e["text"])}</li>' for e in row["audience_evidence"])
        money = "Rp " + f'{int(float(row["price"])):,}'.replace(",", ".")
        availability = {"available": "Tersedia", "sold_out": "Habis", "unknown": "Tanyakan ketersediaan"}[row["availability"]]
        articles.append(f'''<article data-decision="{escape(decision)}" data-search="{escape(row['name']+' '+row['brand'])}">
<img src="data:{cover['mime']};base64,{cover['bytes']}" alt="Foto sampul {escape(row['name'])}" width="220" height="220" {'loading="lazy"' if i else ''}>
<div><p class="brand">{escape(row['brand'])} · ID {escape(row['product_id'])}</p><h2>{escape(row['name'])}</h2>
<p class="offer">{money} · {escape(row['size_ml'])} ml · {escape(row['category'])}</p><p>{availability} · Draft</p>
<p class="{'review' if decision != 'clear' else 'clear'}">{status}: <strong>{escape(row['proposed_gender']) or 'Perlu keputusan Owner'}</strong></p>
<details {'open' if i == 0 else ''}><summary>Baca deskripsi Shopee</summary><p class="copy">{escape(row['cleaned_description'])}</p></details>
<details><summary>Lihat bukti peruntukan</summary>{'<ul>'+evidence+'</ul>' if evidence else '<p>Tidak ada pernyataan jelas pada nama/deskripsi. Tidak ditebak.</p>'}</details>
</div></article>''')
    output.mkdir()
    for name in ("audience-corrections.csv", "size-concentration-corrections.csv"):
        shutil.copyfile(wave_path.parent / name, output / name)
    document = '''<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Review gelombang 1 · Qammaris</title><link rel="stylesheet" href="review.css"><script defer src="review.js"></script></head><body>
<a class="skip" href="#products">Lewati ke produk</a><main><header><p class="brand">Qammaris · Review Owner</p><h1>Gelombang pertama</h1><p>113 draft untuk review foto dan deskripsi. 76 peruntukan jelas; 37 perlu keputusanmu. Belum dipublish.</p><details><summary>Cara review dan daftar koreksi</summary><p>Periksa foto dan isi deskripsi sebelum menyetujui publikasi staging. Harga dan status mengikuti snapshot staging; halaman ini adalah berkas review.</p><p><a href="audience-corrections.csv" download>Unduh 37 koreksi peruntukan</a> · <a href="size-concentration-corrections.csv" download>Kekurangan 246 draft lainnya</a></p></details></header>
<form id="filters"><label>Cari nama atau merek<input id="search" type="search" autocomplete="off"></label><label>Peruntukan<select id="audience"><option value="all">Semua 113 produk</option><option value="clear">Jelas · 76</option><option value="review">Perlu review · 37</option><option value="conflict">Bertentangan · 3</option></select></label><button type="reset">Reset</button></form>
<p id="count" role="status" aria-live="polite">113 produk</p><p id="empty" hidden>Tidak ada produk yang cocok. Ubah pencarian atau reset filter.</p><section id="products" aria-label="Produk gelombang pertama">'''+"".join(articles)+'''</section><footer>266 draft tanpa foto tetap draft. Ukuran/konsentrasi lainnya tidak menghalangi gelombang ini. Tidak ada tombol publish pada berkas review.</footer></main></body></html>'''
    (output / "index.html").write_text(document, encoding="utf8")
    (output / "review.css").write_text('''*{box-sizing:border-box}body{margin:0;background:#f9f9f7;color:#1a1a1a;font:16px/1.55 Arial,sans-serif}main{max-width:1100px;margin:auto;padding:24px}h1{font-size:32px;margin:8px 0}h2{font-size:21px;line-height:1.35;margin:6px 0;overflow-wrap:anywhere}header{max-width:850px}p{margin:8px 0}.brand{font-size:14px;color:#565656}.offer{font-weight:600}a{color:#0d3f33;text-underline-offset:3px}form{display:flex;gap:16px;align-items:end;margin:24px 0}label{display:grid;gap:5px;flex:1}input,select,button,summary{font:inherit}input,select,button{min-height:44px;padding:8px 12px;border:1px solid #777;border-radius:3px;background:#fff;color:#1a1a1a;max-width:100%}button,summary{cursor:pointer}summary{padding:10px 0;min-height:44px}article{display:grid;grid-template-columns:220px minmax(0,1fr);gap:28px;padding:28px 0;border-top:1px solid #d7d7d1}img{width:220px;height:220px;object-fit:contain;background:white}details{border-top:1px solid #e5e5df;margin-top:10px}.copy{white-space:pre-line;overflow-wrap:anywhere}li{overflow-wrap:anywhere}.review{color:#74420a}.clear{color:#0d3f33}footer{padding:24px 0;border-top:1px solid #d7d7d1}.skip{position:absolute;left:-9999px}.skip:focus{left:16px;top:10px;background:white;padding:8px;z-index:1}:focus-visible{outline:3px solid #0d3f33;outline-offset:3px}[hidden]{display:none!important}@media(max-width:650px){main{padding:20px}h1{font-size:28px}form{flex-direction:column;align-items:stretch;gap:12px}label{width:100%}article{grid-template-columns:100px minmax(0,1fr);gap:14px}img{width:100px;height:120px}article>div{display:contents}article>.brand{grid-column:2}h2,.offer,article p,details{grid-column:1/-1}article img{grid-row:1/4}article .brand,article h2,article .offer{grid-column:2}h2{font-size:18px}ul{padding-left:22px}}''', encoding="utf8")
    (output / "review.js").write_text('''const form=document.querySelector('#filters'),search=document.querySelector('#search'),audience=document.querySelector('#audience'),rows=[...document.querySelectorAll('article')];function filter(){const q=search.value.trim().toLocaleLowerCase('id'),mode=audience.value;let count=0;for(const row of rows){const match=row.dataset.search.toLocaleLowerCase('id').includes(q)&&(mode==='all'||mode===row.dataset.decision||(mode==='review'&&row.dataset.decision!=='clear'));row.hidden=!match;if(match)count++;}document.querySelector('#count').textContent=`${count} produk`;document.querySelector('#empty').hidden=count!==0;}form.addEventListener('submit',e=>e.preventDefault());search.addEventListener('input',filter);audience.addEventListener('change',filter);form.addEventListener('reset',()=>requestAnimationFrame(filter));''', encoding="utf8")
    return {"products": len(articles), "covers": len(covers), "html_bytes": (output / "index.html").stat().st_size}


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    for name in ("wave", "capture", "output"):
        parser.add_argument(name, type=Path)
    args = parser.parse_args()
    print(json.dumps(build(args.wave, args.capture, args.output)))
