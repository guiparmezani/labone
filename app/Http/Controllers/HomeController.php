<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Enums\SubtaskKind;
use App\Models\Project;
use App\Models\Subtask;
use App\Models\TimeLog;
use App\Models\User;
use App\Services\TimeClock;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(Request $request, TimeClock $clock): View
    {
        $user = $request->user();

        if ($user->isOperator()) {
            $openLogs = $clock->openLogs($user);
            $openLogs->load('subtask.project');

            return view('home.operator', [
                'projects' => Project::query()
                    ->where('status', ProjectStatus::Open)
                    ->orderBy('name')
                    ->get(['id', 'name']),
                'openLogs' => $openLogs,
            ]);
        }

        $openLogs = TimeLog::query()
            ->whereNull('ended_at')
            ->with(['user', 'subtask.project'])
            ->orderBy('started_at')
            ->get();

        $data = [
            'projects' => Project::query()
                ->where('status', ProjectStatus::Open)
                ->withTotals()
                ->orderBy('name')
                ->get(),
            'openLogs' => $openLogs,
            'myOpenLogs' => $openLogs->where('user_id', $user->id)->values(),
            'people' => User::query()->where('active', true)->orderBy('name')->get(['id', 'name']),
            'clockSubtasks' => Subtask::query()
                ->where('kind', SubtaskKind::Internal)
                ->whereHas('project', fn ($query) => $query->where('status', ProjectStatus::Open))
                ->with('project')
                ->orderBy('name')
                ->get(),
        ];

        return view('home.manager', $data);
    }
}
