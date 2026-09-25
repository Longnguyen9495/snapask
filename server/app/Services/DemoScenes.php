<?php

namespace App\Services;

/**
 * Những màn hình mẫu mà khách thử ngay trên trang chủ.
 *
 * Ba cảnh, chọn theo ba nhóm người khác nhau: người viết phần mềm, người làm
 * sổ sách, người đọc báo cáo. Bản cũ chỉ có đúng một đoạn mã lỗi — ai không
 * lập trình nhìn vào không thấy mình trong đó, và phần lớn khách của SnapAsk
 * không lập trình.
 *
 * Nội dung nằm ở đây chứ không nằm trong Blade: cả trang chủ lẫn lượt hỏi AI
 * đều đọc từ một nguồn, nên câu hỏi gửi lên mô hình luôn khớp đúng thứ khách
 * đang nhìn thấy trên màn hình.
 */
class DemoScenes
{
    /**
     * @return array<string, array{
     *     label: string,
     *     kind: string,
     *     title: string,
     *     hint: string,
     *     selection: string,
     *     context: string,
     *     questions: array<int, string>,
     *     lines: array<int, array{n: string, text: string, target?: bool}>,
     *     note: ?string
     * }>
     */
    public function all(): array
    {
        return [
            'invoice' => [
                'label' => __('An invoice'),
                'kind' => 'document',
                'title' => 'hoa-don-thang-9.pdf',
                'hint' => __('Drag around the totals'),
                'selection' => __('Invoice totals'),
                'questions' => [
                    __('Is the VAT calculated correctly?'),
                    __('What is the total after discount?'),
                    __('Summarise this invoice in one line'),
                ],
                'lines' => [
                    ['n' => '', 'text' => 'CÔNG TY TNHH AN PHÁT'],
                    ['n' => '', 'text' => 'Hoá đơn GTGT · Số 0009412 · 30/09/2026'],
                    ['n' => '', 'text' => '─────────────────────────────────'],
                    ['n' => '1', 'text' => 'Bàn phím cơ K380      12 × 890.000 = 10.680.000'],
                    ['n' => '2', 'text' => 'Chuột không dây M720   8 × 650.000 =  5.200.000'],
                    ['n' => '3', 'text' => 'Đế tản nhiệt          15 × 320.000 =  4.800.000'],
                    ['n' => '', 'text' => '─────────────────────────────────'],
                    ['n' => '', 'text' => 'Cộng tiền hàng                    20.680.000', 'target' => true],
                    ['n' => '', 'text' => 'Chiết khấu 5%                     -1.034.000', 'target' => true],
                    ['n' => '', 'text' => 'Thuế GTGT 8%                       1.571.680', 'target' => true],
                    ['n' => '', 'text' => 'TỔNG THANH TOÁN                   21.217.680', 'target' => true],
                ],
                'context' => <<<'TEXT'
                Ảnh chụp một hoá đơn GTGT của CÔNG TY TNHH AN PHÁT, số 0009412, ngày 30/09/2026.

                Các dòng hàng:
                1. Bàn phím cơ K380 — 12 cái × 890.000 = 10.680.000
                2. Chuột không dây M720 — 8 cái × 650.000 = 5.200.000
                3. Đế tản nhiệt — 15 cái × 320.000 = 4.800.000

                Phần vùng người dùng khoanh chọn:
                Cộng tiền hàng: 20.680.000
                Chiết khấu 5%: -1.034.000
                Thuế GTGT 8%: 1.571.680
                TỔNG THANH TOÁN: 21.217.680
                TEXT,
                'note' => __('Numbers in this sample are made up.'),
            ],

            'report' => [
                'label' => __('A sales report'),
                'kind' => 'sheet',
                'title' => 'doanh-thu-quy-3.xlsx',
                'hint' => __('Drag around the table'),
                'selection' => __('Revenue by month'),
                'questions' => [
                    __('Draw me a chart of this'),
                    __('Which month dropped the most?'),
                    __('Put this into a table I can copy'),
                ],
                'lines' => [
                    ['n' => '', 'text' => 'Doanh thu quý 3 · đơn vị: triệu đồng'],
                    ['n' => '', 'text' => '────────────────────────────────'],
                    ['n' => '', 'text' => 'Tháng    Miền Bắc   Miền Nam   Tổng', 'target' => true],
                    ['n' => '', 'text' => '07          1.240      1.680   2.920', 'target' => true],
                    ['n' => '', 'text' => '08            980      1.520   2.500', 'target' => true],
                    ['n' => '', 'text' => '09          1.450      1.910   3.360', 'target' => true],
                    ['n' => '', 'text' => '────────────────────────────────'],
                    ['n' => '', 'text' => 'Cộng        3.670      5.110   8.780'],
                ],
                'context' => <<<'TEXT'
                Ảnh chụp một bảng doanh thu quý 3, đơn vị triệu đồng.

                Vùng người dùng khoanh chọn là bảng này:
                Tháng | Miền Bắc | Miền Nam | Tổng
                07 | 1.240 | 1.680 | 2.920
                08 | 980 | 1.520 | 2.500
                09 | 1.450 | 1.910 | 3.360
                Cộng | 3.670 | 5.110 | 8.780
                TEXT,
                'note' => null,
            ],

            'code' => [
                'label' => __('An error message'),
                'kind' => 'code',
                'title' => 'orders.ts',
                'hint' => __('Drag around the error'),
                'selection' => __('The failing line'),
                'questions' => [
                    __('Why does this fail?'),
                    __('How do I fix it?'),
                    __('Explain this in plain words'),
                ],
                'lines' => [
                    ['n' => '11', 'text' => 'async function createOrder(input) {'],
                    ['n' => '12', 'text' => '  const customer = await findCustomer(input.email);'],
                    ['n' => '13', 'text' => '  const total = input.items.reduce(sumPrice);', 'target' => true],
                    ['n' => '14', 'text' => '  return db.orders.create({ customer, total });'],
                    ['n' => '15', 'text' => '}'],
                    ['n' => '', 'text' => ''],
                    ['n' => '', 'text' => "TypeError: Cannot read properties of undefined (reading 'reduce')", 'target' => true],
                ],
                'context' => <<<'TEXT'
                Ảnh chụp một đoạn mã TypeScript trong tệp orders.ts:

                11  async function createOrder(input) {
                12    const customer = await findCustomer(input.email);
                13    const total = input.items.reduce(sumPrice);
                14    return db.orders.create({ customer, total });
                15  }

                Bên dưới là thông báo lỗi:
                TypeError: Cannot read properties of undefined (reading 'reduce')

                Vùng người dùng khoanh chọn gồm dòng 13 và dòng báo lỗi.
                TEXT,
                'note' => null,
            ],
        ];
    }

    /** Một cảnh theo mã, hoặc null khi mã không có thật. */
    public function find(?string $key): ?array
    {
        return $this->all()[$key] ?? null;
    }

    /** @return array<int, string> */
    public function keys(): array
    {
        return array_keys($this->all());
    }
}
