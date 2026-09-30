<?php

namespace App\Http\Middleware;

use App\Models\FundSetting;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'fundRoles' => fn (): array => $this->fundRoles($request),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /** @return array{treasurer: bool, administrator: bool} */
    private function fundRoles(Request $request): array
    {
        $user = $request->user();
        if ($user === null) {
            return ['treasurer' => false, 'administrator' => false];
        }

        $fund = FundSetting::query()->find(1);

        return [
            'treasurer' => $fund?->isTreasurer($user) ?? false,
            'administrator' => $fund?->isAdministrator($user) ?? false,
        ];
    }
}
