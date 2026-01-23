<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostcodeRecord extends Model
{
    use HasFactory;

    protected $table = 'postcode_records';

    protected $fillable = [
        'import_id',
        'postcode',
        'postcode2',
        'polar4_quintile',
        'polar3_quintile',
        'reason_removed_polar',
        'tundra_msoa_quintile',
        'reason_removed_tundra_msoa',
        'tundra_lsoa_quintile',
        'reason_removed_tundra_lsoa',
        'adult_he_2011_quintile',
        'reason_removed_adult_he_2011',
        'gaps_gcse_quintile',
        'gaps_gcse_ethnicity_quintile',
        'reason_removed_gaps',
        'uni_connect_target_ward',
        'postcode_status',
        'msoa_current',
        'msoa_name',
        'msoa_polar',
        'msoa_tundra',
        'msoa_adult_he_2011',
        'lsoa_current',
        'lsoa_name',
        'lsoa_tundra',
        'cas_ward_current',
        'cas_ward_name',
        'cas_ward_measures',
        'itl2_code',
        'itl2_name',
        'country',
    ];

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }
}
