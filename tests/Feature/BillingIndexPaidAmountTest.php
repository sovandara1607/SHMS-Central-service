<?php

namespace Tests\Feature;

use App\Http\Controllers\BillingApiController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * central-service has no migrations of its own (it shares Database-final's
 * Postgres schema in production) and its test DB starts empty, so this
 * builds just the columns BillingApiController::index() actually touches.
 * Calls the controller method directly rather than going through HTTP
 * routing, since the real routes sit behind the central-service.key
 * middleware — this test is about query correctness, not auth.
 *
 * Guards the rewrite from a LEFT JOIN payment + 9-column GROUP BY to a
 * correlated scalar subquery for paid_amount (scalability-plan.md §5.1):
 * both forms must produce the identical SUM per bill, including the
 * zero-payments case, which is the one a join/group-by rewrite is easiest
 * to get subtly wrong (an inner join would drop the bill entirely; a
 * broken subquery could return null instead of 0).
 */
class BillingIndexPaidAmountTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('patient', function (Blueprint $table) {
            $table->string('patient_id')->primary();
            $table->string('first_name');
            $table->string('last_name');
        });

        Schema::create('bill', function (Blueprint $table) {
            $table->string('bill_id')->primary();
            $table->string('patient_id');
            $table->string('appointment_id')->nullable();
            $table->string('generated_by')->nullable();
            $table->date('bill_date');
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->string('status')->default('unpaid');
        });

        Schema::create('payment', function (Blueprint $table) {
            $table->string('payment_id')->primary();
            $table->string('bill_id');
            $table->decimal('amount_paid', 10, 2);
            $table->date('payment_date');
        });

        Schema::create('bill_item', function (Blueprint $table) {
            $table->string('bill_item_id')->primary();
            $table->string('bill_id');
        });
    }

    public function test_paid_amount_sums_correctly_including_the_zero_payments_case(): void
    {
        \DB::table('patient')->insert(['patient_id' => 'PAT0001', 'first_name' => 'Ada', 'last_name' => 'Lovelace']);

        \DB::table('bill')->insert([
            ['bill_id' => 'BIL0001', 'patient_id' => 'PAT0001', 'bill_date' => '2026-01-01', 'total_amount' => 150, 'status' => 'partially_paid'],
            ['bill_id' => 'BIL0002', 'patient_id' => 'PAT0001', 'bill_date' => '2026-01-02', 'total_amount' => 50, 'status' => 'paid'],
            ['bill_id' => 'BIL0003', 'patient_id' => 'PAT0001', 'bill_date' => '2026-01-03', 'total_amount' => 75, 'status' => 'unpaid'],
        ]);

        // BIL0001: two payments (100 + 25 = 125, partially paid).
        // BIL0002: one payment (exactly covers the bill).
        // BIL0003: zero payments — the case a join/group-by rewrite is
        // easiest to break (inner join drops the row; a bad subquery
        // returns null instead of 0).
        \DB::table('payment')->insert([
            ['payment_id' => 'PAY0001', 'bill_id' => 'BIL0001', 'amount_paid' => 100, 'payment_date' => '2026-01-01'],
            ['payment_id' => 'PAY0002', 'bill_id' => 'BIL0001', 'amount_paid' => 25, 'payment_date' => '2026-01-02'],
            ['payment_id' => 'PAY0003', 'bill_id' => 'BIL0002', 'amount_paid' => 50, 'payment_date' => '2026-01-02'],
        ]);

        $controller = new BillingApiController();
        $response = $controller->index(Request::create('/api/bills', 'GET'));
        $body = json_decode($response->getContent(), true);

        $byId = collect($body['bills']['data'])->keyBy('bill_id');

        $this->assertEquals(125.0, (float) $byId['BIL0001']['paid_amount']);
        $this->assertEquals(50.0, (float) $byId['BIL0002']['paid_amount']);
        $this->assertEquals(0.0, (float) $byId['BIL0003']['paid_amount'], 'a bill with zero payments must report paid_amount = 0, not null');

        // Untouched by this change — item_count already used the same
        // correlated-subquery pattern paid_amount now follows.
        $this->assertEquals(0, $byId['BIL0001']['item_count']);
    }
}
