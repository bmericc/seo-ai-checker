<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Check;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckController extends Controller
{
    public function lighthouseRaw(Request $request, Check $check): JsonResponse
    {
        $check->loadMissing('keyword.domain');
        $this->ensureCanAccessCheck($request, $check);

        abort_if($check->lighthouse_raw === null, 404);

        return response()->json($check->lighthouse_raw)
            ->header('Content-Disposition', sprintf('attachment; filename="lighthouse-check-%d.json"', $check->id));
    }
}
