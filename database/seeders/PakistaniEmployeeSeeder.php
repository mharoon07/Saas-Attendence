<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeePosition;
use App\Models\EmployeeSalary;
use App\Models\EmployeeShift;
use App\Models\Position;
use App\Models\Shift;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class PakistaniEmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds for Pakistani employees.
     */
    public function run(): void
    {
        // Ensure default Branch, Department, Position, and Shift exist
        $branch = Branch::first() ?? Branch::create(['name' => 'Lahore Head Office']);
        $departmentIt = Department::where('name', 'IT')->first() ?? Department::create(['name' => 'IT']);
        $departmentHr = Department::where('name', 'HR')->first() ?? Department::create(['name' => 'HR']);
        $departmentSales = Department::where('name', 'Sales')->first() ?? Department::create(['name' => 'Sales']);
        $departmentFinance = Department::where('name', 'Finance')->first() ?? Department::create(['name' => 'Finance']);

        $positionDev = Position::where('name', 'Software Engineer')->first() ?? Position::create(['name' => 'Software Engineer', 'description' => 'Develops and maintains web applications']);
        $positionHr = Position::where('name', 'HR Executive')->first() ?? Position::create(['name' => 'HR Executive', 'description' => 'Handles recruitment and HR policies']);
        $positionDesigner = Position::where('name', 'UI/UX Designer')->first() ?? Position::create(['name' => 'UI/UX Designer', 'description' => 'Designs user interfaces and workflows']);
        $positionQa = Position::where('name', 'QA Engineer')->first() ?? Position::create(['name' => 'QA Engineer', 'description' => 'Quality assurance and software testing']);

        $shiftDay = Shift::first() ?? Shift::create([
            'name' => 'Standard Day Shift',
            'start_time' => '09:00:00',
            'end_time' => '18:00:00',
        ]);

        $employeeRole = Role::findOrCreate('employee', 'web');

        $pakistaniEmployees = [
            [
                'device_employee_id' => 101,
                'name' => 'Muhammad Ali Khan',
                'email' => 'ali.khan@example.pk',
                'phone' => '03001234567',
                'national_id' => '35202-1234567-1',
                'address' => 'House 42, Block H-3, Johar Town, Lahore',
                'bank_acc_no' => 'PK36MEZN0001020304050601',
                'hired_on' => '2023-01-15',
                'department_id' => $departmentIt->id,
                'position_id' => $positionDev->id,
                'salary' => 180000,
                'salary_basis' => 'monthly',
                'regular_duty_hours' => 8,
            ],
            [
                'device_employee_id' => 102,
                'name' => 'Hamza Ahmed',
                'email' => 'hamza.ahmed@example.pk',
                'phone' => '03219876543',
                'national_id' => '35201-7654321-3',
                'address' => 'Street 15, Sector F-8/2, Islamabad',
                'bank_acc_no' => 'PK12HABB0002938475610293',
                'hired_on' => '2023-03-01',
                'department_id' => $departmentIt->id,
                'position_id' => $positionDev->id,
                'salary' => 150000,
                'salary_basis' => 'monthly',
                'regular_duty_hours' => 8,
            ],
            [
                'device_employee_id' => 103,
                'name' => 'Usman Tariq',
                'email' => 'usman.tariq@example.pk',
                'phone' => '03335554433',
                'national_id' => '42101-5544332-5',
                'address' => 'Flat B-4, PECHS Block 6, Karachi',
                'bank_acc_no' => 'PK88BAHL0001827364519283',
                'hired_on' => '2023-05-10',
                'department_id' => $departmentHr->id,
                'position_id' => $positionHr->id,
                'salary' => 95000,
                'salary_basis' => 'monthly',
                'regular_duty_hours' => 8,
            ],
            [
                'device_employee_id' => 104,
                'name' => 'Ayesha Malik',
                'email' => 'ayesha.malik@example.pk',
                'phone' => '03024443322',
                'national_id' => '35202-9988776-2',
                'address' => 'Phase 5, DHA, Lahore',
                'bank_acc_no' => 'PK45MCB0000192837465019',
                'hired_on' => '2023-06-15',
                'department_id' => $departmentFinance->id,
                'position_id' => $positionHr->id,
                'salary' => 120000,
                'salary_basis' => 'monthly',
                'regular_duty_hours' => 8,
            ],
            [
                'device_employee_id' => 105,
                'name' => 'Sana Fatima',
                'email' => 'sana.fatima@example.pk',
                'phone' => '03138887766',
                'national_id' => '37405-1122334-4',
                'address' => 'Saddar, Rawalpindi',
                'bank_acc_no' => 'PK90UBL0000564738291029',
                'hired_on' => '2023-08-01',
                'department_id' => $departmentIt->id,
                'position_id' => $positionDesigner->id,
                'salary' => 110000,
                'salary_basis' => 'monthly',
                'regular_duty_hours' => 8,
            ],
            [
                'device_employee_id' => 106,
                'name' => 'Bilal Hussain',
                'email' => 'bilal.hussain@example.pk',
                'phone' => '03456667788',
                'national_id' => '33100-3344556-7',
                'address' => 'People\'s Colony 1, Faisalabad',
                'bank_acc_no' => 'PK67FABL0009876543210123',
                'hired_on' => '2023-09-20',
                'department_id' => $departmentIt->id,
                'position_id' => $positionQa->id,
                'salary' => 85000,
                'salary_basis' => 'monthly',
                'regular_duty_hours' => 8,
            ],
            [
                'device_employee_id' => 107,
                'name' => 'Zeeshan Raza',
                'email' => 'zeeshan.raza@example.pk',
                'phone' => '03017778899',
                'national_id' => '35201-4455667-8',
                'address' => 'Model Town, Block C, Lahore',
                'bank_acc_no' => 'PK23ALLH0004561237890123',
                'hired_on' => '2023-11-01',
                'department_id' => $departmentSales->id,
                'position_id' => $positionHr->id,
                'salary' => 75000,
                'salary_basis' => 'monthly',
                'regular_duty_hours' => 8,
            ],
            [
                'device_employee_id' => 108,
                'name' => 'Zainab Shah',
                'email' => 'zainab.shah@example.pk',
                'phone' => '03221112233',
                'national_id' => '17301-8899001-6',
                'address' => 'Hayatabad Phase 3, Peshawar',
                'bank_acc_no' => 'PK54BOP0000789123456012',
                'hired_on' => '2024-01-10',
                'department_id' => $departmentHr->id,
                'position_id' => $positionHr->id,
                'salary' => 70000,
                'salary_basis' => 'monthly',
                'regular_duty_hours' => 8,
            ],
            [
                'device_employee_id' => 109,
                'name' => 'Imran Chaudhry',
                'email' => 'imran.chaudhry@example.pk',
                'phone' => '03009998877',
                'national_id' => '38403-6677889-9',
                'address' => 'Satellite Town, Sargodha',
                'bank_acc_no' => 'PK09NBP0000123987456102',
                'hired_on' => '2024-02-15',
                'department_id' => $departmentSales->id,
                'position_id' => $positionHr->id,
                'salary' => 90000,
                'salary_basis' => 'monthly',
                'regular_duty_hours' => 8,
            ],
            [
                'device_employee_id' => 110,
                'name' => 'Omar Farooq',
                'email' => 'omar.farooq@example.pk',
                'phone' => '03343332211',
                'national_id' => '35202-7766554-3',
                'address' => 'Wapda Town, Lahore',
                'bank_acc_no' => 'PK78HABB0009988776655443',
                'hired_on' => '2024-04-01',
                'department_id' => $departmentIt->id,
                'position_id' => $positionDev->id,
                'salary' => 135000,
                'salary_basis' => 'monthly',
                'regular_duty_hours' => 8,
            ],
        ];

        foreach ($pakistaniEmployees as $empData) {
            $employee = Employee::updateOrCreate(
                ['email' => $empData['email']],
                [
                    'device_employee_id' => $empData['device_employee_id'],
                    'name' => $empData['name'],
                    'phone' => $empData['phone'],
                    'national_id' => $empData['national_id'],
                    'address' => $empData['address'],
                    'bank_acc_no' => $empData['bank_acc_no'],
                    'hired_on' => $empData['hired_on'],
                    'department_id' => $empData['department_id'],
                    'branch_id' => $branch->id,
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );

            // Assign employee role
            if (!$employee->hasRole('employee') && !$employee->hasRole('admin')) {
                $employee->assignRole($employeeRole);
            }

            // Position Assignment
            EmployeePosition::updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'position_id' => $empData['position_id'],
                ],
                [
                    'start_date' => $empData['hired_on'],
                    'end_date' => null,
                ]
            );

            // Shift Assignment
            EmployeeShift::updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'shift_id' => $shiftDay->id,
                ],
                [
                    'start_date' => $empData['hired_on'],
                    'end_date' => null,
                ]
            );

            // Salary Assignment (PKR)
            EmployeeSalary::updateOrCreate(
                [
                    'employee_id' => $employee->id,
                ],
                [
                    'currency' => 'PKR',
                    'monthly_salary' => $empData['salary'],
                    'daily_salary' => round($empData['salary'] / 30, 2),
                    'overtime_rate' => round(($empData['salary'] / 30 / 8) * 1.5, 2),
                    'start_date' => $empData['hired_on'],
                    'end_date' => null,
                ]
            );
        }
    }
}
