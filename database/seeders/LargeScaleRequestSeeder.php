<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Department;
use App\Models\Stock;
use Faker\Factory as Faker;

class LargeScaleRequestSeeder extends Seeder
{
    /**
     * Run the database seeds for 5 Million entries.
     * Note: This is optimized for fast insertion.
     */
    public function run(): void
    {
        $faker = Faker::create();
        
        // Preparation
        $userIds = User::pluck('id')->toArray();
        $deptIds = Department::pluck('id')->toArray();
        $stockIds = Stock::pluck('id')->toArray();
        
        $totalRecords = 5000000;
        $chunkSize = 2000; // Smaller chunk for multi-table inserts (Postgres param limits)

        $this->command->info("Starting 5M record generation with nested items & history...");

        // Get starting ID
        $nextId = (int)DB::table('requests')->max('id') + 1;

        for ($i = 0; $i < $totalRecords; $i += $chunkSize) {
            $requests = [];
            $items = [];
            $histories = [];

            for ($j = 0; $j < $chunkSize; $j++) {
                $requestId = $nextId++;
                $createdAt = $faker->dateTimeBetween('-3 years', 'now');
                $isCompleted = (rand(1, 10) > 3); // 70% completed
                $status = $isCompleted ? 'COMPLETED' : $faker->randomElement(['SUBMITTED', 'VERIFIED', 'APPROVED']);

                $requests[] = [
                    'id' => $requestId,
                    'user_id' => $faker->randomElement($userIds),
                    'department_id' => $faker->randomElement($deptIds),
                    'status' => $status,
                    'total_amount' => $faker->randomFloat(2, 50000, 2000000),
                    'notes' => $faker->sentence(),
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ];

                // Create 1-3 items per request (supports the Monthly Category query)
                $numItems = rand(1, 3);
                for ($k = 0; $k < $numItems; $k++) {
                    $qty = rand(1, 100);
                    $price = $faker->randomFloat(2, 1000, 50000);
                    $items[] = [
                        'request_id' => $requestId,
                        'stock_id' => $faker->randomElement($stockIds),
                        'qty_requested' => $qty,
                        'snapshot_price' => $price,
                        'subtotal' => $qty * $price,
                    ];
                }

                // Lead time support: Add SUBMITTED history
                $histories[] = [
                    'request_id' => $requestId,
                    'previous_status' => 'DRAFT',
                    'new_status' => 'SUBMITTED',
                    'user_id' => $faker->randomElement($userIds),
                    'changed_at' => $createdAt,
                ];

                if ($isCompleted) {
                    // Completed history (gives valid lead time duration)
                    $completedAt = (clone $createdAt)->modify('+' . rand(2, 10) . ' days');
                    $histories[] = [
                        'request_id' => $requestId,
                        'previous_status' => 'IN_PROCUREMENT',
                        'new_status' => 'COMPLETED',
                        'user_id' => $faker->randomElement($userIds),
                        'changed_at' => $completedAt,
                    ];
                }
            }
            
            DB::transaction(function () use ($requests, $items, $histories) {
                DB::table('requests')->insert($requests);
                DB::table('request_items')->insert($items);
                DB::table('status_history')->insert($histories);
            });
            
            $percent = round(($i / $totalRecords) * 100, 2);
            $this->command->info("Progress: {$percent}% completed.");
        }

        $this->command->info("Seeding completed successfully.");
    }
}
