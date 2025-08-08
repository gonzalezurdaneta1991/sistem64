<?php

namespace App\Exports;

use App\Models\LoanAuthority;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class ExportLoanAuthority implements FromCollection,  WithHeadings, ShouldAutoSize, WithEvents
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

        $query = LoanAuthority::where('name', 'LIKE', '%' . $term . '%')
            ->orWhere('slug', 'LIKE', '%' . $term . '%')
            ->orWhere('cc_limit', 'LIKE', '%' . $term . '%')
            ->orWhere('email', 'LIKE', '%' . $term . '%')
            ->orWhere('contact_number', 'LIKE', '%' . $term . '%');

        // Retrieve Loan Authorities
        return  $query->latest()->get()->map(function ($LoanAuthority) {
            $currencySymbol = getGeneralSettingsInfo()['currency']['symbol'];
            return [
                $LoanAuthority->name ?? 'N/A',
                $LoanAuthority->email,
                $LoanAuthority->status ? 'Active' : 'Inactive',
                $LoanAuthority->contact_number,
                $currencySymbol . strval($LoanAuthority->cc_limit),
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Name',
            'Email',
            'Status',
            'Contact Number',
            'CC Limit',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                // Style the header row (headings)
                $event->getSheet()->getDelegate()->getStyle('A1:E1')->applyFromArray([
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
