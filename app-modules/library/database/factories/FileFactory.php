<?php

declare(strict_types=1);

namespace Lahatre\Library\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Lahatre\Library\Models\File;
use Lahatre\Shared\Database\Factories\Concerns\ResolvesOrganizationId;

/** @extends Factory<File> */
class FileFactory extends Factory
{
    use ResolvesOrganizationId;

    public function definition(): array
    {
        $organizationId = $this->resolveOrganizationId();
        $id = Str::uuid7()->toString();
        $name = $this->faker->word().'.txt';

        return [
            'id'              => $id,
            'organization_id' => $organizationId,
            'folder_id'       => null,
            'name'            => $name,
            'original_name'   => $name,
            'mime_type'       => 'text/plain',
            'extension'       => 'txt',
            'size'            => 12,
            'storage_disk'    => config('library.disk', 'local'),
            'storage_key'     => sprintf('organizations/%s/%s/%s.txt', $organizationId, now()->format('Y/m'), $id),
            'checksum'        => hash('sha256', 'factory-file'),
            'uploaded_by'     => Str::uuid7()->toString(),
        ];
    }
}
