<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Test;
use App\Models\Student;
use App\Models\Question;
use App\Models\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use App\Mail\TestResultMail;
use App\Exports\TestResultsExport;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\StoreTestRequest;
use App\Http\Requests\UpdateTestRequest;
use App\Http\Requests\StoreQuestionRequest;
use App\Http\Requests\UpdateQuestionRequest;
use Maatwebsite\Excel\Facades\Excel;

class AdminController extends Controller
{
    public function dashboard()
    {
        try {
            $testCount = Test::count();
            $studentCount = Student::count();
            $recentTests = Test::latest()->take(5)->get();
            $userCount = User::count();
            $completedTestsCount = Test::where('deadline', '<', now())->count();
            return view('admin.dashboard', compact('testCount', 'studentCount', 'recentTests', 'userCount', 'completedTestsCount'));
        } catch (\Exception $e) {
            return back()->with('error', 'Error loading dashboard: ' . $e->getMessage());
        }
    }

    // User Management
    public function users()
    {
        try {
            $users = User::paginate(15);
            return view('admin.users.index', compact('users'));
        } catch (\Exception $e) {
            return back()->with('error', 'Error loading users: ' . $e->getMessage());
        }
    }

    public function createUser()
    {
        if (Gate::denies('create-user')) {
            abort(403);
        }
        return view('admin.users.create');
    }

    public function storeUser(StoreUserRequest $request)
    {
        if (Gate::denies('create-user')) {
            abort(403);
        }
        
        try {
            $validated = $request->validated();
            $validated['password'] = Hash::make($validated['password']);
            
            User::create($validated);
            
            return redirect()->route('admin.users')->with('success', 'User created successfully');
        } catch (\Exception $e) {
            return back()->with('error', 'Error creating user: ' . $e->getMessage())->withInput();
        }
    }

    public function showUser(User $user)
    {
        if (Gate::denies('view-user', $user)) {
            abort(403);
        }
        
        try {
            $user->load('createdTests');
            return view('admin.users.show', compact('user'));
        } catch (\Exception $e) {
            return back()->with('error', 'Error loading user details: ' . $e->getMessage());
        }
    }

    public function editUser(User $user)
    {
        if (Gate::denies('edit-user', $user)) {
            abort(403);
        }
        return view('admin.users.edit', compact('user'));
    }

    public function updateUser(UpdateUserRequest $request, User $user)
    {
        if (Gate::denies('edit-user', $user)) {
            abort(403);
        }
        
        try {
            $user->update($request->validated());
            return redirect()->route('admin.users')->with('success', 'User updated successfully');
        } catch (\Exception $e) {
            return back()->with('error', 'Error updating user: ' . $e->getMessage())->withInput();
        }
    }

    public function destroyUser(User $user)
    {
        if (Gate::denies('delete-user', $user)) {
            abort(403);
        }
        
        if ($user->id === auth()->id()) {
            return redirect()->route('admin.users')->with('error', 'You cannot delete your own account');
        }
        
        try {
            $user->delete();
            return redirect()->route('admin.users')->with('success', 'User deleted successfully');
        } catch (\Exception $e) {
            return back()->with('error', 'Error deleting user: ' . $e->getMessage());
        }
    }

    public function changePasswordForm(User $user)
    {
        if (Gate::denies('change-user-password', $user)) {
            abort(403);
        }
        return view('admin.users.change-password', compact('user'));
    }

    public function changePassword(Request $request, User $user)
    {
        if (Gate::denies('change-user-password', $user)) {
            abort(403);
        }
        
        $request->validate([
            'password' => 'required|min:8|confirmed',
        ]);
        
        try {
            $user->update([
                'password' => Hash::make($request->password)
            ]);
            return redirect()->route('admin.users')->with('success', 'Password changed successfully');
        } catch (\Exception $e) {
            return back()->with('error', 'Error changing password: ' . $e->getMessage());
        }
    }

    // Test Management
    public function tests()
    {
        try {
            $tests = Test::with('creator')->paginate(10);
            return view('admin.tests.index', compact('tests'));
        } catch (\Exception $e) {
            return back()->with('error', 'Error loading tests: ' . $e->getMessage());
        }
    }

    public function createTest()
    {
        if (Gate::denies('create-test')) {
            abort(403);
        }
        return view('admin.tests.create');
    }

    public function storeTest(StoreTestRequest $request)
    {
        if (Gate::denies('create-test')) {
            abort(403);
        }
        
        try {
            $validated = $request->validated();
            $validated['created_by'] = auth()->id();
            
            Test::create($validated);
            
            return redirect()->route('admin.tests')->with('success', 'Test created successfully');
        } catch (\Exception $e) {
            return back()->with('error', 'Error creating test: ' . $e->getMessage())->withInput();
        }
    }

    public function showTest(Test $test)
    {
        if (Gate::denies('view-test', $test)) {
            abort(403);
        }
        
        try {
            $test->load('questions', 'responses.student');
            return view('admin.tests.show', compact('test'));
        } catch (\Exception $e) {
            return back()->with('error', 'Error loading test details: ' . $e->getMessage());
        }
    }

    public function editTest(Test $test)
    {
        if (Gate::denies('edit-test', $test)) {
            abort(403);
        }
        return view('admin.tests.edit', compact('test'));
    }

    public function updateTest(UpdateTestRequest $request, Test $test)
    {
        if (Gate::denies('edit-test', $test)) {
            abort(403);
        }
        
        try {
            $test->update($request->validated());
            return redirect()->route('admin.tests')->with('success', 'Test updated successfully');
        } catch (\Exception $e) {
            return back()->with('error', 'Error updating test: ' . $e->getMessage())->withInput();
        }
    }

    public function destroyTest(Test $test)
    {
        if (Gate::denies('delete-test', $test)) {
            abort(403);
        }
        
        try {
            DB::transaction(function () use ($test) {
                $test->questions()->delete();
                $test->responses()->delete();
                $test->delete();
            });
            return redirect()->route('admin.tests')->with('success', 'Test deleted successfully');
        } catch (\Exception $e) {
            return back()->with('error', 'Error deleting test: ' . $e->getMessage());
        }
    }

    // Question Management
    public function createQuestion(Test $test)
    {
        if (Gate::denies('create-question', $test)) {
            abort(403);
        }
        return view('admin.questions.create', compact('test'));
    }

    public function storeQuestion(StoreQuestionRequest $request, Test $test)
    {
        if (Gate::denies('create-question', $test)) {
            abort(403);
        }
        
        try {
            DB::transaction(function () use ($request, $test) {
                $question = $test->questions()->create($request->validated());
                
                if ($question->question_type === 'multiple_choice') {
                    foreach ($request->options as $index => $option_text) {
                        $question->options()->create([
                            'option_text' => $option_text,
                            'is_correct' => $index == $request->correct_option,
                        ]);
                    }
                }
            });
            
            return redirect()->route('admin.tests.show', $test)->with('success', 'Question added successfully');
        } catch (\Exception $e) {
            return back()->with('error', 'Error adding question: ' . $e->getMessage())->withInput();
        }
    }

    public function editQuestion(Question $question)
    {
        if (Gate::denies('edit-question', $question)) {
            abort(403);
        }
        return view('admin.questions.edit', compact('question'));
    }

    public function updateQuestion(UpdateQuestionRequest $request, Question $question)
    {
        if (Gate::denies('edit-question', $question)) {
            abort(403);
        }
        
        try {
            DB::transaction(function () use ($request, $question) {
                $question->update($request->validated());
                
                if ($question->question_type === 'multiple_choice') {
                    $question->options()->delete();
                    foreach ($request->options as $index => $option_text) {
                        $question->options()->create([
                            'option_text' => $option_text,
                            'is_correct' => $index == $request->correct_option,
                        ]);
                    }
                }
            });
            
            return redirect()->route('admin.tests.show', $question->test)->with('success', 'Question updated successfully');
        } catch (\Exception $e) {
            return back()->with('error', 'Error updating question: ' . $e->getMessage())->withInput();
        }
    }

    public function destroyQuestion(Question $question)
    {
        if (Gate::denies('delete-question', $question)) {
            abort(403);
        }
        
        try {
            $test = $question->test;
            $question->delete();
            return redirect()->route('admin.tests.show', $test)->with('success', 'Question deleted successfully');
        } catch (\Exception $e) {
            return back()->with('error', 'Error deleting question: ' . $e->getMessage());
        }
    }

    // Student Management
    public function students()
    {
        try {
            $students = Student::paginate(15);
            return view('admin.students.index', compact('students'));
        } catch (\Exception $e) {
            return back()->with('error', 'Error loading students: ' . $e->getMessage());
        }
    }

    public function showStudent(Student $student)
    {
        try {
            $student->load('responses.test');
            return view('admin.students.show', compact('student'));
        } catch (\Exception $e) {
            return back()->with('error', 'Error loading student details: ' . $e->getMessage());
        }
    }

    // Response Management
    public function gradeResponse(Request $request, Response $response)
    {
        if (Gate::denies('grade-response', $response)) {
            abort(403);
        }
        
        $request->validate([
            'score' => 'required|numeric|min:0|max:100',
            'feedback' => 'nullable|string',
        ]);
        
        try {
            $response->update($request->only(['score', 'feedback']));
            return redirect()->back()->with('success', 'Response graded successfully');
        } catch (\Exception $e) {
            return back()->with('error', 'Error grading response: ' . $e->getMessage());
        }
    }

    // Reporting
    public function reports()
    {
        try {
            $tests = Test::all();
            return view('admin.reports.index', compact('tests'));
        } catch (\Exception $e) {
            return back()->with('error', 'Error loading reports: ' . $e->getMessage());
        }
    }

    public function generateTestReport(Test $test)
    {
        try {
            $test->load('responses.student', 'questions');
            return view('admin.reports.test', compact('test'));
        } catch (\Exception $e) {
            return back()->with('error', 'Error generating test report: ' . $e->getMessage());
        }
    }

    public function exportTestResults(Test $test)
    {
        try {
            return Excel::download(new TestResultsExport($test), 'test_results.xlsx');
        } catch (\Exception $e) {
            return back()->with('error', 'Error exporting test results: ' . $e->getMessage());
        }
    }

    // Settings
    public function settings()
    {
        try {
            $settings = [
                'site_name' => config('app.name'),
                'admin_email' => config('mail.from.address'),
                // Add more settings as needed
            ];
            return view('admin.settings', compact('settings'));
        } catch (\Exception $e) {
            return back()->with('error', 'Error loading settings: ' . $e->getMessage());
        }
    }

    public function updateSettings(Request $request)
    {
        $request->validate([
            'site_name' => 'required|string|max:255',
            'admin_email' => 'required|email',
            // Add validation for other settings
        ]);
        
        try {
            // Update settings in config or database
            config(['app.name' => $request->site_name]);
            config(['mail.from.address' => $request->admin_email]);
            // Update other settings as needed
            
            return redirect()->route('admin.settings')->with('success', 'Settings updated successfully');
        } catch (\Exception $e) {
            return back()->with('error', 'Error updating settings: ' . $e->getMessage())->withInput();
        }
    }

    // Email Test Results
    public function sendTestResults(Test $test)
    {
        if (Gate::denies('send-test-results', $test)) {
            abort(403);
        }

        try {
            $test->load('responses.student');
            foreach ($test->responses as $response) {
                Mail::to($response->student->email)->send(new TestResultMail($response));
            }
            return redirect()->back()->with('success', 'Test results sent successfully');
        } catch (\Exception $e) {
            return back()->with('error', 'Error sending test results: ' . $e->getMessage());
        }
    }
}