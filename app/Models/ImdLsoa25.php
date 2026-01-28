<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImdLsoa25 extends Model
{
    use HasFactory;

    protected $table = 'imd_lsoa25';

    protected $fillable = [
        'import_id',
        'lsoa_code_2021',
        'lsoa_name_2021',
        'local_authority_district_code_2024',
        'local_authority_district_name_2024',
        'imd_rank',
        'imd_decile',
        'imd_quantile_2025',
    ];

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }
}