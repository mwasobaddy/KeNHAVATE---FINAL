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
                'name' => 'Customer Service Excellence',
                'description' => 'Revolutionize our client interactions and organizational systems for unparalleled service delivery. Focus on technology or processes that improve communication with clients, innovative solutions that streamline query resolution, and ways to personalize and enhance the customer experience.',
                'sort_order' => 1,
                'active' => true,
            ],
            [
                'name' => 'Quality & Safety Innovation',
                'description' => 'Enhance road longevity and user safety through cutting-edge technologies and practices. Explore ways to maintain high-quality road standards while minimizing pollution, sustainable materials or practices, and innovative approaches to improve road safety.',
                'sort_order' => 2,
                'active' => true,
            ],
            [
                'name' => 'Advanced Road Materials',
                'description' => 'Pioneer next-gen materials for durable, sustainable, and intelligent road infrastructure. Discover new asphalt mixtures or eco-friendly additives that improve durability, advanced reinforcement techniques, and cost-effective solutions that enhance resilience in road construction.',
                'sort_order' => 3,
                'active' => true,
            ],
            [
                'name' => 'Construction Technologies',
                'description' => 'Transform road construction with revolutionary technologies and methodologies. Explore new technologies that enhance foundational strength of road pavements, inventive engineering solutions for improved longevity and safety, and data-driven insights with smart technologies for optimizing construction processes.',
                'sort_order' => 4,
                'active' => true,
            ],
            [
                'name' => 'Climate Resilience Solutions',
                'description' => 'Develop adaptive infrastructure to combat and mitigate effects of climate change. Focus on incorporating sustainable materials and practices into road design, advanced drainage systems or renewable energy sources, and making infrastructure more adaptable to changing climate conditions.',
                'sort_order' => 5,
                'active' => true,
            ],
            [
                'name' => 'Value Optimization & Revenue',
                'description' => 'Maximize resource utilization and unlock new revenue streams for sustainable growth. Develop strategies or innovations that ensure high-quality outputs while optimizing resource utilization cost-effectively, use data analytics or market insights to uncover underutilized resources, and implement monetization strategies to enhance financial resilience.',
                'sort_order' => 6,
                'active' => true,
            ],
            [
                'name' => 'Other',
                'description' => 'Ideas that don\'t fit into any of the specific categories above. This category accommodates innovative concepts that may span multiple areas or represent entirely new approaches to road infrastructure and management challenges.',
                'sort_order' => 7,
                'active' => true,
            ],
        ];

        foreach ($categories as $category) {
            \App\Models\Category::updateOrCreate(
                ['name' => $category['name']],
                $category
            );
        }
    }
}
