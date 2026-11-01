<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Honest placeholder for sidebar menus whose page is not built yet.
 * Items come from config/navigation.php, never from the request.
 */
class PendingPageController extends Controller
{
    public function __invoke(Request $request, string $menu): Response
    {
        foreach (config('navigation.sections') as $section) {
            $items = $section['type'] === 'group' ? $section['items'] : [$section];

            foreach ($items as $item) {
                if (($item['id'] ?? null) === $menu && ($item['status'] ?? null) === 'pending') {
                    return Inertia::render('pending', [
                        'menu' => ['id' => $item['id'], 'label' => $item['label']],
                        'group' => $section['type'] === 'group' ? $section['label'] : null,
                        'spec' => $section['spec'] ?? null,
                    ]);
                }
            }
        }

        abort(404);
    }
}
