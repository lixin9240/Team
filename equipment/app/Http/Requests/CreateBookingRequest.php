<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateBookingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'device_id' => 'required|exists:devices,id',
            'borrow_start' => 'required|date',
            'borrow_end' => 'required|date|after_or_equal:borrow_start',
            'purpose' => 'nullable|string',
        ];
    }
}
