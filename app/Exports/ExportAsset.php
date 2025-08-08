<?php

namespace App\Exports;

use App\Models\Asset;
use App\Http\Resources\AssetResource;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ExportAsset implements FromCollection,  WithHeadings, ShouldAutoSize, WithEvents
{
    protected $startDate;
    protected $endDate;
    protected $term;

    public function __construct($startDate, $endDate, $term)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->term = $term;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $term = $this->term;
        $query = Asset::with('assetType');

        if ($this->startDate && $this->endDate) {
            $query = $query->whereBetween('date', [$this->startDate, $this->endDate]);
        }

        $query->where(function ($query) use ($term) {
            $query->where('name', 'LIKE', '%' . $term . '%')
                ->orWhere('asset_cost', 'LIKE', '%' . $term . '%')
                ->orWhere('salvage_value', 'LIKE', '%' . $term . '%')
                ->orWhere('useful_life', 'LIKE', '%' . $term . '%')
                ->orWhereHas('assetType', function ($newQuery) use ($term) {
                    $newQuery->where('name', 'LIKE', '%' . $term . '%');
                });
        });

        $assets = AssetResource::collection($query->latest()->get())->map(function ($asset) {
            $currencySymbol = getGeneralSettingsInfo()['currency']['symbol'];
            return [
                $asset->name,
                date('jS M, Y', strtotime($asset->date)),
                $asset->status ? 'Active' : 'Inactive',
                $asset->assetType?->name,
                $currencySymbol . strval($asset->asset_cost),
                $currencySymbol . strval($asset->currentValue()),
            ];
        });

        $totalCost = $assets->sum(function ($row) {
            return floatval(str_replace(getGeneralSettingsInfo()['currency']['symbol'], '', $row[4]  ?? 0));
        });

        $currentValue = $assets->sum(function ($row) {
            return floatval(str_replace(getGeneralSettingsInfo()['currency']['symbol'], '', $row[5]  ?? 0));
        });

        // Add the total paid as a new row
        $assets->push([
            '', '', '', '', 'Total Cost = ' . getGeneralSettingsInfo()['currency']['symbol'] . $totalCost, 'Total Value = ' . getGeneralSettingsInfo()['currency']['symbol'] . $currentValue,
        ]);

        return $assets;
    }

    public function headings(): array
    {
        return [
            'Asset Name',
            'Created At',
            'Status',
            'Asset Type',
            'Asset Cost',
            'Current Value',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                // Get the last row index
                $lastRow = $event->getSheet()->getDelegate()->getHighestRow();

                // Style the last row (total amount row)
                $event->getSheet()->getDelegate()->getStyle('A' . $lastRow . ':F' . $lastRow)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 13,
                    ],
                    'fill' => [
                        'fillType' => 'solid',
                        'startColor' => [
                            'rgb' => '00FF00',
                        ],
                    ],
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT,
                    ],
                ]);

                // Style the header row (headings)
                $event->getSheet()->getDelegate()->getStyle('A1:F1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 13,
                    ],
                    'fill' => [
                        'fillType' => 'solid',
                        'startColor' => [
                            'rgb' => '00FF00',
                        ],
                    ],
                ]);

                // Style the header row (headings)
                $event->getSheet()->getDelegate()->getStyle('E1')->applyFromArray([
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_RIGHT,
                    ],
                ]);
                $event->getSheet()->getDelegate()->getStyle('F1')->applyFromArray([
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_RIGHT,
                    ],
                ]);
                // Align column to the right
                $columnE = 'E';
                $event->getSheet()->getDelegate()->getStyle("{$columnE}2:{$columnE}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $columnF = 'F';
                $event->getSheet()->getDelegate()->getStyle("{$columnF}2:{$columnF}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            },
        ];
    }
}
