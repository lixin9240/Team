<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AuditReturnRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'action' => 'required|in:approve,reject',
            'reason' => 'required_if:action,reject|string|max:255',
        ];
    }
}
