<?php

namespace App\Services;

/**
 * Rút các bảng markdown ra khỏi câu trả lời của mô hình.
 *
 * Dùng cho việc xuất file: người dùng chụp một bảng số liệu, hỏi, rồi muốn mở
 * kết quả bằng Excel. Bảng nằm lẫn trong văn xuôi nên phải tách ra trước.
 *
 * Luật nhận dạng giống hệt bản chạy trong ứng dụng desktop
 * (`desktop/src/renderer/lib/markdown.js`): một dòng tiêu đề, một dòng phân
 * cách `|---|`, rồi các dòng dữ liệu. Hai bên phải khớp nhau, nếu không thì cái
 * người dùng nhìn thấy và cái họ tải về sẽ khác nhau.
 */
class TableExtractor
{
    /** Trần số hàng mỗi bảng, để một câu trả lời hỏng không ngốn hết bộ nhớ. */
    private const MAX_ROWS = 5000;

    /**
     * Mọi bảng trong một câu trả lời, theo thứ tự xuất hiện.
     *
     * @return array<int, array{headers: array<int, string>, rows: array<int, array<int, string>>}>
     */
    public function extract(string $content): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];
        $tables = [];
        $total = count($lines);

        for ($i = 0; $i < $total; $i++) {
            $table = $this->tableAt($lines, $i);

            if ($table === null) {
                continue;
            }

            $tables[] = $table['table'];
            $i = $table['end'];
        }

        return $tables;
    }

    /** Bảng đầu tiên, hoặc null khi câu trả lời không có bảng nào. */
    public function first(string $content): ?array
    {
        return $this->extract($content)[0] ?? null;
    }

    /**
     * Mô tả biểu đồ trong khối ```chart, nếu có.
     *
     * Chỉ lấy đúng những trường dùng để dựng slide: nhan đề, nhãn trục và các
     * chuỗi số. Mô hình có ghi thêm gì cũng bỏ, giống hệt bản chạy trong ứng
     * dụng desktop — thứ gì không nằm trong danh sách cho phép thì không đi tiếp.
     *
     * @return array{title: string, labels: array<int, string>, datasets: array<int, array{label: string, data: array<int, float|null>}>}|null
     */
    public function chart(string $content): ?array
    {
        if (preg_match('/^[ \t]{0,3}```[ \t]*chart[ \t]*\R(.*?)\R?[ \t]{0,3}```/ms', $content, $match) !== 1) {
            return null;
        }

        $spec = json_decode(trim($match[1]), true);

        if (! is_array($spec) || array_is_list($spec)) {
            return null;
        }

        $datasets = [];

        foreach (array_slice($spec['datasets'] ?? [], 0, 8) as $set) {
            if (! is_array($set) || ! is_array($set['data'] ?? null)) {
                continue;
            }

            $data = array_map($this->number(...), array_slice($set['data'], 0, self::MAX_ROWS));

            if (array_filter($data, fn ($value) => $value !== null) === []) {
                continue;
            }

            $datasets[] = ['label' => $this->text($set['label'] ?? ''), 'data' => $data];
        }

        if ($datasets === []) {
            return null;
        }

        $labels = array_map($this->text(...), array_slice($spec['labels'] ?? [], 0, self::MAX_ROWS));

        return ['title' => $this->text($spec['title'] ?? ''), 'labels' => $labels, 'datasets' => $datasets];
    }

    /** Một ô số: nhận cả "1.250" và "3,5" như người Việt hay viết. */
    private function number(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return is_finite((float) $value) ? (float) $value : null;
        }

        if (! is_string($value)) {
            return null;
        }

        $cleaned = preg_replace('/[\s.](?=\d{3}\b)/', '', trim($value)) ?? '';
        $cleaned = str_replace(',', '.', $cleaned);
        $cleaned = preg_replace('/[^\d.eE+-]/', '', $cleaned) ?? '';

        return is_numeric($cleaned) ? (float) $cleaned : null;
    }

    private function text(mixed $value): string
    {
        return mb_substr(is_scalar($value) ? trim((string) $value) : '', 0, 120);
    }

    /**
     * Một bảng bắt đầu tại dòng $start.
     *
     * @param  array<int, string>  $lines
     * @return array{table: array{headers: array<int, string>, rows: array<int, array<int, string>>}, end: int}|null
     */
    private function tableAt(array $lines, int $start): ?array
    {
        $head = $lines[$start] ?? null;
        $divider = $lines[$start + 1] ?? null;

        if ($head === null || $divider === null || ! str_contains($head, '|')) {
            return null;
        }

        if (preg_match('/^\s{0,3}\|?\s*:?-{1,}:?\s*(\|\s*:?-{1,}:?\s*)*\|?\s*$/', $divider) !== 1) {
            return null;
        }

        $headers = $this->cells($head);
        $width = count($headers);

        if ($width < 1 || count($this->cells($divider)) !== $width) {
            return null;
        }

        $rows = [];
        $end = $start + 1;

        for ($i = $start + 2, $total = count($lines); $i < $total; $i++) {
            $line = $lines[$i];

            if (trim($line) === '' || ! str_contains($line, '|')) {
                break;
            }

            $cells = $this->cells($line);

            // Ép về đúng số cột: mô hình hay trả thừa hoặc thiếu một ô, mà lệch
            // cột thì bảng trong Excel đọc thành vô nghĩa.
            $cells = array_slice(array_pad($cells, $width, ''), 0, $width);

            $rows[] = $cells;
            $end = $i;

            if (count($rows) >= self::MAX_ROWS) {
                break;
            }
        }

        return ['table' => ['headers' => $headers, 'rows' => $rows], 'end' => $end];
    }

    /**
     * Tách một dòng thành các ô và gỡ ký hiệu markdown trong từng ô.
     *
     * Ô mang `**đậm**` hay `` `mã` `` đổ sang Excel sẽ hiện nguyên dấu sao và
     * dấu huyền — người nhận tưởng dữ liệu hỏng.
     *
     * @return array<int, string>
     */
    private function cells(string $line): array
    {
        $trimmed = preg_replace('/^\s*\||\|\s*$/', '', trim($line)) ?? '';

        return array_map(
            fn (string $cell): string => $this->plain($cell),
            explode('|', $trimmed),
        );
    }

    private function plain(string $cell): string
    {
        $text = preg_replace([
            '/`([^`]*)`/u',
            '/!?\[([^\]]*)\]\([^)]*\)/u',
            '/(\*\*|__|~~)(.+?)\1/u',
            '/(?<![\w*])\*([^*]+)\*(?![\w*])/u',
        ], ['$1', '$1', '$2', '$1'], trim($cell)) ?? $cell;

        return trim($text);
    }
}
