# Kế hoạch redesign SnapAsk Desktop Workspace

## Trạng thái tài liệu

- Mục đích: đặc tả triển khai trước khi viết code.
- Phạm vi: Electron desktop workspace và các API Laravel cần bổ sung.
- Trạng thái: chờ duyệt.
- Phiên bản mục tiêu đề xuất: `0.3.0`.
- Không có mã nguồn ứng dụng nào được thay đổi trong giai đoạn lập kế hoạch.

## Design Read

Đây là redesign một workspace desktop cho người dùng SnapAsk hằng ngày, theo ngôn ngữ devtool cao cấp, tối và tập trung. Thiết kế giữ bản sắc charcoal/amber, tham chiếu cấu trúc ZCode nhưng không sao chép thương hiệu hoặc giao diện sáng.

Các thông số định hướng:

- `DESIGN_VARIANCE: 5`
- `MOTION_INTENSITY: 3`
- `VISUAL_DENSITY: 6`

Đây là UI sản phẩm nhiều trạng thái, do đó ưu tiên độ ổn định, khả năng đọc, thao tác bàn phím và phản hồi trạng thái hơn hiệu ứng trang trí.

---

## 1. Mục tiêu sản phẩm

Biến SnapAsk từ ứng dụng chủ yếu hoạt động qua khay hệ thống và cửa sổ chat nổi thành một desktop workspace hoàn chỉnh:

1. Khi mở SnapAsk bình thường, người dùng thấy cửa sổ chính có lịch sử và vùng hỏi AI.
2. Người dùng có thể bắt đầu cuộc trò chuyện bằng văn bản, không bắt buộc chụp màn hình.
3. Người dùng có thể chụp từ workspace hoặc dùng phím tắt toàn cục như hiện tại.
4. Hội thoại mới và cũ được quản lý trực tiếp trong sidebar.
5. Tài khoản, hạn mức và cài đặt có vị trí rõ ràng trong ứng dụng.
6. Luồng chụp nhanh hiện tại tiếp tục tồn tại và không chậm hơn.
7. Toàn bộ request mạng và token tiếp tục nằm trong Electron main process.
8. Workspace và compact chat chia sẻ cùng nguồn dữ liệu hội thoại.

### 1.1 Ngoài phạm vi bản đầu

- Đồng bộ realtime giữa nhiều thiết bị bằng WebSocket.
- Pin, thư mục, tag, archive và bulk action.
- Chia sẻ hội thoại công khai.
- Chỉnh sửa hoặc regenerate từng message.
- Nhiều ảnh trong cùng một câu hỏi.
- Rich-text editor phức tạp.
- Thay đổi provider/model theo từng hội thoại.
- Thiết kế lại trang web quản trị provider và connector.
- Soft delete/trash nếu chưa có trải nghiệm restore hoàn chỉnh.

Các mục trên được đưa vào backlog sau khi workspace nền tảng ổn định.

---

## 2. Nguyên tắc thiết kế

### 2.1 Ngôn ngữ thị giác

- Dùng nền charcoal, không dùng đen tuyệt đối.
- Amber là accent duy nhất cho CTA chính, focus, selection và trạng thái đang xử lý.
- Không dùng AI-purple, glow mạnh hoặc glassmorphism đại trà.
- Sidebar khác nhẹ về sắc độ so với vùng hội thoại để tạo phân lớp mà không cần shadow nặng.
- Chỉ dùng card khi có lớp nổi thật sự như dialog hoặc attachment preview.
- Quy tắc bán kính:
  - Container chính: 12px.
  - Input và button: 8px.
  - Chip trạng thái hoặc phím tắt: pill.
- Tiếp tục dùng font self-host trong `desktop/src/assets/fonts`.
- Dùng một họ icon duy nhất. Không tự vẽ SVG tùy tiện.

### 2.2 Chuyển động

Chuyển động chỉ được dùng để giải thích trạng thái:

- Sidebar mở hoặc thu gọn: transform và opacity, 160-200ms.
- Empty state chuyển sang conversation: fade nhẹ.
- Streaming cursor: animation tối giản.
- Menu và dialog: opacity kết hợp scale rất nhẹ.
- Tôn trọng `prefers-reduced-motion`.
- Không dùng animation vô hạn ngoài loading hoặc streaming thật.

### 2.3 Kích thước mục tiêu

- Kích thước mặc định: khoảng 1180 x 760.
- Kích thước tối thiểu: 860 x 600.
- Sidebar: 276px mặc định, 228px tối thiểu, có thể thu gọn thành rail 64px.
- Nội dung hội thoại: tối đa khoảng 820px.
- Composer bám đáy vùng main, không bám toàn cửa sổ.

---

## 3. Kiến trúc thông tin

```text
┌────────────────────────────────────────────────────────────────────┐
│ Sidebar 276px        │ Main workspace                              │
│                      │                                             │
│ SnapAsk              │ Conversation header / Empty-state header    │
│ [Câu hỏi mới]        │                                             │
│ [Chụp màn hình]      │ Message thread hoặc welcome state           │
│ [Tìm kiếm]           │                                             │
│                      │                                             │
│ Hôm nay              │                                             │
│ - Hội thoại A        │                                             │
│ - Hội thoại B        │                                             │
│ Hôm qua              │                                             │
│ - Hội thoại C        │                                             │
│                      │ Composer + attach/capture + send             │
│ Tài khoản / Cài đặt  │                                             │
└────────────────────────────────────────────────────────────────────┘
```

### 3.1 Sidebar

Theo thứ tự từ trên xuống:

1. Brand bar:
   - Logo SnapAsk.
   - Nút thu gọn sidebar.
2. Primary actions:
   - `Câu hỏi mới`.
   - `Chụp màn hình`.
3. Search:
   - Ô tìm kiếm có phím tắt hiển thị.
4. Conversation history:
   - Nhóm theo `Hôm nay`, `Hôm qua`, `7 ngày qua`, `Cũ hơn`.
   - Mỗi item gồm title và preview một dòng hoặc thời gian tương đối.
   - Item đang chọn dùng surface sáng hơn và viền amber nhỏ.
5. Footer:
   - Avatar hoặc initials.
   - Tên và email rút gọn.
   - Quota ngắn.
   - Menu tài khoản và cài đặt.

### 3.2 Empty state

- Lời chào ngắn theo tên người dùng.
- Một câu mô tả chức năng cụ thể.
- Composer là điểm nhấn chính.
- Hai hành động hỗ trợ: chụp màn hình và mở câu hỏi gần nhất.
- Hiển thị phím tắt chụp nhanh phù hợp hệ điều hành.
- Nếu quota thấp, hiển thị cảnh báo nhỏ bên dưới composer.

### 3.3 Active conversation

Header sticky trong vùng main:

- Title.
- Model/provider dưới dạng metadata phụ nếu cần.
- Menu đổi tên và xóa. Đổi tên chỉ xuất hiện khi API hỗ trợ.

Thread:

- User message có surface nhẹ.
- Assistant message nằm trên nền chính để tối ưu đọc.
- Markdown được sanitize.
- Code block có copy action.
- Tool activity hiển thị bằng disclosure thu gọn.
- Ảnh chụp hiển thị như attachment có thumbnail, kích thước và trạng thái hết hạn.

Composer:

- Textarea tự tăng chiều cao có giới hạn.
- Nút chụp màn hình.
- Preview ảnh đính kèm và nút bỏ ảnh.
- Nút gửi chuyển thành nút dừng khi streaming.
- `Enter` để gửi, `Shift + Enter` xuống dòng.

### 3.4 Account

Dùng popover từ footer thay vì màn hình dashboard riêng:

- Tên và email.
- Gói hiện tại.
- Số lượt còn lại và tổng hạn mức.
- Trạng thái xác minh email nếu API trả về.
- Mở trang quản lý nhà cung cấp hiện có.
- Đăng xuất.

### 3.5 Settings

Dùng màn hình con trong workspace hoặc dialog lớn:

- General:
  - Ngôn ngữ Việt/Anh.
  - Mở SnapAsk khi đăng nhập hệ điều hành.
  - Đóng cửa sổ sẽ ẩn xuống tray hoặc thoát.
- Capture:
  - Phím tắt hiện tại.
  - Độ rộng ảnh tối đa.
  - Sau chụp mở compact chat hoặc workspace.
- Updates:
  - Phiên bản hiện tại.
  - Trạng thái cập nhật.
  - Kiểm tra cập nhật.
- Advanced:
  - Server URL có cảnh báo rõ ràng.
  - Reset settings.

---

## 4. Trạng thái giao diện

### 4.1 Khởi động

1. Không có token:
   - Mở auth window.
   - Không dựng workspace nửa đăng nhập.
2. Có token và profile hợp lệ:
   - Mở workspace.
   - Tải profile/quota và trang history đầu tiên song song.
3. Token hết hạn:
   - Xóa token sau khi xác định lỗi xác thực.
   - Hủy stream, khóa workspace và mở auth với thông báo hết phiên.
4. Server không truy cập được:
   - Vẫn mở shell workspace.
   - Hiển thị offline và nút thử lại.
   - Không xóa token.

### 4.2 History

- Loading lần đầu: skeleton đúng hình dạng item.
- Empty: thông báo ngắn và CTA `Câu hỏi mới`.
- Loaded: nhóm theo thời gian tại renderer.
- Tải trang tiếp: skeleton cuối danh sách.
- Pagination lỗi: lỗi inline kèm thử lại.
- Refresh nền: giữ danh sách cũ, không nhấp nháy.
- Conversation mới: chèn lên đầu khi nhận `conversation_id`.
- Conversation vừa tiếp tục: chuyển lên đầu theo last activity.
- Xóa: optimistic removal, rollback nếu API lỗi.

### 4.3 Conversation

- Loading detail: message skeleton.
- Không tìm thấy: thông báo đã xóa hoặc không tồn tại.
- Loaded: message theo thứ tự tăng dần.
- Streaming: append delta vào assistant message hiện tại.
- Hủy stream: giữ phần đã nhận và đánh dấu đã dừng.
- Lỗi trước delta đầu: hiển thị retry.
- Lỗi sau khi có delta: giữ nội dung và đánh dấu chưa hoàn tất.
- Xóa conversation đang mở: về empty state hoặc chọn item kế tiếp.

### 4.4 Composer

- Idle: cho nhập và gửi.
- Không có question và ảnh: disable gửi.
- Có ảnh nhưng không có question: vẫn yêu cầu câu hỏi ngắn.
- Submitting: chống gửi lặp nhưng cho phép stop.
- Offline: giữ draft trong memory, không gửi.
- Hết quota: disable gửi và dẫn tới thông tin gói.
- Ảnh lỗi hoặc quá lớn: lỗi cạnh attachment.

---

## 5. Luồng người dùng

### 5.1 Câu hỏi văn bản mới

1. Chọn `Câu hỏi mới`.
2. Chuyển về empty draft, chưa tạo record server.
3. Nhập câu hỏi và gửi.
4. Main process gọi `/api/ask` không có `conversation_id` và ảnh.
5. Renderer tạo user message và assistant placeholder theo optimistic UI.
6. SSE cập nhật assistant message.
7. Khi nhận `conversation_id`, app đánh dấu conversation đã được tạo.
8. Sidebar chèn conversation mới; title tạm lấy từ câu hỏi đầu tiên rồi đồng bộ server.

Không tạo conversation rỗng khi chỉ bấm `Câu hỏi mới`.

### 5.2 Tiếp tục hội thoại

1. Chọn item sidebar.
2. Tải detail.
3. Gửi follow-up kèm `conversation_id`.
4. Stream message mới.
5. Refresh metadata và đưa item lên đầu.

### 5.3 Chụp từ workspace

1. Chọn `Chụp màn hình`.
2. Ẩn workspace để nó không xuất hiện trong ảnh.
3. Chạy overlay hiện tại.
4. Khi xác nhận:
   - Nếu origin là workspace, khôi phục workspace và gắn ảnh vào composer.
   - Nếu setting là compact, mở quick chat.
5. Người dùng bổ sung câu hỏi và gửi.

Mặc định đề xuất: nút capture trong workspace quay về workspace; global shortcut mở compact chat.

### 5.4 Global shortcut

- Hoạt động khi workspace đang mở, ẩn hoặc chưa tạo.
- Mở overlay trực tiếp.
- Sau khi chọn vùng, mở compact chat gần vùng chụp.
- Khi compact chat nhận `conversation_id`, main process thông báo workspace để đồng bộ sidebar.
- Compact chat có action `Mở trong workspace`.

### 5.5 Xóa hội thoại

1. Chọn xóa từ context menu hoặc header.
2. Hiện confirm dialog có title.
3. Gọi DELETE sau xác nhận.
4. Xóa khỏi sidebar.
5. Nếu đang mở, chuyển empty state.
6. Không có undo server trong bản đầu nên nội dung xác nhận phải rõ.

### 5.6 Tìm kiếm

- Search phía server, không chỉ lọc 20 item đã tải.
- Debounce 250-350ms.
- Tìm title và nội dung message nếu hiệu năng cho phép.
- Query rỗng quay về history chuẩn.
- Có empty state riêng.
- Escape lần đầu xóa query, lần sau trả focus về thread.

---

## 6. Kiến trúc Electron

### 6.1 Các cửa sổ

1. `workspaceWindow`
   - Cửa sổ chính, resizable, có minimum size.
   - Hiện khi app mở bình thường.
   - Giữ instance và hide/show thay vì tạo lại liên tục.
2. `chatWindow`
   - Compact chat hiện có cho capture nhanh.
3. `authWindow`
   - Giữ luồng login/register hiện tại.
4. Overlay windows
   - Dùng chọn vùng trên từng display.

Không hợp nhất compact chat với workspace vì hai bề mặt có mục tiêu khác nhau.

### 6.2 Vòng đời ứng dụng

- Normal launch: đã xác thực thì mở workspace, chưa xác thực thì mở auth.
- Second instance: focus workspace hoặc auth, không tự capture.
- Tray double click: mở/focus workspace.
- Tray menu:
  - Mở SnapAsk.
  - Chụp màn hình.
  - Câu hỏi mới.
  - Cài đặt.
  - Kiểm tra cập nhật.
  - Thoát.
- Close workspace: mặc định hide xuống tray.
- Global shortcut: giữ capture flow hiện tại.
- macOS activation: mở/focus workspace nếu không có cửa sổ hiển thị.

### 6.3 Cấu trúc file renderer

Thêm riêng:

- `desktop/src/renderer/workspace.html`
- `desktop/src/renderer/workspace.css`
- `desktop/src/renderer/workspace.js`
- `desktop/src/preload/workspace.js`

Không nhồi workspace vào compact chat hiện tại. Có thể tách helper dùng chung sau khi hành vi ổn định:

- Markdown rendering và sanitization.
- Message DOM builder.
- Stream reducer.
- Locale helper.
- Date grouping.

---

## 7. Hợp đồng IPC

Tất cả IPC trả cấu trúc nhất quán: `{ ok, data, message, code }`.

### 7.1 Workspace

- `workspace:ready`: trả profile, quota, public settings, locale, shortcut và version.
- `workspace:new-chat`.
- `workspace:open-settings`.
- `workspace:hide`.
- `workspace:quit`.

### 7.2 Conversation

- `conversations:list`: page, search và pagination metadata.
- `conversations:show`: conversation ID.
- `conversations:delete`: conversation ID.
- `conversations:rename`: chỉ thêm khi server hỗ trợ.
- `conversations:image`: trả dữ liệu ảnh an toàn, không trả token hoặc path nội bộ.

### 7.3 Ask streaming

- `ask:start`: request ID, optional conversation ID, question và optional image.
- Event stream luôn kèm request ID.
- `ask:cancel`: request ID.
- `ask:complete`: conversation ID và trạng thái cuối.

Không dùng một `cancelStream` toàn cục. Dùng `Map<requestId, AbortController>` hoặc key kết hợp `webContents.id` và request ID. Khi window bị destroy, hủy mọi request thuộc window đó.

### 7.4 Capture

- `capture:start`: origin `workspace`, `shortcut` hoặc `tray`.
- `capture:completed`: ảnh, dimensions và origin.
- `capture:cancelled`.
- `capture:error`.

### 7.5 Account/settings/update

- `account:refresh`.
- `account:logout`.
- `settings:get`.
- `settings:update`.
- `settings:reset`.
- `updates:check`.
- `updates:install`.
- `external:open-provider-management`.

Preload chỉ expose từng method cụ thể qua `contextBridge`, không expose raw `ipcRenderer`.

---

## 8. API server

### 8.1 Giữ endpoint ask

`POST /api/ask` hiện đã hỗ trợ:

- Conversation mới không có ID.
- Follow-up có ID.
- Có hoặc không có ảnh.
- Streaming và trả `conversation_id` khi hoàn tất.

Không cần endpoint tạo conversation rỗng trong bản đầu.

### 8.2 Mở rộng conversation list

Payload đề xuất:

- `id`
- `title`
- `model`
- `created_at`
- `updated_at`
- `last_activity_at`
- `messages_count`
- `last_message_preview`
- `has_image`

Sắp xếp theo hoạt động gần nhất, không theo ID. Bảo đảm conversation được touch khi thêm message thành công. Thêm index `(user_id, updated_at)` nếu dùng `updated_at` làm last activity.

### 8.3 Mở rộng conversation detail

Trả:

- Conversation metadata.
- Image object: available, width, height, expires_at.
- Message: id, role, content, tools_used, created_at.

Không trả filesystem path nội bộ.

### 8.4 Ảnh được bảo vệ

Thêm:

- `GET /api/conversations/{conversation}/image`

Yêu cầu:

- Sanctum authentication.
- Ownership check.
- Kiểm tra file tồn tại và chưa hết hạn.
- MIME, cache policy và inline disposition chính xác.
- Không đưa bearer token vào URL/query.
- Main process fetch ảnh rồi chuyển dữ liệu an toàn cho renderer.

### 8.5 Search

Mở rộng endpoint list:

- `GET /api/conversations?search=...&page=...`

Quy tắc:

- Tìm title trước.
- Tìm nội dung message nếu hiệu năng cho phép.
- Giới hạn độ dài query.
- Escape wildcard.
- Pagination bắt buộc.
- Có thể bắt đầu bằng `LIKE`, chuyển full-text khi dữ liệu thực tế cần.

### 8.6 Rename

Đề xuất:

- `PATCH /api/conversations/{conversation}`.

Validation:

- Title bắt buộc, trim.
- Tối đa khoảng 120 ký tự.
- Ownership check.

Nếu chưa triển khai trong `0.3.0`, ẩn action hoàn toàn.

### 8.7 Delete

Giữ endpoint hiện tại và bổ sung test:

- Không xóa conversation người khác.
- Message cascade đúng.
- File ảnh được xóa hoặc cleanup rõ ràng.
- Response thống nhất.

### 8.8 API Resources

Khi payload mở rộng, dùng Laravel API Resources để list/detail thống nhất field, date format và null handling.

---

## 9. Dữ liệu và migration

Bắt buộc hoặc rất nên có:

- Bảo đảm `conversations.updated_at` thay đổi khi có message mới.
- Index `(user_id, updated_at)`.
- Index search title nếu dữ liệu thực tế cần.

Chưa cần:

- `pinned_at`
- `archived_at`
- `folder_id`
- `sort_order`
- `deleted_at`

Không thêm field dự phòng nếu chưa có trải nghiệm sử dụng tương ứng.

---

## 10. Bản đồ thay đổi theo file

### 10.1 Electron main

#### `desktop/src/main/main.js`

- Quản lý `workspaceWindow`.
- Tách hàm create/focus workspace.
- Điều chỉnh launch, second instance, tray, close và activation.
- Đăng ký IPC workspace/conversation/settings.
- Registry stream controller theo request/window.
- Route capture result về đúng origin.
- Broadcast compact conversation tới workspace.

Khi file quá lớn, tách có kiểm soát thành:

- `desktop/src/main/windows/workspace.js`
- `desktop/src/main/windows/chat.js`
- `desktop/src/main/ipc/ask.js`
- `desktop/src/main/ipc/conversations.js`
- `desktop/src/main/ipc/settings.js`

#### `desktop/src/main/api.js`

Thêm method:

- List conversations.
- Show conversation.
- Delete conversation.
- Rename conversation.
- Fetch protected image.
- Search qua list query.

Chuẩn hóa lỗi 401, 403, 404, 422, 429 và 5xx.

#### `desktop/src/main/store.js`

Thêm defaults:

- Sidebar collapsed.
- Close behavior.
- Capture destination.
- Launch at login nếu hỗ trợ.

Settings migration phải merge với default và không phá `snapask.json` cũ.

#### `desktop/src/main/capture.js`

- Giữ capture logic hiện tại.
- Bổ sung origin context.
- Chuẩn hóa cancel và error.
- Bảo đảm workspace được ẩn trước capture.

#### `desktop/src/main/updater.js`

- Expose update state cho workspace.
- Cho kiểm tra thủ công.
- Thông báo download completed và install action.

### 10.2 Preload

#### `desktop/src/preload/workspace.js`

- Method-specific API.
- Listener trả cleanup function.
- Không expose Node, token, filesystem path hoặc raw Electron objects.

#### `desktop/src/preload/chat.js`

- Thêm `openInWorkspace(conversationId)`.
- Giữ backward compatibility.

### 10.3 Renderer

#### `desktop/src/renderer/workspace.html`

- Semantic landmarks.
- Live region cho streaming và errors.
- Dialog có focus management.

#### `desktop/src/renderer/workspace.css`

- CSS variables cho charcoal/amber.
- Grid shell và composer.
- Sidebar collapsed state.
- Contrast AA.
- Reduced motion.
- Responsive theo kích thước cửa sổ desktop.

#### `desktop/src/renderer/workspace.js`

Các vùng logic:

- App state reducer.
- History loading, pagination và search.
- Conversation selection.
- Message rendering.
- Composer/draft.
- Stream reducer.
- Capture attachment.
- Account/settings.
- Focus và keyboard.

Không thêm framework mới nếu vanilla HTML/CSS/JS vẫn đáp ứng tốt.

#### Compact chat

Chỉ bổ sung đồng bộ conversation và mở workspace. Không biến compact renderer thành global state store.

### 10.4 Localization

Cập nhật đồng thời:

- `desktop/src/i18n/vi.json`
- `desktop/src/i18n/en.json`

Bao phủ:

- Sidebar và time groups.
- Empty/loading/error/offline.
- Conversation actions.
- Composer/capture.
- Account/quota.
- Settings/update.
- Confirm dialog.
- Keyboard hints.

Không hard-code tiếng Việt trong renderer. Chạy i18n parity checker.

### 10.5 Laravel

- `server/routes/api.php`: image route, optional rename, list search.
- `server/app/Http/Controllers/Api/ConversationController.php`: metadata, search, ordering, rename và detail.
- `server/app/Models/Conversation.php`: scopes/relations phục vụ query.
- `server/app/Models/Message.php`: bảo đảm `tools_used` được serialize.
- `server/app/Services/AskService.php`: touch conversation và quy tắc title.
- Feature tests cho list, detail, image, search, rename, delete và last activity.

---

## 11. State management renderer

Không cần thêm React. Dùng reducer thuần:

```text
session
  user
  quota
  online
  authState

history
  items
  page
  lastPage
  loading
  query
  error

conversation
  selectedId
  entity
  messages
  loading
  error

composer
  draft
  attachment
  submitting
  requestId

ui
  sidebarCollapsed
  settingsOpen
  accountMenuOpen
  confirmDialog
  toastQueue
```

Nguyên tắc:

- Server là nguồn sự thật của conversation/message.
- Renderer giữ optimistic state có rollback.
- Không lưu token trong renderer.
- Draft chưa gửi chỉ cần trong memory ở release đầu.
- Mỗi request có ID để bỏ qua event cũ khi chuyển conversation.

---

## 12. Đồng bộ compact chat và workspace

1. Compact chat tạo conversation:
   - Main phát `conversation:created` tới workspace.
   - Workspace prepend hoặc refresh trang đầu.
2. Workspace đang mở conversation tương ứng:
   - Chỉ refresh detail khi compact stream hoàn tất.
3. Conversation bị xóa:
   - Compact chat đang giữ ID đó phải reset hoặc báo không còn tồn tại.
4. Logout từ bất kỳ window:
   - Hủy stream.
   - Đóng/ẩn workspace và compact chat.
   - Mở auth.
5. Đổi locale:
   - Main persist và broadcast tới mọi renderer.

Main process đóng vai trò coordinator, không cần realtime event bus phức tạp.

---

## 13. Bảo mật và riêng tư

### 13.1 Bắt buộc

- `contextIsolation: true`.
- `nodeIntegration: false`.
- Không expose token qua preload.
- Mọi HTTP request đi từ main process.
- Validate type, length và shape của mọi IPC input.
- Kiểm tra sender thuộc window hợp lệ cho IPC nhạy cảm.
- Không đưa token vào URL, log hoặc renderer error.
- Không trả `image_path` nội bộ.
- Sanitize markdown trước khi render HTML.
- External URL chỉ mở với allowlist protocol/host.
- Renderer không được tự điều khiển update file hoặc shell path.

### 13.2 Ảnh

- Chỉ giữ data URL trong memory đủ thời gian cần thiết.
- Giải phóng attachment sau submit nếu không còn cần.
- Ảnh lịch sử tuân thủ expiration phía server.
- Ảnh hết hạn hiển thị thông báo, không hiện broken image.
- Không cache ảnh nhạy cảm lâu dài trên disk ở release đầu.

### 13.3 Logging

- Không log token, ảnh base64 hoặc toàn bộ question/answer ở production.
- Chỉ log request ID, status, timing và error category.
- Error gửi người dùng phải được sanitize.

---

## 14. Accessibility và bàn phím

### 14.1 Keyboard map

- `Ctrl/Cmd + N`: câu hỏi mới.
- `Ctrl/Cmd + K`: focus search.
- `Ctrl/Cmd + ,`: settings.
- Shortcut capture toàn cục: giữ cấu hình hiện tại.
- `Enter`: gửi khi composer focus.
- `Shift + Enter`: xuống dòng.
- `Escape`: đóng menu/dialog hoặc xóa search theo thứ tự.
- Arrow Up/Down: điều hướng sidebar khi focus nằm trong danh sách.

Shortcut hiển thị phải lấy từ main settings, không hard-code.

### 14.2 Focus

- Empty state: focus composer.
- Chọn conversation: focus heading và thông báo screen reader.
- Mở dialog: trap focus.
- Đóng dialog: trả focus về trigger.
- Xóa active conversation: focus item kế tiếp hoặc `Câu hỏi mới`.

### 14.3 Screen reader và contrast

- Navigation có label.
- Current item dùng `aria-current`.
- Streaming status dùng polite live region.
- Lỗi quan trọng dùng alert.
- Icon button có accessible name.
- Tool activity dùng disclosure semantics.
- Body text đạt WCAG AA.
- Focus ring amber đủ tương phản.
- Click target tối thiểu khoảng 36 x 36, ưu tiên 40 x 40.
- Không chỉ dùng màu để truyền đạt trạng thái.

---

## 15. Hiệu năng

- Không render toàn bộ hàng nghìn conversation.
- Pagination 20-30 item, tải tiếp gần cuối danh sách.
- Chỉ load một conversation detail tại một thời điểm.
- Streaming chỉ cập nhật message đang hoạt động, không render lại toàn thread.
- Batch delta theo animation frame hoặc 16-40ms nếu chunk quá dày.
- Abort request khi window đóng, logout hoặc stop.
- Không resize/crop ảnh lại trong renderer.
- Cache profile và trang history gần nhất trong memory.
- Cân nhắc virtualized thread sau khi có dữ liệu thực tế, không thêm sớm.

Mục tiêu:

- Shell hiển thị dưới khoảng 500ms sau app ready trên máy bình thường.
- Sidebar skeleton xuất hiện ngay.
- Typing không giật khi stream.
- Chuyển conversation đã cache có cảm giác tức thời.

---

## 16. Kiểm thử

### 16.1 Laravel feature tests

- List chỉ trả dữ liệu user hiện tại.
- Pagination đúng.
- Sort theo last activity.
- Search title và content nếu hỗ trợ.
- Show trả tools và image metadata.
- Image route kiểm tra owner, expiry, missing file và MIME.
- Rename validation và ownership.
- Delete ownership/cascade.
- Ask mới tạo conversation và timestamp.
- Follow-up đưa conversation lên đầu.
- 401 nếu không có token.

### 16.2 Desktop module tests

Dùng Node test runner cho các module thuần nếu chưa có test framework:

- Time grouping.
- Merge pagination không duplicate.
- Stream reducer.
- Locale parity.
- Settings migration.
- Query construction.
- Error normalization.

### 16.3 Integration thủ công Windows

- Cài sạch.
- Upgrade từ `0.2.2`.
- Login/logout.
- Normal launch.
- Second instance.
- Tray open/close.
- Global shortcut khi workspace mở, ẩn và minimized.
- Capture nhiều màn hình/DPI.
- Text-only ask.
- Screenshot ask.
- Follow-up.
- Stop/retry.
- Offline/online.
- Token hết hạn.
- Hết quota.
- Delete/search/pagination.
- Update download/install.

### 16.4 macOS

Khi có môi trường build:

- Screen recording permission.
- Dock activation.
- Menu bar behavior.
- Universal DMG.
- Cmd shortcuts.

### 16.5 Visual QA

- 860 x 600.
- 1180 x 760.
- 1440 x 900.
- Windows scaling 100%, 125%, 150%.
- Tiếng Việt và tiếng Anh.
- Reduced motion.
- Keyboard-only.
- High contrast nếu hỗ trợ.
- 0, 5, 20 và hơn 100 conversations.
- Message dài, code block, URL dài và Unicode.

### 16.6 Regression

- Overlay annotation hoạt động.
- Compact `Hỏi AI` hoạt động.
- History action cũ không thành dead action.
- Installer/updater hoạt động.
- API URL migration cũ hoạt động.
- Landing và web conversation pages không bị ảnh hưởng.

---

## 17. Thứ tự triển khai

### Giai đoạn A: API nền tảng

1. Viết tests cho payload list/detail mới.
2. Ordering theo activity.
3. Preview, image metadata và tools.
4. Protected image endpoint.
5. Search.
6. Rename nếu duyệt scope.
7. Formatter và Laravel tests.

Kết quả: API ổn định trước UI.

### Giai đoạn B: Electron shell

1. Workspace window và preload.
2. Launch, second instance, tray và close behavior.
3. Sidebar/main/settings shell.
4. Tokens, typography, resizing và focus cơ bản.

Kết quả: app mở đúng workspace nhưng chưa có ask đầy đủ.

### Giai đoạn C: History và reading

1. Desktop API methods.
2. Sidebar states/pagination.
3. Conversation detail/tools/image.
4. Search/delete.
5. Rename nếu thuộc scope.

Kết quả: quản lý và đọc lịch sử được.

### Giai đoạn D: Composer và streaming

1. Text-only new chat.
2. Follow-up.
3. Stream reducer.
4. Cancel/retry/error.
5. Quota/auth expiry.
6. Sidebar synchronization.

Kết quả: workspace là bề mặt hỏi AI chính.

### Giai đoạn E: Capture integration

1. Capture từ workspace về attachment.
2. Giữ shortcut compact flow.
3. Đồng bộ compact sang workspace.
4. `Mở trong workspace`.
5. Multi-monitor/DPI regression.

### Giai đoạn F: Account/settings/update

1. Account popover.
2. Settings sections.
3. Locale broadcast.
4. Close/capture destination.
5. Update state/check/install.

### Giai đoạn G: Hardening và release

1. Security audit IPC.
2. Accessibility pass.
3. Visual QA.
4. Full tests.
5. Build installer.
6. Upgrade test từ `0.2.2`.
7. Publish installer và update metadata.

---

## 18. Tiêu chí nghiệm thu

1. Normal launch đưa user đã login vào workspace.
2. User chưa login đi qua auth đúng.
3. Sidebar có history, pagination và đầy đủ state.
4. Conversation detail đúng và không lộ dữ liệu user khác.
5. Hỏi text-only được.
6. Follow-up được.
7. Capture từ workspace và gửi ảnh được.
8. Global shortcut vẫn mở quick capture.
9. Compact chat và workspace đồng bộ conversation.
10. Streaming có stop, retry và error rõ.
11. Token không xuất hiện trong renderer hoặc URL.
12. Delete có xác nhận và cập nhật UI đúng.
13. Search toàn bộ history.
14. Account, quota, locale và settings hoạt động.
15. Tray, second instance, close và activation đúng.
16. Việt/Anh đầy đủ, parity test đạt.
17. Keyboard/focus không mắc kẹt.
18. Charcoal/amber nhất quán và không sao chép ZCode.
19. Upgrade từ `0.2.2` không mất token/settings.
20. Laravel tests, syntax checks, i18n check và build đều pass.

---

## 19. Version, rollout và rollback

### 19.1 Version

- Internal QA: `0.3.0-beta.1`.
- Release candidate: `0.3.0-rc.1`.
- Stable: `0.3.0`.

Workspace là thay đổi sản phẩm lớn nên không dùng `0.2.3`.

### 19.2 Rollout

1. Deploy API additive trước.
2. Xác minh desktop `0.2.2` vẫn hoạt động.
3. Phát hành beta cho nhóm nhỏ.
4. Theo dõi auth errors, ask failures, image fetch và updater.
5. Phát hành `0.3.0` qua update feed hiện tại.

### 19.3 Rollback

- API mới phải tương thích ngược.
- Không xóa endpoint hoặc đổi field cũ.
- Settings migration giữ key cũ và có fallback.
- Nếu `0.3.0` lỗi, có thể hạ feed về installer cũ hoặc phát hành `0.3.1`.
- Không dùng migration DB phá hủy dữ liệu.

---

## 20. Các quyết định mặc định cần khóa trước khi code

1. Workspace và compact chat cùng tồn tại: Có.
2. Normal launch mở workspace: Có.
3. Global shortcut mở compact chat: Có.
4. Capture từ workspace quay về workspace: Có.
5. Close button mặc định hide xuống tray: Có.
6. Historical screenshot trong bản đầu: Có, qua API image được bảo vệ.
7. Search server-side trong bản đầu: Có.
8. Rename trong bản đầu: Nên có, có thể cắt nếu cần giảm scope.
9. Pin/archive/folder trong `0.3.0`: Không.
10. Framework UI mới: Không, tiếp tục vanilla HTML/CSS/JS.
11. Theme: dark charcoal cố định trong `0.3.0`.
12. Phiên bản mục tiêu: `0.3.0`.

---

## 21. Rủi ro và biện pháp giảm thiểu

### 21.1 Nhiều stream giữa nhiều cửa sổ

Rủi ro: cancel nhầm stream hoặc event đi sai renderer.

Giảm thiểu: request ID duy nhất, registry theo webContents, cleanup khi window destroy và integration tests cho simultaneous requests.

### 21.2 Đồng bộ history không nhất quán

Rủi ro: compact chat tạo conversation nhưng workspace không cập nhật.

Giảm thiểu: main process là event coordinator, refresh trang đầu sau `done`, deduplicate theo ID.

### 21.3 Token hết hạn giữa stream

Rủi ro: UI kẹt loading hoặc xóa token vì lỗi mạng thường.

Giảm thiểu: chỉ logout với lỗi xác thực xác định, phân biệt network error và 401, hủy mọi request khi chuyển auth state.

### 21.4 Ảnh nhạy cảm

Rủi ro: token lộ qua URL hoặc ảnh bị cache lâu.

Giảm thiểu: main-process fetch, không token query, memory-only response và expiration rõ ràng.

### 21.5 `main.js` tiếp tục phình to

Rủi ro: lifecycle, IPC và window state khó bảo trì.

Giảm thiểu: tách module sau khi hợp đồng window/IPC được khóa; không refactor toàn bộ cùng lúc với UI để giảm regression.

### 21.6 Upgrade làm mất settings

Rủi ro: schema mới ghi đè `snapask.json` cũ.

Giảm thiểu: merge defaults, versioned migration, fixture test từ cấu hình `0.2.2`.

---

## 22. Web Management Portal sau đăng nhập

### 22.1 Mục tiêu

Khu vực website sau đăng nhập phải trở thành một cổng quản lý hoàn chỉnh, thay vì tập hợp các trang form độc lập. Portal phục vụ bốn nhu cầu:

1. Xem nhanh tài khoản, quota, model và dịch vụ đang hoạt động.
2. Quản lý lịch sử hội thoại và ảnh chụp.
3. Cấu hình provider và model AI.
4. Kết nối, theo dõi và sửa lỗi MCP services.

Portal và desktop dùng chung ngôn ngữ charcoal/amber, thuật ngữ và mô hình dữ liệu. Desktop tập trung vào hỏi AI; website tập trung vào quản trị, lịch sử chi tiết và cấu hình.

### 22.2 Kiểm kê hiện trạng

Khu vực authenticated hiện có các hạn chế:

- Layout chỉ là một cột 760px, chưa phù hợp portal quản lý.
- Models, Services và History nằm trong navigation nhỏ, thiếu phân cấp.
- Logout chỉ xuất hiện ở trang Services nên không nhất quán.
- Không có dashboard sau đăng nhập.
- Không có account menu, quota overview hoặc connection summary.
- CSS nằm inline trong `layouts/app.blade.php`, khó mở rộng thành design system.
- Nhiều view dùng inline style, gây khó khăn cho responsive và consistency.
- History thiếu search, filter, preview và quick actions.
- Provider/connector form luôn mở, làm trang dài dù người dùng chủ yếu xem trạng thái.
- Mobile navigation và tablet layout chưa được định nghĩa rõ.

### 22.3 Kiến trúc thông tin

```text
Authenticated portal
├── Tổng quan                 /dashboard
├── Hội thoại                 /conversations
│   └── Chi tiết              /conversations/{conversation}
├── Mô hình AI                /providers
├── Dịch vụ kết nối           /connectors
├── Tài khoản                 /account
└── Cài đặt                   /settings
```

Quyết định:

- Thêm `/dashboard` làm trang mặc định sau login/verify.
- Giữ nguyên slug của provider, connector và conversation để tránh regression.
- Thêm account/settings theo hướng additive.
- `Trang quản lý` trên landing page dẫn đến dashboard.

### 22.4 App shell website

Desktop từ 1024px:

```text
┌─────────────────────────────────────────────────────────────────────┐
│ Sidebar 248px       │ Top bar: title, quota, locale, account        │
│                     ├───────────────────────────────────────────────┤
│ SnapAsk             │                                               │
│ Tổng quan           │ Main content                                  │
│ Hội thoại           │                                               │
│ Mô hình AI          │                                               │
│ Dịch vụ kết nối     │                                               │
│                     │                                               │
│ Tải ứng dụng        │                                               │
│ User / plan         │                                               │
└─────────────────────────────────────────────────────────────────────┘
```

- Sidebar 240-256px với navigation chính và trạng thái active rõ.
- Main content rộng tối đa 1180-1280px tùy trang.
- Top bar chứa page title, quota compact, locale và account menu.
- Sidebar footer chứa initials/avatar, email và plan.
- `Tải ứng dụng` kết nối trải nghiệm web với desktop.
- Không biến mọi dữ liệu thành card; dùng divider và whitespace khi đủ.

Responsive:

- 768-1023px: compact rail hoặc drawer.
- Dưới 768px: off-canvas navigation, sticky header và nội dung một cột.
- Form action có thể sticky ở mobile khi cần.
- Danh sách nhiều cột chuyển thành stacked rows có semantic đầy đủ.

### 22.5 Dashboard

Dashboard gồm:

1. Welcome header:
   - Tên người dùng.
   - Gói hiện tại.
   - CTA tải hoặc mở SnapAsk Desktop.
2. Usage summary:
   - Lượt đã dùng, còn lại và kỳ quota.
   - Trạng thái dùng SnapAsk key hay own key.
3. Active AI setup:
   - Provider và model đang dùng.
   - Trạng thái cấu hình.
   - Action đổi model.
4. Connected services:
   - Tổng số connector.
   - Healthy, error và disabled counts.
   - Lần sync gần nhất.
5. Recent conversations:
   - 5-8 hội thoại gần nhất.
   - Title, preview, thời gian, model và trạng thái ảnh.
6. Setup checklist có điều kiện:
   - Cài desktop.
   - Chọn provider/model.
   - Kết nối service tùy chọn.
   - Hỏi câu đầu tiên.

Checklist biến mất khi hoàn thành, không tồn tại như decoration lâu dài.

### 22.6 Quản lý hội thoại trên web

Danh sách:

- Search toàn bộ lịch sử.
- Filter theo thời gian, model và screenshot nếu query hỗ trợ.
- Mỗi row có title, last-message preview, last activity, message count và model.
- Pagination giữ search/filter query.
- Context action gồm xem, đổi tên nếu có và xóa.
- Empty state dẫn người dùng tải/mở desktop.
- Delete dùng accessible dialog thay `window.confirm`.

Chi tiết:

- Header có title, created/updated time, model và delete action.
- Screenshot hiển thị như attachment với retained/expired state.
- Message thread phân biệt user và SnapAsk bằng spacing/surface, không lặp card giống nhau.
- Tool activity dùng disclosure.
- Code block có style và copy action nếu bổ sung markdown rendering an toàn.
- Metadata rail trên desktop, chuyển xuống cuối ở mobile.

### 22.7 Quản lý mô hình AI

Chia thành ba vùng:

1. Active configuration:
   - Provider/model hiện tại.
   - Quota hoặc own-key status.
   - Quick model switch.
2. Your providers:
   - Danh sách provider với configuration status.
   - Model selection rõ ràng.
   - Edit/delete trong action menu.
   - API key luôn masked và không được render lại.
3. Add provider:
   - Mở bằng drawer, dialog hoặc disclosure thay vì luôn chiếm nửa trang.
   - Format, base URL, API key và model list.
   - Inline validation và error summary.
   - Test connection nếu backend hỗ trợ an toàn.

SnapAsk Default phải là một lựa chọn trong cùng hệ thống, không phải card có cấu trúc hoàn toàn khác.

### 22.8 Quản lý dịch vụ kết nối

- Summary gồm connected, healthy, error và disabled.
- Mỗi connector hiển thị tên, slug, endpoint rút gọn, trạng thái, số tools và last sync.
- Error state có hướng xử lý cụ thể.
- Actions: sync, edit, enable/disable và delete.
- Tool list dài mặc định thu gọn.
- Add service mở qua drawer/dialog/disclosure.
- HTTPS helper nằm cạnh URL field.
- Token không được render lại sau khi lưu.
- Sync có pending state và chống submit lặp.

### 22.9 Account và settings

Account:

- Tên, email và verify status.
- Plan và quota.
- Locale.
- Logout luôn có trong account menu và account page.
- Session/token management có thể bổ sung ở giai đoạn sau.

Settings:

- Locale.
- Data retention và privacy explanation.
- Download/update channel information.
- Chỉ thêm preference dùng chung desktop/web khi có đồng bộ server rõ ràng.

Không đưa server URL hoặc capture settings riêng của desktop lên website.

### 22.10 Landing page theo auth state

- Guest: `Đăng nhập` và `Tải về`.
- Authenticated: `Mở trang quản lý`, account menu và `Tải về` nếu còn đủ chỗ.
- Không hiển thị nhiều CTA cùng ý nghĩa.
- Logo public dẫn về landing; logo portal dẫn về dashboard.

### 22.11 Design system website

- Tách CSS portal khỏi inline `<style>` trong layout.
- Dùng semantic tokens charcoal/amber đồng bộ với landing và desktop.
- Component classes cho shell, navigation, header, section, list, form, status, dialog và empty state.
- Loại bỏ inline style theo từng giai đoạn.
- Tiếp tục Be Vietnam Pro self-host.
- Một dark theme thống nhất trong release đầu.
- Amber là accent duy nhất; green/red chỉ dùng semantic state.
- WCAG AA cho text, controls và focus.
- Label trên input, helper/error bên dưới; không dùng placeholder làm label.

### 22.12 Blade component strategy

Tạo các component tái sử dụng:

- `components/app-shell.blade.php`
- `components/sidebar-nav.blade.php`
- `components/page-header.blade.php`
- `components/status-badge.blade.php`
- `components/empty-state.blade.php`
- `components/form-field.blade.php`
- `components/confirm-dialog.blade.php`
- `components/account-menu.blade.php`

Không cần chuyển sang SPA. Server-rendered Blade phù hợp với các màn hình quản trị hiện tại và giữ validation Laravel đơn giản.

### 22.13 Controller và dữ liệu

Dashboard controller tổng hợp:

- User/quota.
- Active provider/model.
- Connector health counts.
- Recent conversations.
- Onboarding completion.

Conversation web index hỗ trợ:

- Search/filter.
- Order theo last activity.
- Preview text.
- Query-preserving pagination.

Provider/connector controller trả status rõ cho UI. Không health-check dịch vụ ngoài đồng bộ trong mỗi page load nếu gây chậm; dùng kết quả sync gần nhất.

### 22.14 Accessibility

- Giữ skip link.
- Sidebar có label; item active dùng `aria-current="page"`.
- Mobile drawer có focus trap, Escape và focus restoration.
- Account menu hỗ trợ keyboard.
- Delete dialog không phụ thuộc `window.confirm`.
- Flash message dùng live region phù hợp.
- Validation summary link đến field lỗi.
- Status không chỉ truyền đạt bằng màu.
- Pagination có accessible labels.
- Touch target tối thiểu 40px trên mobile.

### 22.15 Security

- CSRF cho mọi mutation.
- Provider key và connector token không render lại.
- Ownership/policy check cho mọi resource.
- Escape provider/connector URL khi render.
- Không render raw AI HTML nếu chưa sanitize.
- Logout tiếp tục dùng POST.
- Destructive action có explicit confirmation.
- Rate-limit sync/test connection nếu gọi service ngoài.
- Không ghi secret vào log.

### 22.16 Localization

- Mọi copy nằm trong `server/lang/vi.json` và `server/lang/en.json`.
- Không hard-code text mới trong Blade/JavaScript.
- Thống nhất thuật ngữ:
  - `Tổng quan` / `Overview`.
  - `Hội thoại` / `Conversations`.
  - `Mô hình AI` / `AI models`.
  - `Dịch vụ kết nối` / `Connected services`.
  - `Tài khoản` / `Account`.
  - `Cài đặt` / `Settings`.
- Locale parity tiếp tục được kiểm tra bằng tests.

### 22.17 Trạng thái và responsive bắt buộc

Mỗi trang có:

- Loading cho action async.
- Empty state.
- Validation error.
- Not found/authorization state.
- Success feedback.
- Degraded state khi service ngoài lỗi.

Breakpoints:

- Dưới 768px: một cột và off-canvas nav.
- 768-1023px: compact rail/drawer.
- Từ 1024px: full app shell.
- Từ 1440px: tăng vùng nội dung nhưng giữ line length hợp lý.

### 22.18 Bản đồ file website

Sửa có kiểm soát:

- `server/resources/views/layouts/app.blade.php`: app shell mới.
- `server/resources/views/providers/index.blade.php`: active setup, list và add flow.
- `server/resources/views/connectors/index.blade.php`: health summary, list và add flow.
- `server/resources/views/conversations/index.blade.php`: search/filter/list.
- `server/resources/views/conversations/show.blade.php`: thread detail.
- `server/routes/web.php`: dashboard/account/settings, giữ route cũ.
- `server/lang/vi.json` và `server/lang/en.json`: copy mới.
- Feature tests provider, connector, conversation và locale.

Tạo mới dự kiến:

- `server/app/Http/Controllers/Web/DashboardController.php`.
- `server/resources/views/dashboard.blade.php`.
- `server/resources/views/account/show.blade.php`.
- `server/resources/views/settings/index.blade.php`.
- Blade components cho app shell.
- Stylesheet/JavaScript portal chuyên biệt.
- Feature tests dashboard/account/settings.

### 22.19 Trình tự triển khai portal

Giai đoạn W1, foundation:

1. Khóa IA, route và thuật ngữ.
2. Tạo responsive app shell.
3. Tách inline CSS thành stylesheet có token/component.
4. Tạo Blade components nền tảng.
5. Giữ route và form cũ hoạt động.

Giai đoạn W2, dashboard/account:

1. Dashboard controller/view.
2. Quota, active model, connector health và recent conversations.
3. Account menu, logout và locale.
4. Redirect login/verify tới dashboard.

Giai đoạn W3, conversations:

1. Search/filter/order.
2. List và detail mới.
3. Accessible delete dialog.
4. Đồng bộ query/payload với API desktop.

Giai đoạn W4, providers/connectors:

1. Active model section.
2. Provider list và add/edit flow.
3. Connector health và sync state.
4. Add/edit connector flow.
5. Validation và destructive actions.

Giai đoạn W5, hardening:

1. Responsive QA.
2. Keyboard/screen-reader pass.
3. Locale parity.
4. Security review.
5. Feature tests, Pint và frontend build.

W1-W2 có thể làm trước desktop workspace. W3 phải phối hợp với conversation API desktop để tránh làm hai lần.

### 22.20 Tiêu chí nghiệm thu portal

1. Sau login, user vào dashboard.
2. Navigation nhất quán trên mọi trang authenticated.
3. Logout và locale luôn truy cập được.
4. Dashboard hiển thị quota, active model, connector health và recent conversations đúng.
5. History có search, pagination, trạng thái ảnh và detail dễ đọc.
6. Provider/model selection rõ và không lộ API key.
7. Connector status, sync, lỗi và tools dễ hiểu, không lộ token.
8. Mutation giữ CSRF và authorization.
9. Mobile/tablet sử dụng được.
10. Keyboard, focus, dialog và flash message đạt yêu cầu.
11. Việt/Anh đầy đủ, thuật ngữ nhất quán.
12. Landing auth state dẫn tới dashboard.
13. Route cũ không bị phá.
14. Desktop `0.2.2` vẫn tương thích khi server deploy trước.
15. Laravel tests, Pint và frontend build đều pass.

## 23. Phạm vi phát hành cập nhật

Chia thành hai phần phối hợp:

- Web Portal refresh: deploy trước, không phụ thuộc desktop mới.
- Desktop Workspace `0.3.0`: phát hành sau khi conversation API chung ổn định.

Nếu dùng cùng một milestone sản phẩm, server vẫn phải được deploy trước desktop. API và route thay đổi theo hướng additive để desktop hiện tại tiếp tục hoạt động.

## Kết luận

Phương án tổng thể gồm hai bề mặt bổ trợ nhau:

- Desktop workspace là nơi hỏi AI, chụp nhanh và tiếp tục hội thoại.
- Web Management Portal là nơi quản lý tài khoản, quota, lịch sử chi tiết, model provider và MCP services.

Cả hai dùng chung ngôn ngữ charcoal/amber, thuật ngữ, conversation ordering và trạng thái dữ liệu. Server được nâng cấp theo hướng additive trước, sau đó triển khai web app shell/dashboard, rồi mới nối desktop workspace vào API mới. Các rủi ro chính là quản lý nhiều stream, đồng bộ lịch sử, secret handling, route compatibility và settings migration.

Chỉ bắt đầu chỉnh sửa mã nguồn sau khi kế hoạch mở rộng này được duyệt.