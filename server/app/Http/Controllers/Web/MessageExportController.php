<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\AnswerExporter;
use App\Services\TableExtractor;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Tải bảng trong một câu trả lời về dạng file mở được bằng Excel hoặc PowerPoint.
 *
 * Bảng không được lưu riêng ở đâu cả: nó nằm trong markdown của câu trả lời và
 * được tách lại mỗi lần tải. Như vậy không có bản sao nào lệch với thứ người
 * dùng đang nhìn, và cũng không phải thêm cột nào vào cơ sở dữ liệu.
 */
class MessageExportController extends Controller
{
    /** Dấu hiệu cho Excel biết file là UTF-8; thiếu nó thì tiếng Việt ra ký tự lạ. */
    private const BOM = "\xEF\xBB\xBF";

    private const FORMATS = ['csv', 'xlsx', 'pptx'];

    public function __construct(
        private TableExtractor $tables,
        private AnswerExporter $exporter,
    ) {}

    public function __invoke(Request $request, Conversation $conversation, Message $message): Response
    {
        abort_unless($conversation->user_id === $request->user()->id, 404);
        abort_unless($message->conversation_id === $conversation->id, 404);

        $format = (string) $request->query('format', 'csv');
        abort_unless(in_array($format, self::FORMATS, true), 404);

        $tables = $this->tables->extract((string) $message->content);
        abort_if($tables === [], 404);

        $title = trim((string) $conversation->title) ?: __('Conversation');

        // CSV là một bảng mỗi file; hai định dạng kia gom cả câu trả lời vào một file.
        if ($format === 'csv') {
            $index = max(0, (int) $request->query('table', '0'));

            return $this->csv($tables[$index] ?? $tables[0], $this->filename($conversation, 'csv', count($tables) > 1 ? $index + 1 : null));
        }

        $path = $format === 'xlsx'
            ? $this->exporter->xlsx($tables, $title)
            : $this->exporter->pptx($tables, $title, $this->tables->chart((string) $message->content));

        return $this->file($path, $this->filename($conversation, $format), $format);
    }

    /** @param  array{headers: array<int, string>, rows: array<int, array<int, string>>}  $table */
    private function csv(array $table, string $name): StreamedResponse
    {
        return response()->streamDownload(function () use ($table): void {
            $out = fopen('php://output', 'wb');

            echo self::BOM;

            fputcsv($out, $table['headers']);

            foreach ($table['rows'] as $row) {
                fputcsv($out, $row);
            }

            fclose($out);
        }, $name, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    /** Gửi tệp tạm rồi xoá: không giữ lại bản sao nội dung của khách trên đĩa. */
    private function file(string $path, string $name, string $format): BinaryFileResponse
    {
        $types = [
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ];

        return response()->download($path, $name, [
            'Content-Type' => $types[$format],
            'Cache-Control' => 'no-store',
        ])->deleteFileAfterSend();
    }

    /** Tên file lấy theo tiêu đề hội thoại, bỏ dấu để mọi hệ điều hành mở được. */
    private function filename(Conversation $conversation, string $extension, ?int $number = null): string
    {
        $base = Str::slug((string) $conversation->title) ?: 'snapask';
        $base = Str::limit($base, 60, '');

        return $base.($number !== null ? '-bang-'.$number : '').'.'.$extension;
    }
}
