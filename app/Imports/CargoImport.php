<?php

namespace App\Imports;

use App\Models\Cargo;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class CargoImport implements ToModel, WithHeadingRow
{
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        return new Cargo([
            'cargo_no' => $row['cargo_no'],
            'cargo_type' => $row['cargo_type'],
            'cargo_size' => $row['cargo_size'],
            'weight' => $row['weight'] ?? null,
            'remarks' => $row['remarks'] ?? null,
            'wharfage' => $row['wharfage'] ?? 0.00,
            'penalty_days' => $row['penalty_days'] ?? 0,
            'storage' => $row['storage'] ?? 0.00,
            'electricity' => $row['electricity'] ?? 0.00,
            'destuffing' => $row['destuffing'] ?? 0.00,
            'lifting' => $row['lifting'] ?? 0.00,
        ]);
    }
}