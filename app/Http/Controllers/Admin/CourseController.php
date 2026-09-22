<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCourseRequest;
use App\Http\Requests\UpdateCourseRequest;
use App\Models\Course;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CourseController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function index(): Response
    {
        abort_unless(request()->user()->role === 'admin', 403);

        return Inertia::render('Admin/Courses', [
            'courses' => Course::query()
                ->withCount(['knowledges', 'conversations'])
                ->orderByRaw("case when status = 'active' then 0 else 1 end")
                ->orderBy('semester')
                ->orderBy('code')
                ->get(),
        ]);
    }

    public function store(StoreCourseRequest $request): RedirectResponse
    {
        $course = Course::query()->create($request->validated());
        $this->audit->record($request->user(), 'course.created', $course, request: $request);

        return back()->with('status', 'Mata kuliah berhasil ditambahkan.');
    }

    public function update(UpdateCourseRequest $request, Course $course): RedirectResponse
    {
        $course->update($request->validated());
        $this->audit->record($request->user(), 'course.updated', $course, request: $request);

        return back()->with('status', 'Mata kuliah berhasil diperbarui.');
    }

    public function destroy(Request $request, Course $course): RedirectResponse
    {
        abort_unless($request->user()->role === 'admin', 403);

        $this->audit->record($request->user(), 'course.deleted', $course, request: $request);
        $course->delete();

        return redirect()->route('admin.courses.index')->with('status', 'Mata kuliah berhasil dihapus.');
    }
}
