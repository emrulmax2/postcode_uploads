<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BigData extends Model
{
    use HasFactory;

    protected $table = 'big_data';

    protected $fillable = [
        'import_id',
        'row_data',
    ];

    protected $casts = [
        'row_data' => 'array',
    ];

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }
}
