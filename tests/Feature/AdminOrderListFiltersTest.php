<?php

namespace Tests\Feature;

use App\Models\OnlineOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsV2Orders;
use Tests\TestCase;

/** ADM-02: three tabs that apply on tap, search without a button, special filters from the banners. */
class AdminOrderListFiltersTest extends TestCase
{
    use BuildsV2Orders, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_tabs_replace_the_dropdown_and_apply_without_a_button(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_STAFF_ORDER]);
        $waiting = $this->v2Order();
        $waiting->forceFill(['courier_status' => 'requested'])->save();
        $done = $this->v2Order();
        $done->forceFill(['stage' => OnlineOrder::STAGE_COMPLETED, 'lifecycle' => 'completed'])->save();
        $cancelled = $this->v2Order();
        $cancelled->forceFill(['stage' => OnlineOrder::STAGE_CANCELLED, 'lifecycle' => 'cancelled'])->save();

        $page = $this->actingAs($staff)->get(route('admin.orders.index'))->assertOk()
            ->assertSee('data-order-tabs', false)->assertDontSee('id="order-status"', false)->assertDontSee('>Tampilkan</button>', false)
            ->assertSeeInOrder(['>Aktif</a>', '>Selesai</a>', '>Dibatalkan</a>'], false);
        $this->assertSame(1, substr_count($this->tabs($page->getContent()), 'aria-current="page"'));
        // An order waiting for the courier pickup is still active work.
        $page->assertSee($waiting->code)->assertDontSee($done->code)->assertDontSee($cancelled->code);

        $this->get(route('admin.orders.index', ['status' => 'completed']))->assertSee($done->code)->assertDontSee($waiting->code);
        $this->get(route('admin.orders.index', ['status' => 'cancelled']))->assertSee($cancelled->code)->assertDontSee($done->code);
    }

    public function test_search_finds_an_order_in_any_status_and_special_filters_still_work_from_links(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_STAFF_ORDER]);
        $done = $this->v2Order(details: ['customer_name' => 'E2E Wira Selesai']);
        $done->forceFill(['stage' => OnlineOrder::STAGE_COMPLETED, 'lifecycle' => 'completed'])->save();

        $found = $this->actingAs($staff)->get(route('admin.orders.index', ['search' => 'Wira Selesai']))->assertOk()
            ->assertSee($done->code)->assertSee('di semua status')->assertSee('Hapus pencarian');
        $this->assertStringNotContainsString('aria-current', $this->tabs($found->getContent()), 'no tab is current while searching');
        $this->get(route('admin.orders.index', ['search' => 'tidak-ada-yang-cocok']))->assertSee('Pesanan tidak ditemukan');

        // Banner links (e.g. open issues) keep their filter, named on the page with a way back.
        $this->get(route('admin.orders.index', ['status' => 'issues']))->assertOk()
            ->assertSee('Ada kendala')->assertSee('Hapus filter')->assertDontSee($done->code);
    }

    private function tabs(string $html): string
    {
        preg_match('/<nav aria-label="Status pesanan".*?<\/nav>/s', $html, $match);

        return $match[0] ?? '';
    }
}
