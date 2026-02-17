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
                'position'  => 1,
                'is_active' => true,
                'children'  => [
                    [
                        'handle'    => 'smartphones',
                        'name'      => 'Smartphones',
                        'position'  => 1,
                        'is_active' => true,
                    ],
                    [
                        'handle'    => 'laptops',
                        'name'      => 'Laptops',
                        'position'  => 2,
                        'is_active' => true,
                        'children'  => [
                            [
                                'handle'    => 'gaming-laptops',
                                'name'      => 'Gaming Laptops',
                                'position'  => 1,
                                'is_active' => true,
                            ],
                            [
                                'handle'    => 'business-laptops',
                                'name'      => 'Business Laptops',
                                'position'  => 2,
                                'is_active' => true,
                            ],
                        ],
                    ],
                    [
                        'handle'    => 'accessories',
                        'name'      => 'Accessories',
                        'position'  => 3,
                        'is_active' => true,
                    ],
                ],
            ],
            [
                'handle'    => 'clothing',
                'name'      => 'Clothing',
                'position'  => 2,
                'is_active' => true,
                'children'  => [
                    [
                        'handle'    => 'mens',
                        'name'      => 'Men\'s Clothing',
                        'position'  => 1,
                        'is_active' => true,
                    ],
                    [
                        'handle'    => 'womens',
                        'name'      => 'Women\'s Clothing',
                        'position'  => 2,
                        'is_active' => true,
                    ],
                ],
            ],
            [
                'handle'    => 'home-garden',
                'name'      => 'Home & Garden',
                'position'  => 3,
                'is_active' => true,
                'children'  => [
                    [
                        'handle'    => 'furniture',
                        'name'      => 'Furniture',
                        'position'  => 1,
                        'is_active' => true,
                    ],
                    [
                        'handle'    => 'decor',
                        'name'      => 'Decor',
                        'position'  => 2,
                        'is_active' => true,
                    ],
                ],
            ],
        ];

        $this->seedCategories($categories);
    }

    private function seedCategories(array $categories, ?Category $parent = null): void
    {
        foreach ($categories as $categoryData) {
            $children = $categoryData['children'] ?? [];
            unset($categoryData['children']);

            $category = Category::firstOrCreate(
                ['handle' => $categoryData['handle']],
                array_merge($categoryData, ['parent_id' => $parent?->id])
            );

            if (!empty($children)) {
                $this->seedCategories($children, $category);
            }
        }
    }
}
