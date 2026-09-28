<?php

namespace Modules\Delivery\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Delivery\Enums\PaymentMethod;
use Modules\Delivery\Enums\MobileMoneyOperator;
use Illuminate\Validation\Rules\Enum;

class RecordPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'payment_method'   => ['required', new Enum(PaymentMethod::class)],
            'operator'         => ['nullable', 'required_if:payment_method,MOBILE_MONEY', new Enum(MobileMoneyOperator::class)],
            'transaction_ref'  => 'nullable|required_if:payment_method,MOBILE_MONEY|string',
            'amount_expected'  => 'required|numeric|min:0',
            'amount_received'  => 'required|numeric|min:0',
            'amount_change'    => 'required|numeric|min:0',
            'paid_at'          => 'required|date',
        ];
    }
}
