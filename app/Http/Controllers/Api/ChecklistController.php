<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\ChecklistRunItem;
use App\Models\Wallet;
use App\Services\ChecklistService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ChecklistController extends Controller
{
    public function __construct(private ChecklistService $checklistService)
    {
    }

    public function index(Request $request)
    {
        $checklists = Checklist::with(['wallet', 'activeRun.items.category'])
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($checklists);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:150',
            'wallet_id' => 'nullable|exists:wallets,id',
            'items' => 'required|array|min:1',
            'items.*.name' => 'required|string|max:150',
            'items.*.category_id' => 'required|exists:categories,id',
            'items.*.target_price' => 'required|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->wallet_id) {
            $wallet = Wallet::where('id', $request->wallet_id)->where('user_id', $request->user()->id)->first();
            if (! $wallet) {
                return response()->json(['message' => 'Dompet tidak ditemukan'], 404);
            }
        }

        $itemsError = $this->validateItemCategories($request->items, $request->user()->id);
        if ($itemsError) {
            return $itemsError;
        }

        $checklist = $this->checklistService->createChecklist(
            $request->user(),
            $request->name,
            $request->wallet_id,
            $request->items,
        );

        return response()->json($checklist->load(['wallet', 'items', 'activeRun.items.category']), 201);
    }

    public function show(Request $request, Checklist $checklist)
    {
        if ($checklist->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $checklist->load(['wallet', 'items.category', 'activeRun.items.category']);
        $checklist->loadCount('runs');

        return response()->json($checklist);
    }

    public function update(Request $request, Checklist $checklist)
    {
        if ($checklist->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:150',
            'wallet_id' => 'nullable|exists:wallets,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $checklist->update(['name' => $request->name, 'wallet_id' => $request->wallet_id]);

        return response()->json($checklist->load('wallet'));
    }

    public function destroy(Request $request, Checklist $checklist)
    {
        if ($checklist->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $checklist->delete();

        return response()->json(['message' => 'Checklist dihapus']);
    }

    public function addItem(Request $request, Checklist $checklist)
    {
        if ($checklist->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:150',
            'category_id' => 'required|exists:categories,id',
            'target_price' => 'required|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $itemsError = $this->validateItemCategories([$request->only(['category_id'])], $request->user()->id);
        if ($itemsError) {
            return $itemsError;
        }

        $item = $this->checklistService->addTemplateItem(
            $checklist,
            $request->name,
            (int) $request->category_id,
            (float) $request->target_price,
        );

        return response()->json($item->load('category'), 201);
    }

    public function updateItem(Request $request, ChecklistItem $item)
    {
        if ($item->checklist->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:150',
            'category_id' => 'required|exists:categories,id',
            'target_price' => 'required|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $itemsError = $this->validateItemCategories([$request->only(['category_id'])], $request->user()->id);
        if ($itemsError) {
            return $itemsError;
        }

        $updated = $this->checklistService->updateTemplateItem(
            $item,
            $request->name,
            (int) $request->category_id,
            (float) $request->target_price,
        );

        return response()->json($updated->load('category'));
    }

    public function deleteItem(Request $request, ChecklistItem $item)
    {
        if ($item->checklist->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $this->checklistService->deleteTemplateItem($item);

        return response()->json(['message' => 'Item dihapus']);
    }

    public function startRun(Request $request, Checklist $checklist)
    {
        if ($checklist->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $run = $this->checklistService->startNewRun($checklist);

        return response()->json($run->load('items.category'), 201);
    }

    public function runHistory(Request $request, Checklist $checklist)
    {
        if ($checklist->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($checklist->runs()->with('items')->get());
    }

    public function checkRunItem(Request $request, ChecklistRunItem $runItem)
    {
        if ($runItem->run->checklist->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'actual_price' => 'required|numeric|min:0.01',
            'wallet_id' => 'required|exists:wallets,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $wallet = Wallet::where('id', $request->wallet_id)->where('user_id', $request->user()->id)->first();
        if (! $wallet) {
            return response()->json(['message' => 'Dompet tidak ditemukan'], 404);
        }

        $transaction = $this->checklistService->checkItem(
            $runItem,
            (float) $request->actual_price,
            $wallet->id,
            $request->user(),
        );

        return response()->json([
            'transaction' => $transaction,
            'item' => $runItem->fresh(['category']),
        ]);
    }

    public function uncheckRunItem(Request $request, ChecklistRunItem $runItem)
    {
        if ($runItem->run->checklist->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $this->checklistService->uncheckItem($runItem);

        return response()->json(['item' => $runItem->fresh(['category'])]);
    }

    private function validateItemCategories(array $items, int $userId)
    {
        $categoryIds = collect($items)->pluck('category_id')->filter()->unique();

        $validCount = Category::whereIn('id', $categoryIds)
            ->where('type', 'expense')
            ->where(function ($q) use ($userId) {
                $q->whereNull('user_id')->orWhere('user_id', $userId);
            })
            ->count();

        if ($validCount !== $categoryIds->count()) {
            return response()->json(['message' => 'Kategori item harus kategori pengeluaran yang valid'], 422);
        }

        return null;
    }
}
