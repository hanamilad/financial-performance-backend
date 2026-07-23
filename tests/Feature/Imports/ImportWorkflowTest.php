<?php

use App\Models\User;
use App\Modules\Clients\Models\Branch;
use App\Modules\Clients\Models\Client;
use App\Modules\Imports\Enums\ImportBatchStatus;
use App\Modules\Imports\Models\ImportBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function seedBatch(?Branch $branch = null, ImportBatchStatus $status = ImportBatchStatus::Draft, string $period = '2026-01', int $rows = 2): ImportBatch
{
    $branch ??= Branch::factory()->for(Client::factory())->create();

    $batch = ImportBatch::create([
        'client_id' => $branch->client_id,
        'branch_id' => $branch->id,
        'reporting_period' => $period,
        'original_filename' => 'performance.xlsx',
        'status' => $status,
        'row_count' => $rows,
        'error_count' => 0,
    ]);

    for ($i = 0; $i < $rows; $i++) {
        $batch->rows()->create([
            'sheet_name' => 'SALES_DAILY',
            'row_number' => $i + 2,
            'data' => ['branch_code' => $branch->code, 'net_sales_ex_vat' => '930'],
        ]);
    }

    return $batch;
}

it('submits a draft for review and records the submitter', function () {
    $admin = actingAsSystemAdmin();
    $batch = seedBatch();

    $this->postJson("/api/v1/admin/imports/{$batch->id}/submit")
        ->assertOk()
        ->assertJsonPath('data.status', 'under_review')
        ->assertJsonPath('data.submitted_by_name', $admin->name);

    $fresh = $batch->fresh();
    expect($fresh->status)->toBe(ImportBatchStatus::UnderReview)
        ->and($fresh->submitted_by)->toBe($admin->id)
        ->and($fresh->submitted_at)->not->toBeNull();
});

it('approves a batch under review and records the approver', function () {
    $admin = actingAsSystemAdmin();
    $batch = seedBatch(status: ImportBatchStatus::UnderReview);

    $this->postJson("/api/v1/admin/imports/{$batch->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.approved_by_name', $admin->name);

    expect($batch->fresh()->approved_by)->toBe($admin->id);
});

it('publishes an approved batch and records the publisher', function () {
    $admin = actingAsSystemAdmin();
    $batch = seedBatch(status: ImportBatchStatus::Approved);

    $this->postJson("/api/v1/admin/imports/{$batch->id}/publish")
        ->assertOk()
        ->assertJsonPath('data.status', 'published')
        ->assertJsonPath('data.published_by_name', $admin->name);

    $fresh = $batch->fresh();
    expect($fresh->status)->toBe(ImportBatchStatus::Published)
        ->and($fresh->published_at)->not->toBeNull();
});

it('returns a batch to draft with a review note', function () {
    actingAsSystemAdmin();
    $batch = seedBatch(status: ImportBatchStatus::UnderReview);

    $this->postJson("/api/v1/admin/imports/{$batch->id}/return-to-draft", ['review_note' => 'يرجى تصحيح المبيعات'])
        ->assertOk()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.review_note', 'يرجى تصحيح المبيعات');

    expect($batch->fresh()->status)->toBe(ImportBatchStatus::Draft);
});

it('requires a review note when returning to draft', function () {
    actingAsSystemAdmin();
    $batch = seedBatch(status: ImportBatchStatus::UnderReview);

    $this->postJson("/api/v1/admin/imports/{$batch->id}/return-to-draft", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('review_note');
});

it('forbids skipping workflow stages', function () {
    actingAsSystemAdmin();

    $this->postJson('/api/v1/admin/imports/'.seedBatch()->id.'/approve')->assertStatus(409);
    $this->postJson('/api/v1/admin/imports/'.seedBatch()->id.'/publish')->assertStatus(409);
    $this->postJson('/api/v1/admin/imports/'.seedBatch(status: ImportBatchStatus::UnderReview)->id.'/publish')->assertStatus(409);
    $this->postJson('/api/v1/admin/imports/'.seedBatch(status: ImportBatchStatus::ValidationFailed, rows: 0)->id.'/submit')->assertStatus(409);
});

it('does not repeat an action on a repeated request', function () {
    actingAsSystemAdmin();
    $batch = seedBatch(status: ImportBatchStatus::UnderReview);

    $this->postJson("/api/v1/admin/imports/{$batch->id}/approve")->assertOk();
    $this->postJson("/api/v1/admin/imports/{$batch->id}/approve")->assertStatus(409);

    $this->postJson("/api/v1/admin/imports/{$batch->id}/publish")->assertOk();
    $this->postJson("/api/v1/admin/imports/{$batch->id}/publish")->assertStatus(409);
});

it('blocks publishing a duplicate for the same client, branch and period', function () {
    actingAsSystemAdmin();
    $branch = Branch::factory()->for(Client::factory())->create();
    seedBatch($branch, ImportBatchStatus::Published, '2026-01');
    $approved = seedBatch($branch, ImportBatchStatus::Approved, '2026-01');

    $this->postJson("/api/v1/admin/imports/{$approved->id}/publish")->assertStatus(409);

    expect($approved->fresh()->status)->toBe(ImportBatchStatus::Approved);
});

it('allows deleting only draft or validation_failed batches', function () {
    actingAsSystemAdmin();

    $this->deleteJson('/api/v1/admin/imports/'.seedBatch()->id)->assertNoContent();
    $this->deleteJson('/api/v1/admin/imports/'.seedBatch(status: ImportBatchStatus::ValidationFailed, rows: 0)->id)->assertNoContent();

    foreach ([ImportBatchStatus::UnderReview, ImportBatchStatus::Approved, ImportBatchStatus::Published] as $status) {
        $this->deleteJson('/api/v1/admin/imports/'.seedBatch(status: $status)->id)->assertStatus(409);
    }
});

it('blocks re-uploading over a batch already in the workflow', function () {
    actingAsSystemAdmin();
    $branch = Branch::factory()->for(Client::factory())->create();
    seedBatch($branch, ImportBatchStatus::UnderReview, '2026-01');

    $this->post('/api/v1/admin/imports', [
        'client_id' => $branch->client_id,
        'branch_id' => $branch->id,
        'reporting_period' => '2026-01',
        'file' => performanceWorkbook($branch->code),
    ])->assertStatus(409);
});

it('filters the import list by status', function () {
    actingAsSystemAdmin();
    seedBatch(status: ImportBatchStatus::Published);
    seedBatch(status: ImportBatchStatus::Draft);

    $response = $this->getJson('/api/v1/admin/imports?status=published')->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.status'))->toBe('published');
});

it('identifies published batches for downstream reports', function () {
    actingAsSystemAdmin();
    seedBatch(status: ImportBatchStatus::Draft);
    seedBatch(status: ImportBatchStatus::Approved);
    $published = seedBatch(status: ImportBatchStatus::Published);

    $ids = ImportBatch::query()->where('status', ImportBatchStatus::Published)->pluck('id')->all();

    expect($ids)->toBe([$published->id]);
});

it('forbids a client_user from workflow actions', function () {
    Sanctum::actingAs(User::factory()->clientUser()->create());
    $batch = seedBatch();

    $this->postJson("/api/v1/admin/imports/{$batch->id}/submit")->assertForbidden();
});

it('requires authentication for workflow actions', function () {
    $batch = seedBatch();

    $this->postJson("/api/v1/admin/imports/{$batch->id}/submit")->assertUnauthorized();
});
