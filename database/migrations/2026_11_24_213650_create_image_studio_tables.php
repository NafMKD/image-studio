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
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 80);
            $table->timestamps();
        });

        Schema::create('generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->uuid('client_request_id')->unique();
            $table->string('provider_id', 120)->nullable()->unique();
            $table->text('prompt');
            $table->string('size', 20);
            $table->string('quality', 20);
            $table->json('reference_urls')->nullable();
            $table->string('status', 24)->default('submitting')->index();
            $table->bigInteger('estimated_charge_nanos');
            $table->bigInteger('reserved_nanos');
            $table->bigInteger('provider_cost_nanos')->nullable();
            $table->bigInteger('actual_charge_nanos')->nullable();
            $table->json('usage')->nullable();
            $table->text('image_url')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('last_polled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

         Schema::create('credit_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('generation_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('event_key', 100)->unique();
            $table->string('type', 20);
            $table->bigInteger('balance_delta_nanos')->default(0);
            $table->bigInteger('reserved_delta_nanos')->default(0);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('webhook_receipts', function (Blueprint $table) {
            $table->id();
            $table->char('payload_sha256', 64)->unique();
            $table->string('provider_id', 120)->index();
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_receipts');
        Schema::dropIfExists('credit_entries');
        Schema::dropIfExists('generations');
        Schema::dropIfExists('conversations');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_admin', 'credit_balance_nanos', 'credit_reserved_nanos']);
        });
    }
};
