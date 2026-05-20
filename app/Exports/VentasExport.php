<?php

namespace App\Exports;

use App\Models\Venta;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class VentasExport implements FromQuery, WithHeadings, WithMapping, WithTitle, ShouldAutoSize, WithStyles
{
    public function __construct(
        protected string $desde,
        protected string $hasta,
        protected ?string $metodoPago = null
    ) {}

    public function title(): string
    {
        return 'Ventas';
    }

    public function query()
    {
        $query = Venta::with(['mesa', 'cliente', 'mozo'])
            ->whereBetween('created_at', [
                Carbon::parse($this->desde)->startOfDay(),
                Carbon::parse($this->hasta)->endOfDay(),
            ]);

        if ($this->metodoPago) {
            $query->where('metodo_pago', $this->metodoPago);
        }

        return $query->orderByDesc('created_at');
    }

    public function headings(): array
    {
        return [
            '# Venta', '# Orden', 'Fecha', 'Hora', 'Mesa',
            'Cliente', 'Identificador', 'Mozo',
            'Método Pago', 'Tipo Consumo',
            'Base Imponible', 'IGV 10.5%', 'Propina', 'Descuento', 'Total',
            'Comprobante', 'Estado SUNAT', 'Estado',
        ];
    }

    public function map($v): array
    {
        return [
            $v->id,
            $v->orden_id,
            Carbon::parse($v->created_at)->format('d/m/Y'),
            Carbon::parse($v->created_at)->format('H:i'),
            $v->mesa?->numero ?? '—',
            $v->cliente ? trim("{$v->cliente->nombre} {$v->cliente->apellido}") : 'Sin cliente',
            $v->cliente?->identificador ?? '—',
            $v->mozo ? trim("{$v->mozo->nombre} {$v->mozo->apellido}") : 'Sin mozo',
            ucfirst($v->metodo_pago),
            ucfirst($v->tipo_consumo),
            round($v->base_imponible, 2),
            round($v->igv, 2),
            round($v->propina, 2),
            round($v->descuento, 2),
            round($v->total, 2),
            $v->numero_comprobante ?? '—',
            $v->estado_sunat ?? ($v->tipo_comprobante === 'NV' ? 'Nota de Venta' : '—'),
            $v->activo ? 'Activa' : 'Anulada',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FF1E3A5F']]],
        ];
    }
}
