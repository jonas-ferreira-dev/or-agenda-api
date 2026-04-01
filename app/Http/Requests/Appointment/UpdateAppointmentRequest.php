<?php

namespace App\Http\Requests\Appointment;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => ['sometimes', 'required', 'integer', 'exists:clients,id'],
            'service_id' => ['sometimes', 'required', 'integer', 'exists:services,id'],
            'appointment_date' => ['sometimes', 'required', 'date'],
            'start_time' => ['sometimes', 'required', 'date_format:H:i'],
            'status' => ['nullable', 'in:scheduled,completed,cancelled'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $startTime = $this->input('start_time');
            $endTime = $this->input('end_time');

            if ($startTime && $endTime && $endTime <= $startTime) {
                $validator->errors()->add('end_time', 'O horário final deve ser maior que o horário inicial.');
            }
        });
    }
}