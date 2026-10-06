import fs from 'node:fs/promises';
import path from 'node:path';
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';

const require = createRequire(process.env.QAMMARIS_REVIEW_NODE_MODULES
  ? path.join(process.env.QAMMARIS_REVIEW_NODE_MODULES, 'package.json') : import.meta.url);
const { Workbook, SpreadsheetFile } = await import(pathToFileURL(require.resolve('@oai/artifact-tool')));
const [capturePath, outputPath] = process.argv.slice(2);
const privateRoot = await fs.realpath(path.resolve('storage/app/private'));
for (const location of [await fs.realpath(capturePath), await fs.realpath(path.dirname(outputPath))]) {
  const relative = path.relative(privateRoot, location);
  if (relative.startsWith('..') || path.isAbsolute(relative)) throw new Error('Use private review paths.');
}
try { await fs.access(outputPath); throw new Error('Workbook already exists.'); }
catch (error) { if (error.code !== 'ENOENT') throw error; }
const capture = JSON.parse(await fs.readFile(capturePath, 'utf8'));
if (capture.schema !== 'qammaris-launch-review-v1' || capture.environment !== 'staging') {
  throw new Error('Expected staging launch review.');
}
const ids = new Set();
for (const row of capture.data) {
  if (row.publication_status !== 'draft' || row.hidden !== false || ids.has(row.product_id)) {
    throw new Error('Only unique visible drafts may be reviewed.');
  }
  ids.add(row.product_id);
}
const isFirst = row => row.source !== 'app'
  && Object.keys(row.blockers).every(key => ['description', 'gender'].includes(key));
const ordered = [...capture.data].sort((a, b) => a.name.localeCompare(b.name, 'id') || Number(a.product_id) - Number(b.product_id));
const workbook = Workbook.create();
const texts = {
  category: 'Konsentrasi', primary_image: 'Foto utama', offer: 'Ukuran dan harga',
  brand: 'Merek', volume: 'Ukuran', price: 'Harga', primary_image_file: 'File foto',
};
const safe = value => typeof value === 'string' && /^[\s]*[=+\-@]/u.test(value) ? `'${value}` : value;
const built = [];
for (const [name, rows, title] of [
  ['Prioritas pertama', ordered.filter(isFirst), 'Lengkapi deskripsi dan peruntukan terlebih dahulu'],
  ['Kekurangan lainnya', ordered.filter(row => !isFirst(row)), 'Produk yang masih memerlukan foto atau data lainnya'],
]) {
  const sheet = workbook.worksheets.add(name);
  sheet.showGridLines = false;
  const last = rows.length + 6;
  sheet.getRange(`A1:Q${last}`).format.font = { name: 'Arial', size: 10, color: '#202020' };
  sheet.mergeCells('A2:H2');
  sheet.getRange('A2').values = [[title]];
  sheet.getRange('A2').format.font = { name: 'Arial', size: 14, bold: true };
  sheet.mergeCells('A3:H3');
  sheet.getRange('A3').values = [[`${rows.length} draft. Isi kolom krem F/G. Tidak ada penerapan atau publish otomatis.`]];
  sheet.mergeCells('A4:H4');
  sheet.getRange('A4').values = [[`Sumber: katalog staging ${capture.captured_at}, checkpoint ${capture.checkpoint}. Data tambahan belum disetujui.`]];
  sheet.getRange('A3:H4').format.rowHeight = 23;
  const headers = ['ID produk', 'Nama produk', 'Harga (Rp)', 'Ukuran (ml)', 'Konsentrasi',
    'Deskripsi dari Owner', 'Peruntukan dari Owner', 'Tindak lanjut', 'Merek', 'Foto',
    'Ketersediaan', 'Asal', 'UUID aplikasi', 'ID Shopee', 'Kekurangan lain',
    'Snapshot updated (UTC)', 'expected_row_fingerprint'];
  sheet.getRange('A6:Q6').values = [headers];
  sheet.getRange('A6:Q6').format = { fill: '#242424', font: { name: 'Arial', size: 10, bold: true, color: '#FFFFFF' }, wrapText: true, rowHeight: 40 };
  const matrix = rows.map(row => [row.product_id, safe(row.name), row.price === null ? null : Number(row.price),
    row.size_ml, row.category, '', '', null, row.brand, row.image_count, row.availability,
    row.source, row.uuid, row.shopee_id,
    [...Object.keys(row.blockers).filter(key => !['description', 'gender'].includes(key)).map(key => texts[key] ?? key),
      ...(row.source === 'app' ? ['Review source=app'] : [])].join(', '),
    new Date(row.expected_updated_at), row.expected_row_fingerprint]).map(values => values.map(safe));
  // The editable workbook is a review, not an import. Exact ISO guards stay in the CSV.
  for (const column of ['A', 'M', 'N', 'Q']) sheet.getRange(`${column}7:${column}${last}`).setNumberFormat('@');
  sheet.getRange(`P7:P${last}`).setNumberFormat('yyyy-mm-dd hh:mm:ss');
  sheet.getRange(`A7:Q${last}`).values = matrix;
  sheet.getRange(`A7:Q${last}`).format.verticalAlignment = 'center';
  sheet.getRange(`A7:Q${last}`).format.rowHeight = 44;
  sheet.getRange(`B7:B${last}`).format.wrapText = true;
  sheet.getRange(`E7:H${last}`).format.wrapText = true;
  sheet.getRange(`O7:O${last}`).format.wrapText = true;
  sheet.getRange(`F7:G${last}`).format.fill = '#FFF6DF';
  sheet.getRange(`G7:G${last}`).dataValidation = { rule: { type: 'list', values: ['Pria', 'Wanita', 'Unisex'] } };
  sheet.getRange(`H7:H${last}`).formulas = rows.map((_, i) => {
    const r = i + 7;
    return [`=IF(O${r}<>"",O${r},IF(AND(LEN(TRIM(F${r}))>0,OR(G${r}="Pria",G${r}="Wanita",G${r}="Unisex")),"Lengkap untuk preview","Isi deskripsi dan peruntukan"))`];
  });
  sheet.getRange(`C7:C${last}`).setNumberFormat('#,##0');
  sheet.getRange(`D7:D${last}`).setNumberFormat('0');
  sheet.getRange(`A7:A${last}`).setNumberFormat('@');
  sheet.getRange(`N7:N${last}`).setNumberFormat('@');
  const widths = [70, 290, 100, 78, 130, 310, 128, 230, 130, 65, 115, 80, 285, 155, 240, 220, 450];
  widths.forEach((width, i) => sheet.getRangeByIndexes(0, i, last, 1).format.columnWidthPx = width);
  sheet.freezePanes.freezeRows(6);
  sheet.freezePanes.freezeColumns(2);
  sheet.tables.add(`A6:Q${last}`, true, name === 'Prioritas pertama' ? 'FirstReview' : 'RemainingReview');
  sheet.getRange(`A7:Q${last}`).format.fill = '#FFFFFF';
  sheet.getRange(`F7:G${last}`).format.fill = '#FFF6DF';
  sheet.getRange(`A7:B${last}`).format.horizontalAlignment = 'left';
  built.push({ sheet, rows, name, last });
}
workbook.recalculate();
// Verify one input never hides a second missing requirement; restore every disposable edit.
const first = built[0].sheet;
first.getRange('F7').values = [['Deskripsi contoh uji yang tidak disimpan.']];
if (first.getRange('H7').values[0][0] !== 'Isi deskripsi dan peruntukan') throw new Error('Audience must remain required.');
first.getRange('G7').values = [['Unisex']];
if (first.getRange('H7').values[0][0] !== 'Lengkap untuk preview') throw new Error('Complete inputs must request preview.');
first.getRange('F7:G7').values = [['', '']];
const remaining = built[1].sheet;
remaining.getRange('F7:G7').values = [['Deskripsi contoh uji.', 'Unisex']];
if (remaining.getRange('H7').values[0][0] === 'Lengkap untuk preview') throw new Error('Other blockers must remain visible.');
remaining.getRange('F7:G7').values = [['', '']];
workbook.recalculate();
const errors = await workbook.inspect({ kind: 'match', searchTerm: '#REF!|#DIV/0!|#VALUE!|#NAME\\?|#N/A|#NUM!|#NULL!', options: { useRegex: true, maxResults: 10 }, summary: 'formula errors' });
console.log(errors.ndjson);
for (const { name } of built) {
  const preview = await workbook.render({ sheetName: name, range: 'A1:H11', scale: 1, format: 'png' });
  await fs.writeFile(path.join(path.dirname(outputPath), name === 'Prioritas pertama' ? 'first-review.png' : 'remaining-review.png'), new Uint8Array(await preview.arrayBuffer()));
}
const xlsx = await SpreadsheetFile.exportXlsx(workbook);
await xlsx.save(outputPath);
console.log(JSON.stringify({ rows: built.map(({ name, rows }) => ({ name, count: rows.length })), inputChecks: 'passed', workbookSaved: true }));
