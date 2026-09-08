<?php

namespace App\Controllers;

use App\Models\ListsModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Organ picker. Migrated from the CodeIgniter 3 Organ controller.
 *
 * This is the one screen not behind the `organ` filter, since it is where the
 * filter sends visitors who have not picked one yet.
 */
class Organ extends BaseController
{
    public function index(): string|RedirectResponse
    {
        $lists  = model(ListsModel::class);
        $organs = $lists->get_enum_values('patients', 'organs');

        $selected_organ = $this->request->getGet('selcted_organ');

        if (! empty($selected_organ) && in_array($selected_organ, $organs, true)) {
            $this->session->set('organ', $selected_organ);

            return redirect()->to(site_url('Patient'));
        }

        return view('organs', ['organs' => $organs]);
    }

    /**
     * Clears the session so a different organ can be chosen.
     *
     * CI3 named this destroy_sess(); the route keeps that URL.
     */
    public function destroySession(): RedirectResponse
    {
        $this->session->destroy();

        return redirect()->to(site_url('Organ'));
    }
}
