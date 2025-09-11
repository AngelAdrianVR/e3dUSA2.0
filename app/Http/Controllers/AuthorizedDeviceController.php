<?php

namespace App\Http\Controllers;

use App\Models\AuthorizedDevice;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;

class AuthorizedDeviceController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $this->authorize('Gestionar quioscos'); // Necesitarás este permiso

        return inertia('AuthorizedDevice/Index', [
            'devices' => AuthorizedDevice::with('creator:id,name')->latest()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('Gestionar quioscos');

        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $token = Str::random(60);

        AuthorizedDevice::create([
            'name' => $request->name,
            'identifier' => $token,
            'created_by' => Auth::id(),
        ]);

        // Redirigir y adjuntar la cookie a la respuesta del servidor.
        // La cookie tendrá una duración de 5 años.
        return redirect()->route('authorized-devices.index')
            ->with('success', 'Dispositivo autorizado correctamente.')
            ->cookie('attendance_device_token', $token, 60 * 24 * 365 * 5);
    }

    public function destroy(AuthorizedDevice $authorizedDevice)
    {
        $this->authorize('Gestionar quioscos');

        $authorizedDevice->delete();

        // Redirigir y enviar una cookie expirada para que el navegador la elimine.
        return redirect()->route('authorized-devices.index')
            ->with('success', 'Dispositivo desautorizado.')
            ->withCookie(Cookie::forget('attendance_device_token'));
    }
}
