<?php
require_once '../model/brainstorming.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExportController {
    public function exportBrainstormingsToExcel() {
        $pdo = $this->getDatabaseConnection();
        $stmt = $pdo->query("SELECT b.title, b.description, b.category, b.created_at, b.status, COUNT(i.id) AS idea_count FROM brainstorming b LEFT JOIN idea i ON b.id = i.brainstorming_id GROUP BY b.id");
        $brainstormings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set header row
        $sheet->setCellValue('A1', 'Title')
              ->setCellValue('B1', 'Description')
              ->setCellValue('C1', 'Category')
              ->setCellValue('D1', 'Created At')
              ->setCellValue('E1', 'Status')
              ->setCellValue('F1', 'Number of Ideas');

        // Populate data rows
        $row = 2;
        foreach ($brainstormings as $brainstorming) {
            $sheet->setCellValue("A$row", $brainstorming['title'])
                  ->setCellValue("B$row", $brainstorming['description'])
                  ->setCellValue("C$row", $brainstorming['category'])
                  ->setCellValue("D$row", $brainstorming['created_at'])
                  ->setCellValue("E$row", $brainstorming['status'])
                  ->setCellValue("F$row", $brainstorming['idea_count']);
            $row++;
        }

        // Write to file
        $writer = new Xlsx($spreadsheet);
        $fileName = '../storage/brainstormings_export.xlsx';
        $writer->save($fileName);

        // Trigger download
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="brainstormings_export.xlsx"');
        readfile($fileName);
        exit;
    }

    private function getDatabaseConnection() {
        // Assuming PDO connection is already set up
        return new PDO('mysql:host=localhost;dbname=secondvoice', 'root', '');
    }
}