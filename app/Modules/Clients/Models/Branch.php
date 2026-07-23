<?php

namespace App\Modules\Clients\Models;

use App\Modules\Clients\Enums\EntityStatus;
use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(BranchFactory::class)]
class Branch extends Model
{
    use HasFactory;

    protected $fillable = ['client_id', 'name', 'code', 'city', 'status'];

    protected function casts(): array
    {
        return [
            'status' => EntityStatus::class,
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
