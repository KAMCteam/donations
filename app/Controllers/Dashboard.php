<?php

namespace App\Controllers;

use App\Models\QueriesModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Chart dashboard. Migrated from the CodeIgniter 3 Dashboard controller.
 */
class Dashboard extends BaseController
{
    public function index(): string
    {
        return view('dashboard', [
            'graphs'      => $this->getPossibleGraphs(),
            'labels'      => json_encode($this->session->get('chart_labels') ?? []),
            'datasets'    => json_encode($this->session->get('chart_datasets') ?? []),
            'backgrounds' => json_encode($this->session->get('chart_backgrounds') ?? []),
        ]);
    }

    /**
     * @return array<string, array{label: string, name: string, data: int, color: string}>
     */
    public function getPossibleGraphs(): array
    {
        $queries = model(QueriesModel::class);

        return [
            'number_of_patients' => [
                'label' => 'Number of Patients',
                'name'  => 'number_of_patients',
                'data'  => $queries->number_of_patients(),
                'color' => $this->getRandomColor(1),
            ],

            'number_of_pairs' => [
                'label' => 'Number of Pairs',
                'name'  => 'number_of_pairs',
                'data'  => $queries->number_of_pairs(),
                'color' => $this->getRandomColor(0.92),
            ],

            'number_for_mrp' => [
                'label' => 'Number of Patients for Zainab',
                'name'  => 'number_for_mrp',
                'data'  => $queries->number_for_mrp(),
                'color' => $this->getRandomColor(0.34),
            ],
        ];
    }

    /**
     * A stable colour per seed, so a bar keeps its colour between requests.
     */
    public function getRandomColor(float|int $n): string
    {
        $hash = crc32((string) $n);

        $r = ($hash & 0xFF0000) >> 16;
        $g = ($hash & 0x00FF00) >> 8;
        $b = $hash & 0x0000FF;

        return "rgb({$r}, {$g}, {$b})";
    }

    /**
     * Stores the picked series in the session, then re-renders the dashboard.
     */
    public function drawGraphs(): RedirectResponse
    {
        $graphs      = $this->getPossibleGraphs();
        $labels      = [];
        $datasets    = [];
        $backgrounds = [];

        $chosenGraphs = $this->request->getGet('get_labels') ?? [];

        foreach ($chosenGraphs as $graph) {
            if (! isset($graphs[$graph])) {
                continue;
            }

            $labels[]      = $graphs[$graph]['label'];
            $datasets[]    = $graphs[$graph]['data'];
            $backgrounds[] = $graphs[$graph]['color'];
        }

        $this->session->set('chart_labels', $labels);
        $this->session->set('chart_datasets', $datasets);
        $this->session->set('chart_backgrounds', $backgrounds);

        return redirect()->to(site_url('Dashboard'));
    }
}
