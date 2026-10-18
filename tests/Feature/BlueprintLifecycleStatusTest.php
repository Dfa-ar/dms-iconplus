<?php

namespace Tests\Feature;

use App\Models\PaOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BlueprintLifecycleStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_blueprint_status_list_exposes_core_lifecycle_and_qc_phases(): void
    {
        $statuses = PaOrder::blueprintStatusOptions();

        $this->assertEquals([
            'UNASSIGNED',
            'ASSIGNED',
            'ON_PROGRESS',
            'DONE',
            'KENDALA',
            'CLOSE_ICRM',
            'PENDING_QC',
            'PASSED',
            'REJECTED',
            'BAST_ISSUED',
            'BAST_VOID',
            'PAID',
        ], array_keys($statuses));

        $this->assertSame('Menunggu penugasan', $statuses['UNASSIGNED']);
        $this->assertSame('Menunggu QC', $statuses['PENDING_QC']);
        $this->assertSame('BAST diterbitkan', $statuses['BAST_ISSUED']);
    }

    public function test_status_transition_guard_blocks_invalid_blueprint_paths(): void
    {
        $this->assertTrue(PaOrder::canTransitionFromTo(PaOrder::STATUS_UNASSIGNED, PaOrder::STATUS_ASSIGNED));
        $this->assertTrue(PaOrder::canTransitionFromTo(PaOrder::STATUS_ASSIGNED, PaOrder::STATUS_ON_PROGRESS));
        $this->assertFalse(PaOrder::canTransitionFromTo(PaOrder::STATUS_UNASSIGNED, PaOrder::STATUS_DONE));
        $this->assertTrue(PaOrder::canTransitionFromTo(PaOrder::STATUS_ON_PROGRESS, PaOrder::BLUEPRINT_STATUS_PENDING_QC));
        $this->assertTrue(PaOrder::canTransitionFromTo(PaOrder::STATUS_DONE, 'PENDING_QC'));
        $this->assertFalse(PaOrder::canTransitionFromTo(PaOrder::STATUS_DONE, PaOrder::QC_STATUS_PASSED));
        $this->assertTrue(PaOrder::canTransitionFromTo(PaOrder::BLUEPRINT_STATUS_PENDING_QC, PaOrder::QC_STATUS_PASSED));
        $this->assertTrue(PaOrder::canTransitionFromTo(PaOrder::BLUEPRINT_STATUS_PENDING_QC, PaOrder::QC_STATUS_REJECTED));
        $this->assertTrue(PaOrder::canTransitionFromTo(PaOrder::QC_STATUS_PASSED, PaOrder::BLUEPRINT_STATUS_BAST_ISSUED));
        $this->assertFalse(PaOrder::canTransitionFromTo('PENDING_QC', PaOrder::STATUS_CLOSE_ICRM));
    }

    public function test_model_blocks_invalid_status_updates_and_allows_explicit_admin_correction(): void
    {
        $paOrder = PaOrder::factory()->create(['current_status' => PaOrder::STATUS_UNASSIGNED]);

        try {
            $paOrder->update(['current_status' => PaOrder::STATUS_DONE]);
            $this->fail('Expected an invalid status transition to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('current_status', $exception->errors());
        }

        $this->assertSame(PaOrder::STATUS_UNASSIGNED, $paOrder->fresh()->current_status);

        $paOrder->correctStatus(['current_status' => PaOrder::STATUS_DONE]);

        $this->assertSame(PaOrder::STATUS_DONE, $paOrder->fresh()->current_status);
    }
}
