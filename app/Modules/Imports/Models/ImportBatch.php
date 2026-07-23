<?php

namespace App\Modules\Imports\Models;

use App\Models\User;
use App\Modules\Clients\Models\Branch;
use App\Modules\Clients\Models\Client;
use App\Modules\Imports\Enums\ImportBatchStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    protected $fillable = [
        'client_id',
        'branch_id',
        'reporting_period',
        'original_filename',
        'status',
        'row_count',
        'error_count',
        'errors',
        'uploaded_by',
        'submitted_at',
        'submitted_by',
        'approved_at',
        'approved_by',
        'published_at',
        'published_by',
        'review_note',
    ];

    protected function casts(): array
    {
        return [
            'status' => ImportBatchStatus::class,
            'errors' => 'array',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class);
    }
}
