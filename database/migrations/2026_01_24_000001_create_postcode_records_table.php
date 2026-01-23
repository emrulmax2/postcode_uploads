<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('postcode_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('imports')->cascadeOnDelete();
            $table->string('postcode')->nullable();
            $table->string('postcode2')->nullable();
            $table->string('polar4_quintile')->nullable();
            $table->string('polar3_quintile')->nullable();
            $table->string('reason_removed_polar')->nullable();
            $table->string('tundra_msoa_quintile')->nullable();
            $table->string('reason_removed_tundra_msoa')->nullable();
            $table->string('tundra_lsoa_quintile')->nullable();
            $table->string('reason_removed_tundra_lsoa')->nullable();
            $table->string('adult_he_2011_quintile')->nullable();
            $table->string('reason_removed_adult_he_2011')->nullable();
            $table->string('gaps_gcse_quintile')->nullable();
            $table->string('gaps_gcse_ethnicity_quintile')->nullable();
            $table->string('reason_removed_gaps')->nullable();
            $table->string('uni_connect_target_ward')->nullable();
            $table->string('postcode_status')->nullable();
            $table->string('msoa_current')->nullable();
            $table->string('msoa_name')->nullable();
            $table->string('msoa_polar')->nullable();
            $table->string('msoa_tundra')->nullable();
            $table->string('msoa_adult_he_2011')->nullable();
            $table->string('lsoa_current')->nullable();
            $table->string('lsoa_name')->nullable();
            $table->string('lsoa_tundra')->nullable();
            $table->string('cas_ward_current')->nullable();
            $table->string('cas_ward_name')->nullable();
            $table->string('cas_ward_measures')->nullable();
            $table->string('itl2_code')->nullable();
            $table->string('itl2_name')->nullable();
            $table->string('country')->nullable();
            $table->timestamps();

            $table->index('import_id');
            $table->index('postcode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('postcode_records');
    }
};
