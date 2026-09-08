<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Dashboard extends CI_Controller {
    public function __construct() {
        parent::__construct();
        organ_chosen();
        set_language('english');
        $this->load->model(['Queries_model']);
    }

    public function index() {
        $graphs = $this->getPossibleGraphs();
        $labels = $this->session->userdata('chart_labels') ?? [];
        $datasets = $this->session->userdata('chart_datasets') ?? [];
        $backgrounds = $this->session->userdata('chart_backgrounds') ?? [];

        $data = [
            'graphs' => $graphs,
            'labels' => json_encode($labels),
            'datasets' => json_encode($datasets),
            'backgrounds' => json_encode($backgrounds),
        ];

        $this->load->view('dashboard', $data);
    }

    public function getPossibleGraphs() {
        $graphs = [
            'number_of_patients' => [
                'label' => 'Number of Patients', 
                'name' => 'number_of_patients',
                'data' => $this->Queries_model->number_of_patients(),
                'color' => $this->getRandomColor(1)
            ],

            'number_of_pairs' => [
                'label' => 'Number of Pairs',
                'name' => 'number_of_pairs',
                'data' => $this->Queries_model->number_of_pairs(),
                'color' => $this->getRandomColor(0.92)
            ],

            'number_for_mrp' => [
                'label' => 'Number of Patients for Zainab',
                'name' => 'number_for_mrp',
                'data' => $this->Queries_model->number_for_mrp(),
                'color' => $this->getRandomColor(0.34)
            ],
        ];

        return $graphs;
    }

    public function getRandomColor($n) {
        $hash = crc32((string)$n);

        $r = ($hash & 0xFF0000) >> 16;
        $g = ($hash & 0x00FF00) >> 8;
        $b = ($hash & 0x0000FF);

        return "rgb($r, $g, $b)";
    }

    public function drawGraphs() {
        $graphs = $this->getPossibleGraphs();
        $labels = [];
        $datasets = [];
        $backgrounds = [];


        $chosenGraphs = $this->input->get('get_labels');

        foreach ($chosenGraphs as $graph) {
            $labels[] = $graphs[$graph]['label'];
            $datasets[] = $graphs[$graph]['data'];
            $backgrounds[] = $graphs[$graph]['color'];
        }

        $labels = $this->session->set_userdata('chart_labels', $labels);
        $datasets = $this->session->set_userdata('chart_datasets', $datasets);
        $backgrounds = $this->session->set_userdata('chart_backgrounds', $backgrounds);
        redirect('Dashboard');
    }
}
