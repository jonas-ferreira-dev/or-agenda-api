<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProfessionalAvailability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfessionalAvailabilityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $availabilities = ProfessionalAvailability::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('weekday')
            ->orderBy('start_time')
            ->get();

        return response()->json([
            'message' => 'Horários de disponibilidade listados com sucesso.',
            'data' => $availabilities,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateAvailability($request);

        $this->ensureThereIsNoOverlap(
            userId: $request->user()->id,
            weekday: (int) $validated['weekday'],
            startTime: $validated['start_time'],
            endTime: $validated['end_time'],
        );

        $availability = ProfessionalAvailability::create([
            'user_id' => $request->user()->id,
            'weekday' => $validated['weekday'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'Horário de disponibilidade criado com sucesso.',
            'data' => $availability,
        ], 201);
    }

    public function update(
        Request $request,
        ProfessionalAvailability $professionalAvailability
    ): JsonResponse {
        $this->authorizeAvailability($request, $professionalAvailability);

        $validated = $this->validateAvailability($request);

        $this->ensureThereIsNoOverlap(
            userId: $request->user()->id,
            weekday: (int) $validated['weekday'],
            startTime: $validated['start_time'],
            endTime: $validated['end_time'],
            ignoreId: $professionalAvailability->id,
        );

        $professionalAvailability->update([
            'weekday' => $validated['weekday'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'is_active' => $validated['is_active'] ?? $professionalAvailability->is_active,
        ]);

        return response()->json([
            'message' => 'Horário de disponibilidade atualizado com sucesso.',
            'data' => $professionalAvailability->fresh(),
        ]);
    }

    public function destroy(
        Request $request,
        ProfessionalAvailability $professionalAvailability
    ): JsonResponse {
        $this->authorizeAvailability($request, $professionalAvailability);

        $professionalAvailability->delete();

        return response()->json([
            'message' => 'Horário de disponibilidade removido com sucesso.',
        ]);
    }

    private function validateAvailability(Request $request): array
    {
        return $request->validate([
            'weekday' => [
                'required',
                'integer',
                Rule::in([0, 1, 2, 3, 4, 5, 6]),
            ],
            'start_time' => [
                'required',
                'date_format:H:i',
            ],
            'end_time' => [
                'required',
                'date_format:H:i',
                'after:start_time',
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ]);
    }

    private function ensureThereIsNoOverlap(
        int $userId,
        int $weekday,
        string $startTime,
        string $endTime,
        ?int $ignoreId = null
    ): void {
        $hasOverlap = ProfessionalAvailability::query()
            ->where('user_id', $userId)
            ->where('weekday', $weekday)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->where(function ($query) use ($startTime, $endTime) {
                $query
                    ->where('start_time', '<', $endTime)
                    ->where('end_time', '>', $startTime);
            })
            ->exists();

        if ($hasOverlap) {
            abort(response()->json([
                'message' => 'Já existe um horário de disponibilidade nesse intervalo.',
                'errors' => [
                    'start_time' => [
                        'Já existe um horário de disponibilidade nesse intervalo.',
                    ],
                ],
            ], 422));
        }
    }

    private function authorizeAvailability(
        Request $request,
        ProfessionalAvailability $professionalAvailability
    ): void {
        if ($professionalAvailability->user_id !== $request->user()->id) {
            abort(403, 'Você não tem permissão para alterar este horário.');
        }
    }
}