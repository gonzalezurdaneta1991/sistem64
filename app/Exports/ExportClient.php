<?php

namespace App\Exports;

use App\Models\Client;
use App\Http\Resources\ClientResource;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class ExportClient implements FromCollection,  WithHeadings, ShouldAutoSize, WithEvents
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
        $query = Client::query();

        if ($this->startDate && $this->endDate) {
            $query = $query->whereBetween('created_at', [$this->startDate, $this->endDate]);
        }

        $query->where(function ($query) use ($term) {
            $query->where('name', 'Like', '%' . $term . '%')
                ->orWhere('client_id', 'Like', '%' . $term . '%')
                ->orWhere('email', 'Like', '%' . $term . '%')
                ->orWhere('phone', 'Like', '%' . $term . '%')
                ->orWhere('company_name', 'Like', '%' . $term . '%');
        });

        $clients = ClientResource::collection($query->latest()->get())->map(function ($client) {
            return [
                config('config.clientPrefix') . ' - ' . $client->client_id,
                $client->name,
                $client->phone,
                $client->email,
                $client->company_name,
                $client->status ? 'Active' : 'Inactive',

            ];
        });

        return $clients;
    }

    public function headings(): array
    {
        return [
            'Client ID',
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
