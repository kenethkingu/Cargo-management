<?php

namespace App\Imports;

use App\Models\Cargo;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class CargoImport implements ToModel, WithHeadingRow, WithValidation
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
            'weight' => $row['weight_kg'] ?? null,
            'remarks' => $row['remarks'] ?? null,
            'wharfage' => $row['wharfage_usd'] ?? 0.00,
            'penalty_days' => $row['penalty_days'] ?? 0,
            'storage' => $row['penalty_days'] * 20 ?? 0.00,
            'electricity' => $row['electricity_usd'] ?? 0.00,
            'destuffing' => $row['destuffing_usd'] ?? 0.00,
            'lifting' => $row['lifting_usd'] ?? 0.00,
        ]);
    }
        public function rules(): array
        {
            return [
                '*.cargo_no' => 'required|unique:cargos,cargo_no',
                '*.cargo_type' => 'required|string',
                '*.cargo_size' => 'required|integer',
                '*.weight' => 'nullable|integer',
                '*.remarks' => 'nullable|string',
                '*.wharfage' => 'nullable|numeric',
                '*.penalty_days' => 'nullable|integer',
                '*.storage' => 'nullable|numeric',
                '*.electricity' => 'nullable|numeric',
                '*.destuffing' => 'nullable|numeric',
                '*.lifting' => 'nullable|numeric',
            ];
        }
    }
