<?php

namespace App\Services;

use PhpOffice\PhpPresentation\PhpPresentation;
use PhpOffice\PhpPresentation\Slide;
use PhpOffice\PhpPresentation\Slide\Background\Color;
use PhpOffice\PhpPresentation\Style\Alignment as SlideAlignment;
use PhpOffice\PhpPresentation\Style\Color as SlideColor;
use PhpOffice\PhpPresentation\Writer\PowerPoint2007;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Sinh file Excel và PowerPoint từ bảng trong câu trả lời.
 *
 * File được ghi vào một tệp tạm rồi trả về đường dẫn: cả hai thư viện đều viết
 * theo dạng luồng ra đĩa chứ không dựng sẵn trong bộ nhớ, và một bảng vài nghìn
 * dòng dựng trong RAM là cách nhanh nhất để hạ một máy chủ nhỏ.
 *
 * Chỗ gọi có trách nhiệm xoá tệp sau khi gửi xong.
 */
class AnswerExporter
{
    /** Màu của sản phẩm, để file tải về trông cùng một nhà với ứng dụng. */
    private const ACCENT = 'E3A04B';

    private const INK = '17150F';

    private const PAPER = 'FFFFFF';

    /**
     * Một tệp .xlsx chứa mọi bảng của câu trả lời, mỗi bảng một trang tính.
     *
     * @param  array<int, array{headers: array<int, string>, rows: array<int, array<int, string>>}>  $tables
     */
    public function xlsx(array $tables, string $title): string
    {
        $book = new Spreadsheet;
        $book->getProperties()->setCreator('SnapAsk')->setTitle($title);
        $book->removeSheetByIndex(0);

        foreach ($tables as $index => $table) {
            $sheet = $book->createSheet();
            $sheet->setTitle($this->sheetName($title, $index, count($tables)));

            $sheet->fromArray($table['headers'], null, 'A1');

            foreach ($table['rows'] as $row => $cells) {
                // Ghi từng ô một để giữ nguyên chuỗi: `fromArray` để Excel tự
                // đoán kiểu, và mã như "0123" hay "1-2" sẽ bị đổi thành số hay ngày.
                foreach ($cells as $column => $value) {
                    $sheet->setCellValueExplicit(
                        [$column + 1, $row + 2],
                        $value,
                        $this->cellType($value),
                    );
                }
            }

            $this->styleSheet($sheet, count($table['headers']), count($table['rows']));
        }

        $path = tempnam(sys_get_temp_dir(), 'snapask-xlsx-');
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        return $path;
    }

    /**
     * Một tệp .pptx: slide tiêu đề, rồi mỗi bảng một slide.
     *
     * @param  array<int, array{headers: array<int, string>, rows: array<int, array<int, string>>}>  $tables
     */
    public function pptx(array $tables, string $title, ?array $chart = null): string
    {
        $deck = new PhpPresentation;
        $deck->getDocumentProperties()->setCreator('SnapAsk')->setTitle($title);

        $first = $deck->getActiveSlide();
        $this->paintBackground($first);
        $this->addText($first, $title, 40, 240, 620, 32, true);

        if ($chart !== null && $chart['title'] !== '') {
            $this->addText($first, $chart['title'], 40, 300, 620, 16);
        }

        foreach ($tables as $index => $table) {
            $slide = $deck->createSlide();
            $this->paintBackground($slide);
            $this->addText($slide, $this->slideHeading($title, $index, count($tables)), 40, 36, 620, 20, true);
            $this->addTable($slide, $table);
        }

        $path = tempnam(sys_get_temp_dir(), 'snapask-pptx-');
        (new PowerPoint2007($deck))->save($path);

        return $path;
    }

    /** Ô rỗng hay số vẫn phải là chuỗi khi nó vốn là chuỗi trong bảng gốc. */
    private function cellType(string $value): string
    {
        return $value === ''
            ? DataType::TYPE_NULL
            : DataType::TYPE_STRING;
    }

    private function styleSheet(Worksheet $sheet, int $columns, int $rows): void
    {
        $last = $sheet->getCell([max(1, $columns), 1])->getColumn();

        $sheet->getStyle("A1:{$last}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => self::INK]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::ACCENT]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        if ($rows > 0) {
            $sheet->getStyle("A1:{$last}".($rows + 1))->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D8D0C5']]],
            ]);
        }

        for ($column = 1; $column <= $columns; $column++) {
            $sheet->getColumnDimensionByColumn($column)->setAutoSize(true);
        }

        // Khoá hàng tiêu đề: bảng dài cuộn xuống vẫn biết cột nào là cột nào.
        $sheet->freezePane('A2');
    }

    private function sheetName(string $title, int $index, int $total): string
    {
        $base = $total > 1 ? $title.' '.($index + 1) : $title;
        // Excel cấm : \ / ? * [ ] trong tên trang tính và giới hạn 31 ký tự.
        $clean = preg_replace('#[:\\\\/?*\[\]]#', ' ', $base) ?? 'Bang';

        return mb_substr(trim($clean) ?: 'Bang', 0, 31);
    }

    private function slideHeading(string $title, int $index, int $total): string
    {
        return $total > 1 ? $title.' — '.($index + 1) : $title;
    }

    private function paintBackground(Slide $slide): void
    {
        $background = new Color;
        $background->setColor(new SlideColor('FF'.self::INK));
        $slide->setBackground($background);
    }

    private function addText(
        Slide $slide,
        string $text,
        int $x,
        int $y,
        int $width,
        int $size,
        bool $bold = false,
    ): void {
        $shape = $slide->createRichTextShape();
        $shape->setHeight(40)->setWidth($width)->setOffsetX($x)->setOffsetY($y);
        $shape->getActiveParagraph()->getAlignment()->setHorizontal(SlideAlignment::HORIZONTAL_LEFT);

        $run = $shape->createTextRun($text);
        $run->getFont()->setBold($bold)->setSize($size)->setColor(new SlideColor('FFF3EFE8'));
    }

    /**
     * Bảng trên slide.
     *
     * Cắt bớt hàng: một slide chứa quá mười mấy dòng thì chữ nhỏ đến mức không
     * đọc được khi chiếu, mà người xem cần Excel chứ không cần slide cho việc đó.
     *
     * @param  array{headers: array<int, string>, rows: array<int, array<int, string>>}  $table
     */
    private function addTable(Slide $slide, array $table): void
    {
        $columns = max(1, count($table['headers']));
        $rows = array_slice($table['rows'], 0, 12);

        $shape = $slide->createTableShape($columns);
        $shape->setWidth(620)->setOffsetX(40)->setOffsetY(90);

        $header = $shape->createRow();

        foreach ($table['headers'] as $index => $text) {
            $cell = $header->nextCell();
            $cell->createTextRun($text)->getFont()->setBold(true)->setSize(12)->setColor(new SlideColor('FF'.self::INK));
            $cell->getFill()->setFillType(\PhpOffice\PhpPresentation\Style\Fill::FILL_SOLID)
                ->setStartColor(new SlideColor('FF'.self::ACCENT))
                ->setEndColor(new SlideColor('FF'.self::ACCENT));
        }

        foreach ($rows as $cells) {
            $row = $shape->createRow();

            foreach ($cells as $text) {
                $cell = $row->nextCell();
                $cell->createTextRun($text === '' ? ' ' : $text)->getFont()->setSize(11)->setColor(new SlideColor('FFF3EFE8'));
            }
        }

        if (count($table['rows']) > count($rows)) {
            $this->addText($slide, '… còn '.(count($table['rows']) - count($rows)).' hàng, xem bản Excel.', 40, 430, 620, 11);
        }
    }
}
