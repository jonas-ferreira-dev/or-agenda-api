<?php

namespace Tests\Feature;

use App\Mail\NewPublicAppointmentMail;
use App\Models\ProfessionalAvailability;
use App\Models\ProfessionalProfile;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PublicBookingNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-05-24 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_professional_receives_email_when_public_appointment_is_created(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'profissional@email.com',
        ]);

        ProfessionalProfile::factory()->create([
            'user_id' => $user->id,
            'slug' => 'joao-barber',
            'is_public' => true,
            'booking_enabled' => true,
        ]);

        $service = Service::factory()->create([
            'user_id' => $user->id,
            'active' => true,
            'duration_minutes' => 60,
            'name' => 'Corte masculino',
        ]);

        ProfessionalAvailability::factory()->create([
            'user_id' => $user->id,
            'weekday' => 1,
            'start_time' => '10:00',
            'end_time' => '12:00',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/public/professionals/joao-barber/appointments', [
            'name' => 'Cliente Teste',
            'phone' => '21999999999',
            'email' => 'cliente@email.com',
            'service_id' => $service->id,
            'appointment_date' => '2026-05-25',
            'start_time' => '10:00',
            'notes' => 'Primeiro atendimento',
        ]);

        $response->assertCreated();

        Mail::assertSent(NewPublicAppointmentMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email)
                && $mail->appointment->client->name === 'Cliente Teste'
                && $mail->appointment->client->phone === '21999999999'
                && $mail->appointment->service->name === 'Corte masculino'
                && $mail->appointment->start_time === '10:00'
                && $mail->appointment->end_time === '11:00';
        });
    }

    public function test_email_is_not_sent_when_public_appointment_fails(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'profissional@email.com',
        ]);

        ProfessionalProfile::factory()->create([
            'user_id' => $user->id,
            'slug' => 'joao-barber',
            'is_public' => true,
            'booking_enabled' => true,
        ]);

        $service = Service::factory()->create([
            'user_id' => $user->id,
            'active' => true,
            'duration_minutes' => 60,
            'name' => 'Corte masculino',
        ]);

        ProfessionalAvailability::factory()->create([
            'user_id' => $user->id,
            'weekday' => 1,
            'start_time' => '10:00',
            'end_time' => '12:00',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/public/professionals/joao-barber/appointments', [
            'name' => 'Cliente Teste',
            'phone' => '21999999999',
            'email' => 'cliente@email.com',
            'service_id' => $service->id,
            'appointment_date' => '2026-05-25',
            'start_time' => '13:00',
            'notes' => 'Fora do horário',
        ]);

        $response->assertUnprocessable();

        Mail::assertNotSent(NewPublicAppointmentMail::class);
    }
}