<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Stock;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class MasterDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create Departments
        $depts = [];
        foreach (['IT', 'HR', 'Finance', 'Operations', 'Purchasing'] as $name) {
            $depts[$name] = Department::create(['name' => $name]);
        }

        // 2. Create Stock
        Stock::create([
            'sku' => 'LAP-001',
            'name' => 'MacBook Pro M3',
            'category' => 'Electronics',
            'quantity' => 10,
            'unit_price' => 30000000,
        ]);
        Stock::create([
            'sku' => 'MON-002',
            'name' => 'Dell 27" Monitor',
            'category' => 'Electronics',
            'quantity' => 0, // Out of stock to trigger PO
            'unit_price' => 5000000,
        ]);
        Stock::create([
            'sku' => 'CHR-003',
            'name' => 'Ergonomic Chair',
            'category' => 'Furniture',
            'quantity' => 20,
            'unit_price' => 2500000,
        ]);

        // 3. Create Vendors
        Vendor::create([
            'name' => 'Global IT Solutions',
            'contact_info' => 'contact@global-it.com',
            'address' => 'Jakarta, Indonesia',
        ]);
        Vendor::create([
            'name' => 'Office Supply Co',
            'contact_info' => 'sales@officesupply.id',
            'address' => 'Surabaya, Indonesia',
        ]);

        // 4. Create Users for testing
        User::create([
            'name' => 'IT Admin',
            'email' => 'it@test.com',
            'password' => Hash::make('password'),
            'role' => 'EMPLOYEE',
            'department_id' => $depts['IT']->id,
        ]);
        User::create([
            'name' => 'Purchasing Officer',
            'email' => 'purchasing@test.com',
            'password' => Hash::make('password'),
            'role' => 'PURCHASING',
            'department_id' => $depts['Purchasing']->id,
        ]);
        User::create([
            'name' => 'Purchasing Manager',
            'email' => 'manager@test.com',
            'password' => Hash::make('password'),
            'role' => 'MANAGER',
            'department_id' => $depts['Purchasing']->id,
        ]);
    }
}
