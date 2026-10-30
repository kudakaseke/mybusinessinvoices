<?php

namespace Database\Factories;

use App\Models\CreditNote;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditNote>
 */
class CreditNoteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(), 'number' => 'CN-'.fake()->unique()->numerify('########'),
            'amount' => '25.00', 'credited_on' => today(), 'reason' => 'Returned goods', 'submission_key' => fake()->uuid(),
        ];
    }
}
