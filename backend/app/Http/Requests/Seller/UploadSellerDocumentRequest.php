<?php

namespace App\Http\Requests\Seller;

use App\Domain\Seller\Enums\SellerDocumentType;
use App\Domain\Seller\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UploadSellerDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user instanceof User || $user->canAccessAdminPanel()) {
            return false;
        }

        $profile = $user->sellerProfile;
        if (! $profile instanceof SellerProfile) {
            return false;
        }

        return Gate::forUser($user)->allows('uploadDocument', $profile);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'document_type' => ['required', 'string', Rule::enum(SellerDocumentType::class)],
            'document' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp,pdf'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $file = $this->file('document');
                if ($file instanceof UploadedFile && ! $file->isValid()) {
                    $maxMb = number_format(max(1, UploadedFile::getMaxFilesize()) / 1048576, 1);
                    $message = in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                        ? "The document exceeds the active PHP server upload limit ({$maxMb} MB max). Choose a smaller file or raise upload_max_filesize."
                        : $file->getErrorMessage();

                    $validator->errors()->forget('document');
                    $validator->errors()->add('document', $message);
                }
            },
        ];
    }
}
