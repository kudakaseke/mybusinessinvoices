<?php

use App\Models\Expense;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function foodExpense(): array
{
    return ['merchant' => 'Food', 'description' => 'Food', 'category' => 'Food', 'expense_date' => '2026-10-30', 'currency' => 'USD', 'amount' => '5.00'];
}

test('repeated expense submissions cannot create a duplicate', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/expenses', foodExpense())->assertSessionHasNoErrors()->assertRedirect(route('records.index', 'expenses'));
    $this->from('/expenses/create')->post('/expenses', foodExpense())->assertRedirect('/expenses/create')
        ->assertSessionHasErrors(['description' => 'This expense is already recorded with the same date, merchant, description, category, amount and currency. Check your expenses before saving it again.']);

    $this->assertDatabaseCount('expenses', 1);
    $this->assertDatabaseHas('expenses', ['user_id' => $user->id, 'amount' => '5.00']);
});

test('duplicate checks ignore text case and equivalent monetary formatting', function () {
    $user = User::factory()->create();
    Expense::factory()->for($user)->create(foodExpense());

    $this->actingAs($user)->post('/expenses', array_replace(foodExpense(), ['merchant' => 'food', 'description' => 'FOOD', 'category' => 'food', 'amount' => '5.0']))->assertSessionHasErrors('description');

    $this->assertDatabaseCount('expenses', 1);
});

test('distinct expenses are still allowed', function (string $field, string $value) {
    $user = User::factory()->create();
    Expense::factory()->for($user)->create(foodExpense());

    $this->actingAs($user)->post('/expenses', array_replace(foodExpense(), [$field => $value]))->assertSessionHasNoErrors()->assertRedirect();

    $this->assertDatabaseCount('expenses', 2);
})->with([['expense_date', '2026-10-31'], ['amount', '6.00'], ['merchant', 'Other shop'], ['description', 'Dinner'], ['category', 'Travel'], ['currency', 'ZWG']]);

test('duplicate checks are scoped to the expense owner', function () {
    Expense::factory()->create(foodExpense());
    $user = User::factory()->create();

    $this->actingAs($user)->post('/expenses', foodExpense())->assertSessionHasNoErrors();

    $this->assertDatabaseCount('expenses', 2);
    $this->assertDatabaseHas('expenses', ['user_id' => $user->id, 'merchant' => 'Food']);
});

test('an expense can retain its own details but cannot be edited into another expense', function () {
    $user = User::factory()->create();
    $first = Expense::factory()->for($user)->create(foodExpense());
    $second = Expense::factory()->for($user)->create(array_replace(foodExpense(), ['amount' => '10.00']));

    $this->actingAs($user)->put('/expenses/'.$first->id, foodExpense())->assertSessionHasNoErrors();
    $this->put('/expenses/'.$second->id, foodExpense())->assertSessionHasErrors('description');

    expect($second->fresh()->amount)->toBe('10.00');
    $this->assertDatabaseCount('expenses', 2);
});

test('duplicate submissions do not save extra receipt files', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    Expense::factory()->for($user)->create(foodExpense());

    $this->actingAs($user)->post('/expenses', foodExpense() + ['receipt' => UploadedFile::fake()->create('food.pdf', 10, 'application/pdf')])->assertSessionHasErrors('description');

    expect(Storage::disk('local')->allFiles())->toBe([]);
    $this->assertDatabaseCount('expenses', 1);
});
