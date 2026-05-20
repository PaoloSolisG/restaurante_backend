<?php

namespace App\Exports;

use App\Models\VentaDetalle;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProductosVendidosExport implements FromQuery, WithHeadings, WithMapping, WithTitle, ShouldAutoSize, WithStyles
{
    public function __construct(
        protected string $desde,
        protected string $hasta
    ) {}

    public function title(): string
    {
        return 'Productos Vendidos';
    }

    public function query()
    {
        return VentaDetalle::select([
                'venta_detalles.producto_id',
                'venta_detalles.nombre_producto',
                'venta_detalles.codigo_producto',
                DB::raw('SUM(venta_detalles.cantidad) as total_cantidad'),
                DB::raw('SUM(venta_detalles.subtotal) as total_ingresos'),
                DB::raw('AVG(venta_detalles.precio_unitario) as precio_promedio'),
                DB::raw('COUNT(DISTINCT venta_detalles.venta_id) as total_ventas'),
            ])
            ->join('ventas', 'ventas.id', '=', 'venta_detalles.venta_id')
            ->where('ventas.activo', true)
            ->whereBetween('ventas.created_at', [
                Carbon::parse($this->desde)->startOfDay(),
                Carbon::parse($this->hasta)->endOfDay(),
            ])
            ->groupBy('venta_detalles.producto_id', 'venta_detalles.nombre_producto', 'venta_detalles.codigo_producto')
            ->orderByDesc('total_cantidad');
    }

    public function headings(): array
    {
        return [
            'Código', 'Producto', 'Veces Vendido', 'Cantidad Total', 'Precio Promedio', 'Total Ingresos',
        ];
    }

    public function map($row): array
    {
        return [
            $row->codigo_producto ?? '—',
            $row->nombre_producto,
            $row->total_ventas,
            $row->total_cantidad,
            round($row->precio_promedio, 2),
            round($row->total_ingresos, 2),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FF16A34A']]],
        ];
    }
}
