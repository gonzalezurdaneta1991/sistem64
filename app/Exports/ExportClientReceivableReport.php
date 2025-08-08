<?php

namespace App\Exports;

use App\Models\Client;
use App\Http\Resources\ClientResource;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ExportClientReceivableReport implements FromCollection,  WithHeadings, ShouldAutoSize, WithEvents
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
        $query = Client::query();

        $query->where(function ($query) use ($term) {
            $query->where('name', 'Like', '%' . $term . '%')
                ->orWhere('client_id', 'Like', '%' . $term . '%')
                ->orWhere('email', 'Like', '%' . $term . '%')
                ->orWhere('phone', 'Like', '%' . $term . '%')
                ->orWhere('company_name', 'Like', '%' . $term . '%');
        });

        $clientReceivableReports = ClientResource::collection($query->latest()->get())->map(function ($clientReceivableReport) {
            $currencySymbol = getGeneralSettingsInfo()['currency']['symbol'];
            return [
                config('config.clientPrefix') . ' - ' . $clientReceivableReport->client_id,
                $clientReceivableReport->status ? 'Active' : 'Inactive',
                $clientReceivableReport->name,
                $clientReceivableReport->phone,
                $clientReceivableReport->email,
                $clientReceivableReport->company_name,
                $currencySymbol . strval($clientReceivableReport->client_invoice_due ?: '0'),
                $currencySymbol . strval($clientReceivableReport->client_non_invoice_due ?: '0'),
                $currencySymbol . strval($clientReceivableReport->client_invoice_due + $clientReceivableReport->client_non_invoice_due ?: '0'),
            ];
        });

        $totalInvoiceDue = $clientReceivableReports->sum(function ($row) {
            return floatval(str_replace(getGeneralSettingsInfo()['currency']['symbol'], '', $row[6]  ?? 0));
        });
        $totalNonInvoiceDue = $clientReceivableReports->sum(function ($row) {
            return floatval(str_replace(getGeneralSettingsInfo()['currency']['symbol'], '', $row[7]  ?? 0));
        });
        $totalDue = $clientReceivableReports->sum(function ($row) {
            return floatval(str_replace(getGeneralSettingsInfo()['currency']['symbol'], '', $row[8]  ?? 0));
        });

        // Add the total salary as a new row
        $clientReceivableReports->push([
            '', '', '', '', '', '', 'Total Invoice Due = ' . getGeneralSettingsInfo()['currency']['symbol'] . $totalInvoiceDue, 'Total Non Invoice Due = ' . getGeneralSettingsInfo()['currency']['symbol'] . $totalNonInvoiceDue, 'Total Due = ' . getGeneralSettingsInfo()['currency']['symbol'] . $totalDue,
        ]);

        return $clientReceivableReports;
    }

    public function headings(): array
    {
        return [
            'Client ID',
            'Status',
            'Name',
            'Contact Number',
            'Email',
            'Company Name',
            'Invoice Due',
            'Non Invoice Due',
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
