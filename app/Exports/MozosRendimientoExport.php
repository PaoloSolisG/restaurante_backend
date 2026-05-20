<?php

namespace App\Exports;

use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MozosRendimientoExport implements FromQuery, WithHeadings, WithMapping, WithTitle, ShouldAutoSize, WithStyles
{
    public function __construct(
        protected string $desde,
        protected string $hasta
    ) {}

    public function title(): string
    {
        return 'Rendimiento Mozos';
    }

    public function query()
    {
        return Venta::select([
                'mozos.id as mozo_id',
                DB::raw("CONCAT(mozos.nombre, ' ', IFNULL(mozos.apellido,'')) as mozo_nombre"),
                DB::raw('COUNT(ventas.id) as total_ventas'),
                DB::raw('SUM(ventas.total) as total_facturado'),
                DB::raw('SUM(ventas.propina) as total_propinas'),
                DB::raw('AVG(ventas.total) as ticket_promedio'),
            ])
            ->join('mozos', 'mozos.id', '=', 'ventas.mozo_id')
            ->where('ventas.activo', true)
            ->whereBetween('ventas.created_at', [
                Carbon::parse($this->desde)->startOfDay(),
                Carbon::parse($this->hasta)->endOfDay(),
            ])
            ->groupBy('mozos.id', 'mozos.nombre', 'mozos.apellido')
            ->orderByDesc('total_facturado');
    }

    public function headings(): array
    {
        return [
            'Mozo', 'Total Ventas', 'Total Facturado (S/)', 'Total Propinas (S/)', 'Ticket Promedio (S/)',
        ];
    }

    public function map($row): array
    {
        return [
            trim($row->mozo_nombre),
            $row->total_ventas,
            round($row->total_facturado, 2),
            round($row->total_propinas, 2),
            round($row->ticket_promedio, 2),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FF7C3AED']]],
        ];
    }
}
