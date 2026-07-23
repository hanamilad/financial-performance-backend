<?php

namespace App\Modules\Imports\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Clients\Models\Branch;
use App\Modules\Clients\Models\Client;
use App\Modules\Imports\Enums\ImportBatchStatus;
use App\Modules\Imports\Http\Requests\ReturnToDraftRequest;
use App\Modules\Imports\Http\Requests\StoreImportRequest;
use App\Modules\Imports\Http\Resources\ImportBatchDetailResource;
use App\Modules\Imports\Http\Resources\ImportBatchResource;
use App\Modules\Imports\Models\ImportBatch;
use App\Modules\Imports\Services\ValidatePerformanceImport;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class ImportController extends Controller
{
    private const RELATIONS = ['client', 'branch', 'uploader', 'submitter', 'approver', 'publisher'];

    public function __construct(private readonly ValidatePerformanceImport $validator) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $batches = ImportBatch::query()
            ->with(['client', 'branch', 'uploader'])
            ->when(
                $request->filled('client_id'),
                fn ($query) => $query->where('client_id', $request->integer('client_id')),
            )
            ->when(
                $request->enum('status', ImportBatchStatus::class),
                fn ($query, ImportBatchStatus $status) => $query->where('status', $status),
            )
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return ImportBatchResource::collection($batches);
    }

    public function store(StoreImportRequest $request): JsonResponse
    {
        $client = Client::findOrFail($request->integer('client_id'));
        $branch = Branch::findOrFail($request->integer('branch_id'));
        $period = (string) $request->string('reporting_period');

        $existing = ImportBatch::query()
            ->where('client_id', $client->id)
            ->where('branch_id', $branch->id)
            ->where('reporting_period', $period)
            ->get();

        if ($existing->contains(fn (ImportBatch $batch) => $batch->status->blocksReupload())) {
            abort(409, 'يوجد استيراد سابق لنفس العميل والفرع والفترة. احذفه أو أكمل دورته أولًا قبل رفع ملف جديد.');
        }

        $result = $this->validator->validate($request->file('file'), $client, $branch, $period);
        $passed = $result->passed();
        $userId = $request->user()->id;

        $batch = DB::transaction(function () use ($existing, $request, $client, $branch, $period, $result, $passed, $userId) {
            $existing->each->delete();

            $batch = ImportBatch::create([
                'client_id' => $client->id,
                'branch_id' => $branch->id,
                'reporting_period' => $period,
                'original_filename' => $request->file('file')->getClientOriginalName(),
                'status' => $passed ? ImportBatchStatus::Draft : ImportBatchStatus::ValidationFailed,
                'row_count' => $result->rowCount(),
                'error_count' => $result->errorCount(),
                'errors' => $passed ? null : $result->errors,
                'uploaded_by' => $userId,
            ]);

            if ($passed) {
                $batch->rows()->createMany(array_map(fn (array $row) => [
                    'sheet_name' => $row['sheet_name'],
                    'row_number' => $row['row_number'],
                    'data' => $row['data'],
                ], $result->validRows));
            }

            return $batch;
        });

        return $this->detail($batch, $passed)->response()->setStatusCode(201);
    }

    public function show(ImportBatch $importBatch): ImportBatchDetailResource
    {
        return $this->detail($importBatch);
    }

    public function submit(Request $request, ImportBatch $importBatch): ImportBatchDetailResource
    {
        $userId = $request->user()->id;

        return $this->transition(
            $importBatch,
            ImportBatchStatus::Draft,
            'لا يمكن تقديم هذه العملية للمراجعة في حالتها الحالية.',
            function (ImportBatch $batch) use ($userId) {
                $batch->status = ImportBatchStatus::UnderReview;
                $batch->submitted_at = now();
                $batch->submitted_by = $userId;
            },
        );
    }

    public function approve(Request $request, ImportBatch $importBatch): ImportBatchDetailResource
    {
        $userId = $request->user()->id;

        return $this->transition(
            $importBatch,
            ImportBatchStatus::UnderReview,
            'لا يمكن اعتماد هذه العملية في حالتها الحالية.',
            function (ImportBatch $batch) use ($userId) {
                $batch->status = ImportBatchStatus::Approved;
                $batch->approved_at = now();
                $batch->approved_by = $userId;
            },
        );
    }

    public function returnToDraft(ReturnToDraftRequest $request, ImportBatch $importBatch): ImportBatchDetailResource
    {
        $note = (string) $request->string('review_note');

        return $this->transition(
            $importBatch,
            ImportBatchStatus::UnderReview,
            'لا يمكن إرجاع هذه العملية إلى المسودة في حالتها الحالية.',
            function (ImportBatch $batch) use ($note) {
                $batch->status = ImportBatchStatus::Draft;
                $batch->review_note = $note;
            },
        );
    }

    public function publish(Request $request, ImportBatch $importBatch): ImportBatchDetailResource
    {
        $userId = $request->user()->id;

        return $this->transition(
            $importBatch,
            ImportBatchStatus::Approved,
            'لا يمكن نشر هذه العملية في حالتها الحالية.',
            function (ImportBatch $batch) use ($userId) {
                $this->guardPublishable($batch);

                $batch->status = ImportBatchStatus::Published;
                $batch->published_at = now();
                $batch->published_by = $userId;
            },
        );
    }

    public function destroy(ImportBatch $importBatch): Response
    {
        if (! $importBatch->status->isDeletable()) {
            abort(409, 'لا يمكن حذف عملية بعد تقديمها للمراجعة.');
        }

        $importBatch->delete();

        return response()->noContent();
    }

    private function transition(ImportBatch $importBatch, ImportBatchStatus $from, string $message, Closure $apply): ImportBatchDetailResource
    {
        $batch = DB::transaction(function () use ($importBatch, $from, $message, $apply) {
            $locked = ImportBatch::query()->whereKey($importBatch->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== $from) {
                abort(409, $message);
            }

            $apply($locked);
            $locked->save();

            return $locked;
        });

        return $this->detail($batch);
    }

    private function guardPublishable(ImportBatch $batch): void
    {
        $branch = Branch::query()->find($batch->branch_id);

        if ($branch === null || $branch->client_id !== $batch->client_id || Client::query()->whereKey($batch->client_id)->doesntExist()) {
            abort(422, 'تعذّر النشر: العميل أو الفرع لم يعد صالحًا.');
        }

        if ($batch->row_count < 1 || $batch->rows()->count() < 1) {
            abort(422, 'تعذّر النشر: لا توجد صفوف صالحة في هذه العملية.');
        }

        $alreadyPublished = ImportBatch::query()
            ->where('client_id', $batch->client_id)
            ->where('branch_id', $batch->branch_id)
            ->where('reporting_period', $batch->reporting_period)
            ->where('status', ImportBatchStatus::Published)
            ->whereKeyNot($batch->getKey())
            ->lockForUpdate()
            ->exists();

        if ($alreadyPublished) {
            abort(409, 'توجد نسخة منشورة لنفس العميل والفرع والفترة. لا يمكن نشر نسخة ثانية.');
        }
    }

    private function detail(ImportBatch $batch, bool $withRows = true): ImportBatchDetailResource
    {
        $batch->load(self::RELATIONS);

        if ($withRows) {
            $batch->load('rows');
        }

        return new ImportBatchDetailResource($batch);
    }
}
