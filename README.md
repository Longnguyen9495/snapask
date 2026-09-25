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
(trên macOS là **`Cmd + Shift + 2`**) để quét chọn một vùng màn hình.

Trang chủ nằm ở `http://localhost:8000`, bản tiếng Anh ở `/en`.

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

Phím tắt cố định theo hệ điều hành. Đổi được bằng biến `SNAPASK_HOTKEY`, dành
cho lúc tổ hợp này đã bị phần mềm khác chiếm.

| Hệ điều hành | Phím tắt | Vì sao |
|---|---|---|
| Windows, Linux | **`Ctrl + Alt + W`** | |
| macOS | **`Cmd + Shift + 2`** | `Ctrl+Alt+W` nằm sai tay trên bàn phím Mac, còn `Cmd+Shift+3/4/5` đã thuộc về công cụ chụp của hệ điều hành |

Lần đầu chụp trên macOS, hệ điều hành hỏi quyền **Screen Recording**. Chưa cấp
thì ảnh chụp ra toàn màu đen, nên app chặn trước và mở thẳng tới đúng trang cài
đặt. Cấp xong phải mở lại app — macOS chỉ đọc lại danh sách quyền lúc khởi động.

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

Khách xem lại hội thoại cũ trên trang `/conversations`, xoá được từng cái một.
Ảnh đi qua controller chứ không nằm trong `public/`: mỗi lần xem đều kiểm tra
chủ sở hữu, nên đoán id không đọc được ảnh của người khác. Xoá một hội thoại là
xoá luôn ảnh của nó, không đợi tới lượt `snapask:prune`.

## Song ngữ

Tiếng Việt và tiếng Anh, dùng chung một cách khai: khoá là câu tiếng Anh, nên
chuỗi nào quên dịch vẫn đọc được thay vì hiện ra một mã khoá trần trụi.

| | File | Chọn ngôn ngữ theo |
|---|---|---|
| Trang công khai (`/`, `/download`) | `server/lang/{vi,en}.json` | Tiền tố URL — `/` là tiếng Việt, `/en` là tiếng Anh |
| Trang quản trị | như trên | `users.locale` → cookie `locale` → `Accept-Language` |
| App desktop | `desktop/src/i18n/{vi,en}.json` | Lựa chọn trong app → `users.locale` → ngôn ngữ hệ điều hành |

Trang công khai **không** nghe cookie hay `Accept-Language`: `/` luôn là tiếng
Việt và `/en` luôn là tiếng Anh, bằng không hai địa chỉ khai là bản dịch của
nhau lại trả về cùng một thứ tiếng và thẻ `hreflang` thành lời nói dối.

Đổi ngôn ngữ ở một nơi thì nơi kia nhận theo, qua cột `users.locale`. App desktop
gửi `Accept-Language` ở mọi lời gọi, nên thông báo lỗi từ máy chủ cũng về đúng
thứ tiếng đang xem.

Thiếu một khoá thì test bắt được:

```bash
cd server && php artisan test --filter=LocaleTest   # so khoá + không còn chữ viết cứng
cd desktop && npm run i18n:check                    # so khoá + so chỗ giữ chỗ :ten
```

## Đóng gói

Mỗi hệ điều hành một file:

```bash
cd desktop
npm run icons      # sinh icon.png, tray, icon.ico, icon.icns
npm run dist:win   # SnapAsk-<ver>-win-x64.exe   — chạy thẳng, không cần cài
npm run dist:mac   # SnapAsk-<ver>-mac-universal.dmg — chỉ chạy được trên máy Mac
```

Bản macOS **phải dựng trên máy Mac**: `electron-builder` gọi tới công cụ của
Xcode để ghép bản universal và tạo ảnh đĩa. `.github/workflows/release.yml` làm
việc đó khi đẩy tag `v*`, dựng song song trên `windows-latest` và `macos-latest`
rồi đính file kèm `SHA256SUMS.txt` vào một GitHub Release nháp.

Bộ cài chưa ký số nên Windows hiện SmartScreen còn macOS hiện Gatekeeper; trang
`/download` đã hướng dẫn cách mở. Muốn ký thì khai các secret `CSC_LINK`,
`CSC_KEY_PASSWORD`, và cho macOS thêm `APPLE_ID`, `APPLE_APP_SPECIFIC_PASSWORD`,
`APPLE_TEAM_ID` — để trống thì workflow bỏ qua bước ký chứ không gãy.

Bản Windows dùng đích `portable`. Sau này cần tự cập nhật thì phải quay lại
`nsis`, vì `electron-updater` không hỗ trợ bản portable.

> Máy nào đã đặt sẵn `ELECTRON_RUN_AS_NODE=1` — terminal tích hợp của VS Code là
> một — thì bộ cài mở lên rồi thoát ngay, không báo lỗi gì. Biến này truyền cả
> sang tiến trình mà bản portable giải nén ra. Xoá hẳn biến trước khi chạy:
> `Remove-Item Env:\ELECTRON_RUN_AS_NODE` trên PowerShell, hoặc
> `env -u ELECTRON_RUN_AS_NODE ./SnapAsk-<ver>-win-x64.exe` trên bash.

### Phát hành lên trang tải về

Khai trong `server/.env` để trang `/download` và `/download/{platform}` biết lấy
file ở đâu. Điền `*_URL` thì người tải được chuyển thẳng sang đó; để trống thì
file được lấy từ `server/storage/app/releases/`.

```dotenv
SNAPASK_RELEASE_VERSION=0.2.0
SNAPASK_RELEASE_WIN_FILE=SnapAsk-0.2.0-win-x64.exe
SNAPASK_RELEASE_WIN_URL=https://github.com/…/SnapAsk-0.2.0-win-x64.exe
SNAPASK_RELEASE_WIN_SIZE=98765432
SNAPASK_RELEASE_WIN_SHA256=…
```

Chưa khai bản nào thì trang tải về nói thẳng là chưa phát hành, còn
`/download/{platform}` trả 404 — không có trang trắng nào.

## Chưa có

Những phần cần làm trước khi bán được cho khách ngoài:

- Đăng nhập bằng Google, Facebook, Zalo — giao diện đã dựng, phần nối thật chưa làm
- Thanh toán và nâng gói — hiện mọi tài khoản mới đều vào gói mặc định trong `config/snapask.php`
- Tự cập nhật bản mới (`electron-updater`)
- Ký số bộ cài: Windows cần chứng chỉ code signing, macOS cần Apple Developer Program
- Màn hình cài đặt trong app cho khoá provider riêng của khách
- OAuth cho connector MCP — hiện mới nhận token dán tay
- Video và ảnh demo thật cho trang chủ — hiện đang là một khối minh hoạ dựng bằng CSS
