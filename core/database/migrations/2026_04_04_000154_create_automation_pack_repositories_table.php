<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('automation_pack_repositories')) {
            Schema::create('automation_pack_repositories', function (Blueprint $table): void {
                $table->string('id')->primary();
                $table->string('organization_id')->index();
                $table->string('scope_id')->nullable()->index();
                $table->string('label', 180);
                $table->string('repository_url', 1024);
                $table->string('repository_sign_url', 1024)->nullable();
                $table->text('public_key_pem');
                $table->string('trust_tier', 40)->default('trusted-partner');
                $table->boolean('is_enabled')->default(true);
                $table->timestamp('last_refreshed_at')->nullable();
                $table->string('last_status', 40)->default('never');
                $table->text('last_error')->nullable();
                $table->string('created_by_principal_id')->nullable()->index();
                $table->string('updated_by_principal_id')->nullable()->index();
                $table->timestamps();
            });
        }

        $indexName = 'automation_pack_repositories_unique_repo_per_scope';

        if ($this->indexExists('automation_pack_repositories', $indexName)) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            // MySQL utf8mb4 index key limit requires a prefix on long URL columns.
            DB::statement(sprintf(
                'CREATE UNIQUE INDEX %s ON automation_pack_repositories (organization_id, scope_id, repository_url(255))',
                $indexName,
            ));

            return;
        }

        Schema::table('automation_pack_repositories', function (Blueprint $table) use ($indexName): void {
            $table->unique(
                ['organization_id', 'scope_id', 'repository_url'],
                $indexName
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_pack_repositories');
    }

    private function indexExists(string $table, string $index): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            $database = DB::getDatabaseName();

            $rows = DB::select(
                'select 1 from information_schema.statistics where table_schema = ? and table_name = ? and index_name = ? limit 1',
                [$database, $table, $index],
            );

            return $rows !== [];
        }

        if ($driver === 'sqlite') {
            $rows = DB::select(sprintf("PRAGMA index_list('%s')", str_replace("'", "''", $table)));

            foreach ($rows as $row) {
                if (($row->name ?? null) === $index) {
                    return true;
                }
            }

            return false;
        }

        return false;
    }
};
