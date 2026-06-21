<?php

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('asset_locations', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('name');
            $table->string('company')->default('WDC');
            $table->boolean('has_gps')->default(false);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('radius_meters')->nullable();
            $table->timestamps();
            $table->unique(['code', 'company']);
        });

        Schema::create('it_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_category_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('asset_location_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->references('id')->on('users')->cascadeOnUpdate()->nullOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('company')->default('WDC');
            $table->string('department')->nullable();
            $table->string('owner_name')->nullable();
            $table->string('status')->default('active');
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable();
            $table->decimal('price', 14, 2)->default(0);
            $table->decimal('book_value', 14, 2)->default(0);
            $table->date('purchased_at')->nullable();
            $table->date('warranty_until')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['status', 'department']);
        });

        Schema::create('asset_inspection_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_location_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->references('id')->on('users')->cascadeOnUpdate()->nullOnDelete();
            $table->string('code')->unique();
            $table->date('inspection_date');
            $table->string('company')->default('WDC');
            $table->unsignedInteger('item_count')->default(0);
            $table->string('status')->default('open');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('asset_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('it_asset_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->string('action');
            $table->text('summary');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamps();
        });

        $now = now();

        if (Schema::hasTable('permissions')) {
            $permissions = collect($this->assetPermissions())->map(fn (array $permission) => [
                ...$permission,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            DB::table('permissions')->upsert($permissions, ['key'], ['group', 'name', 'description', 'sort_order', 'updated_at']);

            $permissionIds = DB::table('permissions')->whereIn('key', collect($this->assetPermissions())->pluck('key'))->pluck('id', 'key');
            $roleIds = DB::table('roles')->whereIn('slug', ['admin', 'super_admin'])->pluck('id', 'slug');

            foreach ($roleIds as $roleId) {
                $rows = $permissionIds->map(fn (int $permissionId) => [
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->values()->all();

                DB::table('permission_role')->insertOrIgnore($rows);
            }

            $itDepartmentId = DB::table('departments')->where('code', 'IT')->value('id');

            if ($itDepartmentId) {
                $itUserIds = DB::table('employees')->where('department_id', $itDepartmentId)->pluck('user_id');
                $rows = $itUserIds->flatMap(fn (int $userId) => $permissionIds->map(fn (int $permissionId) => [
                    'user_id' => $userId,
                    'permission_id' => $permissionId,
                    'effect' => 'grant',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]))->values()->all();

                if ($rows !== []) {
                    DB::table('permission_user')->insertOrIgnore($rows);
                }
            }
        }

        $categoryIds = [];
        foreach ([
            ['code' => 'COM', 'name' => 'Computer / Notebook', 'description' => 'คอมพิวเตอร์และโน้ตบุ๊กสำหรับพนักงาน'],
            ['code' => 'NET', 'name' => 'Network Equipment', 'description' => 'Router, Switch, Access Point และอุปกรณ์เครือข่าย'],
            ['code' => 'PRN', 'name' => 'Printer / Scanner', 'description' => 'เครื่องพิมพ์ สแกนเนอร์ และอุปกรณ์ต่อพ่วง'],
            ['code' => 'CCTV', 'name' => 'CCTV / Security', 'description' => 'กล้องวงจรปิดและอุปกรณ์รักษาความปลอดภัย'],
        ] as $category) {
            DB::table('asset_categories')->updateOrInsert(['code' => $category['code']], [
                ...$category,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $categoryIds[$category['code']] = DB::table('asset_categories')->where('code', $category['code'])->value('id');
        }

        $locationIds = [];
        foreach ([
            ['code' => 'HQ-IT', 'name' => 'สำนักงานใหญ่ - ห้อง IT', 'company' => 'WDC', 'has_gps' => false],
            ['code' => 'HQ-FIN', 'name' => 'สำนักงานใหญ่ - บัญชี', 'company' => 'WDC', 'has_gps' => false],
            ['code' => 'WH-01', 'name' => 'คลังสินค้า 1', 'company' => 'WDC', 'has_gps' => true],
        ] as $location) {
            DB::table('asset_locations')->updateOrInsert(['code' => $location['code'], 'company' => $location['company']], [
                ...$location,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $locationIds[$location['code']] = DB::table('asset_locations')->where('code', $location['code'])->where('company', $location['company'])->value('id');
        }

        $ownerId = DB::table('users')->where('employee_code', 'EMP00200')->value('id');

        foreach ([
            [
                'code' => 'WDC-NB-0001',
                'name' => 'Dell Latitude 5450',
                'asset_category_id' => $categoryIds['COM'] ?? null,
                'asset_location_id' => $locationIds['HQ-IT'] ?? null,
                'owner_id' => $ownerId,
                'owner_name' => 'Malee Pattanangan',
                'company' => 'WDC',
                'department' => 'IT',
                'status' => 'active',
                'brand' => 'Dell',
                'model' => 'Latitude 5450',
                'serial_number' => 'DL-WDC-0001',
                'price' => 38500,
                'book_value' => 38500,
                'purchased_at' => '2026-01-15',
                'warranty_until' => '2029-01-14',
                'notes' => 'เครื่องตัวอย่างสำหรับระบบ IT Asset',
            ],
            [
                'code' => 'WDC-RT-0001',
                'name' => 'TP-Link 4G Router',
                'asset_category_id' => $categoryIds['NET'] ?? null,
                'asset_location_id' => $locationIds['WH-01'] ?? null,
                'owner_id' => null,
                'owner_name' => 'คลังสินค้า',
                'company' => 'WDC',
                'department' => 'Warehouse',
                'status' => 'active',
                'brand' => 'TP-Link',
                'model' => 'TL-MR100',
                'serial_number' => 'RT-WDC-0001',
                'price' => 2900,
                'book_value' => 2900,
                'purchased_at' => '2025-11-20',
                'warranty_until' => '2027-11-19',
                'notes' => 'ใช้สำหรับอินเทอร์เน็ตสำรอง',
            ],
            [
                'code' => 'WDC-PR-0001',
                'name' => 'Brother MFC Printer',
                'asset_category_id' => $categoryIds['PRN'] ?? null,
                'asset_location_id' => $locationIds['HQ-FIN'] ?? null,
                'owner_id' => null,
                'owner_name' => 'ฝ่ายบัญชี',
                'company' => 'WDC',
                'department' => 'Accounting',
                'status' => 'repair',
                'brand' => 'Brother',
                'model' => 'MFC-L5900DW',
                'serial_number' => 'PR-WDC-0001',
                'price' => 16500,
                'book_value' => 9000,
                'purchased_at' => '2024-05-10',
                'warranty_until' => '2027-05-09',
                'notes' => 'รอตรวจชุดลูกกลิ้ง',
            ],
        ] as $asset) {
            DB::table('it_assets')->updateOrInsert(['code' => $asset['code']], [
                ...$asset,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('asset_inspection_documents')->updateOrInsert(['code' => 'AST-CHK-20260621-0001'], [
            'asset_location_id' => $locationIds['HQ-IT'] ?? null,
            'created_by' => $ownerId,
            'code' => 'AST-CHK-20260621-0001',
            'inspection_date' => '2026-06-21',
            'company' => 'WDC',
            'item_count' => 3,
            'status' => 'open',
            'notes' => 'เอกสารตรวจนับตัวอย่างจากระบบ IT Asset Manager',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_audit_logs');
        Schema::dropIfExists('asset_inspection_documents');
        Schema::dropIfExists('it_assets');
        Schema::dropIfExists('asset_locations');
        Schema::dropIfExists('asset_categories');

        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->whereIn('key', collect($this->assetPermissions())->pluck('key'))->delete();
        }
    }

    private function assetPermissions(): array
    {
        return [
            ['key' => 'assets.view', 'group' => 'IT Asset', 'name' => 'ดูทะเบียนทรัพย์สิน IT', 'description' => 'เปิดหน้า IT Asset และค้นหาทรัพย์สิน', 'sort_order' => 125],
            ['key' => 'assets.manage', 'group' => 'IT Asset', 'name' => 'จัดการทรัพย์สิน IT', 'description' => 'เพิ่มทรัพย์สิน เปลี่ยนสถานะ และสร้างเอกสารตรวจนับ', 'sort_order' => 126],
            ['key' => 'assets.reports', 'group' => 'IT Asset', 'name' => 'ส่งออกรายงานทรัพย์สิน', 'description' => 'ดาวน์โหลด CSV และรายงานตรวจนับทรัพย์สิน', 'sort_order' => 127],
        ];
    }
};
