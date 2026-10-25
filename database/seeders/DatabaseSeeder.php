<?php

namespace Database\Seeders;

use App\Models\Holiday;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
        ]);

        // User::factory(10)->create();

        $admin = User::updateOrCreate(
            ['email' => 'admin@attendance.test'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'status' => 'Active',
            ]
        );
        $admin->syncRoles(['Super Admin']);

        $employee = User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'status' => 'Active',
            ]
        );
        $employee->syncRoles(['Employee']);

        $hr = User::updateOrCreate(
            ['email' => 'hr@attendance.test'],
            [
                'name' => 'HR Manager',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'status' => 'Active',
            ]
        );
        $hr->syncRoles(['HR']);

        $this->seedHolidays();
    }

    /**
     * A handful of 2026 public holidays so the employee calendar is populated
     * and the attendance engine has real dates to exclude from working days.
     * Stored as ISO 'Y-m-d' so DateHelper::parseLooseDate() reads them cleanly.
     */
    private function seedHolidays(): void
    {
        $holidays = [
            ['2026-01-01', "New Year's Day"],
            ['2026-04-03', 'Good Friday'],
            ['2026-04-06', 'Easter Monday'],
            ['2026-05-01', 'Labour Day'],
            ['2026-06-01', 'Madaraka Day'],
            ['2026-10-10', 'International Day of Peace'],
            ['2026-10-20', 'Mashujaa Day'],
            ['2026-12-12', 'Jamhuri Day'],
            ['2026-12-25', 'Christmas Day'],
            ['2026-12-26', 'Boxing Day'],
        ];

        foreach ($holidays as [$date, $name]) {
            Holiday::updateOrCreate(
                ['date_holiday' => $date],
                ['name' => $name, 'year' => (int) substr($date, 0, 4)]
            );
        }
    }
}
