<?php

namespace App\Http\Requests\Admin;

use App\Domain\Catalog\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UploadProductImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageCatalog() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $routeProduct = $this->route('product');
        $productId = $routeProduct instanceof Product ? $routeProduct->id : 0;

        return [
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'alt_text' => ['required', 'string', 'max:200'],
            'variant_id' => [
                'nullable',
                'integer',
                Rule::exists('product_variants', 'id')->where('product_id', $productId),
            ],
            'is_primary' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $file = $this->file('image');
                if ($file instanceof UploadedFile && ! $file->isValid()) {
                    $maxMb = number_format(max(1, UploadedFile::getMaxFilesize()) / 1048576, 1);
                    $message = in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                        ? "The image exceeds the active PHP server upload limit ({$maxMb} MB max). Resize/compress it or restart PHP with upload_max_filesize=8M."
                        : $file->getErrorMessage();

                    $validator->errors()->forget('image');
                    $validator->errors()->add('image', $message);
                }
            },
        ];
    }
}
