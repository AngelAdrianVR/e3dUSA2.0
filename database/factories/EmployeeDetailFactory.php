<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class EmployeeDetailFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'week_salary' => $this->faker->randomFloat(2, 1200, 4000),
            'birthdate' => $this->faker->date(),
            'join_date' => $this->faker->dateTimeBetween('-5 years', 'now'),
            'job_position' => $this->faker->jobTitle,
            'department' => $this->faker->word,
            'hours_per_week' => 48.00,
            'work_days' => json_encode([1, 2, 3, 4, 5, 6]), // Lunes a Sábado
            'vacations' => json_encode([]),
            'department_details' => json_encode([]),
        ];
    }
}