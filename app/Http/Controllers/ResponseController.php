<?php

namespace App\Http\Controllers;

use App\Models\Response;
use App\Models\Test;
use App\Models\Student;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Exports\TestResponsesExport;
use Maatwebsite\Excel\Facades\Excel;

class ResponseController extends Controller
{
    public function storeStudentResponses(Request $request, Test $test)
    {
        $student_id = session('student_id');
        if (!$student_id) {
            return redirect()->route('student.biodata')->with('error', 'Please fill in your biodata first');
        }

        $validated = $request->validate([
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|exists:questions,id',
            'answers.*.answer' => 'required|string',
        ]);

        DB::transaction(function () use ($validated, $student_id, $test) {
            foreach ($validated['answers'] as $answer) {
                $question = Question::find($answer['question_id']);
                $score = null;

                if ($question->question_type === 'multiple_choice') {
                    $correctOption = $question->options()->where('is_correct', true)->first();
                    $score = ($answer['answer'] == $correctOption->id) ? $question->points : 0;
                }

                Response::create([
                    'student_id' => $student_id,
                    'test_id' => $test->id,
                    'question_id' => $answer['question_id'],
                    'answer' => $answer['answer'],
                    'score' => $score,
                ]);
            }
        });

        return redirect()->route('student.test.result', $test)->with('success', 'Test submitted successfully');
    }

    public function showTestResult(Test $test)
    {
        $student_id = session('student_id');
        $responses = Response::where('test_id', $test->id)
                             ->where('student_id', $student_id)
                             ->with(['question', 'question.options'])
                             ->get();
        $totalScore = $responses->sum('score');
        $maxScore = $test->questions->sum('points');
        
        return view('student.test_result', compact('test', 'responses', 'totalScore', 'maxScore'));
    }

    public function index(Test $test)
    {
        $responses = Response::where('test_id', $test->id)
                             ->with('student')
                             ->select('student_id', DB::raw('SUM(score) as total_score'))
                             ->groupBy('student_id')
                             ->paginate(15);
        return view('admin.responses.index', compact('test', 'responses'));
    }

    public function show(Test $test, Student $student)
    {
        $responses = Response::where('test_id', $test->id)
                             ->where('student_id', $student->id)
                             ->with(['question', 'question.options'])
                             ->get();
        $totalScore = $responses->sum('score');
        $maxScore = $test->questions->sum('points');
        
        return view('admin.responses.show', compact('test', 'student', 'responses', 'totalScore', 'maxScore'));
    }

    public function grade(Request $request, Response $response)
    {
        $validated = $request->validate([
            'score' => 'required|numeric|min:0|max:' . $response->question->points,
            'feedback' => 'nullable|string|max:1000',
        ]);

        $response->update($validated);

        return back()->with('success', 'Response graded successfully');
    }

    public function bulkGrade(Request $request, Test $test)
    {
        $validated = $request->validate([
            'grades' => 'required|array',
            'grades.*.response_id' => 'required|exists:responses,id',
            'grades.*.score' => 'required|numeric|min:0',
            'grades.*.feedback' => 'nullable|string|max:1000',
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['grades'] as $grade) {
                Response::where('id', $grade['response_id'])->update([
                    'score' => $grade['score'],
                    'feedback' => $grade['feedback'] ?? null,
                ]);
            }
        });

        return back()->with('success', 'Responses graded successfully');
    }

    public function export(Test $test)
    {
        return Excel::download(new TestResponsesExport($test), 'test_responses.xlsx');
    }

    public function analytics(Test $test)
    {
        $questionAnalytics = Question::where('test_id', $test->id)
            ->withCount(['responses as correct_count' => function ($query) {
                $query->whereColumn('score', '=', 'questions.points');
            }])
            ->withCount('responses')
            ->get()
            ->map(function ($question) {
                $question->difficulty = $question->responses_count > 0
                    ? 1 - ($question->correct_count / $question->responses_count)
                    : null;
                return $question;
            });

        $scoreDistribution = Response::where('test_id', $test->id)
            ->select(DB::raw('FLOOR(score / 10) * 10 as score_range'), DB::raw('COUNT(*) as count'))
            ->groupBy('score_range')
            ->orderBy('score_range')
            ->get();

        return view('admin.responses.analytics', compact('test', 'questionAnalytics', 'scoreDistribution'));
    }
}