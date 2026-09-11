<?php

declare(strict_types=1);

namespace Lahatre\Library\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class FileUploadRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $maximumFiles = (int) config('library.upload.max_files');
        $maximumFileSizeInKilobytes = (int) ceil(((int) config('library.upload.max_file_size')) / 1024);
        $allowedMimeTypes = implode(',', (array) config('library.upload.allowed_mimes', []));

        return [
            'folder_id' => [
                'nullable',
                'uuid',
                Rule::exists('library_folders', 'id')
                    ->where('organization_id', currentOrganizationId())
                    ->whereNull('deleted_at'),
            ],
            'files'   => ['required', 'array', 'min:1', "max:{$maximumFiles}"],
            'files.*' => ['required', 'file', "max:{$maximumFileSizeInKilobytes}", "mimetypes:{$allowedMimeTypes}"],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $files = $this->file('files', []);

                if (!is_array($files)) {
                    return;
                }

                $batchSize = array_sum(array_map(
                    static fn (mixed $file): int => $file instanceof UploadedFile
                        ? (int) ($file->getSize() ?: 0)
                        : 0,
                    $files,
                ));
                $maximum = (int) config('library.upload.max_batch_size');

                if ($batchSize > $maximum) {
                    $validator->errors()->add(
                        'files',
                        __('library::validation.files.batch_size', ['maximum' => $maximum]),
                    );
                }
            },
        ];
    }
}
