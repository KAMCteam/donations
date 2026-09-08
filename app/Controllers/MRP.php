<?php

namespace App\Controllers;

use App\Models\LabsModel;
use App\Models\MrpModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Adds labs and MRPs (most responsible physicians).
 *
 * Migrated from the CodeIgniter 3 MRP controller. The bare exit() calls the CI3
 * version used for missing input are now flash-message redirects back to the form.
 */
class MRP extends BaseController
{
    public function index(): string
    {
        return view('mrp_form', [
            'confirmation' => $this->session->getFlashdata('confirmation'),
            'error'        => $this->session->getFlashdata('error'),
        ]);
    }

    public function addLab(): RedirectResponse
    {
        $lab_id    = $this->request->getPost('lab_name');
        $lab_shape = $this->request->getPost('lab_shape');

        if (empty($lab_id) || empty($lab_shape)) {
            $this->session->setFlashdata('error', lang('Form.ctrl_error_missing'));

            return redirect()->to(site_url('MRP'));
        }

        model(LabsModel::class)->insert_lab([
            'lab_name'     => $lab_id,
            'result_shape' => $lab_shape,
        ]);

        $this->session->setFlashdata('confirmation', lang('Form.ctrl_confirmation_lab_add'));

        return redirect()->to(site_url('MRP'));
    }

    public function addMRP(): RedirectResponse
    {
        $mrp_id   = $this->request->getPost('id');
        $mrp_name = $this->request->getPost('name');

        if (empty($mrp_id) || empty($mrp_name)) {
            $this->session->setFlashdata('error', lang('Form.ctrl_error_missing'));

            return redirect()->to(site_url('MRP'));
        }

        model(MrpModel::class)->insert_mrp([
            'mrp_id' => $mrp_id,
            'name'   => $mrp_name,
        ]);

        $this->session->setFlashdata('confirmation', lang('Form.ctrl_confirmation_mrp_add'));

        return redirect()->to(site_url('MRP'));
    }
}
