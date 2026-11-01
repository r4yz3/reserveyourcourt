<?php

namespace Database\Factories;

use App\Models\FeeSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FeeSetting> */
class FeeSettingFactory extends Factory
{
    public function definition(): array
    {
        return ['amount_centavos' => 2500, 'changed_by' => User::factory()];
    }
}
