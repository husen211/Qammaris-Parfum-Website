"""Read Owner's Shopee media XLSX without modifying it; emit only public media fields."""

import argparse
import json
import re
import xml.etree.ElementTree as ET
import zipfile
from pathlib import Path


def read_xml(archive, name):
    if archive.getinfo(name).file_size > 64 * 1024 * 1024:
        raise ValueError("Workbook part exceeds the supported size")
    raw = archive.read(name)
    if b"<!DOCTYPE" in raw.upper() or b"<!ENTITY" in raw.upper():
        raise ValueError("XML declarations are not supported")
    return ET.fromstring(raw)


def extract(path):
    ns = {"s": "http://schemas.openxmlformats.org/spreadsheetml/2006/main"}
    with zipfile.ZipFile(path) as archive:
        shared = []
        if "xl/sharedStrings.xml" in archive.namelist():
            shared = ["".join(node.itertext()) for node in read_xml(archive, "xl/sharedStrings.xml").findall("s:si", ns)]
        sheet = read_xml(archive, "xl/worksheets/sheet1.xml")
        rows = []
        header_found = False
        seen = set()
        for row in sheet.findall("s:sheetData/s:row", ns):
            cells = {}
            for cell in row.findall("s:c", ns):
                value_node = cell.find("s:v", ns)
                value = value_node.text if value_node is not None else ""
                if cell.get("t") == "s":
                    value = shared[int(value)]
                elif cell.get("t") == "inlineStr":
                    value = "".join(cell.find("s:is", ns).itertext())
                cells[re.sub(r"\d+$", "", cell.get("r"))] = value or ""
            if cells.get("A") == "Kode Produk":
                if [cells.get(k) for k in ["B", "C", "E", "F", "G"]] != ["SKU Induk", "Nama Produk", "Foto Sampul", "Foto Produk 1", "Foto Produk 2"]:
                    raise ValueError("Unexpected Shopee media header")
                header_found = True
                continue
            if not header_found or not cells.get("A", "").isdigit():
                continue
            if cells["A"] in seen or not cells.get("C"):
                raise ValueError("Duplicate product ID or missing product name")
            seen.add(cells["A"])
            rows.append({"id": cells["A"], "sku": cells.get("B", ""), "name": cells["C"],
                         "photos": [cells.get(k, "") for k in ["E", "F", "G"]]})
        if not header_found or not rows or len(rows) > 5000:
            raise ValueError("No supported product rows")
        return {"schema": "qammaris-shopee-media-v1", "source_filename": path.name, "data": rows}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("source", type=Path)
    parser.add_argument("output", type=Path)
    args = parser.parse_args()
    private = (Path.cwd() / "storage/app/private").resolve()
    output = args.output.resolve()
    if private not in output.parents or output.suffix != ".json" or not output.parent.is_dir():
        raise ValueError("Output must be a new JSON file inside storage/app/private")
    result = extract(args.source)
    with output.open("x", encoding="utf-8") as stream:
        json.dump(result, stream, ensure_ascii=False, indent=2)
    print(f"Extracted {len(result['data'])} products; cover and first two additional slots only; workbook unchanged.")


if __name__ == "__main__":
    main()
