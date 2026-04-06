<?php

namespace App\Events;

use App\Models\Orden;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow; // Usamos "Now" para que sea instantáneo
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrdenCreada implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $orden;

    /**
     * Al crear el evento, le pasamos la orden con sus detalles
     */
    public function __construct(Orden $orden)
    {
        $this->orden = $orden;
    }

    /**
     * El nombre del canal por donde va a viajar el mensaje
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('ordenes-canal'),
        ];
    }

    /**
     * El nombre del "suceso" que el Frontend (Vue) debe escuchar
     */
    public function broadcastAs(): string
    {
        return 'orden.nueva';
    }
}