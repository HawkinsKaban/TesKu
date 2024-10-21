<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\Test;
use App\Models\Option;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuestionController extends Controller
{
    public function index(Test $test)
    {
        $questions = $test->questions()->paginate(10);
        return view('admin.questions.index', compact('test', 'questions'));
    }

    public function show(Question $question)
    {
        return view('admin.questions.show', compact('question'));
    }

    public function create(Test $test)
    {
        return view('admin.questions.create', compact('test'));
    }

    public function store(Request $request, Test $test)
    {
        $validated = $request->validate([
            'question_text' => 'required|string',
            'question_type' => 'required|in:multiple_choice,essay',
            'points' => 'required|integer|min:1',
            'options' => 'required_if:question_type,multiple_choice|array|min:2',
            'options.*' => 'required_if:question_type,multiple_choice|string',
            'correct_option' => 'required_if:question_type,multiple_choice|integer|min:0',
        ]);

        DB::transaction(function () use ($validated, $test) {
            $question = $test->questions()->create([
                'question_text' => $validated['question_text'],
                'question_type' => $validated['question_type'],
                'points' => $validated['points'],
            ]);

            if ($validated['question_type'] === 'multiple_choice') {
                foreach ($validated['options'] as $index => $optionText) {
                    $question->options()->create([
                        'option_text' => $optionText,
                        'is_correct' => $index === $validated['correct_option'],
                    ]);
                }
            }
        });

        return redirect()->route('admin.tests.questions.index', $test)
                         ->with('success', 'Question added successfully');
    }

    public function edit(Question $question)
    {
        return view('admin.questions.edit', compact('question'));
    }

    public function update(Request $request, Question $question)
    {
        $validated = $request->validate([
            'question_text' => 'required|string',
            'question_type' => 'required|in:multiple_choice,essay',
            'points' => 'required|integer|min:1',
            'options' => 'required_if:question_type,multiple_choice|array|min:2',
            'options.*' => 'required_if:question_type,multiple_choice|string',
            'correct_option' => 'required_if:question_type,multiple_choice|integer|min:0',
        ]);

        DB::transaction(function () use ($validated, $question) {
            $question->update([
                'question_text' => $validated['question_text'],
                'question_type' => $validated['question_type'],
                'points' => $validated['points'],
            ]);

            if ($validated['question_type'] === 'multiple_choice') {
                $question->options()->delete();
                foreach ($validated['options'] as $index => $optionText) {
                    $question->options()->create([
                        'option_text' => $optionText,
                        'is_correct' => $index === $validated['correct_option'],
                    ]);
                }
            } else {
                $question->options()->delete();
            }
        });

        return redirect()->route('admin.tests.questions.index', $question->test)
                         ->with('success', 'Question updated successfully');
    }

    public function destroy(Question $question)
    {
        $test = $question->test;
        $question->delete();
        return redirect()->route('admin.tests.questions.index', $test)
                         ->with('success', 'Question deleted successfully');
    }

    public function randomizeOptions(Question $question)
    {
        if ($question->question_type !== 'multiple_choice') {
            return redirect()->back()->with('error', 'Only multiple choice questions can have randomized options.');
        }

        $options = $question->options->shuffle();
        foreach ($options as $index => $option) {
            $option->update(['order' => $index]);
        }

        return redirect()->back()->with('success', 'Options randomized successfully');
    }

    public function bulkDelete(Request $request, Test $test)
    {
        $validated = $request->validate([
            'questions' => 'required|array',
            'questions.*' => 'exists:questions,id',
        ]);

        Question::whereIn('id', $validated['questions'])->delete();

        return redirect()->route('admin.tests.questions.index', $test)
                         ->with('success', 'Selected questions deleted successfully');
    }
}