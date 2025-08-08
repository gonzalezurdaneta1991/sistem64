<?php

namespace App\Exports;

use App\Models\InventoryAdjustment;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use App\Http\Resources\AdjustmentListResource;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class ExportInventoryAdjustment implements FromCollection,  WithHeadings, ShouldAutoSize, WithEvents
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
        $query = InventoryAdjustment::with('user');

        if ($this->startDate && $this->endDate) {
            $query = $query->whereBetween('date', [$this->startDate, $this->endDate]);
        }

        $query->where(function ($query) use ($term) {
            $query->where('reason', 'LIKE', '%' . $term . '%')->orWhere('code', 'LIKE', '%' . $term . '%');
        });

        $adjustments = AdjustmentListResource::collection($query->latest()->get())->map(function ($adjustment) {
            return [
                config('config.adjustmentPrefix') . ' - ' . $adjustment->code,
                $adjustment->reason,
                date('jS M, Y', strtotime($adjustment->date)),
                $adjustment->status ? 'Active' : 'Inactive',
            ];
        });

        return $adjustments;
    }

    public function headings(): array
    {
        return [
            'Adjustment No',
            'Reason',
            'Date',
            'Status',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                // Style the header row (headings)
                $event->getSheet()->getDelegate()->getStyle('A1:D1')->applyFromArray([
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
            },
        ];
    }
}
