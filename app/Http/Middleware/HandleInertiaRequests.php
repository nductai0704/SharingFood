<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
                'notifications' => $request->user() ? $request->user()->notifications()->latest()->take(10)->get() : [],
                'unreadNotificationsCount' => $request->user() ? $request->user()->unreadNotifications()->count() : 0,
                'receivedClaims' => $request->user() ? \App\Models\FoodClaim::whereHas('foodPost', function ($query) use ($request) {
                        $query->where('user_id', $request->user()->id);
                    })
                    ->with(['user', 'foodPost'])
                    ->latest()
                    ->get() : [],
                'myClaims' => $request->user() ? \App\Models\FoodClaim::where('user_id', $request->user()->id)
                    ->with(['foodPost.user'])
                    ->latest()
                    ->get() : [],
                'pendingDonationsCount' => $request->user() && $request->user()->role === 'charity'
                    ? \App\Models\CampaignDonation::where('status', 'pending')
                        ->whereHas('campaign', function($q) use ($request) {
                            $q->where('user_id', $request->user()->id);
                        })->count()
                    : 0,
            ],
        ];
    }
}
