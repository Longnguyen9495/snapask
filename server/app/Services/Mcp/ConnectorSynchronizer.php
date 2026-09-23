<?php

namespace App\Services\Mcp;

use App\Models\McpConnector;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Throwable;

class ConnectorSynchronizer
{
    public function __construct(private McpClient $client) {}

    /**
     * Bắt tay với dịch vụ và ghi lại danh sách công cụ nó cung cấp.
     *
     * Lỗi kết nối được cất vào `last_error` chứ không ném ra ngoài: connector
     * vẫn phải được lưu lại để khách sửa địa chỉ hay token, thay vì biến mất và
     * bắt họ nhập lại từ đầu.
     */
    public function sync(McpConnector $connector): McpConnector
    {
        try {
            $connector->update([
                'tools' => $this->client->listTools($connector),
                'synced_at' => now(),
                'last_error' => null,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            $connector->update([
                'tools' => null,
                'last_error' => $this->explain($exception),
            ]);
        }

        return $connector;
    }

    /**
     * Đổi một ngoại lệ thành câu mà người khai báo dịch vụ đọc được.
     *
     * Thông điệp thô của tầng HTTP là thứ dành cho lập trình viên — nó kèm cả
     * mã lỗi cURL và đường dẫn tài liệu, đọc như phần mềm hỏng chứ không như
     * một chỉ dẫn sửa được.
     */
    private function explain(Throwable $exception): string
    {
        if ($exception instanceof ConnectionException) {
            return 'Không kết nối được tới địa chỉ này. Hãy kiểm tra lại đường dẫn và xem dịch vụ có đang chạy không.';
        }

        if ($exception instanceof RequestException) {
            return match (true) {
                $exception->response->status() === 401,
                $exception->response->status() === 403 => 'Dịch vụ từ chối token truy cập. Hãy kiểm tra lại token.',
                $exception->response->status() === 404 => 'Địa chỉ này không có máy chủ MCP nào trả lời.',
                $exception->response->serverError() => 'Dịch vụ đang gặp sự cố, hãy thử lại sau.',
                default => 'Dịch vụ trả về lỗi HTTP '.$exception->response->status().'.',
            };
        }

        return mb_substr($exception->getMessage(), 0, 250);
    }
}
