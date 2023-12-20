<?php

namespace App\Http\Requests;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class PrintSettingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules()
    {
        return [
            'name' => 'required|string',
            'connection' => 'required|string',
            'ip' => [
                'required','string',
                // Rule::unique('print_settings')->where('location_id',$this->input('location_id'))
            ],
            'print_type' => 'required|string|in:A4,receipt',
            'status' => 'required|boolean',
            'location_id' => 'required|integer|exists:business_locations,id',
        ];
    }
}
