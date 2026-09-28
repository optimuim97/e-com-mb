<?php

namespace Modules\Customer\Exports;

use App\Models\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CustomersExport implements
    FromCollection,
    WithHeadings,
    WithMapping,
    WithStyles,
    ShouldAutoSize,
    WithTitle
{
    use Exportable;

    public function __construct(
        private readonly ?string $dateFrom = null,
        private readonly ?string $dateTo   = null,
        private readonly ?string $country  = null,
    ) {}

    public function collection(): Collection
    {
        return User::with('roles')
            ->withCount('orders')
            ->withSum('orders', 'total')
            ->when($this->dateFrom, fn($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo,   fn($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->when($this->country,  fn($q) => $q->whereHas('orders', fn($o) =>
                $o->whereJsonContains('shipping_address->country', $this->country)
            ))
            ->whereDoesntHave('roles', fn($q) => $q->where('name', 'admin'))
            ->latest()
            ->get();
    }

    public function headings(): array
    {
        return [
            'Nom',
            'Email',
            'Téléphone',
            'Rôle',
            'Nb commandes',
            'Total dépensé (XOF)',
            'Inscrit le',
        ];
    }

    public function map($user): array
    {
        return [
            $user->name,
            $user->email,
            $user->phone ?? '',
            $user->roles->first()?->name ?? 'customer',
            $user->orders_count ?? 0,
            (float) ($user->orders_sum_total ?? 0),
            $user->created_at->format('d/m/Y'),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E8336D'],
                ],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }

    public function title(): string
    {
        return 'Clients';
    }
}
