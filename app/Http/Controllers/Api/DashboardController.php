<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class DashboardController extends Controller
{
    public function stats(Request $request)
    {
        try {
            $user = $request->user();

            $openAppointments = DB::table('appointments')
                ->where('user_id', $user->id)
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->count();

            $clientsCount = DB::table('clients')
                ->where('user_id', $user->id)
                ->count();

            $completedToday = DB::table('appointments')
                ->where('user_id', $user->id)
                ->where('status', 'completed')
                ->whereDate('appointment_date', now()->toDateString())
                ->count();

            return response()->json([
                'open_appointments' => $openAppointments,
                'clients_count' => $clientsCount,
                'completed_today' => $completedToday,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ], 500);
        }
    }
}