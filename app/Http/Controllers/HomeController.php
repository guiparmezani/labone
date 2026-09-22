<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\Subtask;
use App\Models\TimeLog;
use App\Services\TimeClock;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(Request $request, TimeClock $clock): View
    {
        $user = $request->user();

        if ($user->isOperator()) {
            $open = $clock->openLog($user);
            $open?->load('subtask.project');

            return view('home.operator', [
                'projects' => Project::query()
                    ->where('status', ProjectStatus::Open)
                    ->orderBy('name')
                    ->get(['id', 'name']),
                'openLog' => $open,
            ]);
        }

        $data = [
            'projects' => Project::query()
                ->where('status', ProjectStatus::Open)
                ->withTotals()
                ->orderBy('name')
                ->get(),
            'openLogs' => TimeLog::query()
                ->whereNull('ended_at')
                ->with(['user', 'subtask.project'])
                ->orderBy('started_at')
                ->get(),
        ];

        if ($user->isAdmin()) {
            $data['reachedAlerts'] = Subtask::query()
                ->reached()
                ->withLoggedMinutes()
                ->with('project')
                ->get();
        }

        return view('home.manager', $data);
    }
}
