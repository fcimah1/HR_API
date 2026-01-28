<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

class UpdateSocialLinksRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'fb_profile' => 'nullable|url|max:255',
            'twitter_profile' => 'nullable|url|max:255',
            'gplus_profile' => 'nullable|url|max:255',
            'linkedin_profile' => 'nullable|url|max:255'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'fb_profile.url' => 'رابط فيسبوك يجب أن يكون رابط صحيح',
            'fb_profile.max' => 'رابط فيسبوك يجب أن يكون أقل من 255 حرف',
            'twitter_profile.url' => 'رابط تويتر يجب أن يكون رابط صحيح',
            'twitter_profile.max' => 'رابط تويتر يجب أن يكون أقل من 255 حرف',
            'gplus_profile.url' => 'رابط جوجل بلس يجب أن يكون رابط صحيح',
            'gplus_profile.max' => 'رابط جوجل بلس يجب أن يكون أقل من 255 حرف',
            'linkedin_profile.url' => 'رابط لينكد إن يجب أن يكون رابط صحيح',
            'linkedin_profile.max' => 'رابط لينكد إن يجب أن يكون أقل من 255 حرف'
        ];
    }

        public function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        Log::error('validation errors',[
            'success' => false,
            'status_code' => 422,
            'url' => url()->current(),
            'message' => 'Validation errors',
            'data' => $validator->errors()
        ]);
        throw new \Illuminate\Http\Exceptions\HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation errors',
            'data' => $validator->errors()
        ], 422));
    }
}