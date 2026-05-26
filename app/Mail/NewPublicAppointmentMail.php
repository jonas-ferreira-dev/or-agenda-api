<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewPublicAppointmentMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Appointment $appointment
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject('Novo agendamento recebido - OR Agenda')
            ->view('emails.new-public-appointment');
    }
}