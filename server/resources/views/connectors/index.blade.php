@extends('layouts.app')

@section('title', 'Dịch vụ đã kết nối · SnapAsk')
@section('description', 'Khai báo dịch vụ để AI tra cứu dữ liệu thật thay vì đoán từ ảnh chụp.')

@section('content')
  <div class="row">
    <div>
      <h1>Dịch vụ cho AI tra cứu</h1>
      <p class="note" style="margin-top: 6px; font-variant-numeric: tabular-nums">
        {{ $quota['own_key']
            ? "Gói {$quota['plan']} · dùng khoá riêng, không giới hạn lượt"
            : "Gói {$quota['plan']} · còn {$quota['remaining']}/{$quota['limit']} lượt hỏi trong tháng" }}
      </p>
    </div>

    <form method="POST" action="{{ route('logout') }}">
      @csrf
      <button type="submit" class="btn--quiet">Đăng xuất</button>
    </form>
  </div>

  @if (session('status'))
    <p class="flash">{{ session('status') }}</p>
  @endif

  @forelse ($connectors as $connector)
    <article class="card">
      <div class="row" style="margin-bottom: 4px; align-items: baseline">
        <h2>{{ $connector->name }} <span class="tag">{{ $connector->slug }}</span></h2>
        @unless ($connector->enabled)
          <span class="tag">đang tắt</span>
        @endunless
      </div>

      <p class="note" style="word-break: break-all">{{ $connector->url }}</p>

      @if ($connector->last_error)
        <p class="note status--bad">Không kết nối được: {{ $connector->last_error }}</p>
      @elseif (filled($connector->tools))
        <p class="note status--ok">
          {{ count($connector->tools) }} công cụ · đồng bộ {{ $connector->synced_at?->diffForHumans() }}
        </p>
        <div class="tools">
          @foreach ($connector->tools as $tool)
            <span class="tag">{{ $tool['name'] }}</span>
          @endforeach
        </div>
      @else
        <p class="note">Chưa đọc được công cụ nào.</p>
      @endif

      <div class="actions">
        <form method="POST" action="{{ route('web.connectors.resync', $connector) }}">
          @csrf
          <button type="submit" class="btn--quiet">Đồng bộ lại</button>
        </form>

        <form method="POST"
              action="{{ route('web.connectors.destroy', $connector) }}"
              onsubmit="return confirm('Xoá {{ $connector->name }}? AI sẽ không tra được dữ liệu trong dịch vụ này nữa.')">
          @csrf
          @method('DELETE')
          <button type="submit" class="btn--quiet btn--danger">Xoá</button>
        </form>
      </div>
    </article>
  @empty
    {{--
      Trạng thái rỗng giải thích luôn dịch vụ này để làm gì. Một dòng "chưa có gì"
      không nói cho người mới biết bước tiếp theo là gì.
    --}}
    <div class="card" style="padding: 30px 26px">
      <h2>Chưa nối dịch vụ nào</h2>
      <p class="note" style="margin-top: 8px; max-width: 54ch">
        Hiện AI chỉ đọc được những gì nhìn thấy trong ảnh chụp. Nối một dịch vụ
        theo chuẩn MCP thì nó tra được dữ liệu thật — tồn kho, đơn hàng, lịch hẹn —
        ngay trong lúc trả lời.
      </p>
      <p class="note" style="margin-top: 10px">
        Địa chỉ phải là <code>https</code>, vì token đi kèm mọi lời gọi.
      </p>
    </div>
  @endforelse

  <h2 style="margin: 32px 0 12px">Thêm dịch vụ</h2>

  <form method="POST" action="{{ route('web.connectors.store') }}" class="stack card">
    @csrf

    @if ($errors->any())
      <div class="errors">
        <ul>
          @foreach ($errors->all() as $message)
            <li>{{ $message }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <label>
      Tên hiển thị
      <input type="text" name="name" value="{{ old('name') }}" placeholder="Kho hàng" required>
    </label>

    <label>
      Mã rút gọn
      <input type="text" name="slug" value="{{ old('slug') }}" placeholder="kho"
             pattern="[a-z0-9_-]+" required>
    </label>

    <label>
      Địa chỉ MCP
      <input type="url" name="url" value="{{ old('url') }}" placeholder="https://…/mcp" required>
    </label>

    <label>
      Token truy cập (nếu dịch vụ yêu cầu)
      {{-- Không đổ lại giá trị cũ: token là bí mật, không nên nằm trong HTML. --}}
      <input type="password" name="auth_token" autocomplete="off">
    </label>

    <button type="submit">Thêm và kết nối</button>
  </form>
@endsection
