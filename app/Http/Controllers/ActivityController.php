<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityController extends Controller
{
    public function __invoke(Request $request): View
    {
        $data = $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])]]);
        $logs = ActivityLog::where('user_id', $request->user()->id)
            ->when($data['from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($data['to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->latest('id')->paginate(30)->withQueryString();

        return view('activity', compact('logs'));
    }
}
