<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AuditBookingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'action' => 'required|in:approve,reject',
            'reason' => 'required_if:action,reject|string|max:255',
            'reason_type' => 'nullable|in:device_unavailable,insufficient_stock,invalid_purpose,time_conflict,other',
        ];
    }
}
