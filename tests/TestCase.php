<?php

namespace Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * TEST-ONLY schema scaffolding — NOT an application change.
     *
     * The app's `sales` and `users` tables ship exclusively in the MySQL dump
     * (biztory.sql); the repo's migrations create framework tables only, on
     * purpose. The suite runs on sqlite :memory: (phpunit.xml), so every test
     * boots against an empty database. To make the suite honestly green
     * without touching migrations or app code, each test creates minimal
     * copies of the two tables the tests touch. Columns and types are
     * cross-checked against the CREATE TABLE statements in biztory.sql.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTestSchema();
    }

    private function createTestSchema(): void
    {
        if (! $this->app->environment('testing')) {
            return; // scaffolding is for the test environment only
        }

        if (! Schema::hasTable('sales')) {
            // Mirrors CREATE TABLE `sales` in biztory.sql (int-unsigned PK,
            // decimal(12,2) money columns, tinyint statuses, soft deletes).
            Schema::create('sales', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedTinyInteger('status')->default(1);
                $table->string('ref_num', 50);
                $table->date('invoice_date');
                $table->date('delivery_date')->nullable();
                $table->string('payee');
                $table->unsignedMediumInteger('payee_id');
                $table->decimal('total', 12, 2);
                $table->string('currency', 3)->nullable();
                $table->decimal('currency_total', 12, 2);
                $table->decimal('paid', 12, 2);
                $table->decimal('due', 12, 2);
                $table->decimal('rounding', 3, 2)->nullable()->default(0);
                $table->date('due_date')->nullable();
                $table->string('attn', 200)->nullable();
                $table->string('payment_term', 20)->nullable();
                $table->tinyInteger('payment_status')->default(0);
                $table->tinyInteger('delivery_status')->default(0);
                $table->integer('branch_id')->nullable()->default(0);
                $table->tinyInteger('locked')->default(0);
                $table->smallInteger('staff_id')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->smallInteger('author_id')->nullable();
            });
        }

        if (! Schema::hasTable('users')) {
            // Minimal mirror of CREATE TABLE `users` in biztory.sql — only the
            // columns the suite can reach (the exists:users,id validation rules
            // in CountDailySalesRequest and graphql/sale.graphql).
            Schema::create('users', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
                $table->string('email', 100)->nullable();
                $table->string('password', 64);
                $table->string('remember_token', 100)->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // GraphQLDailyTest filters on payee_id 506 behind an exists:users,id
        // rule. The dump seeds ~843 users (ids 1+), so 506 exists on a real
        // biztory database — seed the same id here so the rule passes.
        if (! DB::table('users')->where('id', 506)->exists()) {
            DB::table('users')->insert([
                'id' => 506,
                'name' => 'Seeded Test Payee',
                'password' => 'not-a-real-password',
            ]);
        }
    }
}
