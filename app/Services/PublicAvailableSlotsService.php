<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\ProfessionalAvailability;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;

class PublicAvailableSlotsService
{
    public function getAvailableSlots(User $professional, Service $service, string $date): array
    {
        $selectedDate = Carbon::parse($date);
        $weekday = $selectedDate->dayOfWeek;

        $duration = (int) $service->duration_minutes;

        if ($duration <= 0) {
            return [];
        }

        $availabilityBlocks = ProfessionalAvailability::query()
            ->where('user_id', $professional->id)
            ->where('weekday', $weekday)
            ->where('is_active', true)
            ->orderBy('start_time')
            ->get();

        if ($availabilityBlocks->isEmpty()) {
            return [];
        }

        $appointments = Appointment::query()
            ->where('user_id', $professional->id)
            ->where('appointment_date', $date)
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->get(['start_time', 'end_time']);

        $slots = [];

        foreach ($availabilityBlocks as $block) {
            $blockStart = Carbon::parse($date . ' ' . $block->start_time);
            $blockEnd = Carbon::parse($date . ' ' . $block->end_time);

            $currentSlotStart = $blockStart->copy();

            while ($currentSlotStart->copy()->addMinutes($duration)->lte($blockEnd)) {
                $currentSlotEnd = $currentSlotStart->copy()->addMinutes($duration);

                if ($this->isPastSlot($selectedDate, $currentSlotStart)) {
                    $currentSlotStart->addMinutes($duration);
                    continue;
                }

                if (! $this->slotOverlapsAppointments($currentSlotStart, $currentSlotEnd, $appointments, $date)) {
                    $slots[] = [
                        'start_time' => $currentSlotStart->format('H:i'),
                        'end_time' => $currentSlotEnd->format('H:i'),
                    ];
                }

                $currentSlotStart->addMinutes($duration);
            }
        }

        return $slots;
    }

    private function isPastSlot(Carbon $selectedDate, Carbon $slotStart): bool
    {
        if (! $selectedDate->isToday()) {
            return false;
        }

        return $slotStart->lte(now());
    }

    private function slotOverlapsAppointments(
        Carbon $slotStart,
        Carbon $slotEnd,
        $appointments,
        string $date
    ): bool {
        return $appointments->contains(function ($appointment) use ($slotStart, $slotEnd, $date) {
            $appointmentStart = Carbon::parse($date . ' ' . $appointment->start_time);
            $appointmentEnd = Carbon::parse($date . ' ' . $appointment->end_time);

            return $slotStart->lt($appointmentEnd) && $slotEnd->gt($appointmentStart);
        });
    }
}