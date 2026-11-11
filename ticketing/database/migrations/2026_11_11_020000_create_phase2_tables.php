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
        Schema::create('organizer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('organization_name', 190);
            $table->string('contact_phone', 30)->nullable();
            $table->string('legal_doc_path')->nullable();
            $table->string('status', 30)->default('pending'); // pending, approved, suspended
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('museum_staff', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('museum_id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('staff_role', 30); // owner, manager, gate_staff
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['museum_id', 'user_id']);
            $table->index(['museum_id', 'staff_role']);
        });

        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('provider', 30); // google, etc.
            $table->string('provider_user_id', 190);
            $table->timestamps();

            $table->unique(['provider', 'provider_user_id']);
            $table->index('user_id');
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100);
            $table->string('subject_type', 190)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['actor_id', 'created_at']);
        });

        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_settings');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('social_accounts');
        Schema::dropIfExists('museum_staff');
        Schema::dropIfExists('organizer_profiles');
    }
};
