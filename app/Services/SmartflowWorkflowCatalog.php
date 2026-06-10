<?php

namespace App\Services;

use App\Models\WorkflowStep;
use App\Models\WorkflowTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SmartflowWorkflowCatalog
{
    public const SOURCE_NOTE = 'SmartFlow live structure read from wdc.smartflow.pw on 2026-06-10.';

    public function sync(): void
    {
        DB::transaction(function () {
            foreach (self::definitions() as $workflow) {
                $this->syncWorkflow($workflow);
            }
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function definitions(): array
    {
        return [
            self::workflow(1, 'E-MEMO', 'เอกสารภายใน', 'Authorization', 'Management', 48, 'บันทึกข้อความและเอกสารภายในที่เลือกผู้อนุมัติได้ตามลำดับของ SmartFlow', [
                self::field('dynamic_33', 'ด่วนพิเศษ', 'checkbox'),
                self::field('dynamic_25', 'เอกสารแนบ 1', 'file'),
                self::field('dynamic_26', 'เอกสารแนบ 2', 'file'),
                self::field('dynamic_30', 'เอกสารแนบ 3', 'file'),
                self::field('dynamic_118', 'เอกสารแนบ 4', 'file'),
                self::field('dynamic_32', 'ต้องการผู้อนุมัติท่านที่ 1', 'checkbox'),
                self::field('dynamic_31', 'ต้องการผู้อนุมัติท่านที่ 2', 'checkbox'),
            ], [
                self::step(14, 1, 'อนุมัติลำดับที่ 1', ['Middle_Management'], ['ต้องการผู้อนุมัติท่านที่ 1 Equals "on"'], false, true, 'Approve / Reject'),
                self::step(16, 2, 'อนุมัติลำดับที่ 2', ['Senior_Management'], ['ต้องการผู้อนุมัติท่านที่ 2 Equals "on"'], false, true, 'Approve / Reject'),
                self::step(17, 3, 'อนุมัติลำดับที่ 3', ['Top_Management', 'Senior_Management', 'Middle_Management'], [], false, true, 'Final Approve / Reject'),
            ]),
            self::workflow(2, 'ใบเบิกสินค้า', 'คลังสินค้า', 'Authorization', 'Warehouse / Marketing / Director', 48, 'ใบเบิกสินค้าตาม SmartFlow พร้อมเงื่อนไขจำนวนรวม ประเภทการเบิก และ Sale tool', [
                self::field('dynamic_1', 'เลขที่เอกสาร (โกดัง)'),
                self::field('dynamic_2', 'วันที่ตัดเบิก (โกดัง)', 'date'),
                self::field('dynamic_3', 'เขต'),
                self::field('dynamic_4', 'สินค้าตัวอย่าง', 'checkbox'),
                self::field('dynamic_5', 'แนบหลักฐานการอนุมัติจาก CEO เช่น รูปถ่าย line 1', 'file'),
                self::field('dynamic_42', 'แนบหลักฐานการอนุมัติจาก CEO เช่น รูปถ่าย line 2', 'file'),
                self::field('dynamic_43', 'แนบหลักฐานการอนุมัติจาก CEO เช่น รูปถ่าย line 3', 'file'),
                self::field('dynamic_36', 'Sale tool', 'checkbox'),
                self::field('dynamic_182', 'ประเภทการเบิก', 'select', false, null, ['ชิ้น', 'กล่อง']),
                self::field('dynamic_6', 'รายการเบิก', 'textarea'),
                self::field('dynamic_38', 'ชื่อลูกค้า'),
                self::field('dynamic_39', 'ผู้ออกแบบ'),
                self::field('dynamic_40', 'วัตถุประสงค์', 'textarea'),
                self::field('dynamic_41', 'ชื่อโครงการ', 'text', true),
            ], [
                self::step(2, 1, 'Sup and Assist Approval', ['Middle_Management'], [], false, true, 'Approve / Reject'),
                self::step(4, 2, 'Manager Approval', ['Senior_Management'], [], false, true, 'Approve / Reject'),
                self::step(5, 4, 'Director', ['Top_Management_Goods_requisition'], ['Total Quantity Greater Than "10"'], false, true, 'Director Approve / Reject'),
                self::step(72, 5, 'Director (กล่อง)', ['บัณฑิต หิรัญญนิธิวัฒนา'], ['Total Quantity Greater Than "5"', 'ประเภทการเบิก Equals "กล่อง"'], false, false, 'Director Approve / Reject'),
                self::step(6, 6, 'รอการตรวจสอบยืนยัน by Marketing', ['วิไลลักษณ์ กำไรนาค', 'บุญประเสริฐ ศิริโรจน์รัตนะ'], ['Sale tool Equals "on"'], true, false, 'Confirm Marketing Check'),
            ]),
            self::workflow(3, 'ขอเครดิต/เปิดบัญชีใหม่', 'บัญชี/เครดิต', 'Authorization', 'Accounting & Finance', 72, 'ขอเปิดบัญชีลูกค้าใหม่หรือขอวงเงินเครดิต โดยมี Manager, Credit Approver และ CFO ตาม SmartFlow', [
                self::field('dynamic_10', 'รายการที่จะดำเนินการ', 'select', true, null, ['เปิดหน้าบัญชี', 'ขอเครดิต']),
                self::field('dynamic_11', 'รหัสลูกค้า FORMULA'),
                self::field('dynamic_12', 'รหัสลูกค้า CRM'),
                self::field('dynamic_13', 'วงเงินเครดิตเดิม', 'number'),
                self::field('dynamic_14', 'พนักงานขายชื่อ'),
                self::field('dynamic_15', 'รหัสพนักงานผู้ขาย'),
                self::field('dynamic_16', 'ชื่อบริษัท'),
                self::field('dynamic_17', 'เลขทะเบียนนิติบุคคล/ บัตรประชาชน'),
                self::field('dynamic_18', 'ทุนจดทะเบียน', 'number'),
                self::field('dynamic_19', 'ลักษณะธุรกิจ'),
                self::field('dynamic_20', 'วงเงินที่ต้องการขออนุมัติ', 'number'),
                self::field('dynamic_23', '( 1 ) หนังสือรับรองกระทรวงพาณิชย์', 'file'),
                self::field('dynamic_44', '( 2 ) ใบสำคัญแสดงการจดทะเบียนภาษีมูลค่าเพิ่ม (ภพ.20)', 'file'),
                self::field('dynamic_45', '( 3 ) แผนที่ตั้งบริษัท / สาขา, รูปถ่ายหน้าบริษัท และสถานที่ส่งสินค้า', 'file'),
                self::field('dynamic_46', '( 4 ) ระเบียบการวางบิล / รับเช็ค', 'file'),
                self::field('dynamic_47', '( 5 ) ใบสั่งซื้อสินค้า (PO) / ใบเสนอราคา ที่ได้รับการลงลายมือชื่อจากลูกค้าแล้วเท่านั้น', 'file'),
                self::field('dynamic_48', '( 6 ) แผนการจัดส่งสินค้า', 'file'),
                self::field('dynamic_49', '( 7 ) กรณีที่เกิน 1 ล้านบาท ต้องแนบสัญญาซื้อขาย หรือ เอกสารการจ่ายเงินมัดจำ (ถ้ามี)', 'file'),
                self::field('dynamic_50', '( 8 ) อื่นๆ โปรดระบุ', 'textarea'),
            ], [
                self::step(9, 1, 'สถานะการอนุมัติจาก Manager', ['Top_Management', 'Middle_Management'], [], false, true, 'Approve / Reject'),
                self::step(10, 2, 'สถานะการพิจาณาจาก Credit Approver (เปิดหน้าบัญชี)', ['กิตติ จุฑามหาธนพงศ์'], ['รายการที่จะดำเนินการ Equals "เปิดหน้าบัญชี"'], false, false, 'Credit Review'),
                self::step(12, 3, 'สภานะ Credit Approver แนบเอกสารเพิ่มเติม', ['กิตติ จุฑามหาธนพงศ์'], [], true, false, 'Attach Credit Result'),
                self::step(13, 4, 'สถานะการพิจาณาจาก CFO', ['กิตติ จุฑามหาธนพงศ์'], [], false, false, 'CFO Review'),
            ]),
            self::workflow(5, 'ใบคืนสินค้า', 'คืนสินค้า', 'Authorization', 'Goods Return / Logistics / Warehouse', 48, 'ใบคืนสินค้าตาม SmartFlow จากฝ่ายขายถึงคลังและโลจิสติกส์', [
                self::field('dynamic_52', 'ชื่อลูกค้า'),
                self::field('dynamic_53', 'ที่อยู่', 'textarea'),
                self::field('dynamic_54', 'เบอร์โทรศัพท์', 'tel'),
                self::field('dynamic_55', 'ชื่อพนักงานขาย'),
                self::field('dynamic_56', 'รายการสินค้า', 'textarea'),
                self::field('dynamic_57', 'จำนวนรวม (กล่อง)', 'number'),
                self::field('dynamic_68', 'ไฟล์แนบ', 'file'),
            ], [
                self::step(19, 1, 'Pending Good Return Approval', ['ประกายแก้ว ศรีแจ่ม', 'ปนัสยา จ้อยรุ่ง'], [], false, false, 'Approve Return'),
                self::step(32, 2, 'Logistic Return Approval', ['จิณห์วรา สกุลด่าน', 'ฐานิดา สันติธัญญาโชค'], [], false, false, 'Logistic Review'),
                self::step(20, 3, 'โกดังสรุปสภาพสินค้ารับคืน', ['บุญพิทักษ์ มีแก้ว', 'ภาวิณี โพธิ์แก้ว'], [], true, false, 'Warehouse Summary'),
            ]),
            self::workflow(7, 'IT Helpdesk', 'IT Helpdesk', 'Your Tasks', 'IT Helpdesk / AI System / SoftpowerIT', 24, 'แจ้งงาน IT, VPN, SAP B1, AI-CRM, Remote Access และยกเลิกเอกสาร โดยแยก branch เหมือน SmartFlow', [
                self::field('dynamic_135', 'แจ้งยกเลิกเอกสาร(โปรดระบุเลข Document Ref ด้วย)', 'checkbox'),
                self::field('dynamic_181', 'แจ้งขอใช้งาน VPN (โปรดระบุวัตถุประสงค์ในการใช้งาน)', 'checkbox'),
                self::field('dynamic_170', 'แจ้งปัญหาโปรแกรม SAP B1', 'checkbox'),
                self::field('dynamic_171', 'แจ้งปัญหาโปรแกรม AI-CRM', 'checkbox'),
                self::field('dynamic_172', 'ขอเข้าถึง,แก้ไขข้อมูล,database/ขอ Remote Access กับเครื่องพนักงานในองค์กร', 'checkbox'),
                self::field('dynamic_62', 'ปัญหา/รายละเอียด', 'rich_text', true),
                self::field('dynamic_63', 'ไฟล์เเนบ 1', 'file'),
                self::field('dynamic_178', 'ไฟล์เเนบ 2', 'file'),
                self::field('dynamic_179', 'ไฟล์เเนบ 3', 'file'),
                self::field('dynamic_180', 'ไฟล์แนบ 4', 'file'),
            ], [
                self::step(45, 2, 'Manager Approval', ['Senior_Management'], ['แจ้งยกเลิกเอกสาร(โปรดระบุเลข Document Ref ด้วย) Equals "on"'], false, true, 'Approve Cancel Document'),
                self::step(28, 4, 'Accept Case', ['พีรสิทธิ์ หนองรั้ง', 'ชนะพล จักรพันธ์'], [], false, false, 'Accept Case'),
                self::step(29, 5, 'Resove case', ['พีรสิทธิ์ หนองรั้ง', 'ชนะพล จักรพันธ์'], [], true, false, 'Resolve Case'),
                self::step(67, 7, 'AI-CRM Accept Case', ['thipaporn aisystem'], ['แจ้งปัญหาโปรแกรม AI-CRM Equals "on"'], false, false, 'AI-CRM Accept Case'),
                self::step(69, 8, 'AI-CRM Resove case', ['thipaporn aisystem'], ['แจ้งปัญหาโปรแกรม AI-CRM Equals "on"'], false, false, 'AI-CRM Resolve Case'),
                self::step(68, 9, 'Softpowerit Accept Case', ['suthathip softpowerit', 'chintana softpowerit', 'nittaya softpowerit', 'sumolwan softpowerit', 'Wipada softpowerit', 'Sun softpowerit', 'neung softpowerit'], ['แจ้งปัญหาโปรแกรม SAP B1 Equals "on"'], false, false, 'SoftpowerIT Accept Case'),
                self::step(70, 10, 'softpowerit - Resove case', ['suthathip softpowerit', 'chintana softpowerit', 'nittaya softpowerit', 'sumolwan softpowerit', 'thipaporn aisystem', 'Wipada softpowerit', 'Sun softpowerit'], ['แจ้งปัญหาโปรแกรม SAP B1 Equals "on"'], false, false, 'SoftpowerIT Resolve Case'),
            ]),
            self::workflow(8, 'ประสานงานภายใน', 'ประสานงาน', 'All Documents', 'ต้นทาง / ฝ่ายปลายทาง', 48, 'ประสานงานระหว่างแผนกโดยเลือกฝ่ายปลายทางและแตก branch ตามฝ่ายที่เลือก', [
                self::field('dynamic_166', 'ฝ่ายกฏหมาย;', 'checkbox'),
                self::field('dynamic_167', 'ฝ่ายการตลาด;', 'checkbox'),
                self::field('dynamic_168', 'ฝ่ายไอที;', 'checkbox'),
                self::field('dynamic_169', 'ฝ่าย Import;', 'checkbox'),
                self::field('dynamic_102', 'ฝ่ายกฏหมาย', 'text'),
                self::field('dynamic_103', 'ฝ่ายการตลาด', 'text'),
                self::field('dynamic_104', 'ฝ่ายไอที', 'text'),
                self::field('dynamic_105', 'Import', 'text'),
                self::field('dynamic_66', 'รายละเอียด', 'rich_text', true),
                self::field('dynamic_67', 'เอกสารแนบ 1', 'file'),
                self::field('dynamic_69', 'เอกสารแนบ 2', 'file'),
                self::field('dynamic_70', 'เอกสารแนบ 3', 'file'),
                self::field('dynamic_71', 'เอกสารแนบ 4', 'file'),
                self::field('dynamic_72', 'เอกสารแนบ 5', 'file'),
                self::field('dynamic_75', 'เอกสารแนบ(สำหรับหัวหน้าผู้รับมอบหมายปลายทาง)', 'file'),
            ], [
                self::step(30, 1, 'หัวหน้าตรวจสอบและอนุมัติ (ผู้ส่ง)', ['Senior_Management', 'HQ_Manager', 'BU99_Manager', 'Middle_Management'], [], true, true, 'Requester Manager Review'),
                self::step(56, 4, 'ผู้รับมอบหมายปลายทางรับทราบ(ฝ่ายการตลาด)', ['HQMarketing_Manager'], ['ฝ่ายการตลาด; Equals "on"'], false, true, 'Marketing Acknowledge'),
                self::step(57, 5, 'หัวหน้าตรวจสอบและอนุมัติ (ฝ่ายการตลาด)', ['HQ_SeniorMarketing'], ['ฝ่ายการตลาด; Equals "on"'], false, true, 'Marketing Manager Review'),
                self::step(58, 6, 'ผู้รับมอบหมายปลายทางรับทราบ (ฝ่ายไอที)', ['พีรสิทธิ์ หนองรั้ง', 'ชนะพล จักรพันธ์'], ['ฝ่ายไอที; Equals "on"'], false, false, 'IT Acknowledge'),
                self::step(59, 7, 'หัวหน้าผู้รับมอบหมายปลายทางตรวจสอบ (ฝ่ายไอที)', ['ณิธิกานต์ ธนิกกุลรุ่งสิทธิ์'], ['ฝ่ายไอที; Equals "on"'], false, false, 'IT Manager Review'),
                self::step(60, 8, 'ผู้รับมอบหมายปลายทางรับทราบ (ฝ่ายimport)', ['HQ_import'], ['ฝ่าย Import; Equals "on"'], false, true, 'Import Acknowledge'),
                self::step(61, 9, 'หัวหน้าผู้รับมอบหมายปลายทางตรวจสอบ(ฝ่าย Import)', ['สมิตา วิโรจน์เตชะ', 'ฉัตรชัย เขมวราภรณ์', 'วิทยาวุธ เสรีวิริยะกุล'], ['ฝ่าย Import; Equals "on"'], false, false, 'Import Manager Review'),
                self::step(33, 17, 'ตรวจสอบว่า ดำเนินการเรียบร้อย(ผู้ส่ง)', ['Submitter'], [], true, false, 'Requester Confirm Complete'),
            ]),
            self::workflow(9, 'ขอสำรวจหน้างานและงานติดตั้ง', 'ติดตั้ง', 'Authorization', 'Sales Admin BU7 / Installation', 72, 'คำขอสำรวจหน้างานและงานติดตั้ง โดย Sales ส่งรายละเอียดให้ Sales Admin BU7 ลงข้อมูลหน้างาน', [
                self::field('dynamic_76', 'ชื่อลูกค้า/บริษัท', 'text', true),
                self::field('dynamic_77', 'วันที่ต้องการสำรวจหน้างาน', 'date'),
                self::field('dynamic_78', 'โครงการ', 'text', true),
                self::field('dynamic_79', 'ชื่อ/เบอร์โทรติดต่อหน้างาน', 'text', true),
                self::field('dynamic_80', 'ชื่อผู้แทนขาย (Sales) / เบอร์โทรติดต่อ', 'text', true),
                self::field('dynamic_81', 'ที่อยู่/ที่ตั้ง', 'textarea'),
                self::field('dynamic_82', 'รายละเอียด', 'rich_text'),
                self::field('dynamic_83', 'งานติดตั้ง', 'checkbox'),
                self::field('dynamic_84', 'กระเบื้อง Size / Nontiles ประเภท', 'text', true),
                self::field('dynamic_93', 'หมายเลขใบเสนอราคาอ้างอิง'),
                self::field('dynamic_94', 'ไฟล์แนบ/รูปภาพ 1 (For Sales)', 'file'),
                self::field('dynamic_95', 'ไฟล์แนบ/รูปภาพ 2 (For Sales)', 'file'),
                self::field('dynamic_96', 'ไฟล์แนบ/รูปภาพ 3 (For Sales)', 'file'),
                self::field('dynamic_97', 'ไฟล์แนบ/รูปภาพ 4 (For Sales)', 'file'),
                self::field('dynamic_98', 'ไฟล์แนบ/รูปภาพ 5 (For Sales)', 'file'),
                self::field('dynamic_99', 'ไฟล์แนบ/รูปภาพ 6 (For Sales)', 'file'),
                self::field('dynamic_100', 'ไฟล์แนบ/รูปภาพ 7 (For Sales)', 'file'),
                self::field('dynamic_101', 'ไฟล์แนบ/รูปภาพ 8 (For Sales)', 'file'),
            ], [
                self::step(40, 1, 'Sales Admin BU7 รับทราบ', ['ภาวิณี โพธิ์แก้ว', 'ณัชชา เจ๊ะยูโซ๊ะ'], [], false, false, 'Acknowledge'),
                self::step(35, 2, 'Sales Admin BU7 ลงรายละเอียดหน้างาน', ['ภาวิณี โพธิ์แก้ว', 'ณัชชา เจ๊ะยูโซ๊ะ'], [], true, false, 'Add Site Survey Detail'),
            ]),
            self::workflow(10, 'ขออนุมัติราคา/ขายสินค้า', 'ฝ่ายขาย', 'Authorization', 'Sales Management', 48, 'ขออนุมัติราคา เงื่อนไขการขาย และส่วนลด ผ่านผู้อนุมัติ 3 ลำดับ', [
                self::field('dynamic_106', 'TEAM'),
                self::field('dynamic_107', 'บริษัท'),
                self::field('dynamic_108', 'โครงการ'),
                self::field('dynamic_109', 'จังหวัด'),
                self::field('dynamic_110', 'พื้นที่ (ตร.ม.)', 'number'),
                self::field('dynamic_111', 'เหตุผล', 'textarea'),
                self::field('dynamic_112', 'ตารางการขาย', 'textarea'),
                self::field('dynamic_116', 'หมายเหตุ', 'textarea'),
                self::field('dynamic_113', 'เอกสารแนบ 1', 'file'),
                self::field('dynamic_114', 'เอกสารแนบ 2', 'file'),
                self::field('dynamic_115', 'เอกสารแนบ 3', 'file'),
            ], [
                self::step(37, 1, 'อนุมัติลำดับที่ 1', ['Middle_Management'], [], false, true, 'Approve / Reject'),
                self::step(38, 2, 'อนุมัติลำดับที่ 2', ['Senior_Management'], [], false, true, 'Approve / Reject'),
                self::step(39, 3, 'อนุมัติลำดับที่ 3', ['Top_Management'], [], false, true, 'Final Approve / Reject'),
            ]),
            self::workflow(11, 'ก่อสร้าง SHOWROOM', 'Showroom', 'Authorization', 'Sales / CEO Evidence', 72, 'ฟอร์มก่อสร้าง SHOWROOM จาก SmartFlow; หน้า Flow เดิมยังไม่มี step ที่เปิดใช้งาน', [
                self::field('dynamic_133', 'เลือกในกรณีได้รับอนุมัติจาก CEO แล้วเท่านั้น', 'checkbox'),
                self::field('dynamic_122', 'TEAM'),
                self::field('dynamic_125', 'แนบหลักฐานการอนุมัติจาก CEO เช่น รูปถ่าย line 1', 'file', true),
                self::field('dynamic_126', 'แนบหลักฐานการอนุมัติจาก CEO เช่น รูปถ่าย line 2', 'file'),
                self::field('dynamic_127', 'แนบหลักฐานการอนุมัติจาก CEO เช่น รูปถ่าย line 3', 'file'),
                self::field('dynamic_128', 'รายการเบิก', 'textarea'),
                self::field('dynamic_129', 'ชื่อลูกค้า'),
                self::field('dynamic_130', 'ผู้ออกแบบ'),
                self::field('dynamic_131', 'ชื่อโครงการ'),
                self::field('dynamic_132', 'วัตถุประสงค์', 'textarea'),
            ], []),
            self::workflow(12, 'แจ้งสินค้าที่มีปัญหา(ยังไม่เปิดใช้งาน)', 'สินค้า/คุณภาพ', 'Authorization', 'Sales / BU7 / Logistic / Import', 96, 'ฟอร์มแจ้งสินค้าที่มีปัญหาจาก SmartFlow แม้ชื่อเดิมระบุว่ายังไม่เปิดใช้งาน', [
                self::field('dynamic_153', 'เบอร์โทรผู้แทนขาย(Sales)', 'tel', true),
                self::field('dynamic_136', 'วันที่เข้าดูหน้างาน', 'date'),
                self::field('dynamic_137', 'บริษัท'),
                self::field('dynamic_138', 'ผู้ติดต่อ'),
                self::field('dynamic_139', 'เบอร์โทรผู้ติดต่อ', 'tel'),
                self::field('dynamic_140', 'โครงการ'),
                self::field('dynamic_141', 'ที่อยู่/ที่ตั้ง', 'textarea'),
                self::field('dynamic_142', 'ชนิดของส่วนติดตั้ง'),
                self::field('dynamic_164', 'ชนิดของส่วนติดตั้งอื่นๆ โปรดระบุ'),
                self::field('dynamic_143', 'ขนาดพื้นที่ติดตั้ง', 'number'),
                self::field('dynamic_144', 'ชนิดของสินค้า'),
                self::field('dynamic_145', 'ชื่อสินค้า'),
                self::field('dynamic_146', 'รหัสสินค้า/Lot Number'),
                self::field('dynamic_147', 'เฉด'),
                self::field('dynamic_148', 'จำนวน(กล่อง/แผ่น)', 'number'),
                self::field('dynamic_149', 'ขนาดพื้นที่(ตร.ม.)', 'number'),
                self::field('dynamic_150', 'มูลค่า(บาท)', 'number'),
                self::field('dynamic_151', 'ความต้องการของลูกค้า ให้บริษัท Support ด้านใด', 'textarea', true),
                self::field('dynamic_152', 'อาการ', 'rich_text', true),
                self::field('dynamic_154', 'รูปภาพ / เอกสารแนบ 1', 'file', true),
                self::field('dynamic_155', 'รูปภาพ / เอกสารแนบ 2', 'file'),
                self::field('dynamic_156', 'รูปภาพ / เอกสารแนบ 3', 'file'),
                self::field('dynamic_157', 'รูปภาพ / เอกสารแนบ 4', 'file'),
                self::field('dynamic_158', 'รูปภาพ / เอกสารแนบ 5', 'file'),
                self::field('dynamic_159', 'รูปภาพ / เอกสารแนบ 6', 'file'),
                self::field('dynamic_160', 'รูปภาพ / เอกสารแนบ 7', 'file'),
                self::field('dynamic_161', 'รูปภาพ / เอกสารแนบ 8', 'file'),
                self::field('dynamic_162', 'รูปภาพ / เอกสารแนบ 9', 'file'),
                self::field('dynamic_163', 'รูปภาพ / เอกสารแนบ 10', 'file'),
            ], [
                self::step(48, 1, 'Sales Manager Approvers', ['Senior_Management'], [], false, true, 'Sales Manager Review'),
                self::step(49, 2, 'BU7 แนบเอกสาร การตรวจสอบหน้างาน', ['บุญพิทักษ์ มีแก้ว', 'ภาวิณี โพธิ์แก้ว', 'ทิวาพร รุ่งสุข', 'ธวัชชัย คำสวาส'], [], true, false, 'Attach Site Inspection'),
                self::step(50, 3, 'Somchai Phokaew', ['สมชาย โพธิ์แก้ว'], [], false, false, 'Review'),
                self::step(55, 4, 'submitted แนบไฟล์เอกสารจาก CRM', ['Submitter'], [], true, false, 'Requester Attach CRM Files'),
                self::step(71, 5, 'Sales Manager ตรวจสอบเอกสาร', ['Senior_Management', 'Middle_Management'], [], false, true, 'Sales Manager Check Documents'),
                self::step(51, 6, 'LG manager แนบเอกสาร', ['ฐานิดา สันติธัญญาโชค'], [], false, false, 'Logistic Attach Documents'),
                self::step(52, 7, 'Import แนบเอกสาร', ['ฐิฌาภรณ์ ภูวัฒนะธีรกรณ์', 'พิมพ์ลลิล วิมุกตายน', 'สมิตา วิโรจน์เตชะ'], [], false, false, 'Import Attach Documents'),
                self::step(53, 8, 'Director Vithayavut Sereeviriyakul', ['วิทยาวุธ เสรีวิริยะกุล'], [], false, false, 'Director Review'),
                self::step(54, 9, 'LG manager2', ['ฐานิดา สันติธัญญาโชค'], [], false, false, 'Final Logistic Review'),
            ]),
            self::workflow(13, 'Developer/IT support', 'IT Helpdesk', 'Your Tasks', 'Developer / IT Support', 48, 'งานส่งต่อระหว่าง Developer และ IT Support ตาม checkbox ปลายทาง', [
                self::field('dynamic_173', 'แจ้งเรื่องถึง IT support', 'checkbox'),
                self::field('dynamic_174', 'แจ้งเรื่องถึง Developer', 'checkbox'),
                self::field('dynamic_176', 'ไฟลแนบ', 'file'),
                self::field('dynamic_183', 'ไฟลแนบ 2', 'file'),
            ], [
                self::step(66, 1, 'Developer รับเรื่อง(กรณี IT Support แจ้งเคสไปยัง Developer)', ['Ekaluk Pongsrihadulchai'], ['แจ้งเรื่องถึง Developer Equals "on"'], false, false, 'Developer Accept Case'),
                self::step(62, 2, 'Developer Resolve(กรณี IT Support แจ้งเคสไปยัง Developer)', ['Ekaluk Pongsrihadulchai'], ['แจ้งเรื่องถึง Developer Equals "on"'], true, false, 'Developer Resolve'),
                self::step(63, 3, 'IT support รับเรื่อง(กรณี Developer แจ้งเคสไปยัง IT Support )', ['พีรสิทธิ์ หนองรั้ง'], ['แจ้งเรื่องถึง IT support Equals "on"'], false, false, 'IT Support Accept Case'),
                self::step(64, 4, 'IT support ปิดเคส', ['พีรสิทธิ์ หนองรั้ง'], [], true, false, 'IT Support Close Case'),
            ]),
            self::workflow(14, 'ขออนุมัติคอนเทนฅ์ (Marketing)', 'Marketing', 'Authorization', 'Marketing / CEO', 48, 'ขออนุมัติคอนเทนต์การตลาด โดย CEO approval เฉพาะ content = WDC', [
                self::field('dynamic_191', 'content', 'select', false, null, ['WDC']),
                self::field('dynamic_193', 'ประเภทคอนเทนต์'),
                self::field('dynamic_194', 'ประเภทคอนเทนต์อื่นๆ'),
                self::field('dynamic_195', 'วันโพสต์', 'date'),
                self::field('dynamic_196', 'Platform'),
                self::field('dynamic_197', 'เอกสาร/รูปแนบ 1', 'file'),
                self::field('dynamic_198', 'เอกสาร/รูปแนบ 2', 'file'),
                self::field('dynamic_199', 'เอกสาร/รูปแนบ 3', 'file'),
                self::field('dynamic_200', 'เอกสาร/รูปแนบ 4', 'file'),
                self::field('dynamic_201', 'เอกสาร/รูปแนบ 5', 'file'),
            ], [
                self::step(73, 1, 'Marketing Assistant Director Approval', ['พสิษฐ์ เหมรัฐวุฒินนท์'], [], false, false, 'Marketing Approval'),
                self::step(74, 2, 'CEO Approval', ['บัณฑิต หิรัญญนิธิวัฒนา'], ['content Equals "WDC"'], false, false, 'CEO Approval'),
            ]),
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    public static function defaultStatusFlow(): array
    {
        return [
            ['from' => 'draft', 'to' => 'submitted', 'action' => 'Submit Document', 'label' => 'ผู้ขอส่งเอกสารเข้า workflow'],
            ['from' => 'submitted', 'to' => 'in_review', 'action' => 'Review', 'label' => 'ผู้อนุมัติหรือหัวหน้าตรวจสอบ'],
            ['from' => 'in_review', 'to' => 'accepted', 'action' => 'Accept Case', 'label' => 'ทีมเจ้าของงานรับเรื่อง'],
            ['from' => 'accepted', 'to' => 'in_progress', 'action' => 'Work / Resolve', 'label' => 'ผู้รับผิดชอบดำเนินการและบันทึกผล'],
            ['from' => 'in_progress', 'to' => 'waiting_requester', 'action' => 'Need More Info', 'label' => 'ส่งกลับผู้ขอเพื่อขอข้อมูลเพิ่ม'],
            ['from' => 'in_progress', 'to' => 'completed', 'action' => 'Complete', 'label' => 'ปิดงานหรือเสร็จสิ้น'],
            ['from' => 'in_review', 'to' => 'approved', 'action' => 'Approve', 'label' => 'อนุมัติเอกสาร'],
            ['from' => 'in_review', 'to' => 'rejected', 'action' => 'Reject', 'label' => 'ไม่อนุมัติเอกสาร'],
            ['from' => 'submitted', 'to' => 'cancelled', 'action' => 'Cancel', 'label' => 'ยกเลิกคำขอ'],
        ];
    }

    private static function workflow(int $id, string $name, string $category, string $menu, string $team, int $slaHours, string $description, array $fields, array $steps): array
    {
        return [
            'id' => (string) $id,
            'name' => $name,
            'category' => $category,
            'smartflow_menu' => $menu,
            'service_team' => $team,
            'sla_hours' => $slaHours,
            'description' => $description,
            'fields' => $fields,
            'steps' => $steps,
            'legacy_url' => "https://wdc.smartflow.pw/document/submit/{$id}/",
            'flow_url' => "https://wdc.smartflow.pw/document/workflow/{$id}/steps/",
        ];
    }

    private static function field(string $key, string $label, string $type = 'text', bool $required = false, ?string $help = null, array $options = []): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'type' => $type,
            'required' => $required,
            'help' => $help,
            'options' => $options,
            'source' => 'smartflow_dynamic_field',
        ];
    }

    private static function step(int $id, int $order, string $name, array $approvers, array $conditions = [], bool $requiresInput = false, bool $userSelectable = false, ?string $actionLabel = null): array
    {
        return [
            'id' => (string) $id,
            'order' => $order,
            'name' => $name,
            'mode' => 'any_one',
            'approvers' => $approvers,
            'conditions' => $conditions,
            'requires_input' => $requiresInput,
            'user_selectable' => $userSelectable,
            'action_label' => $actionLabel,
        ];
    }

    private function syncWorkflow(array $workflow): void
    {
        $template = WorkflowTemplate::updateOrCreate(
            ['source_system' => 'smartflow', 'legacy_workflow_id' => $workflow['id']],
            [
                'name' => $workflow['name'],
                'category' => $workflow['category'],
                'description' => $workflow['description'],
                'smartflow_menu' => $workflow['smartflow_menu'],
                'service_team' => $workflow['service_team'],
                'form_schema' => [
                    'fields' => $workflow['fields'],
                    'routing' => $this->routingFromSteps($workflow['steps']),
                    'statuses' => self::defaultStatusFlow(),
                    'source_notes' => [
                        self::SOURCE_NOTE,
                        'Original submit URL: '.$workflow['legacy_url'],
                        'Original flow URL: '.$workflow['flow_url'],
                    ],
                ],
                'sla_hours' => $workflow['sla_hours'],
                'approval_policy' => 'any_one',
                'legacy_url' => $workflow['legacy_url'],
                'is_active' => true,
                'sort_order' => ((int) $workflow['id']) * 10,
            ],
        );

        $this->syncSteps($template, $workflow['steps']);
    }

    private function syncSteps(WorkflowTemplate $template, array $steps): void
    {
        if ($steps === []) {
            DB::table('workflow_requests')
                ->whereIn('current_step_id', $template->steps()->pluck('id'))
                ->update(['current_step_id' => null, 'updated_at' => now()]);

            $template->steps()->delete();

            return;
        }

        $syncedIds = [];
        $firstStepId = null;

        foreach ($steps as $step) {
            $model = $this->findExistingStep($template, $step) ?? new WorkflowStep(['workflow_template_id' => $template->id]);
            $model->fill($this->stepAttributes($step));
            $model->save();

            $syncedIds[] = $model->id;
            $firstStepId ??= $model->id;
        }

        $template->steps()
            ->whereNotIn('id', $syncedIds)
            ->get()
            ->each(function (WorkflowStep $staleStep) use ($firstStepId) {
                DB::table('workflow_requests')
                    ->where('current_step_id', $staleStep->id)
                    ->update(['current_step_id' => $firstStepId, 'updated_at' => now()]);

                $staleStep->delete();
            });
    }

    private function findExistingStep(WorkflowTemplate $template, array $step): ?WorkflowStep
    {
        $existing = $template->steps()
            ->where('external_step_id', $step['id'])
            ->first();

        if ($existing) {
            return $existing;
        }

        $normalizedName = $this->normalizeStepName($step['name']);

        return $template->steps()
            ->get()
            ->first(function (WorkflowStep $candidate) use ($normalizedName, $step) {
                return $this->normalizeStepName($candidate->name) === $normalizedName
                    || ((int) $candidate->step_order === (int) $step['order'] && empty($candidate->external_step_id));
            });
    }

    private function stepAttributes(array $step): array
    {
        $approvers = $step['approvers'] ?? [];
        $conditions = $step['conditions'] ?? [];

        return [
            'external_step_id' => $step['id'],
            'step_order' => $step['order'],
            'name' => $step['name'],
            'action_label' => $step['action_label'] ?? $step['name'],
            'mode' => $step['mode'] ?? 'any_one',
            'approver_group' => implode(', ', $approvers),
            'approver_hint' => implode(', ', $approvers).(($step['user_selectable'] ?? false) ? ' (User Selectable)' : ''),
            'condition_label' => implode(' AND ', $conditions) ?: null,
            'branch_label' => $conditions[0] ?? null,
            'metadata' => [
                'smartflow_step_id' => $step['id'],
                'approvers' => $approvers,
                'conditions' => $conditions,
                'user_selectable' => (bool) ($step['user_selectable'] ?? false),
                'source_note' => self::SOURCE_NOTE,
            ],
            'requires_input' => (bool) ($step['requires_input'] ?? false),
        ];
    }

    private function routingFromSteps(array $steps): array
    {
        return collect($steps)
            ->filter(fn (array $step) => ($step['conditions'] ?? []) !== [])
            ->map(fn (array $step) => [
                'when' => implode(' AND ', $step['conditions']),
                'step' => $step['name'],
                'approvers' => $step['approvers'],
                'action' => $step['action_label'] ?? $step['name'],
            ])
            ->values()
            ->all();
    }

    private function normalizeStepName(string $name): string
    {
        return Str::of($name)
            ->lower()
            ->replace('resove', 'resolve')
            ->replace([' ', '-', '_', '(', ')'], '')
            ->squish()
            ->toString();
    }
}
