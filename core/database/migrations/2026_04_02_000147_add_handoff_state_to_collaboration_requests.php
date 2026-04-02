<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collaboration_requests', function (Blueprint $table): void {
            $table->string('handoff_state', 40)->default('review')->after('priority');
            $table->index('handoff_state', 'collab_requests_handoff_state_idx');
        });
    }

    public function down(): void
    {
        Schema::table('collaboration_requests', function (Blueprint $table): void {
            $table->dropIndex('collab_requests_handoff_state_idx');
            $table->dropColumn('handoff_state');
        });
    }
};
