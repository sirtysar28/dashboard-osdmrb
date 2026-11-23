<?php

namespace Database\Factories;

use App\Models\ServiceRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Pengajuan layanan kepegawaian (uji). unit_id & employee_id wajib
 * diberikan pemanggil — model Unit/Employee tidak memiliki factory.
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ServiceRequest>
 */
class ServiceRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'service_type' => $this->faker->randomElement(array_keys(ServiceRequest::typeOptions())),
            'nota_path' => 'layanan/'.$this->faker->uuid.'.pdf',
            'nota_name' => 'nota-dinas.pdf',
            'supporting_link' => $this->faker->optional()->url(),
            'status' => ServiceRequest::STATUS_PENDING,
        ];
    }
}
