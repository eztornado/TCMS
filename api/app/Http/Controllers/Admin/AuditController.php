<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\Activitylog\Models\Activity;

/** Consulta del log de auditoría con filtros por usuario, evento y fecha. */
class AuditController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $activities = Activity::query()
            ->with('causer')
            ->when($request->user_id, fn ($q, $id) => $q->where('causer_id', $id)->where('causer_type', 'App\Models\User'))
            ->when($request->event, fn ($q, $event) => $q->where('event', $event))
            ->when($request->subject_type, fn ($q, $type) => $q->where('subject_type', $type))
            ->when($request->q, function ($q, $search) {
                $q->where(fn ($w) => $w
                    ->where('description', 'like', "%{$search}%")
                    ->orWhere('subject_id', 'like', "%{$search}%"));
            })
            ->when($request->from, fn ($q, $from) => $q->where('created_at', '>=', $from))
            ->when($request->to, fn ($q, $to) => $q->where('created_at', '<=', $to.' 23:59:59'))
            ->latest()
            ->paginate($request->integer('per_page', 25));

        return ActivityResource::collection($activities);
    }

    public function show(Activity $activity): ActivityResource
    {
        return new ActivityResource($activity->load('causer'));
    }
}
