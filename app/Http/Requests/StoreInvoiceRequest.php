<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sale_id' => 'required|exists:sales,id',
            'branch_id' => 'required|exists:branches,id',
            'folio' => 'required|string|max:255|unique:invoices,folio',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|max:10',
            'installment_number' => 'required|integer|min:1',
            'total_installments' => 'required|integer|min:1|gte:installment_number',
            'issue_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:issue_date',
            'payment_option' => 'nullable|string|max:255',
            'payment_method' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ];
    }
}
