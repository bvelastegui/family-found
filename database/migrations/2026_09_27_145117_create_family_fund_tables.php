<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fund_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->foreignId('administrator_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('treasurer_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->char('currency', 3)->default('USD');
            $table->string('timezone', 64)->default('America/Guayaquil');
            $table->timestamps(6);
        });
        Schema::create('banks', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->string('normalized_name', 191)->unique();
            $table->boolean('active')->default(true);
            $table->timestamps(6);
        });
        Schema::create('contribution_periods', function (Blueprint $table) {
            $table->id();
            $table->date('month')->unique();
            $table->bigInteger('amount_cents');
            $table->dateTime('locked_at', 6)->nullable();
            $table->timestamps(6);
        });
        Schema::create('evidences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('uploaded_by_id')->constrained('users')->restrictOnDelete();
            $table->string('path')->unique();
            $table->string('original_name');
            $table->string('mime', 127);
            $table->unsignedInteger('size');
            $table->char('sha256', 64);
            $table->dateTime('created_at', 6);
        });
        Schema::create('fund_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('bank_id')->constrained()->restrictOnDelete();
            $table->string('bank_name', 191);
            $table->string('reference', 191);
            $table->string('normalized_reference', 191);
            $table->string('active_reference', 191)->nullable();
            $table->date('transaction_date');
            $table->bigInteger('amount_cents');
            $table->foreignId('evidence_id')->constrained('evidences')->restrictOnDelete();
            $table->string('status', 32)->default('pending');
            $table->foreignId('pending_contributor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('corrected_from_id')->nullable()->constrained('fund_transactions')->restrictOnDelete();
            $table->foreignId('superseded_by_id')->nullable()->constrained('fund_transactions')->restrictOnDelete();
            $table->timestamps(6);
            $table->unique(['bank_id', 'active_reference']);
            $table->unique('pending_contributor_id');
            $table->index(['user_id', 'status', 'id']);
        });
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->bigInteger('principal_cents');
            $table->decimal('monthly_rate', 12, 6);
            $table->unsignedSmallInteger('term_months');
            $table->string('status', 32)->default('reserved');
            $table->foreignId('bank_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('bank_name', 191)->nullable();
            $table->string('reference', 191)->nullable();
            $table->date('disbursed_on')->nullable();
            $table->foreignId('evidence_id')->nullable()->constrained('evidences')->restrictOnDelete();
            $table->foreignId('superseded_by_id')->nullable()->constrained('loans')->restrictOnDelete();
            $table->timestamps(6);
            $table->index(['user_id', 'status', 'id']);
        });
        Schema::create('loan_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('number');
            $table->date('due_on');
            $table->bigInteger('capital_cents');
            $table->bigInteger('interest_cents');
            $table->bigInteger('balance_cents');
            $table->unique(['loan_id', 'number']);
        });
        Schema::create('transaction_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fund_transaction_id')->constrained()->restrictOnDelete();
            $table->foreignId('contribution_period_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('loan_installment_id')->nullable()->constrained()->restrictOnDelete();
            $table->bigInteger('amount_cents');
            $table->bigInteger('capital_cents')->default(0);
            $table->bigInteger('interest_cents')->default(0);
            $table->unique(['fund_transaction_id', 'contribution_period_id'], 'allocations_transaction_period_unique');
            $table->unique(['fund_transaction_id', 'loan_installment_id'], 'allocations_transaction_installment_unique');
        });
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('fund_transaction_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('loan_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('reversal_of_id')->nullable()->unique()->constrained('journal_entries')->restrictOnDelete();
            $table->dateTime('created_at', 6);
        });
        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->restrictOnDelete();
            $table->string('account', 32)->index();
            $table->string('side', 6);
            $table->bigInteger('amount_cents');
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('loan_id')->nullable()->constrained()->restrictOnDelete();
        });
        Schema::create('operation_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('event', 64);
            $table->string('subject_type', 32);
            $table->unsignedBigInteger('subject_id');
            $table->json('data');
            $table->dateTime('created_at', 6);
            $table->index(['subject_type', 'subject_id', 'id']);
        });
        Schema::create('operation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 64);
            $table->uuid('key');
            $table->char('fingerprint', 64);
            $table->unsignedBigInteger('result_id');
            $table->dateTime('created_at', 6);
            $table->unique(['actor_id', 'action', 'key']);
        });

        DB::statement("ALTER TABLE fund_settings ADD CONSTRAINT fund_singleton CHECK (id = 1 AND currency = 'USD' AND timezone = 'America/Guayaquil')");
        DB::statement('ALTER TABLE contribution_periods ADD CONSTRAINT positive_contribution CHECK (amount_cents > 0 AND DAY(month) = 1)');
        DB::statement('ALTER TABLE fund_transactions ADD CONSTRAINT positive_transaction CHECK (amount_cents > 0)');
        DB::statement('ALTER TABLE loans ADD CONSTRAINT valid_loan CHECK (principal_cents > 0 AND monthly_rate >= 0 AND term_months > 0)');
        DB::statement('ALTER TABLE loan_installments ADD CONSTRAINT valid_installment CHECK (capital_cents >= 0 AND interest_cents >= 0 AND balance_cents >= 0 AND capital_cents + interest_cents > 0)');
        DB::statement('ALTER TABLE transaction_allocations ADD CONSTRAINT valid_allocation CHECK (amount_cents > 0 AND capital_cents >= 0 AND interest_cents >= 0 AND ((contribution_period_id IS NOT NULL AND loan_installment_id IS NULL AND capital_cents = 0 AND interest_cents = 0) OR (contribution_period_id IS NULL AND loan_installment_id IS NOT NULL AND amount_cents = capital_cents + interest_cents)))');
        DB::statement("ALTER TABLE journal_lines ADD CONSTRAINT valid_journal_line CHECK (amount_cents > 0 AND side IN ('debit', 'credit') AND account IN ('cash', 'contributions', 'loan_principal', 'interest'))");

        foreach (['journal_entries', 'journal_lines', 'operation_events', 'loan_installments', 'transaction_allocations', 'evidences'] as $table) {
            foreach (['UPDATE', 'DELETE'] as $operation) {
                $trigger = $table.'_immutable_'.strtolower($operation);
                DB::unprepared("CREATE TRIGGER {$trigger} BEFORE {$operation} ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El historial financiero es inmutable'");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['operation_requests', 'operation_events', 'journal_lines', 'journal_entries', 'transaction_allocations', 'loan_installments', 'loans', 'fund_transactions', 'evidences', 'contribution_periods', 'banks', 'fund_settings'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
