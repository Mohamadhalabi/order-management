<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        Branch::firstOrCreate(['slug' => 'mersin'],   ['name' => 'Mersin']);
        Branch::firstOrCreate(['slug' => 'istanbul'], ['name' => 'Istanbul']);
    }
}
