<?php

namespace EvoDevOps\Base\Http\Controllers\Admin;

use EvoDevOps\Base\Http\Controllers\Controller;
use EvoDevOps\Base\Http\Requests\Admin\GeneratePrdRequest;
use EvoDevOps\Base\Support\PrdGenerator;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * @evo-example prd_studio
 */
class PrdController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('evodevops/admin/prd');
    }

    public function generate(GeneratePrdRequest $request, PrdGenerator $generator): JsonResponse
    {
        try {
            return response()->json([
                'prd' => $generator->generate($request->validated()),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'The PRD generator is unavailable right now. Try again in a moment.',
            ], 502);
        }
    }
}
