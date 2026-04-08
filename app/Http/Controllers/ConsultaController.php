<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ConsultaController extends Controller
{
    private $apiKey = '1OQFd2zAopdlkDow2PIutVn0ImD7Dtw9edmT1o7S';
    private $base   = 'https://demov7.valeapp.pe/api/clientes';

    public function consultarDNI(Request $request)
    {
        $request->validate(['dni' => 'required|digits:8']);

        $response = Http::withHeaders(['x-api-key' => $this->apiKey])
            ->asForm()
            ->post("{$this->base}/reniec", ['dni' => $request->dni]);

        if (!$response->ok()) {
            return response()->json(['status' => false, 'message' => 'DNI no encontrado'], 404);
        }

        $arr = $response->json('cliente');

        return response()->json([
            'status'   => true,
            'nombre'   => $arr[6] ?? '',
            'apellido' => trim(($arr[4] ?? '') . ' ' . ($arr[5] ?? ''))
        ]);
    }

    public function consultarRUC(Request $request)
    {
        $request->validate(['ruc' => 'required|digits:11']);

        $response = Http::withHeaders(['x-api-key' => $this->apiKey])
            ->asForm()
            ->post("{$this->base}/sunat", ['ruc' => $request->ruc]);

        if (!$response->ok()) {
            return response()->json(['status' => false, 'message' => 'RUC no encontrado'], 404);
        }

        $c = $response->json('cliente');

        if (($c['result'] ?? '0') !== '1') {
            return response()->json(['status' => false, 'message' => 'RUC no encontrado'], 404);
        }

        return response()->json([
            'status'    => true,
            'nombre'    => $c['RazonSocial'] ?? '',
            'apellido'  => '',
            'direccion' => $c['Direccion'] ?? ''
        ]);
    }
}