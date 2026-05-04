<?php

namespace Database\Factories;

use App\Enums\LeadStatus;
use App\Models\Inquiry;
use App\Models\Lead;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeadFactory extends Factory
{
    protected $model = Lead::class;

    public function definition(): array
    {
        $inquiry = Inquiry::factory()->handedOff()->create();

        return [
            'inquiry_id' => $inquiry->id,
            'created_by' => User::factory()->superAdmin(),
            'status'     => LeadStatus::pending()->value,
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => LeadStatus::pending()->value]);
    }

    public function sent(?User $actor = null): static
    {
        return $this->state(fn () => [
            'status'  => LeadStatus::sent()->value,
            'sent_at' => Carbon::now(),
        ]);
    }

    public function finalized(?User $actor = null): static
    {
        return $this->state(fn () => [
            'status'       => LeadStatus::finalized()->value,
            'sent_at'      => Carbon::now()->subDay(),
            'finalized_at' => Carbon::now(),
        ]);
    }

    public function lost(?User $actor = null): static
    {
        return $this->state(fn () => [
            'status'  => LeadStatus::lost()->value,
            'lost_at' => Carbon::now(),
        ]);
    }
}
