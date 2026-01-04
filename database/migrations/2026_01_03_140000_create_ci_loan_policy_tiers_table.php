<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ci_loan_policy_tiers', function (Blueprint $table) {
            $table->integer('tier_id', true);
            $table->string('tier_name', 100)->comment('English name');
            $table->string('tier_name_ar', 100)->comment('Arabic name');
            $table->string('tier_label_ar', 150)->comment('Full Arabic label with months');
            $table->decimal('salary_multiplier', 3, 2)->comment('0.50, 1.00, 1.50, 3.00');
            $table->integer('max_months')->comment('Maximum installment months');
            $table->boolean('is_one_time')->default(false)->comment('True for advance (tier 1)');
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
        });

        // Seed the 4 policy tiers
        DB::table('ci_loan_policy_tiers')->insert([
            [
                'tier_name' => 'Half-Month Salary Advance',
                'tier_name_ar' => 'سلفة نصف راتب',
                'tier_label_ar' => 'سلفة نصف راتب / شهر',
                'salary_multiplier' => 0.50,
                'max_months' => 1,
                'is_one_time' => true,
                'is_active' => true,
            ],
            [
                'tier_name' => '1-Month Salary Loan',
                'tier_name_ar' => 'قرض راتب شهر',
                'tier_label_ar' => 'قرض راتب شهر / 4 شهور',
                'salary_multiplier' => 1.00,
                'max_months' => 4,
                'is_one_time' => false,
                'is_active' => true,
            ],
            [
                'tier_name' => '1.5-Month Salary Loan',
                'tier_name_ar' => 'قرض راتب شهر ونصف',
                'tier_label_ar' => 'قرض راتب شهر ونصف / 6 شهور',
                'salary_multiplier' => 1.50,
                'max_months' => 6,
                'is_one_time' => false,
                'is_active' => true,
            ],
            [
                'tier_name' => '3-Month Salary Loan',
                'tier_name_ar' => 'قرض 3 رواتب',
                'tier_label_ar' => 'قرض 3 رواتب / سنة',
                'salary_multiplier' => 3.00,
                'max_months' => 12,
                'is_one_time' => false,
                'is_active' => true,
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_loan_policy_tiers');
    }
};
