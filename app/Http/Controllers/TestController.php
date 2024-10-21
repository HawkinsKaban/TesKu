<?php

namespace App\Http\Controllers;

use App\Models\Test;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TestController extends Controller
{
    public function index()
    {
        $tests = Test::with('creator')->paginate(10);
        return view('admin.tests.index', compact('tests'));
    }

    public function create()
    {
        return view('admin.tests.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'start_time' => 'required|date|after:now',
            'end_time' => 'required|date|after:start_time',
            'is_random' => 'boolean',
            'show_result' => 'boolean',
            'time_limit' => 'nullable|integer|min:1',
            'passing_score' => 'nullable|integer|min:0|max:100',
        ]);

        $validated['created_by'] = auth()->id();

        $test = Test::create($validated);

        return redirect()->route('admin.tests.show', $test)->with('success', 'Test created successfully');
    }

    public function show(Test $test)
    {
        $test->load('questions', 'creator');
        $totalQuestions = $test->questions->count();
        $totalPoints = $test->questions->sum('points');
        return view('admin.tests.show', compact('test', 'totalQuestions', 'totalPoints'));
    }

    public function edit(Test $test)
    {
        if (Gate::denies('update-test', $test)) {
            abort(403);
        }
        return view('admin.tests.edit', compact('test'));
    }

    public function update(Request $request, Test $test)
    {
        if (Gate::denies('update-test', $test)) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'is_random' => 'boolean',
            'show_result' => 'boolean',
            'time_limit' => 'nullable|integer|min:1',
            'passing_score' => 'nullable|integer|min:0|max:100',
        ]);

        $test->update($validated);

        return redirect()->route('admin.tests.show', $test)->with('success', 'Test updated successfully');
    }

    public function destroy(Test $test)
    {
        if (Gate::denies('delete-test', $test)) {
            abort(403);
        }

        DB::transaction(function () use ($test) {
            $test->questions()->delete();
            $test->responses()->delete();
            $test->delete();
        });

        return redirect()->route('admin.tests.index')->with('success', 'Test deleted successfully');
    }

    public function duplicate(Test $test)
    {
        $newTest = $test->replicate();
        $newTest->title = "Copy of " . $newTest->title;
        $newTest->start_time = now()->addDay();
        $newTest->end_time = now()->addDays(2);
        $newTest->created_by = auth()->id();
        $newTest->save();

        foreach ($test->questions as $question) {
            $newQuestion = $question->replicate();
            $newQuestion->test_id = $newTest->id;
            $newQuestion->save();

            foreach ($question->options as $option) {
                $newOption = $option->replicate();
                $newOption->question_id = $newQuestion->id;
                $newOption->save();
            }
        }

        return redirect()->route('admin.tests.show', $newTest)->with('success', 'Test duplicated successfully');
    }

    public function toggleRandomization(Test $test)
    {
        $test->update(['is_random' => !$test->is_random]);
        return back()->with('success', 'Test randomization setting updated');
    }

    public function results(Test $test)
    {
        $results = $test->responses()
            ->with('student')
            ->select('student_id', DB::raw('SUM(score) as total_score'))
            ->groupBy('student_id')
            ->orderByDesc('total_score')
            ->paginate(15);

        $maxScore = $test->questions->sum('points');

        return view('admin.tests.results', compact('test', 'results', 'maxScore'));
    }

    public function exportResults(Test $test)
    {
        // Implementation for exporting results to CSV or Excel
        // You might want to use a package like maatwebsite/excel for this
    }

    public function preview(Test $test)
    {
        $questions = $test->is_random ? $test->questions->shuffle() : $test->questions;
        return view('admin.tests.preview', compact('test', 'questions'));
    }
}