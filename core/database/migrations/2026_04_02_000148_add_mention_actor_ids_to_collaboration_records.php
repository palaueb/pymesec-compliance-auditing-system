<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collaboration_comments', function (Blueprint $table): void {
            $table->text('mentioned_actor_ids')->nullable()->after('body');
        });

        Schema::table('collaboration_requests', function (Blueprint $table): void {
            $table->text('mentioned_actor_ids')->nullable()->after('handoff_state');
        });
    }

    public function down(): void
    {
        Schema::table('collaboration_requests', function (Blueprint $table): void {
            $table->dropColumn('mentioned_actor_ids');
        });

        Schema::table('collaboration_comments', function (Blueprint $table): void {
            $table->dropColumn('mentioned_actor_ids');
        });
    }
};
