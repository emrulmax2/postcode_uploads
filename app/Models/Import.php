<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Import extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'original_name',
        'stored_path',
        'status',
        'batch_id',
        'total_rows',
        'processed_rows',
        'failed_rows',
        'error',
    ];

    protected $casts = [
        'total_rows' => 'integer',
        'processed_rows' => 'integer',
        'failed_rows' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(BigData::class);
    }

    public function postcodeRecords(): HasMany
    {
        return $this->hasMany(PostcodeRecord::class);
    }
}
