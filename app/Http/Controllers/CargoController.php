<?php

namespace App\Http\Controllers;

use App\Imports\CargoImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\Cargo;

class CargoController extends Controller
{
        /**
     * Show the upload page (no table)
     */
    public function welcome()
    {
        return view('welcome'); // no $data
    }

    /**
     * Display all cargos (View Data page)
     */
    public function index()
    {
        $data = Cargo::latest()->paginate(10);
        return view('welcome', compact('data')); // table displayed only here
    }

    /**
     * Import cargos from Excel/CSV
     */
    public function import(Request $request)
    {
        // Check if file exists
        if (!$request->hasFile('file')) {
            return redirect()->back()->with('error', 'No file selected');
        }

        // Validate excel file
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:2048',
        ]);

        try {
            DB::beginTransaction();
            Excel::import(new CargoImport, $request->file('file')->store('temp'));
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Import failed: ' . $e->getMessage());
        }

        // Redirect back to upload page (no table)
        return redirect()->route('cargo.welcome')->with('success', 'Import successful!');
    }

    /**
     * Store new cargo
     */
    public function store(Request $request)
    {
        $request->validate([
            'cargo_no'      => 'required|string|max:255',
            'cargo_type'    => 'required|string|max:255',
            'cargo_size'    => 'required|string|max:50',
            'weight'        => 'nullable|numeric',
            'remarks'       => 'nullable|string',
            'wharfage'      => 'nullable|numeric',
            'penalty_days'  => 'nullable|integer',
            'storage'       => 'nullable|numeric',
            'electricity'   => 'nullable|numeric',
            'destuffing'    => 'nullable|numeric',
            'lifting'       => 'nullable|numeric',
        ]);

        Cargo::create($request->all());

        return redirect()->route('cargo.index')->with('success', 'Cargo added successfully!');
    }

    /**
     * Show the edit form
     */
    public function edit($id)
    {
        $cargo = Cargo::findOrFail($id);
        return view('cargo.edit', compact('cargo'));
    }

    /**
     * Update an existing cargo
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'cargo_no'      => 'required|string|max:255',
            'cargo_type'    => 'required|string|max:255',
            'cargo_size'    => 'required|string|max:50',
            'weight'        => 'nullable|numeric',
            'remarks'       => 'nullable|string',
            'wharfage'      => 'nullable|numeric',
            'penalty_days'  => 'nullable|integer',
            'storage'       => 'nullable|numeric',
            'electricity'   => 'nullable|numeric',
            'destuffing'    => 'nullable|numeric',
            'lifting'       => 'nullable|numeric',
        ]);

        $cargo = Cargo::findOrFail($id);
        $cargo->update($request->all());

        return redirect()->route('cargo.index')->with('success', 'Cargo updated successfully.');
    }

    /**
     * Delete a cargo
     */
    public function destroy(Cargo $cargo)
    {
        $cargo->delete();
        return redirect()->route('cargo.index')->with('success', 'Cargo deleted successfully!');
    }
}
