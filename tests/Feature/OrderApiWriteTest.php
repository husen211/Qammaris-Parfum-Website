<?php

namespace Tests\Feature;

use App\Actions\Orders\RecordOnlineOrderMoney;
use App\Models\OnlineOrder;
use App\Models\OnlineOrderEvent;
use App\Support\OrderApi\OrderApiSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsV2Orders;
use Tests\Concerns\SignsOrderApiRequests;
use Tests\TestCase;

/** ORD-02e slice B: Order API v1 mutations against the r4.1 baseline. */
class OrderApiWriteTest extends TestCase
{
    use BuildsV2Orders, RefreshDatabase, SignsOrderApiRequests;

    private const IKRAR = '6660a1b2c3d4e5f601234567';

    private const OWNER = '6650aaaabbbbccccddddeeee';

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableOrderApi();
        Storage::fake('local');
    }

    public function test_every_openapi_operation_has_a_route(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->flatMap(fn ($route) => array_map(fn ($method) => $method.' /'.$route->uri(), $route->methods()))->all();
        foreach (OrderApiSchema::spec()['paths'] as $path => $item) {
            foreach (array_intersect(array_keys($item), ['get', 'post', 'put', 'delete']) as $method) {
                $uri = '/integrations/qammaris-app/orders/v1'.preg_replace(['/\{issue_id\}/', '/\{expense_ref\}/'], ['{issueId}', '{expenseRef}'], $path);
                $this->assertContains(strtoupper($method).' '.$uri, $routes, "{$method} {$path}");
            }
        }
    }

    public function test_claims_are_atomic_with_task_already_claimed_before_revision_conflict(): void
    {
        $order = $this->v2Order();
        $claim = $this->mutate('POST', $order, '/claims', ['task' => 'preparation', 'expected_revision' => $order->revision, 'actor' => $this->actor()]);
        $data = $this->assertMutation($claim);
        $this->assertSame([$order->revision + 1, '665f0c2a9b1e4a0012ab34cd', 'preparing'], [$data->order->revision, $data->order->claims->preparation->holder->app_user_id, $data->order->queue]);

        $again = $this->assertMutation($this->mutate('POST', $order, '/claims', ['task' => 'preparation', 'actor' => $this->actor()]));
        $this->assertNull($again->event_id, 'Same holder again: no-op');
        $this->assertSame($data->order->revision, $again->order->revision);

        // Another employee, even with a stale revision, learns who holds the task.
        $taken = $this->assertApiError($this->mutate('POST', $order, '/claims', ['task' => 'preparation', 'expected_revision' => 1, 'actor' => $this->actor(self::IKRAR, 'Ikrar')]), 409, 'task_already_claimed');
        $this->assertSame(['preparation', '665f0c2a9b1e4a0012ab34cd', 'Andi'], [$taken->error->details->task, $taken->error->details->holder->app_user_id, $taken->error->details->holder->display_name]);

        $this->assertApiError($this->mutate('POST', $order, '/claims', ['task' => 'handover', 'expected_revision' => 1, 'actor' => $this->actor()]), 409, 'revision_conflict');
        $waiting = $this->v2Order(null);
        $this->assertApiError($this->mutate('POST', $waiting, '/claims', ['task' => 'preparation', 'actor' => $this->actor()]), 409, 'invalid_transition');
        $pickup = $this->v2Order('pickup');
        $this->assertApiError($this->mutate('POST', $pickup, '/claims', ['task' => 'courier_booking', 'actor' => $this->actor()]), 409, 'invalid_transition');

        // Releasing someone else's claim: owner only, with a reason.
        $this->assertApiError($this->mutate('DELETE', $order, '/claims/preparation', ['actor' => $this->actor(self::IKRAR, 'Ikrar')]), 403, 'action_not_allowed');
        $this->assertApiError($this->mutate('DELETE', $order, '/claims/preparation', ['actor' => $this->actor(self::OWNER, 'Owner', 'owner')]), 422, 'validation_failed');
        $released = $this->assertMutation($this->mutate('DELETE', $order, '/claims/preparation', ['reason' => 'Andi sakit, dipindah', 'actor' => $this->actor(self::OWNER, 'Owner', 'owner')]));
        $this->assertNull($released->order->claims->preparation);
        $this->assertNull($this->assertMutation($this->mutate('DELETE', $order, '/claims/preparation', ['actor' => $this->actor()]))->event_id, 'Nothing to release: no-op');
    }

    public function test_preparation_requires_the_claim_and_exact_packed_quantities_at_the_latest_revision(): void
    {
        $order = $this->v2Order();
        [$alpha, $beta] = $order->items->pluck('line_id')->all();
        $this->assertApiError($this->mutate('POST', $order, '/preparation', ['status' => 'preparing', 'expected_revision' => $order->revision, 'actor' => $this->actor()]), 403, 'action_not_allowed');
        $revision = $this->claim($order, 'preparation');

        $short = $this->assertApiError($this->mutate('POST', $order, '/preparation', ['status' => 'packed', 'expected_revision' => $revision,
            'packed_items' => [['line_id' => $alpha, 'quantity' => 1], ['line_id' => $beta, 'quantity' => 1]], 'actor' => $this->actor()]), 422, 'validation_failed');
        $this->assertObjectHasProperty('packed_items.0.quantity', $short->error->details->fields);
        $this->assertApiError($this->mutate('POST', $order, '/preparation', ['status' => 'packed', 'expected_revision' => $revision,
            'actor' => $this->actor()]), 422, 'validation_failed');
        $this->assertApiError($this->mutate('POST', $order, '/preparation', ['status' => 'packed', 'expected_revision' => $revision - 1,
            'packed_items' => [['line_id' => $alpha, 'quantity' => 2], ['line_id' => $beta, 'quantity' => 1]], 'actor' => $this->actor()]), 409, 'revision_conflict');

        $packed = $this->assertMutation($this->mutate('POST', $order, '/preparation', ['status' => 'packed', 'expected_revision' => $revision,
            'packed_items' => [['line_id' => $beta, 'quantity' => 1], ['line_id' => $alpha, 'quantity' => 2]], 'actor' => $this->actor()]));
        $this->assertSame(['packed', 'ready'], [$packed->order->fulfillment->preparation->status, $packed->order->queue]);
        $this->assertSame([[$alpha, 2], [$beta, 1]], array_map(fn ($item) => [$item->line_id, $item->quantity], $packed->order->fulfillment->preparation->packed_items));
    }

    public function test_courier_request_needs_courier_booking_claim_and_handover_needs_the_handover_claim(): void
    {
        $order = $this->packedOrder('local_delivery');
        $this->assertApiError($this->mutate('POST', $order, '/courier-requests', ['provider' => 'maxim', 'status' => 'requested', 'expected_revision' => $order->fresh()->revision, 'actor' => $this->actor()]), 403, 'action_not_allowed');
        $revision = $this->claim($order, 'courier_booking');
        $requested = $this->assertMutation($this->mutate('POST', $order, '/courier-requests', ['provider' => 'other', 'provider_label' => 'Ojek pangkalan', 'reference' => 'OP-1', 'status' => 'requested', 'expected_revision' => $revision, 'actor' => $this->actor()]));
        $this->assertSame(['requested', 'other', 'Ojek pangkalan OP-1', 'awaiting_pickup'], [$requested->order->fulfillment->courier->status, $requested->order->fulfillment->courier->provider, $requested->order->fulfillment->courier->reference, $requested->order->queue]);

        $this->assertApiError($this->mutate('POST', $order, '/handover', ['handed_to' => 'courier', 'expected_revision' => $requested->order->revision, 'actor' => $this->actor()]), 403, 'action_not_allowed');
        $revision = $this->claim($order, 'handover');
        $handed = $this->assertMutation($this->mutate('POST', $order, '/handover', ['handed_to' => 'courier', 'occurred_at' => '2026-10-09T03:05:00Z', 'expected_revision' => $revision, 'actor' => $this->actor()]));
        $this->assertSame(['handed_over', 'courier', '2026-10-09T03:05:00Z', 'in_delivery'], [$handed->order->fulfillment->handover->status, $handed->order->fulfillment->handover->handed_to, $handed->order->fulfillment->handover->handed_over_at, $handed->order->queue]);

        $delivered = $this->assertMutation($this->mutate('POST', $order, '/delivery', ['expected_revision' => $handed->order->revision, 'actor' => $this->actor(self::IKRAR, 'Ikrar')]));
        $this->assertSame(['delivered', 'app'], [$delivered->order->fulfillment->delivery->status, $delivered->order->fulfillment->delivery->confirmed_by]);
    }

    public function test_jnt_request_qr_upload_and_pickup_as_atomic_handover(): void
    {
        $order = $this->packedOrder('intercity');
        $revision = $this->claim($order, 'courier_booking');
        $requested = $this->assertMutation($this->mutate('POST', $order, '/jnt', ['status' => 'pickup_requested', 'expected_revision' => $revision, 'actor' => $this->actor()]));
        $this->assertSame('pickup_requested', $requested->order->fulfillment->jnt->status);

        $png = $this->png();
        $qr = $this->assertMutation($this->qrUpload($order, $png, $requested->order->revision));
        $this->assertSame([$requested->order->revision + 1, 'qr_available', true], [$qr->order->revision, $qr->order->fulfillment->jnt->status, $qr->order->fulfillment->jnt->qr_available]);
        $download = $this->orderApi('GET', '/orders/'.$order->public_id.'/jnt/qr')->assertOk();
        $this->assertSame([$png, 'image/png'], [$download->getContent(), $download->headers->get('Content-Type')]);
        $this->assertStringContainsString('no-store', $download->headers->get('Cache-Control'));
        $this->assertCount(1, Storage::disk('local')->allFiles('order-qr'));

        $this->assertApiError($this->qrUpload($order, 'not an image', $qr->order->revision), 422, 'validation_failed');
        $this->assertApiError($this->qrUpload($order, $png, $qr->order->revision, $this->actor(self::IKRAR, 'Ikrar')), 403, 'action_not_allowed');
        $this->assertCount(1, Storage::disk('local')->allFiles('order-qr'), 'A refused upload leaves no file behind');

        // Picking up is the handover: needs the handover claim, one revision, one event.
        $this->assertApiError($this->mutate('POST', $order, '/jnt', ['status' => 'picked_up', 'expected_revision' => $qr->order->revision, 'actor' => $this->actor()]), 403, 'action_not_allowed');
        $revision = $this->claim($order, 'handover');
        $picked = $this->assertMutation($this->mutate('POST', $order, '/jnt', ['status' => 'picked_up', 'occurred_at' => '2026-10-09T07:10:00Z', 'expected_revision' => $revision, 'actor' => $this->actor()]));
        $this->assertSame([$revision + 1, 'picked_up', 'handed_over', 'jnt'], [$picked->order->revision, $picked->order->fulfillment->jnt->status, $picked->order->fulfillment->handover->status, $picked->order->fulfillment->handover->handed_to]);
        $this->assertSame(1, OnlineOrderEvent::where('online_order_id', $order->id)->where('kind', 'jnt_picked_up')->count());

        $tracked = $this->assertMutation($this->mutate('POST', $order, '/jnt', ['tracking_number' => 'JX1234567890', 'expected_revision' => $picked->order->revision, 'actor' => $this->actor()]));
        $this->assertSame('JX1234567890', $tracked->order->fulfillment->jnt->tracking_number);
    }

    public function test_issues_and_owner_only_resolution_of_someone_elses_issue(): void
    {
        $order = $this->v2Order();
        $line = $order->items->first()->line_id;
        $opened = $this->assertMutation($this->mutate('POST', $order, '/issues', ['type' => 'stock_problem', 'note' => 'Stok hanya 1', 'line_id' => $line, 'reported_quantity' => 1, 'actor' => $this->actor()]));
        $issue = $opened->order->issues[0];
        $this->assertSame(['has_issue', 'open', $line, 1, '665f0c2a9b1e4a0012ab34cd'], [$opened->order->queue, $issue->status, $issue->line_id, $issue->reported_quantity, $issue->opened_by->app_user_id]);

        $this->assertApiError($this->mutate('POST', $order, '/issues/'.$issue->id.'/resolve', ['actor' => $this->actor(self::IKRAR, 'Ikrar')]), 403, 'action_not_allowed');
        $resolved = $this->assertMutation($this->mutate('POST', $order, '/issues/'.$issue->id.'/resolve', ['note' => 'Diganti', 'actor' => $this->actor(self::OWNER, 'Owner', 'owner')]));
        $this->assertSame(['resolved', 'needs_handling'], [$resolved->order->issues[0]->status, $resolved->order->queue]);
        $this->assertApiError($this->mutate('POST', $order, '/issues', ['type' => 'lost', 'note' => 'x', 'actor' => $this->actor()]), 422, 'validation_failed');
    }

    public function test_costs_funding_reimbursement_proof_and_unique_expense_ref(): void
    {
        $order = $this->v2Order();
        $ref = 'exp_9b2f4c1e8a7d4b6f9c0e1a2b3c4d5e6f';
        $cost = fn (array $override = []) => array_replace_recursive([
            'kind' => 'actual_shipping', 'status' => 'active', 'amount' => 20000,
            'funding' => [['source' => 'customer_cash_held', 'amount' => 15000], ['source' => 'staff_advance', 'amount' => 5000]],
            'reimbursement' => ['status' => 'awaiting_proof', 'amount' => 5000, 'proof' => 'pending', 'waiver' => null, 'updated_at' => '2026-10-09T03:10:00Z'],
            'source_version' => 1, 'actor' => $this->actor(),
        ], $override);

        $saved = $this->assertMutation($this->mutate('PUT', $order, '/costs/'.$ref, $cost()));
        $this->assertSame([$ref, 20000, 5000, 'awaiting_proof'], [$saved->order->costs[0]->expense_ref, $saved->order->costs[0]->amount, $saved->order->costs[0]->reimbursement->amount, $saved->order->costs[0]->reimbursement->status]);
        $this->assertTrue($saved->order->flags->open_reimbursement);
        $this->assertSame([['reimbursement', 'open', 5000, $ref, false]], array_map(fn ($o) => [$o->type, $o->status, $o->amount, $o->ref, $o->blocks_completion], $saved->order->obligations));

        $this->assertApiError($this->mutate('PUT', $order, '/costs/'.$ref, $cost(['amount' => 21000, 'source_version' => 2])), 422, 'validation_failed');
        $this->assertApiError($this->mutate('PUT', $order, '/costs/'.$ref, $cost(['reimbursement' => ['amount' => 4000], 'source_version' => 2])), 422, 'validation_failed');
        $this->assertApiError($this->mutate('PUT', $order, '/costs/'.$ref, $cost(['reimbursement' => ['status' => 'approved', 'proof' => 'pending'], 'source_version' => 2])), 422, 'validation_failed');
        $this->assertMutation($this->mutate('PUT', $order, '/costs/'.$ref, $cost(['reimbursement' => ['status' => 'submitted', 'proof' => 'pending'], 'source_version' => 2])));
        $waiver = ['by' => ['app_user_id' => self::OWNER, 'display_name' => 'Owner'], 'reason' => 'Struk hilang, sudah dicek', 'at' => '2026-10-09T04:00:00Z'];
        $this->assertApiError($this->mutate('PUT', $order, '/costs/'.$ref, $cost(['reimbursement' => ['status' => 'approved', 'proof' => 'waived', 'waiver' => $waiver], 'source_version' => 3])), 403, 'action_not_allowed');
        $approved = $this->assertMutation($this->mutate('PUT', $order, '/costs/'.$ref, $cost(['reimbursement' => ['status' => 'approved', 'proof' => 'waived', 'waiver' => $waiver], 'source_version' => 3, 'actor' => $this->actor(self::OWNER, 'Owner', 'owner')])));
        $this->assertSame('approved', $approved->order->costs[0]->reimbursement->status);

        $stale = $this->assertMutation($this->mutate('PUT', $order, '/costs/'.$ref, $cost(['source_version' => 2])));
        $this->assertNull($stale->event_id, 'Older source_version: no-op');
        $this->assertSame('approved', $stale->order->costs[0]->reimbursement->status);

        $other = $this->v2Order();
        $this->assertApiError($this->mutate('PUT', $other, '/costs/'.$ref, $cost(['source_version' => 9])), 409, 'expense_already_linked');
        $this->assertApiError($this->mutate('PUT', $order, '/costs/bad', $cost()), 422, 'validation_failed');
    }

    public function test_an_open_reimbursement_does_not_block_completion(): void
    {
        $order = $this->packedOrder('pickup');
        $this->mutate('PUT', $order, '/costs/exp_r8_completion_check', [
            'kind' => 'packaging', 'status' => 'active', 'amount' => 5000, 'funding' => [['source' => 'staff_advance', 'amount' => 5000]],
            'reimbursement' => ['status' => 'submitted', 'amount' => 5000, 'proof' => 'attached', 'waiver' => null, 'updated_at' => '2026-10-09T03:10:00Z'],
            'source_version' => 1, 'actor' => $this->actor(),
        ])->assertOk();
        app(RecordOnlineOrderMoney::class)->recordPayment($order->fresh(), $order->fresh()->revision, $this->orderOwner, '400000', 'cash', 'proof_in_chat');
        $revision = $this->claim($order, 'handover');
        $done = $this->assertMutation($this->mutate('POST', $order, '/handover', ['handed_to' => 'customer', 'expected_revision' => $revision, 'actor' => $this->actor()]));
        $this->assertSame(['completed', 'done', true], [$done->order->lifecycle, $done->order->queue, $done->order->flags->open_reimbursement]);
    }

    public function test_idempotency_replay_reuse_stored_errors_and_expiry(): void
    {
        $order = $this->v2Order();
        $key = Str::random(24);
        $body = ['task' => 'preparation', 'expected_revision' => $order->revision, 'actor' => $this->actor()];
        $first = $this->mutate('POST', $order, '/claims', $body, $key)->assertOk();
        // The reply was lost; the App retries the same bytes with the same key.
        $replay = $this->mutate('POST', $order, '/claims', $body, $key)->assertOk();
        $this->assertSame($first->getContent(), $replay->getContent());
        $this->assertSame('true', $replay->headers->get('X-Qammaris-Idempotent-Replay'));
        $this->assertSame($order->revision + 1, $order->fresh()->revision, 'Revision rose exactly once');
        $this->assertSame(1, OnlineOrderEvent::where('online_order_id', $order->id)->where('kind', 'claimed')->count());

        $this->assertApiError($this->mutate('POST', $order, '/claims', ['task' => 'handover'] + $body, $key), 409, 'idempotency_key_reused');

        // A business error is stored too: a retry sees the same 409; a new action needs a new key.
        $staleKey = Str::random(24);
        $conflict = $this->mutate('POST', $order, '/claims', ['task' => 'handover', 'expected_revision' => 1, 'actor' => $this->actor()], $staleKey);
        $this->assertApiError($conflict, 409, 'revision_conflict');
        $this->assertSame($conflict->getContent(), $this->mutate('POST', $order, '/claims', ['task' => 'handover', 'expected_revision' => 1, 'actor' => $this->actor()], $staleKey)->getContent());

        $this->assertApiError($this->mutate('POST', $order, '/claims', $body, 'short'), 400, 'bad_request');
        $this->assertApiError($this->mutate('POST', $order, '/claims', $body, ''), 400, 'bad_request');

        $this->travel(8)->days();
        $this->enableOrderApi();
        $afterExpiry = $this->assertMutation($this->mutate('POST', $order, '/claims', ['task' => 'handover', 'actor' => $this->actor()], $key));
        $this->assertNotNull($afterExpiry->event_id, 'After 7 days the key is free for a new action');
    }

    public function test_audit_events_keep_the_app_actor_key_and_request_id(): void
    {
        $order = $this->v2Order();
        $this->mutate('POST', $order, '/claims', ['task' => 'preparation', 'actor' => $this->actor()], 'audit-key-0000000001', ['X-Request-Id' => 'app-action-77'])->assertOk();
        $event = OnlineOrderEvent::where('online_order_id', $order->id)->where('kind', 'claimed')->sole();
        $this->assertSame(['app', 'app', '665f0c2a9b1e4a0012ab34cd', 'Andi', 'audit-key-0000000001', 'app-action-77'],
            [$event->actor_type, $event->source, $event->actor_app_user_id, $event->actor_display_name, $event->idempotency_key, $event->request_id]);
    }

    public function test_request_shape_body_size_and_unknown_order(): void
    {
        $order = $this->v2Order();
        $this->assertApiError($this->mutate('POST', $order, '/claims', ['task' => 'preparation', 'actor' => $this->actor() + ['permission' => 'orders.handle']]), 422, 'validation_failed');
        $this->assertApiError($this->mutate('POST', $order, '/claims', ['task' => 'preparation', 'actor' => $this->actor('NOT-HEX')]), 422, 'validation_failed');
        $this->assertApiError($this->mutate('POST', $order, '/claims', '[1,2]'), 400, 'bad_request');
        $this->assertApiError($this->mutate('POST', $order, '/issues', str_repeat('x', 64 * 1024 + 1)), 413, 'payload_too_large');
        $this->assertApiError($this->orderApi('POST', '/orders/01JABCDE2F3G4H5J6K7M8N9P0Q/claims', ['task' => 'preparation', 'actor' => $this->actor()], ['Idempotency-Key' => Str::random(20)]), 404, 'order_not_found');
    }

    private function mutate(string $method, OnlineOrder $order, string $suffix, array|string $body, ?string $key = null, array $headers = []): TestResponse
    {
        return $this->orderApi($method, '/orders/'.$order->public_id.$suffix, $body, ['Idempotency-Key' => $key ?? Str::random(24)] + $headers);
    }

    private function assertMutation(TestResponse $response): object
    {
        $response->assertOk();
        $data = $this->assertMatchesApiSchema($response, 'MutationResponse');
        if ($data->event_id !== null) {
            $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', $data->event_id);
        }

        return $data;
    }

    private function claim(OnlineOrder $order, string $task): int
    {
        return $this->assertMutation($this->mutate('POST', $order, '/claims', ['task' => $task, 'actor' => $this->actor()]))->order->revision;
    }

    private function packedOrder(string $fulfillment): OnlineOrder
    {
        $order = $this->v2Order($fulfillment);
        $revision = $this->claim($order, 'preparation');
        $this->mutate('POST', $order, '/preparation', ['status' => 'packed', 'expected_revision' => $revision, 'actor' => $this->actor(),
            'packed_items' => $order->items->map(fn ($item) => ['line_id' => $item->line_id, 'quantity' => $item->quantity])->all()])->assertOk();

        return $order->fresh(['items']);
    }

    private function qrUpload(OnlineOrder $order, string $bytes, int $revision, ?array $actor = null): TestResponse
    {
        $boundary = 'qamBoundary'.Str::random(8);
        $body = "--{$boundary}\r\nContent-Disposition: form-data; name=\"expected_revision\"\r\n\r\n{$revision}\r\n"
            ."--{$boundary}\r\nContent-Disposition: form-data; name=\"actor\"\r\n\r\n".json_encode($actor ?? $this->actor())."\r\n"
            ."--{$boundary}\r\nContent-Disposition: form-data; name=\"file\"; filename=\"qr.png\"\r\nContent-Type: image/png\r\n\r\n{$bytes}\r\n"
            ."--{$boundary}--\r\n";

        return $this->orderApi('PUT', '/orders/'.$order->public_id.'/jnt/qr', $body,
            ['Idempotency-Key' => Str::random(24), 'Content-Type' => "multipart/form-data; boundary={$boundary}"]);
    }

    private function png(): string
    {
        $image = imagecreatetruecolor(4, 4);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }
}
