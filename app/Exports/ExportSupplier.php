<?php

namespace App\Exports;

use App\Models\Supplier;
use App\Http\Resources\SupplierResource;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class ExportSupplier implements FromCollection, WithHeadings, ShouldAutoSize, WithEvents
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
        $query = Supplier::query();

        if ($this->startDate && $this->endDate) {
            $query = $query->whereBetween('created_at', [$this->startDate, $this->endDate]);
        }

        $query->where(function ($query) use ($term) {
            $query->where('name', 'Like', '%' . $term . '%')
                ->orWhere('email', 'Like', '%' . $term . '%')
                ->orWhere('phone', 'Like', '%' . $term . '%')
                ->orWhere('company_name', 'Like', '%' . $term . '%');
        });

        $suppliers = SupplierResource::collection($query->latest()->get())->map(function ($supplier) {
            return [
                config('config.supplierPrefix') . ' - ' . $supplier->supplier_id,
                $supplier->name,
                $supplier->phone,
                $supplier->email,
                $supplier->company_name,
                $supplier->status ? 'Active' : 'Inactive',

            ];
        });

        return $suppliers;
    }

    public function headings(): array
    {
        return [
            'Supplier ID',
            'Name',
            'Contact Number',
            'Email',
            'Company Name',
            'Status'
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
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
            },
        ];
    }
}
