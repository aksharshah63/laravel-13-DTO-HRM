<?php

use App\Models\Attendance;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\ExpenseClaim;
use App\Models\Leave;
use App\Models\LeaveRequest;
use App\Models\Payroll;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->adminUser = User::where('email', 'admin@example.com')->first();
});

test('admin can create a new employee via web form', function () {
    $dept = Department::first();
    $desig = Designation::first();

    $response = $this->actingAs($this->adminUser)->post(route('admin::employees.store'), [
        'employee_code' => 'EMP-TEST-99',
        'first_name' => 'Karan',
        'last_name' => 'Mehta',
        'email' => 'karan.mehta@example.com',
        'phone' => '+91-9988776655',
        'gender' => 'male',
        'date_of_birth' => '1995-05-15',
        'joining_date' => '2026-08-01',
        'department_id' => $dept->id,
        'designation_id' => $desig->id,
        'employment_type' => 'full_time',
        'salary' => 65000.00,
        'password' => 'password123',
        'role' => 'employee',
    ]);

    $response->assertRedirect(route('admin::employees.index'));
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('employees', [
        'email' => 'karan.mehta@example.com',
        'employee_code' => 'EMP-TEST-99',
    ]);
});

test('admin can create a designation via web form', function () {
    $pageResponse = $this->actingAs($this->adminUser)->get(route('admin::settings.designations'));

    $pageResponse->assertOk();
    $pageResponse->assertSee('name="name"', false);

    $department = Department::first();
    $response = $this->actingAs($this->adminUser)->post(route('admin::settings.designations.store'), [
        'name' => 'Quality Assurance Lead',
        'department_id' => $department->id,
        'description' => 'Leads software quality and release readiness.',
        'is_active' => 1,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');
    $this->assertDatabaseHas('designations', [
        'department_id' => $department->id,
        'name' => 'Quality Assurance Lead',
    ]);
});

test('admin can create a shift schedule via web form', function () {
    $response = $this->actingAs($this->adminUser)->post(route('admin::shifts.store'), [
        'name' => 'Test Morning Shift',
        'shift_code' => 'TEST-MORNING',
        'start_time' => '09:00',
        'end_time' => '17:30',
        'mark_late_after_minutes' => 12,
        'total_hours' => 8.5,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');
    $this->assertDatabaseHas('shifts', [
        'shift_code' => 'TEST-MORNING',
        'mark_late_after_minutes' => 12,
        'total_hours' => 8.5,
    ]);
});

test('admin can record check-in and check-out via web', function () {
    $employee = Employee::first();

    $inResponse = $this->actingAs($this->adminUser)->post(route('admin::attendance.checkIn'), [
        'employee_id' => $employee->id,
        'attendance_date' => '2026-09-02',
        'check_in' => '09:05',
    ]);

    $inResponse->assertRedirect();
    $inResponse->assertSessionHas('success');

    $att = Attendance::where('employee_id', $employee->id)->whereDate('attendance_date', now()->toDateString())->first();
    expect($att)->not->toBeNull();

    $outResponse = $this->actingAs($this->adminUser)->post(route('admin::attendance.checkOut'), [
        'employee_id' => $employee->id,
        'attendance_date' => '2026-09-02',
        'check_out' => '18:10',
    ]);

    $outResponse->assertRedirect();
    $outResponse->assertSessionHas('success');
});

test('admin can apply for leave and approve it via web', function () {
    $employee = Employee::first();
    $leaveType = Leave::first();

    $applyResponse = $this->actingAs($this->adminUser)->post(route('admin::leaves.store'), [
        'employee_id' => $employee->id,
        'leave_id' => $leaveType->id,
        'start_date' => '2026-09-10',
        'end_date' => '2026-09-12',
        'reason' => 'Attending family wedding ceremony.',
    ]);

    $applyResponse->assertRedirect();
    $applyResponse->assertSessionHas('success');

    $leaveReq = LeaveRequest::where('employee_id', $employee->id)->latest('id')->first();
    expect($leaveReq)->not->toBeNull();

    $approveResponse = $this->actingAs($this->adminUser)->patch(route('admin::leaves.process', $leaveReq->id), [
        'status' => 'approved',
    ]);

    $approveResponse->assertRedirect();
    $approveResponse->assertSessionHas('success');

    expect($leaveReq->fresh()->status)->toBe('approved');
});

test('admin can generate payroll batch and mark as paid via web', function () {
    $genResponse = $this->actingAs($this->adminUser)->post(route('admin::payroll.generate'), [
        'month' => 9,
        'year' => 2026,
    ]);

    $genResponse->assertRedirect();
    $genResponse->assertSessionHas('success');

    $payroll = Payroll::where('month', 9)->where('year', 2026)->first();
    expect($payroll)->not->toBeNull();

    $payResponse = $this->actingAs($this->adminUser)->patch(route('admin::payroll.pay', $payroll->id));
    $payResponse->assertRedirect();
    $payResponse->assertSessionHas('success');

    expect($payroll->fresh()->status)->toBe('paid');
});

test('admin can update an employee salary structure via web', function () {
    $employee = Employee::first();
    $pageResponse = $this->actingAs($this->adminUser)->get(route('admin::payroll.structures'));

    $pageResponse->assertOk();
    $pageResponse->assertSee('name="transport_allowance"', false);
    $pageResponse->assertSee('name="tax_deduction"', false);
    $pageResponse->assertSee('name="other_deductions"', false);

    $response = $this->actingAs($this->adminUser)->post(route('admin::payroll.structures.update'), [
        'employee_id' => $employee->id,
        'basic_salary' => 50000,
        'hra' => 5000,
        'medical_allowance' => 1000,
        'transport_allowance' => 1500,
        'special_allowance' => 2000,
        'tax_deduction' => 2500,
        'provident_fund' => 1800,
        'other_deductions' => 300,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');
    $this->assertDatabaseHas('salary_structures', [
        'employee_id' => $employee->id,
        'transport_allowance' => 1500,
        'tax_deduction' => 2500,
        'other_deductions' => 300,
        'net_salary' => 54900,
    ]);
});

test('admin can create a performance goal via web', function () {
    $pageResponse = $this->actingAs($this->adminUser)->get(route('admin::performance.goals'));

    $pageResponse->assertOk();
    $pageResponse->assertSee('name="due_date"', false);
    $pageResponse->assertSee('name="priority"', false);

    $employee = Employee::first();
    $response = $this->actingAs($this->adminUser)->post(route('admin::performance.goals.store'), [
        'employee_id' => $employee->id,
        'title' => 'Complete quarterly service review',
        'start_date' => '2026-10-01',
        'due_date' => '2026-12-31',
        'priority' => 'high',
        'description' => 'Review service performance and publish findings.',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');
    $this->assertDatabaseHas('performance_goals', [
        'employee_id' => $employee->id,
        'title' => 'Complete quarterly service review',
        'due_date' => '2026-12-31 00:00:00',
        'priority' => 'high',
    ]);
});

test('admin can create a performance review via web', function () {
    $pageResponse = $this->actingAs($this->adminUser)->get(route('admin::performance.reviews'));

    $pageResponse->assertOk();
    $pageResponse->assertSee('name="review_cycle"', false);
    $pageResponse->assertSee('name="employee_feedback"', false);
    $pageResponse->assertSee('name="status"', false);

    $employee = Employee::first();
    $response = $this->actingAs($this->adminUser)->post(route('admin::performance.reviews.store'), [
        'employee_id' => $employee->id,
        'review_cycle' => 'Q4 2026',
        'rating' => 4,
        'strengths' => 'Reliable delivery and clear communication.',
        'area_of_improvements' => 'Delegate more project work.',
        'employee_feedback' => 'Strong quarter with measurable impact.',
        'status' => 'submitted',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');
    $this->assertDatabaseHas('performance_reviews', [
        'employee_id' => $employee->id,
        'review_cycle' => 'Q4 2026',
        'rating' => 4,
        'employee_feedback' => 'Strong quarter with measurable impact.',
        'status' => 'submitted',
    ]);
});

test('admin can submit expense claim and process it via web', function () {
    $employee = Employee::first();

    $claimResponse = $this->actingAs($this->adminUser)->post(route('admin::expenses.store'), [
        'employee_id' => $employee->id,
        'title' => 'Hardware Adapter',
        'category' => 'equipment',
        'amount' => 1200.00,
        'expense_date' => '2026-09-01',
        'description' => 'USB-C to HDMI converter for presentation.',
    ]);

    $claimResponse->assertRedirect();
    $claimResponse->assertSessionHas('success');

    $claim = ExpenseClaim::where('employee_id', $employee->id)->latest('id')->first();
    expect($claim)->not->toBeNull();

    $processResponse = $this->actingAs($this->adminUser)->patch(route('admin::expenses.process', $claim->id), [
        'status' => 'accepted',
    ]);

    $processResponse->assertRedirect();
    $processResponse->assertSessionHas('success');

    expect($claim->fresh()->status)->toBe('accepted');
});

test('admin can post announcement and test AI tools via web', function () {
    $noticeResponse = $this->actingAs($this->adminUser)->post(route('admin::announcements.store'), [
        'title' => 'Quarterly Townhall Meeting',
        'content' => 'Join all-hands townhall this Thursday at 4 PM.',
        'priority' => 'medium',
        'start_date' => '2026-09-03',
        'is_published' => true,
    ]);

    $noticeResponse->assertRedirect();
    $noticeResponse->assertSessionHas('success');

    // AI Chat JSON Endpoint
    $aiResponse = $this->actingAs($this->adminUser)->postJson(route('admin::ai.chat'), [
        'message' => 'What is the standard working hours policy?',
    ]);

    $aiResponse->assertStatus(200);
    $aiResponse->assertJsonStructure(['response', 'confidence']);
});

test('admin can update company settings via web form', function () {
    $settingsResponse = $this->actingAs($this->adminUser)->put(route('admin::settings.company.update'), [
        'company_name' => 'Acme Global Corporation',
        'company_email' => 'contact@acmeglobal.com',
        'currency' => 'INR',
        'currency_symbol' => '₹',
        'timezone' => 'Asia/Kolkata',
        'working_hours_per_day' => 8.5,
        'working_days_per_week' => 5,
    ]);

    $settingsResponse->assertRedirect();
    $settingsResponse->assertSessionHas('success');
});

test('regular employee can submit self leave and self expense claim without admin manage rights', function () {
    $empUser = User::where('email', 'employee@example.com')->first();
    $leave = Leave::first();

    // 1. Submit self leave
    $leaveResponse = $this->actingAs($empUser)->post(route('admin::leaves.store'), [
        'leave_id' => $leave->id,
        'start_date' => '2026-09-10',
        'end_date' => '2026-09-12',
        'reason' => 'Personal urgent work',
    ]);

    $leaveResponse->assertRedirect();
    $leaveResponse->assertSessionHas('success');

    $this->assertDatabaseHas('leave_requests', [
        'employee_id' => $empUser->employee->id,
        'leave_id' => $leave->id,
        'status' => 'pending',
    ]);

    // 2. Submit self expense claim
    $expenseResponse = $this->actingAs($empUser)->post(route('admin::expenses.store'), [
        'title' => 'Client Meet Uber Cab',
        'category' => 'travel',
        'amount' => 640.00,
        'expense_date' => '2026-09-02',
        'description' => 'Airport to Office Cab',
    ]);

    $expenseResponse->assertRedirect();
    $expenseResponse->assertSessionHas('success');

    $this->assertDatabaseHas('expenses_claims', [
        'employee_id' => $empUser->employee->id,
        'title' => 'Client Meet Uber Cab',
        'amount' => 640.00,
    ]);
});
