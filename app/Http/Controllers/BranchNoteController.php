<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\BranchNote;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BranchNoteController extends Controller
{
    /**
     * Muestra una lista de las notas para una sucursal.
     *
     * @param Branch $branch
     * @return JsonResponse
     */
    public function index(Branch $branch)
    {
        // Carga las notas relacionadas con la sucursal,
        // incluyendo solo los campos necesarios del usuario asociado.
        // Finalmente, las ordena por fecha de creación descendente.
        $notes = $branch->notes()
                       ->with('user:id,name,profile_photo_path')
                       ->latest()
                       ->get();

        return response()->json($notes);
    }
    public function store(Request $request)
    {
        $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'content' => 'required|string',
        ]);

        $note = BranchNote::create([
            'branch_id' => $request->branch_id,
            'content' => $request->content,
            'user_id' => auth()->id(),
        ]);

        return response()->json($note->load('user'));
    }

    public function update(Request $request, BranchNote $branchNote)
    {
        $request->validate(['content' => 'required|string']);
        $branchNote->update(['content' => $request->content]);
        return response()->json($branchNote->load('user'));
    }

    public function destroy(BranchNote $branchNote)
    {
        $branchNote->delete();
        return response()->json(['message' => 'Nota eliminada.']);
    }
}
