<?php

namespace App\Http\Controllers\Integrations;

use App\Actions\Orders\OnlineOrderFulfillment;
use App\Exceptions\OrderApi\OrderApiException;
use App\Http\Controllers\Controller;
use App\Models\OnlineOrder;
use App\Support\OrderActor;
use App\Support\OrderApi\OrderApiIdempotency;
use App\Support\OrderApi\OrderApiResponse;
use App\Support\OrderApi\OrderApiSchema;
use App\Support\OrderApi\OrderSerializer;
use App\Support\OrderApi\SignedMultipart;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use stdClass;

/**
 * Order API v1 mutations (contract r4.1 §8). Every endpoint: idempotency → request schema → App actor →
 * one V2 domain operation → `MutationResponse {order, event_id}` (`event_id` null for a no-op).
 */
class OrderApiWriteController extends Controller
{
    public function __construct(private readonly OnlineOrderFulfillment $orders) {}

    public function claim(Request $request, string $id): JsonResponse
    {
        return $this->mutate($request, $id, 'ClaimRequest', fn (OnlineOrder $order, stdClass $body, OrderActor $actor) => $this->orders->claim($order, $body->expected_revision ?? null, $actor, $body->task));
    }

    public function release(Request $request, string $id, string $task): JsonResponse
    {
        if (! in_array($task, ['preparation', 'courier_booking', 'handover'], true)) {
            throw new OrderApiException(422, 'validation_failed', 'Tugas tidak dikenal.', ['fields' => ['task' => 'preparation, courier_booking, atau handover']]);
        }

        return $this->mutate($request, $id, 'ReleaseClaimRequest', fn (OnlineOrder $order, stdClass $body, OrderActor $actor) => $this->orders->releaseClaim($order, $body->expected_revision ?? null, $actor, $task, $body->reason ?? null));
    }

    public function preparation(Request $request, string $id): JsonResponse
    {
        return $this->mutate($request, $id, 'PreparationRequest', fn (OnlineOrder $order, stdClass $body, OrderActor $actor) => $body->status === 'packed'
            ? $this->orders->pack($order, $body->expected_revision, $actor, array_map(fn ($item) => (array) $item, $body->packed_items ?? []))
            : $this->orders->startPreparation($order, $body->expected_revision, $actor));
    }

    public function courier(Request $request, string $id): JsonResponse
    {
        return $this->mutate($request, $id, 'CourierRequest', function (OnlineOrder $order, stdClass $body, OrderActor $actor) {
            // provider_label only names an "other" courier; it is kept with the reference.
            $reference = $body->provider === 'other' && filled($body->provider_label ?? null)
                ? trim(($body->provider_label).' '.($body->reference ?? '')) : ($body->reference ?? null);

            return $this->orders->requestCourier($order, $body->expected_revision, $actor, $body->provider, $body->status, $reference === '' ? null : $reference);
        });
    }

    public function jnt(Request $request, string $id): JsonResponse
    {
        return $this->mutate($request, $id, 'JntRequest', fn (OnlineOrder $order, stdClass $body, OrderActor $actor) => $this->orders->recordJnt(
            $order, $body->expected_revision, $actor, $body->status ?? null, $body->tracking_number ?? null, $this->time($body->occurred_at ?? null)));
    }

    public function qr(Request $request, string $id): JsonResponse
    {
        $order = OrderApiReadController::findOrder($id);

        return OrderApiIdempotency::run($request, function () use ($request, $order): JsonResponse {
            $form = SignedMultipart::parse($request);
            $revision = is_string($form['expected_revision'] ?? null) ? $form['expected_revision'] : '';
            $actor = is_string($form['actor'] ?? null) ? json_decode($form['actor'], false) : null;
            $fields = [];
            if (! preg_match('/^\d{1,9}$/', $revision)) {
                $fields['expected_revision'] = 'Wajib angka';
            }
            if (! $actor instanceof stdClass) {
                $fields['actor'] = 'Wajib JSON Actor';
            } else {
                foreach (OrderApiSchema::fieldErrors($actor, 'Actor') as $field => $message) {
                    $fields['actor.'.$field] = $message;
                }
            }
            $file = is_array($form['file'] ?? null) ? $form['file'] : null;
            $mime = $file ? $this->imageMime($file['content']) : null;
            if ($mime === null) {
                $fields['file'] = 'Wajib gambar PNG atau JPEG';
            } elseif (strlen($file['content']) > 2 * 1024 * 1024) {
                throw new OrderApiException(413, 'payload_too_large', 'QR maksimal 2 MiB.');
            }
            if ($fields) {
                throw new OrderApiException(422, 'validation_failed', 'Data QR tidak valid.', ['fields' => $fields]);
            }
            $updated = $this->orders->storeJntQr($order, (int) $revision, $this->actorFrom($request, $actor), $file['content'], $mime);

            return $this->mutationResponse($request, $updated);
        });
    }

    public function handover(Request $request, string $id): JsonResponse
    {
        return $this->mutate($request, $id, 'HandoverRequest', fn (OnlineOrder $order, stdClass $body, OrderActor $actor) => $this->orders->handover(
            $order, $body->expected_revision, $actor, $body->handed_to, $this->time($body->occurred_at ?? null)));
    }

    public function delivery(Request $request, string $id): JsonResponse
    {
        return $this->mutate($request, $id, 'DeliveryRequest', fn (OnlineOrder $order, stdClass $body, OrderActor $actor) => $this->orders->confirmDelivery(
            $order, $body->expected_revision, $actor, 'app', $this->time($body->occurred_at ?? null)));
    }

    public function openIssue(Request $request, string $id): JsonResponse
    {
        return $this->mutate($request, $id, 'IssueRequest', fn (OnlineOrder $order, stdClass $body, OrderActor $actor) => $this->orders->openIssue(
            $order, $body->expected_revision ?? null, $actor, $body->type, $body->note, $body->line_id ?? null, $body->reported_quantity ?? null));
    }

    public function resolveIssue(Request $request, string $id, string $issueId): JsonResponse
    {
        return $this->mutate($request, $id, 'ResolveIssueRequest', fn (OnlineOrder $order, stdClass $body, OrderActor $actor) => $this->orders->resolveIssue(
            $order, $issueId, $body->expected_revision ?? null, $actor, $body->note ?? null));
    }

    public function cost(Request $request, string $id, string $expenseRef): JsonResponse
    {
        if (! preg_match('/^[A-Za-z0-9_-]{8,64}$/', $expenseRef)) {
            throw new OrderApiException(422, 'validation_failed', 'expense_ref tidak valid.', ['fields' => ['expense_ref' => '8–64 karakter A-Z, a-z, 0-9, _ atau -']]);
        }

        return $this->mutate($request, $id, 'CostRequest', fn (OnlineOrder $order, stdClass $body, OrderActor $actor) => $this->orders->upsertCost(
            $order, $body->expected_revision ?? null, $actor, $expenseRef, json_decode(json_encode($body), true)));
    }

    /** @param  Closure(OnlineOrder, stdClass, OrderActor): OnlineOrder  $operation */
    private function mutate(Request $request, string $id, string $schema, Closure $operation): JsonResponse
    {
        $order = OrderApiReadController::findOrder($id);

        return OrderApiIdempotency::run($request, function () use ($request, $order, $schema, $operation): JsonResponse {
            $body = json_decode($request->getContent(), false);
            if (! $body instanceof stdClass) {
                throw new OrderApiException(400, 'bad_request', 'Body harus objek JSON.');
            }
            if ($fields = OrderApiSchema::fieldErrors($body, $schema)) {
                throw new OrderApiException(422, 'validation_failed', 'Data tidak valid.', ['fields' => $fields]);
            }

            return $this->mutationResponse($request, $operation($order, $body, $this->actorFrom($request, $body->actor)));
        });
    }

    private function mutationResponse(Request $request, OnlineOrder $order): JsonResponse
    {
        return OrderApiResponse::json($request, [
            'order' => OrderSerializer::order($order->fresh()),
            'event_id' => $this->orders->lastEvent?->public_id,
        ]);
    }

    private function actorFrom(Request $request, stdClass $actor): OrderActor
    {
        return OrderActor::app($actor->app_user_id, $actor->display_name, $actor->app_role,
            (string) $request->header('Idempotency-Key'), $request->attributes->get('order_api_app_request_id'));
    }

    private function time(?string $value): ?Carbon
    {
        return $value === null ? null : Carbon::parse($value);
    }

    private function imageMime(string $bytes): ?string
    {
        $info = @getimagesizefromstring($bytes);

        return match ($info[2] ?? null) {
            IMAGETYPE_PNG => 'image/png',
            IMAGETYPE_JPEG => 'image/jpeg',
            default => null,
        };
    }
}
