<?php

namespace App\Controllers;

use App\Models\LabsModel;

/**
 * Scratch controller carried over from the CodeIgniter 3 project.
 *
 * CI3 switched the profiler on here; CI4 shows the same information in the
 * debug toolbar whenever CI_ENVIRONMENT is `development`.
 */
class Test extends BaseController
{
    public function index(): string
    {
        return view('test', [
            'test' => model(LabsModel::class)->get_results_by_mrn('1'),
        ]);
    }

    public function test(): string
    {
        return view('test', ['test' => $this->request->getPost('name')]);
    }
}
