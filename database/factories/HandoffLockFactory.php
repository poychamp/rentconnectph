<?php

namespace Database\Factories;

use App\Models\HandoffLock;
use App\Models\Inquiry;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

class HandoffLockFactory extends Factory
{
    protected $model = HandoffLock::class;

    public function definition(): array
    {
        $inquiry = Inquiry::factory()->handedOff()->create();

        return [
            'inquiry_id' => $inquiry->id,
            'listing_id' => $inquiry->listing_id,
            'created_by' => User::factory()->superAdmin(),
        ];
    }

    public function forCreatedBy(User $admin): static
    {
        return $this->state(fn () => ['created_by' => $admin->id]);
    }

    public function createdAt(CarbonInterface $when): static
    {
        return $this->afterCreating(function (HandoffLock $lock) use ($when): void {
            $lock->created_at = $when;
            $lock->save();
        });
    }
}
