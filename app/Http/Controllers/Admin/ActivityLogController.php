<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    /**
     * A few route names read better with a custom label than the auto-humanized fallback
     * below. Nothing here needs to be kept in sync with routes/web.php by hand — unmapped
     * route names just get Str::headline()'d automatically, so adding a new route never
     * silently produces a raw slug in the log.
     */
    private const ACTION_LABELS = [
        'auth.login' => 'Signed in',
        'auth.logout' => 'Signed out',
    ];

    public function index(): View
    {
        $filters = session('activity_log_filters', []);

        $query = ActivityLog::with('user')->latest('created_at');

        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (! empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (! empty($filters['ip'])) {
            $query->where('ip_address', $filters['ip']);
        }

        $logs = $query->paginate(50)->withQueryString();

        $users = User::orderBy('name')->get(['id', 'name']);
        $actions = ActivityLog::query()->distinct()->orderBy('action')->pluck('action');

        return view('admin.activity-logs.index', [
            'logs' => $logs,
            'users' => $users,
            'actions' => $actions,
            'labels' => self::ACTION_LABELS,
            'filters' => $filters,
        ]);
    }

    /**
     * Filters live in the session, not the URL — same convention as budget-tracker's
     * BudgetHeadController::updateFilters().
     */
    public function updateFilters(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'action' => ['sometimes', 'nullable', 'string'],
            'ip' => ['sometimes', 'nullable', 'string', 'max:45'],
        ]);

        session(['activity_log_filters' => $validated]);

        return redirect()->route('admin.activity.index');
    }

    public static function labelFor(string $action): string
    {
        return self::ACTION_LABELS[$action] ?? Str::headline(str_replace('.', ' ', $action));
    }
}
