<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Database\Seeders;

use Illuminate\Database\Seeder;
use Lahatre\Catalog\Models\Category;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'handle'    => 'electronics',
                'name'      => 'Electronics',
                'is_active' => true,
                'children'  => [
                    [
                        'handle'    => 'smartphones',
                        'name'      => 'Smartphones',
                        'is_active' => true,
                    ],
                    [
                        'handle'    => 'laptops',
                        'name'      => 'Laptops',
                        'is_active' => true,
                        'children'  => [
                            [
                                'handle'    => 'gaming-laptops',
                                'name'      => 'Gaming Laptops',
                                'is_active' => true,
                            ],
                            [
                                'handle'    => 'business-laptops',
                                'name'      => 'Business Laptops',
                                'is_active' => true,
                            ],
                        ],
                    ],
                    [
                        'handle'    => 'accessories',
                        'name'      => 'Accessories',
                        'is_active' => true,
                    ],
                ],
            ],
            [
                'handle'    => 'clothing',
                'name'      => 'Clothing',
                'is_active' => true,
                'children'  => [
                    [
                        'handle'    => 'mens',
                        'name'      => 'Men\'s Clothing',
                        'is_active' => true,
                    ],
                    [
                        'handle'    => 'womens',
                        'name'      => 'Women\'s Clothing',
                        'is_active' => true,
                    ],
                ],
            ],
            [
                'handle'    => 'home-garden',
                'name'      => 'Home & Garden',
                'is_active' => true,
                'children'  => [
                    [
                        'handle'    => 'furniture',
                        'name'      => 'Furniture',
                        'is_active' => true,
                    ],
                    [
                        'handle'    => 'decor',
                        'name'      => 'Decor',
                        'is_active' => true,
                    ],
                ],
            ],
        ];

        $this->seedCategories($categories);
    }

    private function seedCategories(array $categories, ?Category $parent = null): void
    {
        $organizationId = getPermissionsTeamId();

        foreach ($categories as $categoryData) {
            $children = $categoryData['children'] ?? [];
            unset($categoryData['children']);

            $category = Category::firstOrCreate(
                [
                    'handle'          => $categoryData['handle'],
                    'organization_id' => $organizationId,
                ],
                array_merge($categoryData, [
                    'parent_id'       => $parent?->id,
                    'organization_id' => $organizationId,
                ])
            );

            if (!empty($children)) {
                $this->seedCategories($children, $category);
            }
        }
    }
}
