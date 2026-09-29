<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news', function (Blueprint $table) {
            // Set the moment every user is notified about this news item, so
            // toggling is_published back and forth (e.g. fixing a typo)
            // never re-notifies everyone a second time.
            $table->timestamp('notified_at')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('news', function (Blueprint $table) {
            $table->dropColumn('notified_at');
        });
    }
};
