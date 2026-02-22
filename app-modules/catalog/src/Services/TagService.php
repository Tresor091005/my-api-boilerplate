<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Services;

use Illuminate\Support\Facades\DB;
use Lahatre\Catalog\Assertions\TagAssertion;
use Lahatre\Catalog\DTO\TagDTO;
use Lahatre\Catalog\DTO\TagFilterDTO;
use Lahatre\Catalog\Http\Resources\TagCollection;
use Lahatre\Catalog\Http\Resources\TagResource;
use Lahatre\Catalog\Models\Tag;
use Lahatre\Shared\Support\HandleGenerator;

class TagService
{
    public function __construct(
        protected TagAssertion $tagAssertion
    ) {}

    public function list(TagFilterDTO $filters): TagCollection
    {
        $query = Tag::query();

        if ($filters->code) {
            $query->where('code', 'like', "%{$filters->code}%");
        }
        if ($filters->name) {
            $query->where('name', 'like', "%{$filters->name}%");
        }

        $query->orderBy($filters->sort_by, $filters->sort_order);

        $tags = $filters->cursor
            ? $query->cursorPaginate($filters->per_page, ['*'], 'cursor', $filters->cursor)
            : $query->cursorPaginate($filters->per_page);

        return TagCollection::make($tags);
    }

    public function retrieve(Tag $tag): TagResource
    {
        $tag->load(['products']);

        return TagResource::make($tag);
    }

    public function create(TagDTO $dto): TagResource
    {
        $tag = new Tag();

        $tag->fill([
            'name' => $dto->name,
        ]);

        $tag->code = HandleGenerator::generate(
            $dto->name,
            $tag->getTable(),
            'code'
        );

        DB::transaction(fn () => $tag->save());

        return TagResource::make($tag->load(['products']));
    }

    public function update(Tag $tag, TagDTO $dto): TagResource
    {
        $tag->fill([
            'name' => $dto->name,
        ]);

        DB::transaction(fn () => $tag->save());

        return TagResource::make($tag->load(['products']));
    }

    public function delete(Tag $tag): void
    {
        $this->tagAssertion->assertCanDelete($tag);

        DB::transaction(function () use ($tag): void {
            $tag->delete();
        });
    }
}
