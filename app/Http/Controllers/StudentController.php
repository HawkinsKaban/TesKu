<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\Test;
use App\Models\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    public function showBiodataForm()
    {
        if (session('student_id')) {
            return redirect()->route('student.tests');
        }
        return view('student.biodata');
    }

    public function storeBiodata(Request $request)
    {
        $validated = $request->validate([
            'nosis' => 'required|unique:students,nosis',
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:students,email',
            'pokjar' => 'required|string|max:255',
            'batch' => 'required|integer|min:1900|max:' . (date('Y') + 1),
        ]);

        $student = Student::create($validated);

        session(['student_id' => $student->id]);

        return redirect()->route('student.tests')->with('success', 'Biodata saved successfully');
    }

    public function showAvailableTests()
    {
        $studentId = session('student_id');
        if (!$studentId) {
            return redirect()->route('student.biodata')->with('error', 'Please fill in your biodata first');
        }

        $tests = Test::where('start_time', '<=', now())
                     ->where('end_time', '>=', now())
                     ->whereDoesntHave('responses', function ($query) use ($studentId) {
                         $query->where('student_id', $studentId);
                     })
                     ->get();

        return view('student.tests', compact('tests'));
    }

    public function startTest(Test $test)
    {
        $studentId = session('student_id');
        if (!$studentId) {
            return redirect()->route('student.biodata')->with('error', 'Please fill in your biodata first');
        }

        if (now() < $test->start_time || now() > $test->end_time) {
            return redirect()->route('student.tests')->with('error', 'This test is not currently available');
        }

        if ($test->responses()->where('student_id', $studentId)->exists()) {
            return redirect()->route('student.tests')->with('error', 'You have already taken this test');
        }

        $questions = $test->is_random ? $test->questions->shuffle() : $test->questions;

        return view('student.take_test', compact('test', 'questions'));
    }

    public function submitTest(Request $request, Test $test)
    {
        $studentId = session('student_id');
        if (!$studentId) {
            return redirect()->route('student.biodata')->with('error', 'Please fill in your biodata first');
        }

        if (now() > $test->end_time) {
            return redirect()->route('student.tests')->with('error', 'The test has already ended');
        }

        $validated = $request->validate([
            'answers' => 'required|array',
            'answers.*' => [
                'required',
                Rule::exists('questions', 'id')->where(function ($query) use ($test) {
                    return $query->where('test_id', $test->id);
                }),
            ],
        ]);

        DB::transaction(function () use ($validated, $test, $studentId) {
            foreach ($validated['answers'] as $questionId => $answer) {
                $question = $test->questions()->findOrFail($questionId);
                $score = null;

                if ($question->type === 'multiple_choice') {
                    $correctOption = $question->options()->where('is_correct', true)->first();
                    $score = ($answer == $correctOption->id) ? $question->points : 0;
                }

                Response::create([
                    'student_id' => $studentId,
                    'test_id' => $test->id,
                    'question_id' => $questionId,
                    'answer' => $answer,
                    'score' => $score,
                ]);
            }
        });

        return redirect()->route('student.test.result', $test)->with('success', 'Test submitted successfully');
    }

    public function showTestResult(Test $test)
    {
        $studentId = session('student_id');
        if (!$studentId) {
            return redirect()->route('student.biodata')->with('error', 'Please fill in your biodata first');
        }

        $responses = Response::where('test_id', $test->id)
                             ->where('student_id', $studentId)
                             ->with(['question', 'question.options'])
                             ->get();

        if ($responses->isEmpty()) {
            return redirect()->route('student.tests')->with('error', 'You have not taken this test');
        }

        $totalScore = $responses->sum('score');
        $maxScore = $test->questions->sum('points');

        return view('student.test_result', compact('test', 'responses', 'totalScore', 'maxScore'));
    }

    public function showTestHistory()
    {
        $studentId = session('student_id');
        if (!$studentId) {
            return redirect()->route('student.biodata')->with('error', 'Please fill in your biodata first');
        }

        $completedTests = Test::whereHas('responses', function ($query) use ($studentId) {
            $query->where('student_id', $studentId);
        })->with(['responses' => function ($query) use ($studentId) {
            $query->where('student_id', $studentId);
        }])->get();

        return view('student.test_history', compact('completedTests'));
    }

    public function logout()
    {
        session()->forget('student_id');
        return redirect('/')->with('success', 'You have been logged out');
    }
}