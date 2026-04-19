<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = 'automation_evidence_delivery_states';
        $orgIdx = 'auto_ev_delivery_states_org_idx';
        $scopeIdx = 'auto_ev_delivery_states_scope_idx';
        $mappingIdx = 'auto_ev_delivery_states_mapping_idx';
        $targetUnique = 'automation_evidence_delivery_states_target_unique';

        if (! Schema::hasTable($tableName)) {
            Schema::create($tableName, function (Blueprint $table) use ($orgIdx, $scopeIdx, $mappingIdx): void {
                $table->string('id')->primary();
                $table->string('organization_id')->index($orgIdx);
                $table->string('scope_id')->nullable()->index($scopeIdx);
                $table->string('automation_output_mapping_id')->index($mappingIdx);
                $table->string('target_subject_type', 80);
                $table->string('target_subject_id');
                $table->string('last_payload_fingerprint', 190)->nullable();
                $table->string('last_check_outcome', 40)->nullable();
                $table->string('last_artifact_id')->nullable();
                $table->timestamp('last_delivered_at')->nullable();
                $table->timestamps();
            });
        }

        if (! $this->indexExists($tableName, $mappingIdx)) {
            Schema::table($tableName, function (Blueprint $table) use ($mappingIdx): void {
                $table->index('automation_output_mapping_id', $mappingIdx);
            });
        }

        if (! $this->indexExists($tableName, $targetUnique)) {
            Schema::table($tableName, function (Blueprint $table) use ($targetUnique): void {
                $table->unique(
                    ['automation_output_mapping_id', 'target_subject_type', 'target_subject_id'],
                    $targetUnique
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_evidence_delivery_states');
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
