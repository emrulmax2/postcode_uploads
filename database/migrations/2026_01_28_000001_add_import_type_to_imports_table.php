<?php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('imports', function (Blueprint $table) {
            $table->string('import_type')->nullable()->after('user_id');
            $table->index('import_type');
        });
    }

    public function down(): void
    {
        Schema::table('imports', function (Blueprint $table) {
            $table->dropIndex(['import_type']);
            $table->dropColumn('import_type');
        });
    }
};