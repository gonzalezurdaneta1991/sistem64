<?php

namespace App\Exports;

use App\Models\Supplier;
use App\Http\Resources\SupplierResource;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ExportSupplierPayableReport implements FromCollection,  WithHeadings, ShouldAutoSize, WithEvents
{
    protected $term;

    public function __construct($term)
    {
        $this->term = $term;
    }


    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $term = $this->term;
        $query = Supplier::query();

        $query->where(function ($query) use ($term) {
            $query->where('name', 'Like', '%' . $term . '%')
                ->orWhere('email', 'Like', '%' . $term . '%')
                ->orWhere('phone', 'Like', '%' . $term . '%')
                ->orWhere('company_name', 'Like', '%' . $term . '%');
        });

        $supplierPayableReports = SupplierResource::collection($query->latest()->get())->map(function ($supplierPayableReport) {
            $currencySymbol = getGeneralSettingsInfo()['currency']['symbol'];
            return [
                config('config.supplierPrefix') . ' - ' . $supplierPayableReport->supplier_id,
                $supplierPayableReport->status ? 'Active' : 'Inactive',
                $supplierPayableReport->name,
                $supplierPayableReport->phone,
                $supplierPayableReport->email,
                $supplierPayableReport->company_name,
                $currencySymbol . strval($supplierPayableReport->purchase_due ?: '0'),
                $currencySymbol . strval($supplierPayableReport->non_purchase_due ?: '0'),
                $currencySymbol . strval($supplierPayableReport->purchase_due + $supplierPayableReport->non_purchase_due ?: '0'),
            ];
        });

        $totalInvoiceDue = $supplierPayableReports->sum(function ($row) {
            return floatval(str_replace(getGeneralSettingsInfo()['currency']['symbol'], '',  $row[6] ?? 0));
        });
        $totalNonInvoiceDue = $supplierPayableReports->sum(function ($row) {
            return floatval(str_replace(getGeneralSettingsInfo()['currency']['symbol'], '',  $row[7] ?? 0));
        });
        $totalDue = $supplierPayableReports->sum(function ($row) {
            return floatval(str_replace(getGeneralSettingsInfo()['currency']['symbol'], '',  $row[8] ?? 0));
        });

        // Add the total salary as a new row
        $supplierPayableReports->push([
            '', '', '', '', '', '', 'Total Invoice Due = ' . getGeneralSettingsInfo()['currency']['symbol'] . $totalInvoiceDue, 'Total Non Invoice Due = ' . getGeneralSettingsInfo()['currency']['symbol'] . $totalNonInvoiceDue, 'Total Due = ' . getGeneralSettingsInfo()['currency']['symbol'] . $totalDue,
        ]);

        return $supplierPayableReports;
    }

    public function headings(): array
    {
        return [
            'Supplier ID',
            'Status',
            'Name',
            'Contact Number',
            'Email',
            'Company Name',
            'Purchase Due',
            'Non Purchase Due',
            'Total Due',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                // Get the last row index
                $lastRow = $event->getSheet()->getDelegate()->getHighestRow();

                // Style the last row (total amount row)
                $event->getSheet()->getDelegate()->getStyle('A' . $lastRow . ':I' . $lastRow)->applyFromArray([
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
                $event->getSheet()->getDelegate()->getStyle('A1:I1')->applyFromArray([
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
                $event->getSheet()->getDelegate()->getStyle('G1')->applyFromArray([
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_RIGHT,
                    ],
                ]);
                $event->getSheet()->getDelegate()->getStyle('H1')->applyFromArray([
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_RIGHT,
                    ],
                ]);
                $event->getSheet()->getDelegate()->getStyle('I1')->applyFromArray([
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_RIGHT,
                    ],
                ]);
                // Align column to the right
                $columnG = 'G';
                $event->getSheet()->getDelegate()->getStyle("{$columnG}2:{$columnG}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $columnH = 'H';
                $event->getSheet()->getDelegate()->getStyle("{$columnH}2:{$columnH}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $columnI = 'I';
                $event->getSheet()->getDelegate()->getStyle("{$columnI}2:{$columnI}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            },
        ];
    }
}
