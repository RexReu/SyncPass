<?php
session_start();
if(!isset($_SESSION['user_id'])){ header("Location: ../login.php"); exit(); }

require_once '../vendor/autoload.php';
require_once '../core/Attendance.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

$session_id = (int)($_GET['session_id'] ?? 0);
if(!$session_id){ header("Location: generate_report.php"); exit(); }

$attendanceModel = new Attendance();
$session         = $attendanceModel->getSessionWithTeacher($session_id);
if(!$session){ header("Location: generate_report.php"); exit(); }

$records_result = $attendanceModel->getRecordsWithCourse($session_id);
$records = [];
while($row = $records_result->fetch_assoc()) $records[] = $row;

$absent_result = $attendanceModel->getAbsentStudents($session['class_id'], $session_id);
$absents = [];
while($row = $absent_result->fetch_assoc()) $absents[] = $row;

$counts         = $attendanceModel->getStatusCounts($session_id);
$total_present  = $counts['present'] ?? 0;
$total_late     = $counts['late']    ?? 0;
$total_excused  = $counts['excused'] ?? 0;
$total_absent   = count($absents);
$total_scanned  = $total_present + $total_late + $total_excused;
$total_enrolled = $total_scanned + $total_absent;
$rate = $total_enrolled > 0 ? round(($total_scanned / $total_enrolled) * 100, 1) : 0;

// Colors
$navy         = '00357A';
$gold         = 'E2B808';
$white        = 'FFFFFF';
$border_color = 'DEE2E6';
$light        = 'F8F9FA';

$status_colors = [
    'present' => ['bg' => 'D4EDDA', 'font' => '155724'],
    'late'    => ['bg' => 'FFF3CD', 'font' => '856404'],
    'excused' => ['bg' => 'E8D5F5', 'font' => '4A1070'],
    'absent'  => ['bg' => 'FFE0E0', 'font' => 'DC3545'],
];

// Column layout: A=#, B:C=Student Number, D:E=Full Name, F:I=Program, J:K=Year Level, L=Block, M=Time Scanned, N=Status
$last_col = 'N';

$spreadsheet = new Spreadsheet();

// =====================
// SHEET 1 — ATTENDANCE
// =====================
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Attendance Records');

// ROW 1-2 — Header
$sheet->mergeCells('A1:' . $last_col . '2');
$sheet->setCellValue('A1', 'PAMANTASAN NG LUNGSOD NG MAYNILA' . "\n" . 'QR-Based Attendance Monitoring System');
$sheet->getStyle('A1')->applyFromArray([
    'font'      => ['bold' => true, 'size' => 14, 'color' => ['rgb' => $gold]],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $navy]],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
]);
$sheet->getRowDimension(1)->setRowHeight(35);
$sheet->getRowDimension(2)->setRowHeight(25);

// ROW 3 — Spacer
$sheet->getRowDimension(3)->setRowHeight(8);

// ROWS 4-9 — Session Info
$info = [
    ['Class / Section', $session['class_name']],
    ['Subject',         $session['subject']],
    ['Date of Session', date('F d, Y', strtotime($session['session_date']))],
    ['Time of Session', date('h:i A', strtotime($session['start_time'])) . ' — ' . date('h:i A', strtotime($session['expiry_time']))],
    ['Faculty',         $session['teacher_name']],
    ['Date Printed',    date('F d, Y h:i A')],
];
$info_row = 4;
foreach($info as $item){
    $sheet->mergeCells('A' . $info_row . ':B' . $info_row);
    $sheet->setCellValue('A' . $info_row, $item[0]);
    $sheet->mergeCells('C' . $info_row . ':' . $last_col . $info_row);
    $sheet->setCellValue('C' . $info_row, $item[1]);
    $sheet->getStyle('A' . $info_row)->applyFromArray([
        'font'      => ['bold' => true, 'color' => ['rgb' => $navy]],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EEF2FF']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
    ]);
    $sheet->getStyle('C' . $info_row)->applyFromArray([
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $white]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
    ]);
    $sheet->getRowDimension($info_row)->setRowHeight(18);
    $info_row++;
}

// ROW 11 — Spacer
$sheet->getRowDimension(11)->setRowHeight(10);
$sheet->getStyle('A10:N11')->applyFromArray([
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_NONE]],
    'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $navy]],
]);

// ROW 12 — Summary Cards
$sheet->mergeCells('A12:C12');
$sheet->mergeCells('D12:F12');
$sheet->mergeCells('G12:I12');
$sheet->mergeCells('J12:N12');
$summary_cards = [
    ['A12', 'PRESENT: '  . $total_present,  '28A745'],
    ['D12', 'LATE: '     . $total_late,     'D09C00'],
    ['G12', 'EXCUSED: '  . $total_excused,  '6F42C1'],
    ['J12', 'ABSENT: '   . $total_absent,   'DC3545'],
];
foreach($summary_cards as $s){
    $sheet->setCellValue($s[0], $s[1]);
    $sheet->getStyle($s[0])->applyFromArray([
        'font'      => ['bold' => true, 'size' => 11, 'color' => ['rgb' => $white]],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $s[2]]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    ]);
}
$sheet->getRowDimension(12)->setRowHeight(28);

// ROW 13 — Attendance Rate
$sheet->mergeCells('A13:N14');
$sheet->setCellValue('A13', 'ATTENDANCE RATE: ' . $rate . '%  |  Total Enrolled: ' . $total_enrolled);
$sheet->getStyle('A13')->applyFromArray([
    'font'      => ['bold' => true, 'size' => 11, 'color' => ['rgb' => $navy]],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF8E1']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
]);
$sheet->getRowDimension(13)->setRowHeight(22);
$sheet->getRowDimension(14)->setRowHeight(22);

// ROW 15 — Table Headers
$headers = [
    'A' => '#',
    'B' => 'Student Number',
    'D' => 'Full Name',
    'F' => 'Program',
    'J' => 'Year Level',
    'L' => 'Block',
    'M' => 'Time Scanned',
    'N' => 'Status',
];
foreach($headers as $col => $header){
    $sheet->setCellValue($col . '15', $header);
}
$sheet->mergeCells('B15:C15');
$sheet->mergeCells('D15:E15');
$sheet->mergeCells('F15:I15');
$sheet->mergeCells('J15:K15');
$sheet->getStyle('A15:' . $last_col . '15')->applyFromArray([
    'font'      => ['bold' => true, 'size' => 11, 'color' => ['rgb' => $white]],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $navy]],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $white]]],
]);
$sheet->getRowDimension(15)->setRowHeight(22);

// ROWS 16+ — Scanned Records
$row = 16;
$i   = 1;
foreach($records as $rec){
    $bg     = ($i % 2 == 0) ? $light : $white;
    $status = $rec['status'];
    $s_bg   = $status_colors[$status]['bg']   ?? $bg;
    $s_font = $status_colors[$status]['font'] ?? '333333';

    $sheet->setCellValue('A' . $row, $i);
    $sheet->mergeCells('B' . $row . ':C' . $row);
    $sheet->setCellValue('B' . $row, $rec['student_number']);
    $sheet->mergeCells('D' . $row . ':E' . $row);
    $sheet->setCellValue('D' . $row, $rec['full_name']);
    $sheet->mergeCells('F' . $row . ':I' . $row);
    $sheet->setCellValue('F' . $row, $rec['course']);
    $sheet->mergeCells('J' . $row . ':K' . $row);
    $sheet->setCellValue('J' . $row, 'Year ' . $rec['year_level']);
    $sheet->setCellValue('L' . $row, isset($rec['block']) ? 'Block ' . $rec['block'] : '—');
    $sheet->setCellValue('M' . $row, date('h:i A', strtotime($rec['time_scanned'])));
    $sheet->setCellValue('N' . $row, strtoupper($status));

    $sheet->getStyle('A' . $row . ':M' . $row)->applyFromArray([
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $border_color]]],
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
    ]);
    $sheet->getStyle('N' . $row)->applyFromArray([
        'font'      => ['bold' => true, 'color' => ['rgb' => $s_font]],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $s_bg]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $border_color]]],
    ]);
    $sheet->getRowDimension($row)->setRowHeight(18);
    $row++; $i++;
}

// Absent Students Section
if(count($absents) > 0){
    $row++;
    $sheet->mergeCells('A' . $row . ':' . $last_col . $row);
    $sheet->setCellValue('A' . $row, 'ABSENT STUDENTS');
    $sheet->getStyle('A' . $row)->applyFromArray([
        'font'      => ['bold' => true, 'size' => 11, 'color' => ['rgb' => $white]],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DC3545']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);
    $sheet->getRowDimension($row)->setRowHeight(22);
    $row++;

    foreach(['A'=>'#','B'=>'Student Number','D'=>'Full Name','F'=>'Program','J'=>'Year Level','L'=>'Block','M'=>'','N'=>'Status'] as $col => $h){
        $sheet->setCellValue($col . $row, $h);
    }
    $sheet->mergeCells('B' . $row . ':C' . $row);
    $sheet->mergeCells('D' . $row . ':E' . $row);
    $sheet->mergeCells('F' . $row . ':I' . $row);
    $sheet->mergeCells('J' . $row . ':K' . $row);
    $sheet->getStyle('A' . $row . ':' . $last_col . $row)->applyFromArray([
        'font'      => ['bold' => true, 'color' => ['rgb' => $white]],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '6C757D']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);
    $sheet->getRowDimension($row)->setRowHeight(20);
    $row++;

    $j = 1;
    foreach($absents as $abs){
        $bg = ($j % 2 == 0) ? 'FFF5F5' : $white;
        $sheet->setCellValue('A' . $row, $j);
        $sheet->mergeCells('B' . $row . ':C' . $row);
        $sheet->setCellValue('B' . $row, $abs['student_number']);
        $sheet->mergeCells('D' . $row . ':E' . $row);
        $sheet->setCellValue('D' . $row, $abs['full_name']);
        $sheet->mergeCells('F' . $row . ':I' . $row);
        $sheet->setCellValue('F' . $row, $abs['course']);
        $sheet->mergeCells('J' . $row . ':K' . $row);
        $sheet->setCellValue('J' . $row, 'Year ' . $abs['year_level']);
        $sheet->setCellValue('L' . $row, isset($abs['block']) ? 'Block ' . $abs['block'] : '—');
        $sheet->setCellValue('M' . $row, '');
        $sheet->setCellValue('N' . $row, 'ABSENT');
        $sheet->getStyle('A' . $row . ':' . $last_col . $row)->applyFromArray([
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $border_color]]],
        ]);
        $sheet->getStyle('N' . $row)->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'DC3545']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(18);
        $row++; $j++;
    }
}

// Signature Section
$row += 2;
$sig_start = $row;

$sheet->mergeCells('A' . $row . ':E' . $row);
$sheet->mergeCells('J' . $row . ':N' . $row);
$sheet->getStyle('A' . $row . ':E' . $row)->applyFromArray([
    'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '333333']]],
]);
$sheet->getStyle('J' . $row . ':N' . $row)->applyFromArray([
    'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '333333']]],
]);
$sheet->getRowDimension($row)->setRowHeight(20);
$row++;

$sheet->mergeCells('A' . $row . ':E' . $row);
$sheet->mergeCells('J' . $row . ':N' . $row);
$sheet->setCellValue('A' . $row, $session['teacher_name']);
$sheet->setCellValue('J' . $row, 'Department Head / Dean');
$sheet->getStyle('A' . $row)->applyFromArray([
    'font'      => ['bold' => true, 'color' => ['rgb' => $navy]],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
]);
$sheet->getStyle('J' . $row)->applyFromArray([
    'font'      => ['bold' => true, 'color' => ['rgb' => $navy]],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
]);
$row++;

$sheet->mergeCells('A' . $row . ':E' . $row);
$sheet->mergeCells('J' . $row . ':N' . $row);
$sheet->setCellValue('A' . $row, 'Faculty / Instructor');
$sheet->setCellValue('J' . $row, 'Noted by');
$sheet->getStyle('A' . $row)->applyFromArray([
    'font'      => ['italic' => true, 'color' => ['rgb' => '666666']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
]);
$sheet->getStyle('J' . $row)->applyFromArray([
    'font'      => ['italic' => true, 'color' => ['rgb' => '666666']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
]);
$sig_end = $row;

// Clear all borders in the entire signature area
$sheet->getStyle('A' . $sig_start . ':N' . $sig_end)->applyFromArray([
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_NONE]],
]);
// Re-apply only the signature underlines
$sheet->getStyle('A' . $sig_start . ':E' . $sig_start)->applyFromArray([
    'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '333333']]],
]);
$sheet->getStyle('J' . $sig_start . ':N' . $sig_start)->applyFromArray([
    'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '333333']]],
]);

// Column widths
$sheet->getColumnDimension('A')->setWidth(5);   // #
$sheet->getColumnDimension('B')->setWidth(13);  // Student Number (B:C)
$sheet->getColumnDimension('C')->setWidth(13);
$sheet->getColumnDimension('D')->setWidth(16);  // Full Name (D:E)
$sheet->getColumnDimension('E')->setWidth(16);
$sheet->getColumnDimension('F')->setWidth(10);  // Program (F:I)
$sheet->getColumnDimension('G')->setWidth(10);
$sheet->getColumnDimension('H')->setWidth(10);
$sheet->getColumnDimension('I')->setWidth(15);
$sheet->getColumnDimension('J')->setWidth(5);  // Year Level (J:K)
$sheet->getColumnDimension('K')->setWidth(10);
$sheet->getColumnDimension('L')->setWidth(10);  // Block
$sheet->getColumnDimension('M')->setWidth(14);  // Time Scanned
$sheet->getColumnDimension('N')->setWidth(12);  // Status

// =====================
// SHEET 2 — SUMMARY
// =====================
$spreadsheet->createSheet();
$spreadsheet->setActiveSheetIndex(1);
$ss = $spreadsheet->getActiveSheet();
$ss->setTitle('Summary');

$ss->mergeCells('A1:D1');
$ss->setCellValue('A1', 'ATTENDANCE SUMMARY REPORT');
$ss->getStyle('A1')->applyFromArray([
    'font'      => ['bold' => true, 'size' => 14, 'color' => ['rgb' => $gold]],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $navy]],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
]);
$ss->getRowDimension(1)->setRowHeight(30);

foreach([
    ['A3', 'Class / Section', $session['class_name']],
    ['A4', 'Subject',         $session['subject']],
    ['A5', 'Date',            date('F d, Y', strtotime($session['session_date']))],
    ['A6', 'Faculty',         $session['teacher_name']],
] as $r){
    $ss->setCellValue($r[0], $r[1]);
    $ss->setCellValue('B' . substr($r[0], 1), $r[2]);
    $ss->getStyle($r[0])->getFont()->setBold(true);
    $ss->getStyle($r[0])->getFont()->getColor()->setRGB($navy);
}

$ss->setCellValue('A8', 'Metric');
$ss->setCellValue('B8', 'Count');
$ss->setCellValue('C8', 'Percentage');
$ss->getStyle('A8:C8')->applyFromArray([
    'font'      => ['bold' => true, 'color' => ['rgb' => $white]],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $navy]],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
]);

$pct = fn($n) => $total_enrolled > 0 ? round($n / $total_enrolled * 100, 1) . '%' : '0%';
$summary_rows = [
    ['Total Enrolled',   $total_enrolled, '100%',               'FFFFFF'],
    ['Present',          $total_present,  $pct($total_present), 'D4EDDA'],
    ['Late',             $total_late,     $pct($total_late),    'FFF3CD'],
    ['Excused',          $total_excused,  $pct($total_excused), 'E8D5F5'],
    ['Absent',           $total_absent,   $pct($total_absent),  'FFE0E0'],
    ['Attendance Rate',  $rate . '%',     '',                   'FFF8E1'],
];
$s_row = 9;
foreach($summary_rows as $idx => $data){
    $ss->setCellValue('A' . $s_row, $data[0]);
    $ss->setCellValue('B' . $s_row, $data[1]);
    $ss->setCellValue('C' . $s_row, $data[2]);
    $ss->getStyle('A' . $s_row . ':C' . $s_row)->applyFromArray([
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $data[3]]],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $border_color]]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);
    if($idx === 0 || $idx === 5) $ss->getStyle('A' . $s_row)->getFont()->setBold(true);
    $s_row++;
}
$ss->getColumnDimension('A')->setWidth(20);
$ss->getColumnDimension('B')->setWidth(15);
$ss->getColumnDimension('C')->setWidth(15);

// Output
$spreadsheet->setActiveSheetIndex(0);
$filename = 'Attendance_' . preg_replace('/_+/', '_', preg_replace('/[^a-zA-Z0-9_\-]/', '_', $session['class_name'])) . '_' . $session['session_date'] . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

(new Xlsx($spreadsheet))->save('php://output');
exit();
