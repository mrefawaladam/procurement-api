<?php

namespace Database\Factories;

use App\Models\Request;
use App\Models\User;
use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

class RequestFactory extends Factory
{
    protected $model = Request::class;

    public function definition(): array
    {
        $statuses = ['DRAFT', 'SUBMITTED', 'VERIFIED', 'APPROVED', 'REJECTED', 'CHECKING_STOCK', 'IN_PROCUREMENT', 'COMPLETED'];
        return [
            'user_id' => User::factory(),
            'department_id' => Department::factory(),
            'status' => $this->faker->randomElement($statuses),
            'total_amount' => $this->faker->randomFloat(2, 100000, 50000000),
            'notes' => $this->faker->sentence(),
            'created_at' => $this->faker->dateTimeBetween('-2 years', 'now'),
        ];
    }
}
