<?php

namespace Database\Seeders;

use App\Models\SalesEmployee;
use Illuminate\Database\Seeder;

class SalesEmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $employees = [
            ['code' => 'S001', 'name' => 'John Kamau', 'phone' => '+254701234567'],
            ['code' => 'S002', 'name' => 'Mary Wanjiku', 'phone' => '+254702345678'],
            ['code' => 'S003', 'name' => 'Peter Ochieng', 'phone' => '+254703456789'],
            ['code' => 'S004', 'name' => 'Grace Achieng', 'phone' => '+254704567890'],
            ['code' => 'S005', 'name' => 'Samuel Mwangi', 'phone' => '+254705678901'],
        ];

        foreach ($employees as $employee) {
            SalesEmployee::query()->updateOrCreate(['code' => $employee['code']], $employee);
        }
    }
}
