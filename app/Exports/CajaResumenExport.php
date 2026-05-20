<?php

namespace App\Exports;

use App\Models\Caja;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CajaResumenExport implements FromQuery, WithHeadings, WithMapping, WithTitle, ShouldAutoSize, WithStyles
{
    public function __construct(
        protected string $desde,
        protected string $hasta
    ) {}

    public function title(): string
    {
        return 'Resumen Cajas';
    }

    public function query()
    {
        return Caja::whereBetween('apertura_at', [
            Carbon::parse($this->desde)->startOfDay(),
            Carbon::parse($this->hasta)->endOfDay(),
        ])->orderByDesc('apertura_at');
    }

    public function headings(): array
    {
        return [
            '# Caja', 'Apertura', 'Cierre', 'Monto Inicial',
            'Efectivo', 'Tarjeta', 'Yape', 'Plin', 'Depósito', 'Mixto',
            'Total Esperado', 'Total Real', 'Diferencia', 'Estado',
        ];
    }

    public function map($c): array
    {
        $estado = $c->estado ?? ($c->cierre_at ? 'cerrada' : 'abierta');
        return [
            $c->id,
            $c->apertura_at ? Carbon::parse($c->apertura_at)->format('d/m/Y H:i') : '—',
            $c->cierre_at  ? Carbon::parse($c->cierre_at)->format('d/m/Y H:i')  : 'Aún abierta',
            round($c->monto_inicial, 2),
            round($c->total_efectivo ?? 0, 2),
            round($c->total_tarjeta  ?? 0, 2),
            round($c->total_yape     ?? 0, 2),
            round($c->total_plin     ?? 0, 2),
            round($c->total_deposito ?? 0, 2),
            round($c->total_mixto    ?? 0, 2),
            round($c->monto_esperado ?? 0, 2),
            round($c->monto_real     ?? 0, 2),
            round($c->diferencia     ?? 0, 2),
            ucfirst($estado),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FFD97706']]],
        ];
    }
}
