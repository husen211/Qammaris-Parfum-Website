<?php

namespace App\Http\Controllers\Integrations;

use App\Exceptions\OrderApi\OrderApiException;
use App\Http\Controllers\Controller;
use App\Models\OnlineOrder;
use App\Support\OnlineOrderState;
use App\Support\OrderApi\OrderApiResponse;
use App\Support\OrderApi\OrderSerializer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/** Order API v1 reads (contract r4.1 §6, §8). Only V2 orders exist for the App; anything else is `order_not_found`. */
class OrderApiReadController extends Controller
{
    private const SCAN_BATCH = 200;

    public function index(Request $request): JsonResponse
    {
        [$since, $after, $limit, $lifecycle, $queue] = $this->listParameters($request);
        $data = [];
        $last = null;
        $more = false;
        $cursor = $after;

        // `queue` is derived, so rows are scanned in key order until the page is full; the cursor marks the last row scanned.
        while (count($data) < $limit) {
            $batch = $this->batch($since, $cursor, $lifecycle, self::SCAN_BATCH);
            foreach ($batch as $index => $order) {
                $last = $order;
                if ($queue === null || OnlineOrderState::queue($order, $order->issues->where('status', 'open')->count(), $order->claims->contains('task', 'preparation')) === $queue) {
                    $data[] = OrderSerializer::summary($order);
                }
                if (count($data) === $limit) {
                    $more = $index < $batch->count() - 1 || $this->batch($since, $this->key($order), $lifecycle, 1)->isNotEmpty();
                    break 2;
                }
            }
            if ($batch->count() < self::SCAN_BATCH) {
                break;
            }
            $cursor = $this->key($last);
        }

        return OrderApiResponse::json($request, [
            'data' => $data,
            'next_cursor' => $more && $last ? $this->encodeCursor($this->key($last)) : null,
            'has_more' => $more,
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        return OrderApiResponse::json($request, OrderSerializer::order(self::findOrder($id)));
    }

    /** QR image from private storage; never cached. */
    public function qr(Request $request, string $id): Response
    {
        $order = self::findOrder($id);
        if ($order->jnt_qr_path === null || ! Storage::disk('local')->exists($order->jnt_qr_path)) {
            throw new OrderApiException(404, 'order_not_found', 'QR J&T belum tersedia untuk order ini.');
        }

        return response(Storage::disk('local')->get($order->jnt_qr_path), 200, [
            'Content-Type' => $order->jnt_qr_mime, 'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff',
            'X-Qammaris-Api-Version' => '1', 'X-Qammaris-Request-Id' => OrderApiResponse::requestId($request),
        ]);
    }

    public static function findOrder(string $id): OnlineOrder
    {
        return OnlineOrder::query()->where('state_model', OnlineOrder::STATE_V2)->where('public_id', $id)->firstOrFail();
    }

    private function batch(?Carbon $since, ?array $after, ?string $lifecycle, int $size)
    {
        return OnlineOrder::query()->with(OrderSerializer::RELATIONS)
            ->where('state_model', OnlineOrder::STATE_V2)
            ->when($since, fn ($query) => $query->where('updated_at', '>=', $since))
            ->when($lifecycle, fn ($query) => $query->where('lifecycle', $lifecycle))
            ->when($after, fn ($query) => $query->where(fn ($inner) => $inner->where('updated_at', '>', $after[0])
                ->orWhere(fn ($same) => $same->where('updated_at', $after[0])->where('id', '>', $after[1]))))
            ->orderBy('updated_at')->orderBy('id')->limit($size)->get();
    }

    /** @return array{0: string, 1: int} */
    private function key(OnlineOrder $order): array
    {
        return [$order->getRawOriginal('updated_at'), $order->id];
    }

    private function encodeCursor(array $key): string
    {
        return rtrim(strtr(base64_encode(json_encode(['u' => $key[0], 'i' => $key[1]])), '+/', '-_'), '=');
    }

    private function listParameters(Request $request): array
    {
        $fields = [];
        $since = null;
        if ($request->filled('updated_since')) {
            $raw = (string) $request->query('updated_since');
            if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:\d{2})$/', $raw)) {
                $since = Carbon::parse($raw)->setTimezone(config('app.timezone'));
            } else {
                $fields['updated_since'] = 'Harus waktu ISO 8601, misalnya 2026-10-08T00:00:00Z';
            }
        }
        $after = null;
        if ($request->filled('cursor')) {
            $decoded = json_decode((string) base64_decode(strtr((string) $request->query('cursor'), '-_', '+/'), true), true);
            if (is_array($decoded) && is_string($decoded['u'] ?? null) && is_int($decoded['i'] ?? null)) {
                $after = [$decoded['u'], $decoded['i']];
            } else {
                $fields['cursor'] = 'Cursor tidak dikenal; kirim kembali next_cursor apa adanya';
            }
        }
        $limit = $request->query('limit', 50);
        if (! is_numeric($limit) || (int) $limit != $limit || $limit < 1 || $limit > 100) {
            $fields['limit'] = '1–100';
        }
        $lifecycle = $request->query('lifecycle');
        if ($lifecycle !== null && ! in_array($lifecycle, ['draft', 'awaiting_customer', 'active', 'completed', 'cancelled'], true)) {
            $fields['lifecycle'] = 'Nilai tidak dikenal';
        }
        $queue = $request->query('queue');
        if ($queue !== null && ! in_array($queue, OnlineOrderState::QUEUES, true)) {
            $fields['queue'] = 'Nilai tidak dikenal';
        }
        if ($fields) {
            throw new OrderApiException(422, 'validation_failed', 'Parameter tidak valid.', ['fields' => $fields]);
        }

        return [$since, $after, (int) $limit, $lifecycle, $queue];
    }
}
