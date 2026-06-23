<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\AssetAuditLog;
use App\Models\AssetCategory;
use App\Models\AssetInspectionDocument;
use App\Models\AssetLocation;
use App\Models\ItAsset;
use App\Models\User;
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
            'manageableUsers' => $user->canManageItAssets() ? User::with('employee.department')->where('is_active', true)->orderBy('name')->get() : collect(),
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
            'owner_id' => ['nullable', 'exists:users,id'],
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

        $owner = ! empty($data['owner_id']) ? User::with('employee.department')->find($data['owner_id']) : null;

        $asset = ItAsset::create([
            ...$data,
            'company' => $data['company'] ?? 'WDC',
            'owner_name' => $owner?->name ?? ($data['owner_name'] ?? null),
            'department' => $owner?->employee?->department?->name ?? ($data['department'] ?? null),
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

    public function storeCategory(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canManageItAssets(), 403);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'unique:asset_categories,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        AssetCategory::create($data);

        $this->logGeneral($request, 'create_asset_category', AssetCategory::class, null, "Created asset category {$data['code']}");

        return back()->with('status', 'เพิ่มหมวดหมู่ทรัพย์สินแล้ว');
    }

    public function storeLocation(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canManageItAssets(), 403);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:60'],
            'name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'has_gps' => ['nullable', 'boolean'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'radius_meters' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        $location = AssetLocation::updateOrCreate([
            'code' => $data['code'],
            'company' => $data['company'] ?? 'WDC',
        ], [
            ...$data,
            'company' => $data['company'] ?? 'WDC',
            'has_gps' => $request->boolean('has_gps'),
        ]);

        $this->logGeneral($request, 'create_asset_location', AssetLocation::class, $location->id, "Saved asset location {$data['code']}");

        return back()->with('status', 'เพิ่มสถานที่จัดเก็บทรัพย์สินแล้ว');
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

    public function exportMaster(Request $request)
    {
        abort_unless($request->user()->canExportItAssets(), 403);

        $payload = [
            'exported_at' => now()->toIso8601String(),
            'source' => 'WDC Portal IT Asset',
            'categories' => AssetCategory::withCount('assets')->orderBy('code')->get(),
            'locations' => AssetLocation::withCount('assets')->orderBy('code')->get(),
            'assets' => ItAsset::with('category', 'location')->orderBy('code')->get()->map(fn (ItAsset $asset) => [
                'code' => $asset->code,
                'name' => $asset->name,
                'category' => $asset->category?->code,
                'location' => $asset->location?->code,
                'company' => $asset->company,
                'department' => $asset->department,
                'owner_name' => $asset->owner_name,
                'status' => $asset->status,
                'brand' => $asset->brand,
                'model' => $asset->model,
                'serial_number' => $asset->serial_number,
            ]),
        ];

        return Response::make(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="wdc-it-asset-master.json"',
        ]);
    }

    public function importSync(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canManageItAssets(), 403);

        $data = $request->validate([
            'sync_file' => ['required', 'file', 'mimes:json,txt', 'max:4096'],
        ]);

        $payload = json_decode((string) file_get_contents($data['sync_file']->getRealPath()), true);

        if (! is_array($payload)) {
            return back()->withErrors(['sync_file' => 'ไฟล์ Sync ต้องเป็น JSON ที่อ่านได้']);
        }

        $updatedAssets = 0;
        $createdDocuments = 0;
        $assets = collect($payload['assets'] ?? [])->filter(fn ($row) => is_array($row));

        foreach ($assets as $incoming) {
            $code = trim((string) ($incoming['code'] ?? ''));

            if ($code === '') {
                continue;
            }

            $asset = ItAsset::where('code', $code)->first();

            if (! $asset) {
                continue;
            }

            $before = $asset->only(['status', 'department', 'owner_name', 'notes']);
            $asset->update([
                'status' => in_array(($incoming['status'] ?? $asset->status), ItAsset::STATUSES, true) ? $incoming['status'] : $asset->status,
                'department' => $incoming['department'] ?? $asset->department,
                'owner_name' => $incoming['owner_name'] ?? ($incoming['owner'] ?? $asset->owner_name),
                'notes' => $incoming['notes'] ?? $asset->notes,
            ]);

            $this->logAsset($request, $asset, 'sync_asset', "Synced offline result for {$asset->code}", $before, $asset->only(['status', 'department', 'owner_name', 'notes']));
            $updatedAssets++;
        }

        $documents = collect($payload['documents'] ?? [])->filter(fn ($row) => is_array($row));

        foreach ($documents as $document) {
            $code = trim((string) ($document['code'] ?? ''));

            if ($code === '') {
                $code = $this->nextInspectionCode();
            }

            AssetInspectionDocument::updateOrCreate(
                ['code' => $code],
                [
                    'created_by' => $request->user()->id,
                    'inspection_date' => $document['inspection_date'] ?? $document['date'] ?? today(),
                    'company' => $document['company'] ?? 'WDC',
                    'item_count' => (int) ($document['item_count'] ?? $document['count'] ?? 0),
                    'status' => $document['status'] ?? 'open',
                    'notes' => $document['notes'] ?? 'Imported from offline sync',
                ],
            );
            $createdDocuments++;
        }

        $this->logGeneral($request, 'import_asset_sync', null, null, "Imported offline sync: {$updatedAssets} assets, {$createdDocuments} documents");

        return back()->with('status', "นำเข้า Sync สำเร็จ: อัปเดตทรัพย์สิน {$updatedAssets} รายการ, เอกสาร {$createdDocuments} รายการ");
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

    private function logGeneral(Request $request, string $action, ?string $subjectType, ?int $subjectId, string $description): void
    {
        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'description' => $description,
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);
    }
}
