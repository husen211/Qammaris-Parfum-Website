<?php

namespace Tests\Support;

use Illuminate\Http\UploadedFile;
use ZipArchive;

class ShopeeWorkbook
{
    public static function make(string $kind, array $rows, bool $formula = false, bool $entity = false): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'shopee-test-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $headers = $kind === 'basic' ? ['Kode Produk', 'SKU Induk', 'Nama Produk', 'Deskripsi Produk']
            : ['Kode Produk', 'SKU Induk', 'Nama Produk', 'Kategori', 'Foto Sampul', 'Foto Produk 1', 'Foto Produk 2'];
        $xml = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach (array_merge([$headers], $rows) as $i => $values) {
            $xml .= '<row r="'.($i + 1).'">';
            foreach ($values as $j => $value) {
                $xml .= '<c r="'.chr(65 + $j).($i + 1).'" t="inlineStr">'.($formula && $i === 1 && $j === 2 ? '<f>HYPERLINK("https://example.test")</f>' : '')
                    .'<is><t>'.htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</t></is></c>';
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData></worksheet>';
        if ($entity) {
            $xml = '<!DOCTYPE worksheet [<!ENTITY unsafe SYSTEM "file:///not-readable">]>'.$xml;
        }
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml);
        $zip->close();

        return new UploadedFile($path, $kind.'.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', UPLOAD_ERR_OK, true);
    }
}
