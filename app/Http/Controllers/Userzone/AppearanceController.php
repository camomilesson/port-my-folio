<?php

namespace App\Http\Controllers\Userzone;

use App\Actions\EditInlinePortfolioField;
use App\Actions\EditPortfolioDraft;
use App\Http\Controllers\Controller;
use App\Http\Requests\Userzone\UpdateAppearanceRequest;
use App\Models\Portfolio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class AppearanceController extends Controller
{
    public function __invoke(UpdateAppearanceRequest $request, Portfolio $portfolio, EditPortfolioDraft $editor): JsonResponse|RedirectResponse
    {
        $data = $request->validated();
        $appearance = $editor->handle($portfolio, function (Portfolio $locked) use ($data): array {
            EditInlinePortfolioField::requireReady($locked);
            $locked->update([$data['field'] => strtolower($data['value'])]);

            return ['preset' => $locked->preset, 'accent_color' => $locked->accent_color];
        });

        return $request->expectsJson()
            ? response()->json(['appearance' => $appearance, 'message' => 'Appearance saved. Publish to update your public portfolio.'])
            : back()->with('status', 'Appearance saved to your private draft.');
    }
}
