<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Database\Seeders;

use Illuminate\Database\Seeder;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\Tag;

class ProductTagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tagsData = [
            ['code' => 'new',          'name' => 'New Arrival'],
            ['code' => 'bestseller',   'name' => 'Bestseller'],
            ['code' => 'on-sale',      'name' => 'On Sale'],
            ['code' => 'eco-friendly', 'name' => 'Eco-Friendly'],
            ['code' => 'limited',      'name' => 'Limited Edition'],
        ];

        $productTags = [];
        foreach ($tagsData as $tagData) {
            $tag = Tag::firstOrCreate(
                ['code' => $tagData['code']],
                $tagData
            );
            $productTags[] = $tag;
        }

        // --- Attach tags to products ---
        // Get some products to attach tags to
        $iphone15Pro = Product::where('handle', 'iphone-15-pro')->first();
        $macbookPro = Product::where('handle', 'macbook-pro-16-inch')->first();
        $diningTable = Product::where('handle', 'wooden-dining-table')->first();

        // Get some tags
        $newTag = Tag::where('code', 'new')->first();
        $bestsellerTag = Tag::where('code', 'bestseller')->first();
        $onSaleTag = Tag::where('code', 'on-sale')->first();
        $ecoFriendlyTag = Tag::where('code', 'eco-friendly')->first();

        if ($iphone15Pro && $newTag && $bestsellerTag) {
            $iphone15Pro->tags()->syncWithoutDetaching([$newTag->id, $bestsellerTag->id]);
        }

        if ($macbookPro && $bestsellerTag && $onSaleTag) {
            $macbookPro->tags()->syncWithoutDetaching([$bestsellerTag->id, $onSaleTag->id]);
        }

        if ($diningTable && $ecoFriendlyTag) {
            $diningTable->tags()->syncWithoutDetaching([$ecoFriendlyTag->id]);
        }
    }
}
