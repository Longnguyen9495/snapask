# Kế hoạch: trang chủ, tải về, bản macOS, song ngữ

Trạng thái: **đề xuất, chưa làm** · Lập ngày 2026-09-23

## Mục tiêu

1. Có **trang chủ quảng cáo** SnapAsk. Hiện `/` chuyển thẳng tới `/connectors`.
2. Có **trang tải về** bản Windows (`.exe`) và macOS.
3. Ứng dụng **gói gọn trong một file** trên mỗi hệ điều hành.
4. Cả **website lẫn app desktop** có đủ **tiếng Việt và tiếng Anh**.
5. Giao diện tham khảo **Google Stitch** và dựng theo luật của **taste-skill**.

## Ngoài phạm vi

Các mục dưới đây đã ghi trong mục "Chưa có" của README. Kế hoạch này không đụng tới:

- Thanh toán và nâng gói, nên trang chủ chưa có phần bảng giá
- Đăng nhập Google, Facebook, Zalo
- Tự cập nhật bản mới (`electron-updater`)
- Trang xem lại lịch sử hội thoại

---

## 0. Chuẩn bị

### 0.1 Cài taste-skill

Nguồn: <https://github.com/Leonxlnx/taste-skill>

Cài vào `.claude/skills/` của repo để ai clone về cũng dùng chung một bộ luật:

```bash
npx skills add https://github.com/Leonxlnx/taste-skill --skill "design-taste-frontend"
npx skills add https://github.com/Leonxlnx/taste-skill --skill "stitch-design-taste"
```

| Skill | Dùng để làm gì |
|---|---|
| `design-taste-frontend` | Luật chính về chữ, khoảng cách, chuyển động, chống giao diện "AI dựng vội". Có ba nút vặn: VARIANCE, MOTION_INTENSITY, VISUAL_DENSITY |
| `stitch-design-taste` | Luật tương thích Google Stitch, xuất được file `DESIGN.md` |

Mức đề xuất cho trang chủ SnapAsk:

- VARIANCE **trung bình**: bố cục có điểm nhấn nhưng không phá cấu trúc
- MOTION_INTENSITY **thấp–trung bình**: chuyển động phục vụ việc minh hoạ thao tác chụp
- VISUAL_DENSITY **thấp**: nhiều khoảng thở, ít chữ

### 0.2 Google Stitch

Stitch (<https://stitch.withgoogle.com/>) phải đăng nhập tài khoản Google, agent không tự mở được. Quy trình:

1. Dựng các màn hình chính trên Stitch: hero, khối tính năng, trang tải về, bản mobile.
2. Xuất ảnh chụp hoặc `DESIGN.md` rồi đặt vào `docs/design/`.
3. Code theo bố cục đó, **giữ nguyên hệ màu hiện có** (xem 0.3).

Nếu không có bản Stitch thì dựng thẳng theo `stitch-design-taste`.

### 0.3 Giữ nguyên hệ thiết kế

Bảng màu đã khai ở `desktop/src/renderer/app.css` và `server/resources/views/layouts/app.blade.php`. Lý do chọn đã ghi trong README. Stitch và taste-skill chỉ quyết định **bố cục, nhịp chữ, chuyển động**, không đổi nhận diện.

| Token | Giá trị |
|---|---|
| Nền | `--ink-0 #100f0e`, `--ink-1 #1a1816`, `--ink-2 #232019` |
| Chữ | `--text #f3efe8`, `--text-dim #a49c90`, `--text-faint #6f675d` |
| Nhấn | `--accent #e3a04b` (hổ phách), chỉ một màu nhấn |
| Font | Be Vietnam Pro 400/500/600/700, đã nhúng sẵn |

Tách phần `:root` và reset trong `layouts/app.blade.php` ra `public/css/tokens.css`, để trang chủ và trang quản trị dùng chung một nguồn.

---

## Phase 1: Song ngữ cho website

### 1.1 File ngôn ngữ

```
server/lang/
├── vi.json      # chuỗi giao diện, key là tiếng Anh
├── en.json
├── vi/validation.php
└── en/validation.php   # php artisan lang:publish
```

- Key dùng câu tiếng Anh, ví dụ `__('Sign in')`, để chuỗi nào quên dịch vẫn đọc được.
- Chuyển toàn bộ chữ viết cứng trong `resources/views/**` sang `__()`: `auth/login`, `auth/register`, `providers/index`, `connectors/index`, `errors/404`, `layouts/app`. Hiện có **0** chỗ gọi `__()`.
- Chuyển cả thông báo flash và lỗi validate trong `app/Http/Controllers/Web/*` và các FormRequest.

### 1.2 Chọn ngôn ngữ

| Khu vực | Cách nhận ngôn ngữ | Lý do |
|---|---|---|
| Trang công khai (`/`, `/download`) | Tiền tố URL: `/` là tiếng Việt, `/en` là tiếng Anh | Google lập chỉ mục riêng từng bản, chia sẻ link giữ nguyên ngôn ngữ |
| Trang quản trị (`/login`, `/providers`, `/connectors`…) | `users.locale`, rồi đến cookie `locale`, rồi `Accept-Language`, cuối cùng là `vi` | Không phải đổi các route đang có |

Việc cần làm:

- Middleware `SetLocale` gắn vào nhóm `web`, đọc theo thứ tự ở bảng trên.
- Route `POST /locale/{vi|en}`: ghi cookie, cập nhật `users.locale` nếu đã đăng nhập, rồi quay về trang trước.
- Migration thêm cột `users.locale` kiểu `string(5)`, mặc định `vi`.
- `.env`: `APP_LOCALE=vi`, `APP_FALLBACK_LOCALE=en` (hiện đang là `en`/`en`).
- Layout: `<html lang="{{ app()->getLocale() }}">` và nút chuyển VI / EN trên header.
- Trang công khai có `<link rel="alternate" hreflang="vi|en|x-default">` và `og:locale`.

### 1.3 Kiểm thử

- Feature test: `/` ra tiếng Việt, `/en` ra tiếng Anh.
- Đổi locale thì cookie được ghi, và `users.locale` được cập nhật khi đã đăng nhập.
- Test so khoá `vi.json` với `en.json`, thiếu khoá nào thì fail.

---

## Phase 2: Trang chủ quảng cáo

### 2.1 Route

```php
Route::get('/', LandingController::class)->name('home');                 // vi
Route::get('/en', LandingController::class)->name('home.en');           // en
Route::get('/download', DownloadPageController::class)->name('download');
Route::get('/en/download', DownloadPageController::class)->name('download.en');
Route::get('/download/{platform}', DownloadFileController::class)       // windows|mac
    ->whereIn('platform', ['windows', 'mac'])->name('download.file');
```

Bỏ dòng `Route::get('/', fn () => redirect()->route('web.connectors.index'))`. Người đã đăng nhập vẫn xem được trang chủ, chỉ là nút ở header đổi thành "Trang quản lý".

### 2.2 Các phần của trang

| # | Phần | Nội dung |
|---|---|---|
| 1 | Header | Logo, Tính năng, Tải về, FAQ, nút VI/EN, Đăng nhập hoặc Trang quản lý |
| 2 | Hero | Tiêu đề ngắn: *"Chụp một vùng. Hỏi ngay tại chỗ."* Nút tải chính tự nhận hệ điều hành, có link nhỏ tới bản còn lại. Bên cạnh là video hoặc ảnh động quét chọn, hỏi, trả lời |
| 3 | Ba bước | Bấm phím tắt → quét vùng → hỏi AI, mỗi bước kèm một ảnh nhỏ |
| 4 | Tính năng | Ghi chú lên ảnh (khung, mũi tên, vẽ tay, chữ). **Làm mờ** thông tin nhạy cảm. Kính lúp để chọn sát mép chữ. Nối dịch vụ riêng qua MCP. Dùng khoá AI riêng, không bị hạn mức |
| 5 | Riêng tư | Ảnh tự xoá sau 14 ngày. Khoá AI chỉ nằm trên máy chủ. Token dịch vụ được mã hoá |
| 6 | Tải về | Hai thẻ Windows và macOS: phiên bản, dung lượng, yêu cầu hệ thống, nút tải |
| 7 | FAQ | Có mất phí không? Dùng mô hình nào? Ảnh có bị lưu không? Vì sao Windows hoặc macOS cảnh báo lúc mở? |
| 8 | Footer | Bản quyền, link đăng nhập, ngôn ngữ |

### 2.3 Kỹ thuật

- Blade và CSS viết trong trang, không thêm Vite, theo đúng cách các trang hiện có.
- Nhận hệ điều hành bằng `navigator.userAgentData` hoặc `userAgent` ở phía client. Nhận sai thì vẫn còn link tới bản kia.
- Video demo dùng `<video autoplay muted loop playsinline>`, có ảnh poster, dưới 2 MB, đặt trong `public/media/`.
- Chuyển động dùng CSS và `IntersectionObserver`, tắt hết khi người dùng bật `prefers-reduced-motion`.
- Dàn trang từ 360px trở lên, không cuộn ngang.
- Mục tiêu Lighthouse ≥ 90 cho cả Performance, Accessibility và SEO.

### 2.4 Kiểm thử

- `GET /` và `GET /en` trả 200, có đủ `hreflang`.
- Người đã đăng nhập vào `/` vẫn thấy trang chủ, và có link sang trang quản lý.

---

## Phase 3: Build mỗi hệ điều hành một file

### 3.1 Đích build

| Hệ điều hành | Đích | File ra | Ghi chú |
|---|---|---|---|
| Windows x64 | `portable` | `SnapAsk-<ver>-win-x64.exe` | Một file, không cần cài. Mỗi lần mở tự giải nén vào thư mục tạm nên chậm hơn 1–3 giây |
| macOS | `dmg`, arch `universal` | `SnapAsk-<ver>-mac-universal.dmg` | Một file. Khách kéo SnapAsk vào Applications. Chạy được cả Intel lẫn Apple Silicon |

Nếu sau này cần tự cập nhật thì Windows phải quay lại `nsis`, vì `electron-updater` không hỗ trợ bản portable.

### 3.2 Sửa `desktop/package.json`

```jsonc
"scripts": {
  "dist:win": "electron-builder --win --publish never",
  "dist:mac": "electron-builder --mac --publish never",
  "dist": "npm run dist:win"
},
"build": {
  "artifactName": "SnapAsk-${version}-${os}-${arch}.${ext}",
  "win": { "target": [{ "target": "portable", "arch": ["x64"] }], "icon": "build/icon.ico" },
  "mac": {
    "target": [{ "target": "dmg", "arch": ["universal"] }],
    "icon": "build/icon.icns",
    "category": "public.app-category.productivity",
    "hardenedRuntime": true,
    "entitlements": "build/entitlements.mac.plist",
    "extendInfo": {
      "NSScreenCaptureUsageDescription": "SnapAsk cần quyền ghi màn hình để chụp vùng bạn chọn.",
      "LSUIElement": true
    }
  }
}
```

### 3.3 Sửa riêng cho macOS

- **Quyền Screen Recording.** Lần đầu chạy, gọi `systemPreferences.getMediaAccessStatus('screen')`. Nếu chưa được cấp thì hiện hướng dẫn và nút mở System Settings. Không có quyền này thì `desktopCapturer` trả ảnh đen.
- **Phím tắt.** `Ctrl+Alt+W` không tự nhiên trên Mac. Đề xuất `Cmd+Shift+2`, vì `Cmd+Shift+3/4/5` đã thuộc về hệ thống. Hằng số trong `desktop/src/main/config.js` sẽ chọn theo `process.platform`.
- **Khay hệ thống.** Mac cần ảnh template đơn sắc cho thanh menu (`trayTemplate.png` và `@2x`). Ẩn icon Dock bằng `LSUIElement` hoặc `app.dock.hide()`.
- **Icon.** `build/make-icon.js` tạo thêm `.icns` và `.ico`.
- **Cửa sổ overlay.** Kiểm tra trên nhiều màn hình, màn Retina và khi đang có app full screen.

### 3.4 Ký số

| | Chưa ký | Đã ký |
|---|---|---|
| Windows | SmartScreen báo "Windows protected your PC", khách phải bấm More info → Run anyway | Cần chứng chỉ code signing (EV hoặc OV) |
| macOS | Gatekeeper chặn, khách phải chuột phải → Open, hoặc vào System Settings → Privacy | Cần **Apple Developer Program ($99/năm)** để ký và notarize |

Giai đoạn đầu phát hành chưa ký cũng được, nhưng FAQ và trang tải về phải hướng dẫn cách mở.

### 3.5 CI build

Bản macOS **phải build trên máy Mac**. Dùng GitHub Actions `.github/workflows/release.yml`:

- Chạy khi đẩy tag `v*`.
- Hai job: `windows-latest` chạy `npm run dist:win`, `macos-latest` chạy `npm run dist:mac`.
- Tải file build lên GitHub Release kèm `SHA256SUMS.txt`.
- Secrets để ký, khi có: `CSC_LINK`, `CSC_KEY_PASSWORD`, `APPLE_ID`, `APPLE_APP_SPECIFIC_PASSWORD`, `APPLE_TEAM_ID`.

### 3.6 Phục vụ file tải về

Tạo `server/config/snapask.php` → khoá `releases` (hoặc `storage/app/releases/manifest.json`):

```json
{
  "version": "0.2.0",
  "released_at": "2026-10-01",
  "windows": { "file": "SnapAsk-0.2.0-win-x64.exe", "size": 98765432, "sha256": "…", "min_os": "Windows 10" },
  "mac":     { "file": "SnapAsk-0.2.0-mac-universal.dmg", "size": 123456789, "sha256": "…", "min_os": "macOS 12" }
}
```

- `DownloadFileController` đọc manifest. Nếu file nằm trên GitHub Releases hoặc CDN thì redirect sang đó, nếu nằm local thì trả thẳng.
- Đếm lượt tải theo nền tảng bằng bảng `downloads` hoặc chỉ ghi log.
- Trang tải về hiện SHA-256 để khách kiểm tra file.

### 3.7 Kiểm thử

- Trên Windows 10/11 sạch: mở `.exe`, đăng nhập, chụp, hỏi.
- Trên macOS Intel và Apple Silicon: cấp quyền lần đầu, chụp đa màn hình.
- Feature test: `/download/windows` và `/download/mac` redirect hoặc trả đúng file, nền tảng lạ trả 404.

---

## Phase 4: Song ngữ cho app desktop

### 4.1 Cấu trúc

```
desktop/src/i18n/
├── index.js     # t(key, vars), getLocale(), setLocale()
├── vi.json
└── en.json
```

- Main process nạp từ điển rồi gửi cho renderer qua preload: `window.snapask.t`, `window.snapask.locale`.
- HTML dùng `data-i18n="key"`, script sẽ thay chữ khi trang tải và khi đổi ngôn ngữ.
- Phạm vi dịch:
  - `login.html`, `login.js`
  - `chat.html`, `app.js`
  - `overlay.html`, `overlay.js` (tooltip công cụ, gợi ý phím)
  - Menu khay ở `main.js:159`
  - `dialog` lưu file và các hộp lỗi

### 4.2 Chọn ngôn ngữ

1. Người dùng đã chọn trong app, lưu ở `snapask.json` qua `store.js`.
2. `users.locale` lấy từ `GET /api/me` sau khi đăng nhập.
3. `app.getLocale()`: bắt đầu bằng `vi` thì dùng `vi`, còn lại dùng `en`.

- Đổi ngôn ngữ trong cửa sổ Tài khoản. Lựa chọn được ghi local và gửi lên server bằng API mới `PATCH /api/me {locale}`.
- `desktop/src/main/api.js` gửi `Accept-Language` trong mọi request.

### 4.3 Câu trả lời của AI

- Trong `server/app/Services/AskService.php`, thêm vào system prompt: trả lời bằng ngôn ngữ của câu hỏi, nếu câu hỏi trống hoặc không rõ thì dùng `users.locale`.
- Lỗi API, như hết hạn mức hay nhà cung cấp chưa cấu hình, được dịch theo `Accept-Language`.

### 4.4 Kiểm thử

- Script `npm run i18n:check` so khoá `vi.json` với `en.json`.
- Chạy app với `LANG=en` rồi với `LANG=vi`, kiểm tra không còn chữ viết cứng.

---

## Phase 5: Chốt phát hành

- [ ] Chọn tên miền thật, cập nhật `APP_URL` và `DEFAULT_SERVER_URL` trong `desktop/src/main/config.js`
- [ ] HTTPS cho tên miền. App desktop chỉ nên nói chuyện qua `https`
- [ ] Nâng `version` trong `desktop/package.json` lên `0.2.0`
- [ ] Viết lại README: build Mac, phát hành, song ngữ
- [ ] Chụp ảnh và quay video demo cho trang chủ, có cả bản tiếng Việt và tiếng Anh
- [ ] Chạy toàn bộ test server (`php artisan test`)

---

## Thứ tự làm

```
Phase 0 ──> Phase 1 ──> Phase 2 ──┐
    │                             ├──> Phase 5
    └────> Phase 3 ──> Phase 4 ───┘
```

- Phase 3 nên bắt đầu sớm, vì nó phụ thuộc máy Mac, CI và việc ký số, là những thứ chờ lâu.
- Phase 2 cần file build thật từ Phase 3 để hoàn thiện phần tải về. Trong lúc chờ thì dùng manifest giả.

## Câu hỏi còn mở

| # | Câu hỏi | Ảnh hưởng |
|---|---|---|
| 1 | Có được chạy `npx skills add` để cài taste-skill không? | Phase 0 |
| 2 | Có bản thiết kế Stitch không, hay dựng thẳng theo `stitch-design-taste`? | Phase 2 |
| 3 | Có máy Mac hoặc tài khoản Apple Developer không? | Phase 3: ký, notarize, cách test |
| 4 | Tên miền và hosting cho web và file tải về là gì? | Phase 3.6, Phase 5 |
| 5 | Phím tắt trên Mac có dùng `Cmd+Shift+2` không? | Phase 3.3 |
| 6 | Windows chỉ cần x64, hay thêm bản ARM64? | Phase 3.1 |
