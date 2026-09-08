<?php

namespace App\Controllers;

use App\Models\PairsModel;

/**
 * Migrated from the CodeIgniter 3 WaitingList controller.
 *
 * The organ_chosen() call the constructor used to make is now the `organ`
 * filter declared on the route.
 */
class WaitingList extends BaseController
{
    public function index(): string
    {
        $blood_group = $this->request->getGet('blood_group');

        // Clicking an already-active filter button clears it.
        if ($this->request->getGet('set_blood_group')) {
            $temp        = $this->request->getGet('set_blood_group');
            $blood_group = ($blood_group === $temp) ? null : $temp;
        }

        return view('waiting_list', [
            'unmatchedRecipients' => model(PairsModel::class)->get_some_unmatched_recipients($blood_group),
            'blood_group'         => $blood_group,
        ]);
    }
}
