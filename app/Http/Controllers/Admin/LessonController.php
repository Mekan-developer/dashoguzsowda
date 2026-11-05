<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\LessonService;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LessonController extends Controller
{
    public function __construct(private readonly LessonService $lessons) {}

    public function index(): Response
    {
        return Inertia::render('Lessons/Index', [
            'installed' => $this->lessons->isInstalled(),
        ]);
    }

    public function file(string $path): BinaryFileResponse
    {
        $file = $this->lessons->resolve($path);

        abort_if($file === null, 404);

        return response()->file($file['path'], [
            'Content-Type'  => $file['mime'],
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
