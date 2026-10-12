<?php

namespace App\Support\OrderApi;

use App\Exceptions\OrderApi\OrderApiException;
use Illuminate\Http\Request;

/**
 * multipart/form-data for `PUT /orders/{id}/jnt/qr`. PHP does not parse multipart bodies of PUT requests, so the
 * raw body stays available for the HMAC (signed byte for byte) and is parsed here.
 */
final class SignedMultipart
{
    /** @return array<string, string|array{filename: ?string, content: string}> text fields as strings, file parts as arrays */
    public static function parse(Request $request): array
    {
        if (! preg_match('/^multipart\/form-data;.*boundary="?([^";]+)"?/i', (string) $request->header('Content-Type'), $match)) {
            throw new OrderApiException(400, 'bad_request', 'Content-Type harus multipart/form-data dengan boundary.');
        }
        $parts = explode('--'.$match[1], $request->getContent());
        $fields = [];
        foreach (array_slice($parts, 1) as $part) {
            if (str_starts_with($part, '--')) {
                break;
            }
            $part = substr($part, str_starts_with($part, "\r\n") ? 2 : 0);
            [$head, $content] = array_pad(explode("\r\n\r\n", $part, 2), 2, null);
            if ($content === null || ! preg_match('/content-disposition:\s*form-data;\s*name="([^"]+)"(?:;\s*filename="([^"]*)")?/i', $head, $disposition)) {
                throw new OrderApiException(400, 'bad_request', 'Bagian multipart tidak valid.');
            }
            $content = str_ends_with($content, "\r\n") ? substr($content, 0, -2) : $content;
            $fields[$disposition[1]] = isset($disposition[2]) ? ['filename' => $disposition[2], 'content' => $content] : $content;
        }

        return $fields;
    }
}
