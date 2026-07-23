<?php

namespace App\Modules\Imports\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Clients\Models\Branch;
use App\Modules\Clients\Models\Client;
use App\Modules\Imports\Enums\ImportBatchStatus;
use App\Modules\Imports\Http\Requests\StoreImportRequest;
use App\Modules\Imports\Http\Resources\ImportBatchDetailResource;
use App\Modules\Imports\Http\Resources\ImportBatchResource;
use App\Modules\Imports\Models\ImportBatch;
use App\Modules\Imports\Services\ValidatePerformanceImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class ImportController extends Controller
{
    public function __construct(private readonly ValidatePerformanceImport $validator) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $batches = ImportBatch::query()
            ->with(['client', 'branch', 'uploader'])
            ->when(
                $request->filled('client_id'),
                fn ($query) => $query->where('client_id', $request->integer('client_id')),
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
        $clientId = $client->id;
        $period = (string) $request->string('reporting_period');

        $existing = ImportBatch::query()
            ->where('client_id', $clientId)
            ->where('branch_id', $branch->id)
            ->where('reporting_period', $period)
            ->get();

        $blocking = $existing->first(fn (ImportBatch $batch) => in_array(
            $batch->status,
            [ImportBatchStatus::Draft, ImportBatchStatus::Validated],
            true,
        ));

        if ($blocking !== null) {
            abort(409, 'يوجد استيراد سابق لنفس العميل والفرع والفترة. احذفه أولًا قبل رفع ملف جديد.');
        }

        $result = $this->validator->validate($request->file('file'), $client, $branch, $period);
        $passed = $result->passed();

        $batch = DB::transaction(function () use ($existing, $request, $clientId, $branch, $period, $result, $passed) {
            $existing->each->delete();

            $batch = ImportBatch::create([
                'client_id' => $clientId,
                'branch_id' => $branch->id,
                'reporting_period' => $period,
                'original_filename' => $request->file('file')->getClientOriginalName(),
                'status' => $passed ? ImportBatchStatus::Draft : ImportBatchStatus::ValidationFailed,
                'row_count' => $result->rowCount(),
                'error_count' => $result->errorCount(),
                'errors' => $passed ? null : $result->errors,
                'uploaded_by' => $request->user()->id,
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

        $batch->load(['client', 'branch', 'uploader']);
        if ($passed) {
            $batch->load('rows');
        }

        return (new ImportBatchDetailResource($batch))->response()->setStatusCode(201);
    }

    public function show(ImportBatch $importBatch): ImportBatchDetailResource
    {
        $importBatch->load(['client', 'branch', 'uploader', 'rows']);

        return new ImportBatchDetailResource($importBatch);
    }

    public function destroy(ImportBatch $importBatch): Response
    {
        $importBatch->delete();

        return response()->noContent();
    }
}
