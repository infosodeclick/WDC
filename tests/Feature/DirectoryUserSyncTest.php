<?php

namespace Tests\Feature;

use App\Models\EmployeeDirectoryEntry;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use ZipArchive;

class DirectoryUserSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_creates_logins_from_current_directory_and_pads_employee_codes(): void
    {
        $this->seed(DatabaseSeeder::class);

        $employeeEntry = EmployeeDirectoryEntry::create([
            'source_system' => 'notion',
            'source_record_id' => 'sync-employee',
            'entry_type' => 'employee',
            'display_name' => 'Sync Employee',
            'english_name' => 'Sync Employee',
            'thai_name' => 'ซิงค์ พนักงาน',
            'nickname' => 'ซิงค์',
            'department' => 'Sales',
            'position' => 'Sales Executive',
            'location' => 'Lumpini',
            'email' => 'sync.employee@wdc.co.th',
            'is_active' => true,
        ]);
        $hrEntry = EmployeeDirectoryEntry::create([
            'source_system' => 'notion',
            'source_record_id' => 'sync-hr',
            'entry_type' => 'employee',
            'display_name' => 'Sync HR',
            'english_name' => 'Sync HR',
            'thai_name' => 'ซิงค์ เอชอาร์',
            'department' => 'Human Resources',
            'position' => 'HR Officer',
            'is_active' => true,
        ]);
        $itEntry = EmployeeDirectoryEntry::create([
            'source_system' => 'notion',
            'source_record_id' => 'sync-it',
            'entry_type' => 'employee',
            'display_name' => 'Sync IT',
            'english_name' => 'Sync IT',
            'thai_name' => 'ซิงค์ ไอที',
            'department' => 'Information Technology',
            'position' => 'IT Support',
            'is_active' => true,
        ]);
        $admin = User::where('employee_code', 'EMP09999')->firstOrFail();
        EmployeeDirectoryEntry::create([
            'source_system' => 'notion',
            'source_record_id' => 'sync-admin',
            'user_id' => $admin->id,
            'entry_type' => 'employee',
            'display_name' => 'Protected Admin',
            'english_name' => 'Protected Admin',
            'department' => 'IT',
            'position' => 'Admin',
            'is_active' => true,
        ]);

        $path = $this->makeWorkbook([
            ['Start Date', 'Position', 'Department', 'Staff ID', 'ชื่อ-สกุล (Eng)', 'ชื่อ- นามสกุล(ไทย)', 'ชื่อเล่น'],
            ['2026-07-01', 'Sales Executive', 'Sales', '7', 'Sync Employee', 'ซิงค์ พนักงาน', 'ซิงค์'],
            ['2026-07-01', 'HR Officer', 'Human Resources', '8.0', 'Sync HR', 'ซิงค์ เอชอาร์', 'เอชอาร์'],
            ['2026-07-01', 'IT Support', 'Information Technology', '9', 'Sync IT', 'ซิงค์ ไอที', 'ไอที'],
            ['2026-07-01', 'Admin', 'IT', '999', 'Protected Admin', 'ผู้ดูแล', 'แอดมิน'],
            ['2026-07-01', 'No Match', 'Other', '10', 'Not In WDC', 'ไม่มีในระบบ', 'ไม่ใช้'],
        ]);

        Artisan::call('wdc:sync-directory-users', [
            'file' => $path,
            '--commit' => true,
        ]);

        $employee = User::where('employee_code', '000007')->firstOrFail();
        $this->assertTrue(Hash::check('Wdc@2026', $employee->password));
        $this->assertSame('employee', $employee->role->slug);
        $this->assertSame($employee->id, $employeeEntry->fresh()->user_id);

        $this->assertSame('hr', User::where('employee_code', '000008')->firstOrFail()->role->slug);
        $this->assertSame('it_support', User::where('employee_code', '000009')->firstOrFail()->role->slug);
        $this->assertDatabaseMissing('users', ['employee_code' => '000010']);
        $this->assertSame('EMP09999', $admin->fresh()->employee_code);
        $this->assertSame('super_admin', $admin->fresh('role')->role->slug);

        $this->assertSame('000008', $hrEntry->fresh()->employeeCode());
        $this->assertSame('000009', $itEntry->fresh()->employeeCode());

        $this->actingAs($admin);

        $this->get(route('admin.index', ['section' => 'permissions']))
            ->assertOk()
            ->assertSee('000007')
            ->assertSee('000008')
            ->assertSee('000009')
            ->assertDontSee('Protected Admin')
            ->assertDontSee('EMP09999 ·');
    }

    /**
     * @param list<list<string>> $rows
     */
    private function makeWorkbook(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'wdc-sync-').'.xlsx';
        $shared = [];
        $sharedIndex = [];
        $sheetRows = [];

        foreach ($rows as $rowIndex => $row) {
            $cells = [];
            foreach ($row as $columnIndex => $value) {
                $sharedIndex[$value] ??= count($shared);
                if (! in_array($value, $shared, true)) {
                    $shared[] = $value;
                }
                $reference = $this->columnName($columnIndex + 1).($rowIndex + 1);
                $cells[] = '<c r="'.$reference.'" t="s"><v>'.$sharedIndex[$value].'</v></c>';
            }
            $sheetRows[] = '<row r="'.($rowIndex + 1).'">'.implode('', $cells).'</row>';
        }

        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0" encoding="UTF-8"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.count($shared).'" uniqueCount="'.count($shared).'">'.collect($shared)->map(fn (string $value) => '<si><t>'.htmlspecialchars($value, ENT_XML1).'</t></si>')->implode('').'</sst>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.implode('', $sheetRows).'</sheetData></worksheet>');
        $zip->close();

        return $path;
    }

    private function columnName(int $column): string
    {
        $name = '';

        while ($column > 0) {
            $column--;
            $name = chr(65 + ($column % 26)).$name;
            $column = intdiv($column, 26);
        }

        return $name;
    }
}
