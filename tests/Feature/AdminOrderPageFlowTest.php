<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Rupiah;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsV2Orders;
use Tests\TestCase;

/** ADM-04: the step to work on stands out, routine and exception cards are separated, member points need no reason. */
class AdminOrderPageFlowTest extends TestCase
{
    use BuildsV2Orders, RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['orders.simple_ux' => true]);
        $this->staff = User::factory()->create(['role' => User::ROLE_STAFF_ORDER])->fresh();
    }

    public function test_only_the_current_step_is_highlighted_and_exceptions_sit_after_a_divider(): void
    {
        $order = $this->v2Order();
        $html = $this->actingAs($this->staff)->get(route('admin.orders.show', $order))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'data-step-current'));
        $this->assertSame(1, substr_count($html, 'Langkah sekarang'));
        $this->assertMatchesRegularExpression('/data-step-row="pembayaran"\s+data-step-current\s*>.*?<details id="pembayaran" class="[^"]*border-\[#C5A059\]/s', $html);
        $this->assertStringNotContainsString('▾', $html);
        $this->assertStringContainsString('data-chevron', $html);
        // Steps, then the divider, then the exception cards.
        $this->assertMatchesRegularExpression('/aria-label="Langkah pesanan".*data-flow-divider.*Ada masalah\?.*id="lanjutan"/s', $html);
        $this->assertStringContainsString('Penyesuaian harga, poin member, batalkan pesanan', $html);

        // A finished order has no current step.
        $order->forceFill(['lifecycle' => 'completed', 'stage' => 'completed'])->save();
        $this->assertStringNotContainsString('data-step-current', $this->get(route('admin.orders.show', $order))->getContent());
    }

    public function test_member_points_discount_needs_no_reason_while_other_adjustments_still_do(): void
    {
        $order = $this->v2Order();
        $total = Rupiah::minorUnits($order->customerTotal());
        $this->actingAs($this->staff)->get(route('admin.orders.show', $order))->assertSee('<option value="member_points"', false)->assertSee('data-adjustment-reason', false);

        $this->post(route('admin.orders.v2.adjustments', $order), ['revision' => $order->revision, 'direction' => 'discount', 'amount' => '10.000'])
            ->assertSessionHasErrors('reason');
        $this->post(route('admin.orders.v2.adjustments', $order), ['revision' => $order->revision, 'direction' => 'member_points', 'amount' => '15.000'])
            ->assertSessionHasNoErrors()->assertSessionHas('order_notice.type', 'success');

        $adjustment = $order->adjustments()->sole();
        $this->assertSame(['Poin member', 'approved', '-15000.00'], [$adjustment->reason, $adjustment->status, $adjustment->amount]);
        $this->assertSame($total - 1500000, Rupiah::minorUnits($order->fresh()->customerTotal()));
    }
}
