<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\AssetAuditLog;
use App\Models\AssetCategory;
use App\Models\AssetInspectionDocument;
use App\Models\AssetLocation;
use App\Models\ItAsset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AssetController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user()->load('role.permissions', 'permissionOverrides', 'employee.department');

        abort_unless($user->canAccessItAssets(), 403);

        $query = ItAsset::with('category', 'location', 'owner.employee.department');
        $status = $request->string('status')->toString();
        $categoryId = $request->integer('category_id') ?: null;
        $locationId = $request->integer('location_id') ?: null;
        $q = trim($request->string('q')->toString());

        if ($status !== '' && in_array($status, ItAsset::STATUSES, true)) {
            $query->where('status', $status);
        } else {
            $status = '';
        }

        if ($categoryId) {
            $query->where('asset_category_id', $categoryId);
        }

        if ($locationId) {
            $query->where('asset_location_id', $locationId);
        }

        if ($q !== '') {
            $query->where(function ($assetQuery) use ($q) {
                $assetQuery->where('code', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%")
                    ->orWhere('brand', 'like', "%{$q}%")
                    ->orWhere('model', 'like', "%{$q}%")
                    ->orWhere('serial_number', 'like', "%{$q}%")
                    ->orWhere('owner_name', 'like', "%{$q}%")
                    ->orWhere('department', 'like', "%{$q}%");
            });
        }

        $baseAssetQuery = ItAsset::query();

        return view('assets.index', [
            'assets' => $query->latest()->paginate(12)->withQueryString(),
            'categories' => AssetCategory::withCount('assets')->orderBy('code')->get(),
            'locations' => AssetLocation::withCount('assets')->orderBy('code')->get(),
            'inspectionDocuments' => AssetInspectionDocument::with('location', 'creator')->latest('inspection_date')->take(8)->get(),
            'auditLogs' => AssetAuditLog::with('asset', 'user')->latest()->take(12)->get(),
            'status' => $status,
            'categoryId' => $categoryId,
            'locationId' => $locationId,
            'q' => $q,
            'assetCount' => (clone $baseAssetQuery)->count(),
            'activeCount' => (clone $baseAssetQuery)->where('status', 'active')->count(),
            'repairCount' => (clone $baseAssetQuery)->where('status', 'repair')->count(),
            'lostCount' => (clone $baseAssetQuery)->where('status', 'lost')->count(),
            'totalValue' => (clone $baseAssetQuery)->sum('price'),
            'canManageAssets' => $user->canManageItAssets(),
            'canExportAssets' => $user->canExportItAssets(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canManageItAssets(), 403);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:80', 'unique:it_assets,code'],
            'name' => ['required', 'string', 'max:255'],
            'asset_category_id' => ['nullable', 'exists:asset_categories,id'],
            'asset_location_id' => ['nullable', 'exists:asset_locations,id'],
            'company' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'owner_name' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(ItAsset::STATUSES)],
            'brand' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'book_value' => ['nullable', 'numeric', 'min:0'],
            'purchased_at' => ['nullable', 'date'],
            'warranty_until' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $asset = ItAsset::create([
            ...$data,
            'company' => $data['company'] ?? 'WDC',
            'price' => $data['price'] ?? 0,
            'book_value' => $data['book_value'] ?? ($data['price'] ?? 0),
        ]);

        $this->logAsset($request, $asset, 'create_asset', "Created {$asset->code} {$asset->name}", null, $asset->toArray());

        return back()->with('status', 'เพิ่มทรัพย์สิน IT เรียบร้อยแล้ว');
    }

    public function updateStatus(ItAsset $asset, Request $request): RedirectResponse
    {
        abort_unless($request->user()->canManageItAssets(), 403);

        $data = $request->validate([
            'status' => ['required', Rule::in(ItAsset::STATUSES)],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $before = $asset->only(['status', 'notes']);
        $asset->update([
            'status' => $data['status'],
            'notes' => $data['notes'] ?? $asset->notes,
        ]);

        $this->logAsset($request, $asset, 'update_asset_status', "Changed {$asset->code} to {$asset->status}", $before, $asset->only(['status', 'notes']));

        return back()->with('status', 'อัปเดตสถานะทรัพย์สินแล้ว');
    }

    public function storeInspection(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canManageItAssets(), 403);

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:80', 'unique:asset_inspection_documents,code'],
            'asset_location_id' => ['nullable', 'exists:asset_locations,id'],
            'inspection_date' => ['required', 'date'],
            'company' => ['nullable', 'string', 'max:255'],
            'item_count' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', 'in:open,closed'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $document = AssetInspectionDocument::create([
            ...$data,
            'created_by' => $request->user()->id,
            'code' => $data['code'] ?? $this->nextInspectionCode(),
            'company' => $data['company'] ?? 'WDC',
            'item_count' => $data['item_count'] ?? 0,
        ]);

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'create_asset_inspection',
            'subject_type' => AssetInspectionDocument::class,
            'subject_id' => $document->id,
            'description' => "Created asset inspection {$document->code}",
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);

        return back()->with('status', 'สร้างเอกสารตรวจนับทรัพย์สินแล้ว');
    }

    public function export(Request $request)
    {
        abort_unless($request->user()->canExportItAssets(), 403);

        $rows = ItAsset::with('category', 'location')->orderBy('code')->get();
        $csvRows = [
            ['code', 'name', 'category', 'location', 'company', 'department', 'owner', 'status', 'brand', 'model', 'serial_number', 'price', 'book_value'],
            ...$rows->map(fn (ItAsset $asset) => [
                $asset->code,
                $asset->name,
                $asset->category?->name,
                $asset->location?->code,
                $asset->company,
                $asset->department,
                $asset->owner_name,
                $asset->status,
                $asset->brand,
                $asset->model,
                $asset->serial_number,
                $asset->price,
                $asset->book_value,
            ])->all(),
        ];

        $content = collect($csvRows)
            ->map(fn (array $row) => collect($row)->map(fn ($value) => $this->csvEscape($value))->implode(','))
            ->implode("\n");

        return Response::make($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="wdc-it-assets.csv"',
        ]);
    }

    private function nextInspectionCode(): string
    {
        return 'AST-CHK-'.now()->format('Ymd').'-'.str_pad((string) (AssetInspectionDocument::whereDate('created_at', today())->count() + 1), 4, '0', STR_PAD_LEFT);
    }

    private function csvEscape($value): string
    {
        $text = (string) ($value ?? '');

        return str_contains($text, ',') || str_contains($text, '"') || str_contains($text, "\n")
            ? '"'.str_replace('"', '""', $text).'"'
            : $text;
    }

    private function logAsset(Request $request, ItAsset $asset, string $action, string $summary, ?array $before = null, ?array $after = null): void
    {
        AssetAuditLog::create([
            'it_asset_id' => $asset->id,
            'user_id' => $request->user()->id,
            'action' => $action,
            'summary' => $summary,
            'before' => $before,
            'after' => $after,
        ]);

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'subject_type' => ItAsset::class,
            'subject_id' => $asset->id,
            'description' => $summary,
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);
    }
}
