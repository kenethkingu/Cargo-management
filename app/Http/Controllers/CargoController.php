<?php

namespace App\Http\Controllers;

use App\Imports\CargoImport;
use Maatwebsite\Excel\Facades\Excel;
use App\Http\Controllers\Controller;

class CargoController extends Controller
{
    public function import(Request  $request)
    {

    if(!$request->hasFile('file')){
        return redirect()->back()->with('error', 'No file selected');
    }
     //validate excel
    $request->validate([
        'file' => 'required|mimes:xlsx,xls,csv|max:2048',
    ]);
     Excel::import(new CargoImport, $request->file('file')->store('temp'));

    return redirect('/')->with('success', 'All good!');
    }
}