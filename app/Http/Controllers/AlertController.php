<?php

namespace App\Http\Controllers;

use App\Models\Subtask;
use App\Services\AlertInbox;
use App\Services\ShiftWatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AlertController extends Controller
{
    public function index(Request $request, ShiftWatch $watch, AlertInbox $inbox): View
    {
        $anteriores = max(0, min(365, (int) $request->session()->get('alertas_anteriores', 0)));
        ['gaps' => $gaps, 'podeCarregar' => $podeCarregar] = $watch->page($anteriores);
        $inbox->markRead($request->user());

        return view('alerts.index', [
            'gaps' => $gaps,
            'reached' => Subtask::query()->reached()->with('project')->orderBy('name')->get(),
            'anteriores' => $anteriores,
            'podeCarregar' => $podeCarregar,
        ]);
    }

    public function carregar(Request $request): RedirectResponse
    {
        $anteriores = max(0, min(365, (int) $request->input('anteriores', 0)));
        $request->session()->flash('alertas_anteriores', $anteriores);

        return redirect()->route('alertas.index');
    }
}
