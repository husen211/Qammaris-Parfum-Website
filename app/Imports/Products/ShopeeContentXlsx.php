<?php

namespace App\Imports\Products;

use App\Exceptions\InvalidProductImportFile;
use Illuminate\Http\UploadedFile;
use SimpleXMLElement;
use XMLReader;
use ZipArchive;

class ShopeeContentXlsx
{
    public function read(UploadedFile $file, string $kind): array
    {
        if (! class_exists(ZipArchive::class) || ! extension_loaded('simplexml') || ! class_exists(XMLReader::class)) {
            throw new InvalidProductImportFile('Server perlu ekstensi PHP ZIP, XMLReader dan SimpleXML untuk membaca XLSX.');
        }
        if (! in_array($kind, ['basic', 'media'], true)) {
            throw new InvalidProductImportFile('Jenis ekspor tidak didukung.');
        }
        $zip = new ZipArchive;
        if ($file->getSize() > 8 * 1024 * 1024 || $zip->open($file->getRealPath()) !== true) {
            throw new InvalidProductImportFile('File bukan XLSX yang valid atau melebihi 8 MB.');
        }
        try {
            $total = 0;
            if ($zip->numFiles > 512) {
                throw new InvalidProductImportFile('Isi XLSX terlalu besar.');
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);
                $total += $entry['size'];
                if ($total > 64 * 1024 * 1024 || str_contains(strtolower($entry['name']), 'vbaproject')) {
                    throw new InvalidProductImportFile('XLSX terlalu besar atau mengandung makro.');
                }
            }
            $shared = [];
            if ($zip->locateName('xl/sharedStrings.xml') !== false) {
                foreach ($this->xml($zip, 'xl/sharedStrings.xml')->xpath('//*[local-name()="si"]') as $node) {
                    $shared[] = $this->text($node);
                }
            }
            $sheet = $this->xml($zip, 'xl/worksheets/sheet1.xml');
            $headers = ['A' => 'Kode Produk', 'B' => 'SKU Induk', 'C' => 'Nama Produk'] + ($kind === 'basic'
                ? ['D' => 'Deskripsi Produk']
                : ['E' => 'Foto Sampul', 'F' => 'Foto Produk 1', 'G' => 'Foto Produk 2']);
            $result = [];
            $found = false;
            $headerRow = 0;
            foreach ($sheet->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]') as $row) {
                $cells = [];
                foreach ($row->xpath('./*[local-name()="c"]') as $cell) {
                    $column = preg_replace('/\d+$/', '', (string) $cell['r']);
                    if (! array_key_exists($column, $headers)) {
                        continue;
                    }
                    if ($cell->xpath('./*[local-name()="f"]')) {
                        throw new InvalidProductImportFile('Kolom produk harus berupa nilai teks, bukan formula.');
                    }
                    $v = $cell->xpath('./*[local-name()="v"]');
                    $value = $v ? (string) $v[0] : '';
                    if ((string) $cell['t'] === 's') {
                        if (! ctype_digit($value) || ! isset($shared[(int) $value])) {
                            throw new InvalidProductImportFile('Referensi teks XLSX tidak valid.');
                        }
                        $value = $shared[(int) $value];
                    } elseif ((string) $cell['t'] === 'inlineStr') {
                        $value = $this->text($cell);
                    }
                    if (mb_strlen($value) > 20000) {
                        throw new InvalidProductImportFile('Teks produk melebihi 20.000 karakter.');
                    }
                    $cells[$column] = trim($value);
                }
                if (($cells['A'] ?? '') === 'Kode Produk') {
                    if ($found || array_diff_assoc($headers, $cells)) {
                        throw new InvalidProductImportFile('Pilih ekspor Shopee '.($kind === 'basic' ? 'Informasi Dasar' : 'Media').' dengan kolom aslinya.');
                    }
                    $found = true;
                    $headerRow = (int) $row['r'];

                    continue;
                }
                if (! $found || ! array_filter($cells, fn ($v) => $v !== '')) {
                    continue;
                }
                // Shopee Media includes two instruction rows below its visible header.
                if ($kind === 'media' && ! $result && (int) $row['r'] <= $headerRow + 3
                    && ($cells['A'] ?? '') === '' && ($cells['B'] ?? '') === '' && ($cells['C'] ?? '') === ''
                    && (($cells['E'] ?? '') === 'Wajib' || str_starts_with($cells['E'] ?? '', 'Mohon masukkan link foto.'))) {
                    continue;
                }
                $id = $cells['A'] ?? '';
                if (! preg_match('/^[0-9]{1,30}$/D', $id) || isset($result[$id]) || empty($cells['C']) || mb_strlen($cells['C']) > 255) {
                    throw new InvalidProductImportFile('Kode produk/nama tidak valid atau kode produk berulang pada baris '.(string) $row['r'].'.');
                }
                $result[$id] = ['id' => $id, 'name' => $cells['C'], 'sku' => $cells['B'] ?? '', 'source_row' => (int) $row['r']]
                    + ($kind === 'basic' ? ['description' => $cells['D'] ?? ''] : ['photos' => [$cells['E'] ?? '', $cells['F'] ?? '', $cells['G'] ?? '']]);
                if (count($result) > 1000) {
                    throw new InvalidProductImportFile('Maksimum 1.000 produk per impor.');
                }
            }
            if (! $found || ! $result) {
                throw new InvalidProductImportFile('Tidak ada baris produk Shopee yang dapat dibaca.');
            }

            return $result;
        } finally {
            $zip->close();
        }
    }

    private function xml(ZipArchive $zip, string $name): SimpleXMLElement
    {
        $stat = $zip->statName($name);
        if (! $stat || $stat['size'] > 8 * 1024 * 1024) {
            throw new InvalidProductImportFile('Bagian XLSX tidak ditemukan atau terlalu besar.');
        }
        $raw = $zip->getFromName($name);
        if ($raw === false || trim($raw) === '') {
            throw new InvalidProductImportFile('XML di dalam XLSX tidak valid.');
        }
        if (preg_match('/<!DOCTYPE|<!ENTITY/i', $raw)) {
            throw new InvalidProductImportFile('Deklarasi XML eksternal tidak diizinkan.');
        }
        $previous = libxml_use_internal_errors(true);
        try {
            // Count structure before building a DOM; compressed uploads cannot exhaust memory with tiny XML nodes.
            $reader = new XMLReader;
            if (! $reader->XML($raw, null, LIBXML_NONET)) {
                throw new InvalidProductImportFile('XML di dalam XLSX tidak valid.');
            }
            $nodes = 0;
            $rows = 0;
            try {
                while ($reader->read()) {
                    if (++$nodes > 100000 || $reader->nodeType === XMLReader::DOC_TYPE
                        || ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'row' && ++$rows > 1100)) {
                        throw new InvalidProductImportFile('Struktur XLSX terlalu besar atau tidak aman. Ekspor maksimum 1.000 produk.');
                    }
                }
            } finally {
                $reader->close();
            }
            $xml = simplexml_load_string($raw, SimpleXMLElement::class, LIBXML_NONET);
            if ($xml === false) {
                throw new InvalidProductImportFile('XML di dalam XLSX tidak valid.');
            }

            return $xml;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function text(SimpleXMLElement $node): string
    {
        return implode('', array_map(fn ($text) => (string) $text, $node->xpath('.//*[local-name()="t"]')));
    }
}
