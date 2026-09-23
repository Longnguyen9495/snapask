# SnapAsk

Chụp một vùng bất kỳ trên màn hình rồi hỏi AI ngay tại chỗ.

```
snapask/
├── desktop/   Ứng dụng Electron: phím tắt, lớp phủ quét chọn, ô chat
└── server/    Laravel: xác thực, hạn mức, gọi mô hình, lưu hội thoại
```

**Khoá nhà cung cấp mô hình chỉ nằm ở `server/`.** Gói Electron giải nén được
trong vài giây, nên mọi khoá nhúng vào ứng dụng desktop coi như đã công khai.
Desktop chỉ giữ token phiên của người dùng, mã hoá bằng kho bí mật của hệ điều
hành khi máy hỗ trợ.

## Chạy thử

```bash
# 1. Máy chủ
cd server
cp .env.example .env && php artisan key:generate
php artisan migrate
php artisan snapask:user ban@vidu.com --limit=200   # hoặc tự đăng ký trong app
php artisan serve

# 2. Ứng dụng desktop
cd ../desktop
npm install
npm start
```

Đăng nhập hoặc tạo tài khoản ngay trong cửa sổ vừa hiện, rồi bấm **`Ctrl + Alt + W`**
để quét chọn một vùng màn hình.

App không hỏi địa chỉ máy chủ — nó nằm trong `desktop/src/main/config.js`, sửa
trước khi build. Lúc phát triển thì đặt biến `SNAPASK_SERVER_URL` để trỏ đi nơi
khác mà không phải build lại.

> Chạy `npm start` từ cửa sổ dòng lệnh tích hợp của VS Code thì Electron khởi
> động như Node thường rồi chết ngay, vì biến `ELECTRON_RUN_AS_NODE` đã được đặt
> sẵn ở đó. Dùng terminal riêng, hoặc `env -u ELECTRON_RUN_AS_NODE npm start`.

## Nhà cung cấp mô hình

Mỗi khách tự cắm khoá của mình trên trang `/providers`: tên, định dạng API,
base URL, khoá, và danh sách mã mô hình. Chọn một mô hình là dùng ngay.

Hai định dạng được hỗ trợ:

| Định dạng | Endpoint | Ai dùng |
|---|---|---|
| `openai-chat` | `{base}/chat/completions` | OpenAI, Qwen, Groq, OpenRouter, DeepSeek, và hầu hết dịch vụ tự dựng |
| `anthropic-messages` | `{base}/v1/messages` | Anthropic |

Khách đã cắm khoá riêng thì **không bị hạn mức gói chặn** — hoá đơn token về
thẳng nhà cung cấp họ chọn.

### Nhà cung cấp mặc định

Khách chưa cắm gì thì dùng khoá của hệ thống, kèm hạn mức theo gói. Khai trong
`server/.env`; để trống cũng chạy được, khi đó khách buộc phải tự cắm khoá.

```dotenv
SNAPASK_BASE_URL=https://dashscope-intl.aliyuncs.com/compatible-mode/v1
SNAPASK_API_KEY=sk-...
SNAPASK_MODEL=qwen-vl-plus
SNAPASK_API_FORMAT=openai-chat
```

Mô hình nào cũng được, miễn đọc được ảnh.

## Hệ thiết kế

Một bảng màu duy nhất dùng chung cho app desktop và trang web, khai ở
`desktop/src/renderer/app.css` và trong layout Blade.

| | Giá trị | Lý do |
|---|---|---|
| Nền | `#100f0e` → `#232019` | Đen ngả ấm, không phải xám xanh |
| Chữ | `#f3efe8` / `#a49c90` / `#6f675d` | Ba bậc, cùng một họ màu ấm |
| Màu nhấn | `#e3a04b` | Hổ phách, màu của bút dạ quang — đúng việc phần mềm này làm |
| Chữ | Be Vietnam Pro 400/500/600/700 | Bộ chữ thiết kế riêng cho tiếng Việt |

Tông xanh tím quen thuộc bị bỏ vì nó là dấu vân tay của mọi sản phẩm AI dựng
vội. Chỉ một màu nhấn duy nhất; bảng màu bút vẽ trong lớp phủ là màu chức năng,
không tính là màu thương hiệu.

Font nhúng thẳng vào gói (135 KB, có bộ ký tự tiếng Việt riêng) để máy không có
mạng vẫn hiện đúng dấu.

## Thao tác khi chụp

Phím tắt cố định **`Ctrl + Alt + W`** trên mọi máy. Đổi được bằng biến
`SNAPASK_HOTKEY`, dành cho lúc tổ hợp này đã bị phần mềm khác chiếm.

| Thao tác | Kết quả |
|---|---|
| Rê chuột khi chưa chọn | Kính lúp phóng to kèm toạ độ, để bắt đúng mép một dòng chữ nhỏ |
| Kéo | Chọn vùng |
| Kéo 8 tay nắm | Đổi kích thước vùng đã chọn |
| Kéo bên trong vùng | Di chuyển cả vùng |
| Bấm ra ngoài vùng | Chọn lại từ đầu |
| Chuột phải | Bỏ ghi chú cuối → bỏ vùng chọn → thoát |
| `Esc` | Thoát ngay |
| `Enter` | Gửi cho AI |
| `Ctrl+Z` | Hoàn tác ghi chú |
| `Ctrl+C` / `Ctrl+S` | Chép vào clipboard / lưu thành file |

Thanh công cụ có sáu công cụ: di chuyển, khung chữ nhật, mũi tên, vẽ tay, chữ,
và **làm mờ** để che thông tin nhạy cảm trước khi gửi. Mọi ghi chú được nung
thẳng vào ảnh ở độ phân giải gốc, nên màn hình HiDPI không bị mất nét.

## Kết nối dịch vụ của khách (MCP)

Ngoài ảnh chụp, AI gọi được công cụ của những dịch vụ khách khai báo — giống
cách Claude Desktop nối tới các connector.

Mọi việc khai báo nằm trên **trang web** của máy chủ, tại `/connectors`: tên
hiển thị, mã rút gọn, địa chỉ MCP và token nếu dịch vụ yêu cầu. App desktop chỉ
chụp và hỏi; cửa sổ Tài khoản của app có nút mở thẳng trang này.

SnapAsk chỉ nói chuyện với **máy chủ MCP qua HTTPS**, không chạy tiến trình MCP
cục bộ như Claude Desktop: mô hình được gọi từ máy chủ SnapAsk, nên một tiến
trình nằm trên máy khách thì máy chủ không với tới được.

Khi đã nối, một lượt hỏi chạy như sau:

```
ảnh + câu hỏi ──> mô hình ──> "gọi kho__ton_kho{ma:SP01}"
                     │
                     └──> máy chủ MCP của khách ──> "SP01: còn 12"
                              │
                     mô hình <─┘ ──> "Còn 12 cái."
```

App hiện dòng *Đang hỏi Kho hàng…* trong lúc chờ, tối đa 4 vòng gọi công cụ mỗi
lượt trả lời. Dịch vụ của khách chết thì AI vẫn trả lời được bằng ảnh chứ không
bỏ dở cả lượt.

Ba điểm về an toàn, đã có kiểm thử phủ:

- Token dịch vụ mã hoá trong cơ sở dữ liệu và không bao giờ trả ngược ra API
- Chỉ nhận địa chỉ `https`, vì token đi kèm mọi lời gọi
- Tên công cụ mô hình bịa ra bị chặn tại chỗ, không đẩy sang hệ thống của khách

## Hai chỗ tốn tiền nhất

| Biến | Mặc định | Ảnh hưởng |
|---|---|---|
| `SNAPASK_RESEND_IMAGE` | `true` | Gắn lại ảnh ở mọi lượt trong cùng hội thoại. Tắt thì rẻ hơn nhiều nhưng câu hỏi nối tiếp kiểu "dòng thứ ba ghi gì" sẽ được trả lời bằng suy đoán. |
| `maxImageWidth` (desktop) | `1280` | Ảnh full-HD tốn cỡ 1.100 token mỗi lượt; bản 1280px tốn khoảng một nửa mà vẫn đọc rõ chữ. |

Hạn mức mỗi tài khoản nằm ở cột `users.monthly_ask_limit` và được kiểm tra
**trước** khi gọi mô hình. Khách điền `users.provider_api_key` (cột đã mã hoá)
thì dùng khoá của chính họ và không bị hạn mức chặn.

## Riêng tư

Ảnh chụp màn hình thường chứa dữ liệu nhạy cảm. Ảnh được xoá sau
`SNAPASK_IMAGE_RETENTION_DAYS` ngày (mặc định 14) bởi `php artisan snapask:prune`,
đã đặt lịch chạy hằng ngày lúc 03:10. Lịch sử chữ vẫn được giữ.

## Chưa có

Những phần cần làm trước khi bán được cho khách ngoài:

- Đăng nhập bằng Google, Facebook, Zalo — giao diện đã dựng, phần nối thật chưa làm
- Thanh toán và nâng gói — hiện mọi tài khoản mới đều vào gói mặc định trong `config/snapask.php`
- Trang web xem lại lịch sử hội thoại (API đã có: `GET /api/conversations`)
- Tự cập nhật bản mới (`electron-updater`) và ký số bộ cài Windows
- Màn hình cài đặt trong app cho khoá provider riêng của khách
- OAuth cho connector MCP — hiện mới nhận token dán tay
