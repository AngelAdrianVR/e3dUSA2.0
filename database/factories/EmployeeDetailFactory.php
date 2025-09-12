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
            'hours_per_week' => 40.00, // Ajustado a 8 horas x 5 días
            'work_days' => [
                ['day' => 'Lunes', 'works' => true, 'start_time' => '09:00:00', 'end_time' => '18:00:00', 'break_minutes' => 60],
                ['day' => 'Martes', 'works' => true, 'start_time' => '09:00:00', 'end_time' => '18:00:00', 'break_minutes' => 60],
                ['day' => 'Miércoles', 'works' => true, 'start_time' => '09:00:00', 'end_time' => '18:00:00', 'break_minutes' => 60],
                ['day' => 'Jueves', 'works' => true, 'start_time' => '09:00:00', 'end_time' => '18:00:00', 'break_minutes' => 60],
                ['day' => 'Viernes', 'works' => true, 'start_time' => '09:00:00', 'end_time' => '18:00:00', 'break_minutes' => 60],
                ['day' => 'Sábado', 'works' => false, 'start_time' => null, 'end_time' => null, 'break_minutes' => 0],
                ['day' => 'Domingo', 'works' => false, 'start_time' => null, 'end_time' => null, 'break_minutes' => 0],
            ],
            'vacations' => [],
            'department_details' => [],
        ];
    }
}