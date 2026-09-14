<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\TrainingParticipant;
use App\Models\TrainingSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingParticipant>
 */
class TrainingParticipantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'training_session_id' => TrainingSession::factory(),
            'employee_id' => Employee::factory(),
            'attendance_status' => 'invited',
        ];
    }
}
