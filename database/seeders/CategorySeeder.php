<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Infrastructure Development',
                'description' => 'Ideas related to road construction, maintenance, and infrastructure improvements',
                'sort_order' => 1,
            ],
            [
                'name' => 'Digital Innovation',
                'description' => 'Technology solutions, digital transformation, and IT improvements',
                'sort_order' => 2,
            ],
            [
                'name' => 'Environmental Sustainability',
                'description' => 'Green initiatives, environmental protection, and sustainable practices',
                'sort_order' => 3,
            ],
            [
                'name' => 'Process Improvement',
                'description' => 'Operational efficiency, workflow optimization, and procedural enhancements',
                'sort_order' => 4,
            ],
            [
                'name' => 'Safety & Security',
                'description' => 'Road safety measures, security enhancements, and risk mitigation',
                'sort_order' => 5,
            ],
            [
                'name' => 'Customer Service',
                'description' => 'Public service improvements and stakeholder engagement initiatives',
                'sort_order' => 6,
            ],
            [
                'name' => 'Cost Optimization',
                'description' => 'Budget efficiency, cost reduction strategies, and resource optimization',
                'sort_order' => 7,
            ],
            [
                'name' => 'Quality Management',
                'description' => 'Quality assurance, standards improvement, and compliance initiatives',
                'sort_order' => 8,
            ],
            [
                'name' => 'Human Resources',
                'description' => 'Staff development, training programs, and workplace improvements',
                'sort_order' => 9,
            ],
            [
                'name' => 'Other',
                'description' => 'Miscellaneous ideas that don\'t fit other categories',
                'sort_order' => 10,
            ],
        ];

        foreach ($categories as $category) {
            \App\Models\Category::create($category);
        }
    }
}
