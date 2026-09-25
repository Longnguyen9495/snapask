<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\TableExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

class TableExportTest extends TestCase
{
    use RefreshDatabase;

    /** Câu trả lời mẫu: một đoạn văn, một bảng, rồi một đoạn kết. */
    private const ANSWER = <<<'TEXT'
    Số liệu đọc được từ ảnh:

    | Tháng | **Doanh thu** |
    |---|---:|
    | T1 | `1.200` |
    | T2 | 980 |

    Tháng 2 giảm so với tháng 1.
    TEXT;

    #[Test]
    public function tach_duoc_bang_va_go_ky_hieu_markdown_trong_o(): void
    {
        $table = app(TableExtractor::class)->first(self::ANSWER);

        // Ô mang ** hay ` mà đổ thẳng sang Excel thì người nhận tưởng dữ liệu hỏng.
        $this->assertSame(['Tháng', 'Doanh thu'], $table['headers']);
        $this->assertSame([['T1', '1.200'], ['T2', '980']], $table['rows']);
    }

    #[Test]
    public function hang_thieu_o_duoc_dem_cho_khop_so_cot(): void
    {
        $table = app(TableExtractor::class)->first("| a | b | c |\n|---|---|---|\n| 1 |");

        $this->assertSame([['1', '', '']], $table['rows']);
    }

    #[Test]
    public function van_xuoi_co_dau_gach_dung_khong_bi_nham_la_bang(): void
    {
        $this->assertNull(app(TableExtractor::class)->first('chỉ là chữ | có gạch đứng'));
        $this->assertNull(app(TableExtractor::class)->first("| thiếu dòng phân cách |\n| 1 |"));
    }

    #[Test]
    public function doc_duoc_mo_ta_bieu_do_trong_cau_tra_loi(): void
    {
        $content = "văn xuôi\n\n```chart\n".
            '{"title":"Theo tháng","labels":["T1"],"datasets":[{"label":"Triệu","data":["1.200"]}]}'.
            "\n```";

        $chart = app(TableExtractor::class)->chart($content);

        $this->assertSame('Theo tháng', $chart['title']);
        $this->assertSame(['T1'], $chart['labels']);
        // "1.200" là một nghìn hai trăm theo cách viết ở đây, không phải 1,2.
        $this->assertSame([1200.0], $chart['datasets'][0]['data']);

        $this->assertNull(app(TableExtractor::class)->chart('không có khối chart nào'));
        $this->assertNull(app(TableExtractor::class)->chart("```chart\n{hỏng\n```"));
    }

    #[Test]
    public function tai_duoc_bang_cua_minh_duoi_dang_csv(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->for($user)->create(['title' => 'Đọc hoá đơn']);
        $message = Message::factory()->for($conversation)->create(['role' => 'assistant', 'content' => self::ANSWER]);

        $csv = $this->actingAs($user)
            ->get(route('web.conversations.message.table', [$conversation, $message]))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertDownload('doc-hoa-don.csv')
            ->streamedContent();

        // BOM ở đầu file: thiếu nó thì Excel đọc tiếng Việt ra ký tự lạ.
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Tháng', $csv);
        $this->assertStringContainsString('1.200', $csv);
        $this->assertStringContainsString('980', $csv);
    }

    #[Test]
    public function chon_dung_bang_khi_cau_tra_loi_co_nhieu_bang(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->for($user)->create();
        $message = Message::factory()->for($conversation)->create([
            'role' => 'assistant',
            'content' => "| a |\n|---|\n| đầu |\n\nvăn xuôi\n\n| b |\n|---|\n| sau |",
        ]);

        $second = $this->actingAs($user)
            ->get(route('web.conversations.message.table', [$conversation, $message, 'table' => 1]))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('sau', $second);
        $this->assertStringNotContainsString('đầu', $second);
    }

    #[Test]
    public function tai_duoc_ban_excel_gom_moi_bang(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->for($user)->create(['title' => 'Doanh thu quý']);
        $message = Message::factory()->for($conversation)->create([
            'role' => 'assistant',
            'content' => self::ANSWER."\n\n| Khu vực |\n|---|\n| Bắc |",
        ]);

        $path = tempnam(sys_get_temp_dir(), 'test-xlsx-');
        file_put_contents($path, $this->actingAs($user)
            ->get(route('web.conversations.message.table', [$conversation, $message, 'format' => 'xlsx']))
            ->assertOk()
            ->assertDownload('doanh-thu-quy.xlsx')
            ->streamedContent());

        $book = IOFactory::load($path);

        // Mỗi bảng một trang tính, và ô vốn là chuỗi phải ở nguyên dạng chuỗi:
        // để Excel tự đoán kiểu thì "1.200" biến thành 1.2.
        $this->assertSame(2, $book->getSheetCount());
        $this->assertSame('Tháng', $book->getSheet(0)->getCell('A1')->getValue());
        $this->assertSame('1.200', $book->getSheet(0)->getCell('B2')->getValue());

        $book->disconnectWorksheets();
        unlink($path);
    }

    #[Test]
    public function tai_duoc_ban_slide(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->for($user)->create(['title' => 'Doanh thu quý']);
        $message = Message::factory()->for($conversation)->create(['role' => 'assistant', 'content' => self::ANSWER]);

        $path = tempnam(sys_get_temp_dir(), 'test-pptx-');
        file_put_contents($path, $this->actingAs($user)
            ->get(route('web.conversations.message.table', [$conversation, $message, 'format' => 'pptx']))
            ->assertOk()
            ->assertDownload('doanh-thu-quy.pptx')
            ->streamedContent());

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);

        $slides = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            if (str_starts_with((string) $zip->getNameIndex($i), 'ppt/slides/slide')) {
                $slides++;
            }
        }

        // Một slide tiêu đề cộng một slide cho bảng duy nhất.
        $this->assertSame(2, $slides);

        $zip->close();
        unlink($path);
    }

    #[Test]
    public function dinh_dang_la_bi_tu_choi(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->for($user)->create();
        $message = Message::factory()->for($conversation)->create(['role' => 'assistant', 'content' => self::ANSWER]);

        $this->actingAs($user)
            ->get(route('web.conversations.message.table', [$conversation, $message, 'format' => 'exe']))
            ->assertNotFound();
    }

    #[Test]
    public function cau_tra_loi_khong_co_bang_thi_tra_404(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->for($user)->create();
        $message = Message::factory()->for($conversation)->create(['role' => 'assistant', 'content' => 'Không có bảng nào ở đây.']);

        $this->actingAs($user)
            ->get(route('web.conversations.message.table', [$conversation, $message]))
            ->assertNotFound();
    }

    #[Test]
    public function khong_tai_duoc_bang_cua_nguoi_khac(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $conversation = Conversation::factory()->for($owner)->create();
        $message = Message::factory()->for($conversation)->create(['role' => 'assistant', 'content' => self::ANSWER]);

        $this->actingAs($other)
            ->get(route('web.conversations.message.table', [$conversation, $message]))
            ->assertNotFound();
    }

    #[Test]
    public function khong_tai_duoc_tin_nhan_thuoc_hoi_thoai_khac(): void
    {
        $user = User::factory()->create();
        $mine = Conversation::factory()->for($user)->create();
        $otherConversation = Conversation::factory()->for($user)->create();
        $message = Message::factory()->for($otherConversation)->create(['role' => 'assistant', 'content' => self::ANSWER]);

        $this->actingAs($user)
            ->get(route('web.conversations.message.table', [$mine, $message]))
            ->assertNotFound();
    }
}
