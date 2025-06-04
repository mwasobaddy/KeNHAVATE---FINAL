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
        Schema::table('ideas', function (Blueprint $table) {
            // Add new columns
            $table->text('business_case')->nullable()->after('description');
            $table->text('expected_impact')->nullable()->after('business_case');
            $table->text('implementation_timeline')->nullable()->after('expected_impact');
            $table->text('resource_requirements')->nullable()->after('implementation_timeline');
            $table->foreignId('category_id')->nullable()->constrained('categories')->after('author_id');
            
            // Remove old columns
            $table->dropColumn([
                'problem_statement',
                'proposed_solution', 
                'expected_benefits',
                'implementation_plan',
                'attachments',
                'category'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ideas', function (Blueprint $table) {
            // Add back old columns
            $table->text('problem_statement')->after('description');
            $table->text('proposed_solution')->after('problem_statement');
            $table->text('expected_benefits')->nullable()->after('proposed_solution');
            $table->text('implementation_plan')->nullable()->after('expected_benefits');
            $table->json('attachments')->nullable()->after('implementation_plan');
            $table->string('category')->nullable()->after('author_id');
            
            // Remove new columns
            $table->dropForeign(['category_id']);
            $table->dropColumn([
                'business_case',
                'expected_impact',
                'implementation_timeline',
                'resource_requirements',
                'category_id'
            ]);
        });
    }
};
