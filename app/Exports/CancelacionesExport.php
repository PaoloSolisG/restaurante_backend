<?php

namespace App\Exports;

use App\Models\Orden;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CancelacionesExport implements FromQuery, WithHeadings, WithMapping, WithTitle, ShouldAutoSize, WithStyles
{
    public function __construct(
        protected string $desde,
        protected string $hasta
    ) {}

    public function title(): string
    {
        return 'Cancelaciones';
    }

    public function query()
    {
        return Orden::with(['mesa', 'mozo'])
            ->where('estado', 'cancelado')
            ->whereBetween('created_at', [
                Carbon::parse($this->desde)->startOfDay(),
                Carbon::parse($this->hasta)->endOfDay(),
            ])
            ->orderByDesc('created_at');
    }

    public function headings(): array
    {
        return [
            '# Orden', 'Fecha', 'Hora', 'Mesa', 'Mozo', 'Origen',
            'Total (S/)', 'Motivo de Cancelación',
        ];
    }

    public function map($o): array
    {
        // El motivo se guarda en notas con prefijo "CANCELADO: "
        $motivo = $o->notas ?? '—';
        if (str_starts_with($motivo, 'CANCELADO: ')) {
            $motivo = substr($motivo, strlen('CANCELADO: '));
        }

        return [
            $o->id,
            Carbon::parse($o->created_at)->format('d/m/Y'),
            Carbon::parse($o->created_at)->format('H:i'),
            $o->mesa?->numero ?? '—',
            $o->mozo ? trim("{$o->mozo->nombre} {$o->mozo->apellido}") : 'Sin mozo',
            ucfirst($o->origen ?? 'mozo'),
            round($o->total, 2),
            $motivo,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FFDC2626']]],
        ];
    }
}
