<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\Service;
use Lahatre\Customer\Models\Customer;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Iam\Models\Permission;
use Lahatre\Iam\Models\Role;
use Lahatre\Iam\Models\User;
use Lahatre\Library\Contracts\LibraryInterface;
use Lahatre\Library\Exceptions\LibraryException;
use Lahatre\Library\Models\File;
use Lahatre\Library\Models\FileAttachment;
use Lahatre\Library\Services\FileService;
use Lahatre\Library\Services\LibraryService;
use Lahatre\Organization\Models\Organization;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutMiddleware(ThrottleRequests::class);
    Storage::fake('local');
    config()->set('library.disk', 'local');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->organization = Organization::factory()->create();
    setPermissionsTeamId($this->organization->id);
    $this->user = User::factory()->create();
    $member = OrganizationMember::create([
        'user_id'         => $this->user->id,
        'organization_id' => $this->organization->id,
    ]);
    $role = Role::query()->firstOrCreate(['name' => 'attachment-test-role', 'guard_name' => 'sanctum']);
    $memberRole = MemberRole::create([
        'organization_id' => $this->organization->id,
        'member_id'       => $member->id,
        'role_id'         => $role->id,
    ]);
    $permissions = [
        'catalog_product.list', 'catalog_product.retrieve', 'catalog_product.update', 'catalog_product.delete',
        'catalog_service.list', 'catalog_service.retrieve', 'catalog_service.update', 'catalog_service.delete',
        'customer_customer.list', 'customer_customer.retrieve', 'customer_customer.update', 'customer_customer.delete',
    ];

    foreach ($permissions as $name) {
        Permission::query()->firstOrCreate(['name' => $name, 'guard_name' => 'sanctum']);
    }
    $memberRole->givePermissionTo($permissions);
    $this->memberRole = $memberRole;

    $token = $this->user->createToken('attachment-test');
    $token->accessToken->update(['metadata' => [
        'organization_id' => $this->organization->id,
        'member_id'       => $member->id,
        'member_role_id'  => $memberRole->id,
        'role_id'         => $role->id,
    ]]);
    $this->withToken($token->plainTextToken);
});

function imageFile(string $organizationId, string $mimeType = 'image/webp'): File
{
    $file = File::factory()->create(['organization_id' => $organizationId, 'mime_type' => $mimeType]);
    Storage::disk('local')->put($file->storage_key, 'image bytes');

    return $file;
}

it('attaches and orders product images through the HTTP contract', function (): void {
    $product = Product::factory()->create(['organization_id' => $this->organization->id]);
    $files = collect(range(1, 3))->map(fn (): File => imageFile($this->organization->id));
    $base = "/v1/catalog/products/{$product->id}";

    $this->getJson("{$base}?include=files.main,files.gallery")->assertOk()->assertJsonPath('data.files.main', [])
        ->assertJsonPath('data.files.gallery', []);
    $this->getJson($base)->assertOk()->assertJsonMissingPath('data.files');
    $this->getJson("{$base}?include=files")->assertUnprocessable();
    $this->putJson("{$base}/files/main", ['file_ids' => [$files[0]->id]])
        ->assertUnprocessable()->assertJsonValidationErrors('file_id');
    $this->putJson("{$base}/files/main", ['file_ids' => [$files[0]->id, $files[1]->id]])->assertUnprocessable();
    $this->putJson("{$base}/files/main", ['file_id' => $files[0]->id, 'file_ids' => [$files[1]->id]])
        ->assertUnprocessable()->assertJsonValidationErrors('file_ids');

    $this->putJson("{$base}/files/main", ['file_id' => $files[0]->id])->assertNoContent();
    $this->putJson("{$base}/files/main", ['file_id' => $files[0]->id])->assertNoContent();
    expect(FileAttachment::query()->where('organization_id', $this->organization->id)->where('slot', 'main')->count())->toBe(1);
    $this->putJson("{$base}/files/main", ['file_id' => $files[1]->id])->assertNoContent();
    $main = $this->getJson("{$base}?include=files.main")->json('data.files.main.0');
    expect($main['file']['id'])->toBe($files[1]->id)
        ->and($main['position'])->toBe(1)
        ->and($main['content_url'])->toBe("{$base}/files/{$main['id']}/content")
        ->and($main['file'])->not->toHaveKey('storage_disk')
        ->and($main['file'])->not->toHaveKey('content_url');

    foreach ($files as $file) {
        $this->postJson("{$base}/files/gallery", ['file_ids' => [$file->id]])->assertNoContent();
    }

    $this->getJson("{$base}?include=files.main")->assertOk()->assertJsonMissingPath('data.files.gallery');
    $this->getJson("{$base}?include=files.gallery")->assertOk()->assertJsonMissingPath('data.files.main');

    $gallery = $this->getJson("{$base}?include=files.gallery")->assertOk()->json('data.files.gallery');
    expect(array_column($gallery, 'position'))->toBe([1, 2, 3]);
    $orderedIds = [$gallery[2]['id'], $gallery[0]['id'], $gallery[1]['id']];
    $this->putJson("{$base}/files/gallery", ['attachment_ids' => $orderedIds])->assertNoContent();
    expect(array_column($this->getJson("{$base}?include=files.gallery")->json('data.files.gallery'), 'id'))->toBe($orderedIds);

    $this->putJson("{$base}/files/gallery", ['attachment_ids' => [$orderedIds[0]]])->assertUnprocessable();
    $this->putJson("{$base}/files/gallery", ['attachment_ids' => [$orderedIds[0], $orderedIds[0], $orderedIds[2]]])->assertUnprocessable();
    $otherProduct = Product::factory()->create(['organization_id' => $this->organization->id]);
    $this->postJson("/v1/catalog/products/{$otherProduct->id}/files/gallery", ['file_ids' => [imageFile($this->organization->id)->id]])->assertNoContent();
    $foreignAttachmentId = $otherProduct->fileAttachments()->firstOrFail()->id;
    $this->putJson("{$base}/files/gallery", ['attachment_ids' => [$orderedIds[0], $orderedIds[1], $foreignAttachmentId]])->assertUnprocessable();
    expect(array_column($this->getJson("{$base}?include=files.gallery")->json('data.files.gallery'), 'id'))->toBe($orderedIds);
    $this->get("{$base}/files/{$foreignAttachmentId}/content")->assertNotFound();
    $this->postJson("{$base}/files/gallery", ['file_ids' => [$files[0]->id]])->assertUnprocessable();
    $this->deleteJson("{$base}/files/gallery", ['attachment_ids' => []])
        ->assertUnprocessable();
    $this->deleteJson("{$base}/files/gallery", ['attachment_ids' => [$orderedIds[0], $orderedIds[0]]])
        ->assertUnprocessable()->assertJsonValidationErrors('attachment_ids.1');
    $this->deleteJson("{$base}/files/gallery", ['attachment_ids' => [$orderedIds[0], $foreignAttachmentId]])
        ->assertNotFound();
    expect(array_column($this->getJson("{$base}?include=files.gallery")->json('data.files.gallery'), 'id'))->toBe($orderedIds);
    $this->deleteJson("{$base}/files/gallery", ['attachment_ids' => [$orderedIds[1], $orderedIds[2]]])->assertNoContent();
    expect(array_column($this->getJson("{$base}?include=files.gallery")->json('data.files.gallery'), 'id'))->toBe([$orderedIds[0]])
        ->and(array_column($this->getJson("{$base}?include=files.gallery")->json('data.files.gallery'), 'position'))->toBe([1]);

    $this->putJson("{$base}/files/main", ['file_id' => null])->assertNoContent();
    expect($this->getJson("{$base}?include=files.main")->json('data.files.main'))->toBe([]);
});

it('enforces the declared gallery quota across HTTP and direct Library calls', function (): void {
    $product = Product::factory()->create(['organization_id' => $this->organization->id]);
    $files = collect(range(1, 21))->map(fn (): File => imageFile($this->organization->id));
    $base = "/v1/catalog/products/{$product->id}/files/gallery";

    $this->postJson($base, ['file_ids' => $files->pluck('id')->all()])
        ->assertUnprocessable()->assertJsonValidationErrors('file_ids');
    $this->postJson($base, ['file_ids' => $files->take(20)->pluck('id')->all()])->assertNoContent();
    $this->postJson($base, ['file_ids' => [$files[20]->id]])->assertUnprocessable();
    expect(fn () => app(LibraryInterface::class)->addAttachments($product, 'gallery', [$files[20]->id]))
        ->toThrow(LibraryException::class);
    expect($product->fileAttachments()->where('slot', 'gallery')->count())->toBe(20);

    $firstId = $product->fileAttachments()->where('slot', 'gallery')->firstOrFail()->id;
    $this->deleteJson($base, ['attachment_ids' => [$firstId]])->assertNoContent();
    $this->postJson($base, ['file_ids' => [$files[20]->id]])->assertNoContent();
    expect($product->fileAttachments()->where('slot', 'gallery')->pluck('position')->all())->toBe(range(1, 20));
});

it('returns 404 for malformed attachment identifiers', function (): void {
    $product = Product::factory()->create(['organization_id' => $this->organization->id]);
    $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);

    $this->get("/v1/catalog/products/{$product->id}/files/not-an-uuid/content")->assertNotFound();
    $this->deleteJson("/v1/catalog/products/{$product->id}/files/gallery", ['attachment_ids' => ['not-an-uuid']])
        ->assertUnprocessable()->assertJsonValidationErrors('attachment_ids.0');
    $this->get("/v1/customer/customers/{$customer->id}/files/not-an-uuid/content")->assertNotFound();
});

it('appends product gallery files in one insert and rejects invalid batches atomically', function (): void {
    $product = Product::factory()->create(['organization_id' => $this->organization->id]);
    $base = "/v1/catalog/products/{$product->id}";
    $existing = imageFile($this->organization->id);
    $batch = collect(range(1, 4))->map(fn (): File => imageFile($this->organization->id));
    $this->postJson("{$base}/files/gallery", ['file_ids' => [$existing->id]])->assertNoContent();

    $inserts = [];
    DB::listen(function ($query) use (&$inserts): void {
        if (str_contains(strtolower($query->sql), 'insert into "library_file_attachments"')) {
            $inserts[] = $query->sql;
        }
    });

    $this->postJson("{$base}/files/gallery", ['file_ids' => $batch->pluck('id')->all()])->assertNoContent();
    expect($inserts)->toHaveCount(1)
        ->and($product->fileAttachments()->where('slot', 'gallery')->pluck('file_id')->all())
        ->toBe([$existing->id, ...$batch->pluck('id')->all()]);

    $this->postJson("{$base}/files/gallery", ['file_ids' => [$batch[0]->id, $batch[0]->id]])
        ->assertUnprocessable()->assertJsonValidationErrors('file_ids.1');
    $this->postJson("{$base}/files/gallery", ['file_ids' => []])
        ->assertUnprocessable();
    $this->postJson("{$base}/files/gallery", ['file_id' => $existing->id])->assertUnprocessable();
    $this->postJson("{$base}/files/gallery", ['file_id' => $existing->id, 'file_ids' => [$existing->id]])
        ->assertUnprocessable()->assertJsonValidationErrors('file_id');

    $newFile = imageFile($this->organization->id);
    $pdf = imageFile($this->organization->id, 'application/pdf');
    $this->postJson("{$base}/files/gallery", ['file_ids' => [$newFile->id, $pdf->id]])->assertUnprocessable();
    $this->postJson("{$base}/files/gallery", ['file_ids' => [$newFile->id, $existing->id]])->assertUnprocessable();
    $this->postJson("{$base}/files/gallery", ['file_ids' => [$newFile->id, (string) Str::uuid7()]])->assertNotFound();
    $otherOrganization = Organization::factory()->create();
    $foreignFile = imageFile($otherOrganization->id);
    $this->postJson("{$base}/files/gallery", ['file_ids' => [$newFile->id, $foreignFile->id]])->assertNotFound();
    expect($product->fileAttachments()->where('slot', 'gallery')->count())->toBe(5);
});

it('appends service gallery files in one request', function (): void {
    $catalogItem = CatalogItem::factory()->service()->create(['organization_id' => $this->organization->id]);
    $service = Service::factory()->forCatalogItem($catalogItem)->create();
    $files = collect(range(1, 3))->map(fn (): File => imageFile($this->organization->id));

    $this->postJson("/v1/catalog/services/{$service->id}/files/gallery?response=resource&include=files.gallery", [
        'file_ids' => $files->pluck('id')->all(),
    ])->assertCreated()->assertJsonPath('data.files.gallery.0.file.id', $files[0]->id)
        ->assertJsonPath('data.files.gallery.2.file.id', $files[2]->id);

    $attachmentIds = $service->fileAttachments()->where('slot', 'gallery')->pluck('id')->all();
    $this->deleteJson("/v1/catalog/services/{$service->id}/files/gallery", [
        'attachment_ids' => [$attachmentIds[0], $attachmentIds[2]],
    ])->assertNoContent();
    expect($service->fileAttachments()->where('slot', 'gallery')->pluck('id')->all())->toBe([$attachmentIds[1]]);
});

it('keeps declared slots independent through the Library contract', function (): void {
    $product = Product::factory()->create(['organization_id' => $this->organization->id]);
    $files = collect(range(1, 3))->map(fn (): File => imageFile($this->organization->id));
    $library = app(LibraryInterface::class);

    $library->addAttachments($product, 'gallery', $files->pluck('id')->all());
    $library->replaceAttachments($product, 'main', [$files[0]->id]);

    $galleryIds = $product->fileAttachments()->where('slot', 'gallery')->pluck('id')->all();
    $mainId = $product->fileAttachments()->where('slot', 'main')->firstOrFail()->id;
    expect($product->fileAttachments()->where('slot', 'gallery')->pluck('position')->all())->toBe([1, 2, 3]);

    $library->reorderAttachments($product, 'gallery', array_reverse($galleryIds));
    expect($product->fileAttachments()->where('slot', 'gallery')->pluck('id')->all())->toBe(array_reverse($galleryIds));

    expect(fn () => $library->reorderAttachments($product, 'gallery', [$galleryIds[0], $mainId]))
        ->toThrow(LibraryException::class);
    $library->removeAttachments($product, 'gallery', [$galleryIds[1], $galleryIds[2]]);
    expect($product->fileAttachments()->where('slot', 'gallery')->pluck('position')->all())->toBe([1])
        ->and($product->fileAttachments()->where('slot', 'main')->pluck('id')->all())->toBe([$mainId]);
});

it('replaces the complete contents of a slot limited to one file', function (): void {
    $product = Product::factory()->create(['organization_id' => $this->organization->id]);
    $first = imageFile($this->organization->id);
    $second = imageFile($this->organization->id);
    $library = app(LibraryInterface::class);

    $library->replaceAttachments($product, 'main', [$first->id]);
    $library->replaceAttachments($product, 'main', [$second->id]);
    $attachmentId = $product->fileAttachments()->where('slot', 'main')->firstOrFail()->id;
    $library->replaceAttachments($product, 'main', [7 => $second->id]);

    expect($product->fileAttachments()->where('slot', 'main')->pluck('file_id')->all())->toBe([$second->id])
        ->and($product->fileAttachments()->where('slot', 'main')->pluck('position')->all())->toBe([1])
        ->and($product->fileAttachments()->where('slot', 'main')->firstOrFail()->id)->toBe($attachmentId);
    expect(fn () => $library->replaceAttachments($product, 'main', [$first->id, $second->id]))
        ->toThrow(LibraryException::class);
    expect(fn () => $library->addAttachments($product, 'main', [$first->id]))
        ->toThrow(LibraryException::class);
    expect(fn () => $library->replaceAttachments($product, 'hero', [$first->id]))
        ->toThrow(LibraryException::class);
});

it('enforces model slot rules in Library and parent active state in the owning modules', function (): void {
    $product = Product::factory()->create(['organization_id' => $this->organization->id]);
    $pdf = imageFile($this->organization->id, 'application/pdf');
    $library = app(LibraryInterface::class);
    $foreignOrganization = Organization::factory()->create();
    $foreignFile = imageFile($foreignOrganization->id);

    expect($library->findFiles([$pdf->id, $foreignFile->id, 'not-an-uuid'])->pluck('id')->all())->toBe([$pdf->id]);

    $this->putJson("/v1/catalog/products/{$product->id}/files/main", ['file_id' => $pdf->id])->assertUnprocessable();
    expect(fn () => $library->replaceAttachments($product, 'main', [$pdf->id]))->toThrow(LibraryException::class);

    $catalogItem = CatalogItem::factory()->service()->create([
        'organization_id' => $this->organization->id,
        'is_active'       => false,
    ]);
    $service = Service::factory()->forCatalogItem($catalogItem)->create();
    $image = imageFile($this->organization->id);
    $this->postJson("/v1/catalog/services/{$service->id}/files/gallery", ['file_ids' => [$image->id]])->assertUnprocessable();
    $library->addAttachments($service, 'gallery', [$image->id]);
    expect($service->fileAttachments()->where('slot', 'gallery')->count())->toBe(1);

    $activeCustomer = Customer::factory()->create(['organization_id' => $this->organization->id]);
    $this->putJson("/v1/customer/customers/{$activeCustomer->id}/files/profile-picture", ['file_id' => $pdf->id])
        ->assertUnprocessable();

    $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'is_active' => false]);
    $this->putJson("/v1/customer/customers/{$customer->id}/files/profile-picture", ['file_id' => $image->id])->assertUnprocessable();
    $library->replaceAttachments($customer, 'profile_picture', [$image->id]);
    expect($customer->fileAttachments()->where('slot', 'profile_picture')->firstOrFail()->file_id)->toBe($image->id);
});

it('authorizes linked file use through the parent permission', function (): void {
    $product = Product::factory()->create(['organization_id' => $this->organization->id]);
    $image = imageFile($this->organization->id);
    $base = "/v1/catalog/products/{$product->id}";

    $this->getJson("/v1/library/files/{$image->id}")->assertForbidden();
    $this->putJson("{$base}/files/main", ['file_id' => $image->id])->assertNoContent();

    $attachmentId = $product->fileAttachments()->where('slot', 'main')->firstOrFail()->id;
    $this->get("{$base}/files/{$attachmentId}/content")->assertOk();
});

it('updates gallery positions in one statement per reorder and removal', function (): void {
    $product = Product::factory()->create(['organization_id' => $this->organization->id]);
    $base = "/v1/catalog/products/{$product->id}";

    foreach (range(1, 4) as $unused) {
        $this->postJson("{$base}/files/gallery", ['file_ids' => [imageFile($this->organization->id)->id]])->assertNoContent();
    }

    $ids = $product->fileAttachments()->where('slot', 'gallery')->pluck('id')->all();
    $updates = [];
    DB::listen(function ($query) use (&$updates): void {
        if (str_contains(strtolower($query->sql), 'update library_file_attachments')) {
            $updates[] = $query->sql;
        }
    });

    $reordered = array_reverse($ids);
    $this->putJson("{$base}/files/gallery", ['attachment_ids' => $reordered])->assertNoContent();
    expect($updates)->toHaveCount(1)
        ->and($product->fileAttachments()->where('slot', 'gallery')->pluck('id')->all())->toBe($reordered);

    $updates = [];
    $this->deleteJson("{$base}/files/gallery", ['attachment_ids' => [$reordered[1], $reordered[2]]])->assertNoContent();
    expect($updates)->toHaveCount(1)
        ->and($product->fileAttachments()->where('slot', 'gallery')->pluck('position')->all())->toBe([1, 2]);
});

it('requires a persisted attachable parent for attachment factories', function (): void {
    expect(app(LibraryInterface::class))->toBeInstanceOf(LibraryService::class);

    $product = Product::factory()->create(['organization_id' => $this->organization->id]);
    $attachment = FileAttachment::factory()->forParent($product)->create();

    expect($attachment->attachable_type)->toBe($product->getMorphClass())
        ->and($attachment->attachable_id)->toBe($product->id)
        ->and($attachment->organization_id)->toBe($product->organization_id)
        ->and($attachment->file->organization_id)->toBe($product->organization_id);

    expect(fn () => FileAttachment::factory()->create())->toThrow(LogicException::class);

    $fileWithoutTrait = File::factory()->create(['organization_id' => $this->organization->id]);
    expect(fn () => FileAttachment::factory()->forParent($fileWithoutTrait))->toThrow(TypeError::class);
    expect(fn () => app(LibraryInterface::class)->find($fileWithoutTrait, (string) Str::uuid7()))
        ->toThrow(TypeError::class);
});

it('enforces MIME, tenant, file lifecycle, and parent permissions', function (): void {
    $product = Product::factory()->create(['organization_id' => $this->organization->id]);
    $image = imageFile($this->organization->id);
    $pdf = imageFile($this->organization->id, 'application/pdf');
    $otherOrganization = Organization::factory()->create();
    $foreignImage = imageFile($otherOrganization->id);
    $base = "/v1/catalog/products/{$product->id}";

    $this->putJson("{$base}/files/main", ['file_id' => $pdf->id])->assertUnprocessable();
    $this->putJson("{$base}/files/main", ['file_id' => $foreignImage->id])->assertNotFound();
    $this->putJson("{$base}/files/main", ['file_id' => $image->id])->assertNoContent();
    $this->putJson("{$base}/files/main", ['file_id' => $pdf->id])->assertUnprocessable();
    expect($this->getJson("{$base}?include=files.main")->json('data.files.main.0.file.id'))->toBe($image->id);
    $attachment = FileAttachment::query()->where('organization_id', $this->organization->id)->firstOrFail();
    $this->get("{$base}/files/{$attachment->id}/content")->assertOk();
    expect(fn () => app(FileService::class)->delete($image))->toThrow(LibraryException::class);

    $this->memberRole->revokePermissionTo('catalog_product.retrieve');
    $this->get("{$base}/files/{$attachment->id}/content")->assertForbidden();
    $this->memberRole->givePermissionTo('catalog_product.retrieve');
    $this->putJson("{$base}/files/main", ['file_id' => null])->assertNoContent();
    app(FileService::class)->delete($image);
    $this->putJson("{$base}/files/main", ['file_id' => $image->id])->assertNotFound();
});

it('links service and customer images and detaches them when parents are deleted', function (): void {
    $catalogItem = CatalogItem::factory()->service()->create(['organization_id' => $this->organization->id]);
    $service = Service::factory()->forCatalogItem($catalogItem)->create();
    $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
    $serviceImage = imageFile($this->organization->id);
    $customerImage = imageFile($this->organization->id);
    $replacementImage = imageFile($this->organization->id);

    $this->putJson("/v1/catalog/services/{$service->id}/files/main?response=resource&include=files.main", ['file_id' => $serviceImage->id])
        ->assertOk()->assertJsonPath('data.files.main.0.file.id', $serviceImage->id);
    $this->putJson("/v1/customer/customers/{$customer->id}/files/profile-picture?response=resource&include=files.profile_picture", ['file_id' => $customerImage->id])
        ->assertOk()->assertJsonPath('data.files.profile_picture.0.file.id', $customerImage->id);
    $this->putJson("/v1/customer/customers/{$customer->id}/files/profile-picture", ['file_id' => $replacementImage->id])->assertNoContent();
    expect($this->getJson("/v1/customer/customers/{$customer->id}?include=files.profile_picture")->json('data.files.profile_picture.0.file.id'))
        ->toBe($replacementImage->id);
    $this->putJson("/v1/customer/customers/{$customer->id}/files/profile-picture", ['file_id' => null])->assertNoContent();
    $this->putJson("/v1/customer/customers/{$customer->id}/files/profile-picture", ['file_id' => $customerImage->id])->assertNoContent();

    $this->postJson("/v1/catalog/services/{$service->id}/files/gallery?response=resource&include=files.gallery", ['file_ids' => [$replacementImage->id]])
        ->assertCreated()->assertJsonPath('data.files.gallery.0.file.id', $replacementImage->id);
    expect($this->getJson("/v1/catalog/services/{$service->id}?include=files.gallery")->json('data.files.gallery.0.file.id'))
        ->toBe($replacementImage->id);

    $this->deleteJson("/v1/catalog/services/{$service->id}")->assertNoContent();
    $this->deleteJson("/v1/customer/customers/{$customer->id}")->assertNoContent();

    expect(FileAttachment::query()->where('organization_id', $this->organization->id)->count())->toBe(0)
        ->and(File::query()->where('organization_id', $this->organization->id)->count())->toBe(3);
});

it('loads attachments in bulk on product lists and removes links when a product is deleted', function (): void {
    $products = Product::factory()->count(3)->create(['organization_id' => $this->organization->id]);
    $files = $products->map(fn (): File => imageFile($this->organization->id));

    foreach ($products as $index => $product) {
        $this->putJson("/v1/catalog/products/{$product->id}/files/main", ['file_id' => $files[$index]->id])->assertNoContent();
    }
    $this->postJson("/v1/catalog/products/{$products[0]->id}/files/gallery", [
        'file_ids' => [imageFile($this->organization->id)->id],
    ])->assertNoContent();

    $attachmentQueries = [];
    DB::listen(function ($query) use (&$attachmentQueries): void {
        if (str_contains($query->sql, 'library_file_attachments') && str_contains($query->sql, 'select')) {
            $attachmentQueries[] = $query->bindings;
        }
    });
    $response = $this->getJson('/v1/catalog/products?include=files.main')->assertOk();
    expect(collect($response->json('data'))->pluck('files.main.0.file.id')->all())->toEqualCanonicalizing($files->pluck('id')->all())
        ->and($attachmentQueries)->toHaveCount(1)
        ->and($attachmentQueries[0])->toContain('main')->not->toContain('gallery');
    expect(collect($response->json('data'))->every(fn (array $row): bool => !array_key_exists('gallery', $row['files'])))->toBeTrue();

    $this->deleteJson("/v1/catalog/products/{$products[0]->id}")->assertNoContent();
    expect(FileAttachment::query()->where('organization_id', $this->organization->id)->count())->toBe(2)
        ->and(File::query()->where('organization_id', $this->organization->id)->count())->toBe(4);
});
