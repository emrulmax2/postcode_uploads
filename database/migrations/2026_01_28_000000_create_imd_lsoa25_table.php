<?php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('imd_lsoa25', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained()->cascadeOnDelete();
            $table->string('lsoa_code_2021')->nullable();
            $table->string('lsoa_name_2021')->nullable();
            $table->string('local_authority_district_code_2024')->nullable();
            $table->string('local_authority_district_name_2024')->nullable();
            $table->string('imd_rank')->nullable();
            $table->string('imd_decile')->nullable();
            $table->integer('imd_quantile_2025')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imd_lsoa25');
    }
};