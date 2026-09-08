<?php

namespace App\Controllers;

use App\Models\PairsModel;

/**
 * Migrated from the CodeIgniter 3 Pairs controller.
 */
class Pairs extends BaseController
{
    public function index(int|string|null $recipient_id = null): string
    {
        if (! empty($recipient_id)) {
            return $this->loadPair($recipient_id);
        }

        $match_status = $this->request->getGet('match_status');
        $blood_group  = $this->request->getGet('blood_group');

        // Clicking an already-active filter button clears it.
        if ($this->request->getGet('set_match_status')) {
            $temp         = $this->request->getGet('set_match_status');
            $match_status = ($match_status === $temp) ? null : $temp;
        }

        if ($this->request->getGet('set_blood_group')) {
            $temp        = $this->request->getGet('set_blood_group');
            $blood_group = ($blood_group === $temp) ? null : $temp;
        }

        return view('pairs', [
            'allPairs'     => model(PairsModel::class)->get_custom_pairs_info($blood_group, $match_status),
            'match_status' => $match_status,
            'blood_group'  => $blood_group,
        ]);
    }

    /**
     * The single-pair detail view.
     */
    public function loadPair(int|string $recipient_id): string
    {
        return view('pair', [
            'pair' => model(PairsModel::class)->get_custom_pairs_info(null, null, $recipient_id),
        ]);
    }
}
