<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collaboration_external_collaborators', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('owner_component', 64)->index();
            $table->string('subject_type', 64)->index();
            $table->string('subject_id', 120)->index();
            $table->string('organization_id', 64)->index();
            $table->string('scope_id', 64)->nullable()->index();
            $table->string('contact_name')->nullable();
            $table->string('contact_email');
            $table->string('lifecycle_state', 32)->default('active')->index();
            $table->timestamp('blocked_at')->nullable();
            $table->string('blocked_by_principal_id')->nullable();
            $table->timestamp('last_link_issued_at')->nullable();
            $table->string('last_link_id')->nullable();
            $table->timestamps();

            $table->unique(['owner_component', 'subject_type', 'subject_id', 'contact_email'], 'collaboration_external_collaborators_unique_contact');
        });

        Schema::table('collaboration_external_links', function (Blueprint $table): void {
            $table->string('collaborator_id')->nullable()->index()->after('subject_id');
        });

        DB::table('collaboration_external_links')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->each(static function (object $link): void {
                $email = Str::lower(trim((string) $link->contact_email));

                if ($email === '') {
                    return;
                }

                $existing = DB::table('collaboration_external_collaborators')
                    ->where('owner_component', (string) $link->owner_component)
                    ->where('subject_type', (string) $link->subject_type)
                    ->where('subject_id', (string) $link->subject_id)
                    ->where('contact_email', $email)
                    ->first();

                $collaboratorId = $existing !== null
                    ? (string) $existing->id
                    : 'external-collaborator-'.Str::lower(Str::ulid());

                if ($existing === null) {
                    DB::table('collaboration_external_collaborators')->insert([
                        'id' => $collaboratorId,
                        'owner_component' => (string) $link->owner_component,
                        'subject_type' => (string) $link->subject_type,
                        'subject_id' => (string) $link->subject_id,
                        'organization_id' => (string) $link->organization_id,
                        'scope_id' => is_string($link->scope_id) ? $link->scope_id : null,
                        'contact_name' => is_string($link->contact_name) ? $link->contact_name : null,
                        'contact_email' => $email,
                        'lifecycle_state' => 'active',
                        'blocked_at' => null,
                        'blocked_by_principal_id' => null,
                        'last_link_issued_at' => $link->created_at,
                        'last_link_id' => (string) $link->id,
                        'created_at' => $link->created_at,
                        'updated_at' => $link->updated_at,
                    ]);
                } else {
                    DB::table('collaboration_external_collaborators')
                        ->where('id', $collaboratorId)
                        ->update([
                            'contact_name' => is_string($existing->contact_name) ? $existing->contact_name : (is_string($link->contact_name) ? $link->contact_name : null),
                            'organization_id' => (string) $link->organization_id,
                            'scope_id' => is_string($link->scope_id) ? $link->scope_id : null,
                            'last_link_issued_at' => $link->created_at,
                            'last_link_id' => (string) $link->id,
                            'updated_at' => $link->updated_at,
                        ]);
                }

                DB::table('collaboration_external_links')
                    ->where('id', (string) $link->id)
                    ->update([
                        'collaborator_id' => $collaboratorId,
                        'contact_email' => $email,
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('collaboration_external_links', function (Blueprint $table): void {
            $table->dropColumn('collaborator_id');
        });

        Schema::dropIfExists('collaboration_external_collaborators');
    }
};
