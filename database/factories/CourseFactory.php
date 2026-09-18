<?php

namespace Database\Factories;

use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $letters = $this->faker->lexify(str_repeat('?', $this->faker->numberBetween(2, 4)));
        $numbers = $this->faker->numerify('###');
        return [
            'code' => strtoupper($letters) . $numbers,
            'name' => $this->faker->sentence(3),
            'credits' => $this->faker->numberBetween(1, 6),
        ];
    }
}
