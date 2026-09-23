@extends('layouts.app')

@section('title', 'Đăng nhập · SnapAsk')
@section('description', 'Đăng nhập SnapAsk để quản lý dịch vụ đã kết nối.')
@section('wrap-modifier', 'wrap--narrow')

@section('content')
  <h1>Đăng nhập</h1>
  <p class="lead">Quản lý dịch vụ đã kết nối và xem hạn mức của bạn.</p>

  <form method="POST" action="{{ route('login.store') }}" class="stack card">
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
      Email
      <input type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
    </label>

    <label>
      Mật khẩu
      <input type="password" name="password" autocomplete="current-password" required>
    </label>

    <label class="inline">
      <input type="checkbox" name="remember" value="1">
      Ghi nhớ trên máy này
    </label>

    <button type="submit">Đăng nhập</button>
  </form>

  <p class="note center" style="margin-top: 18px">
    Chưa có tài khoản? <a href="{{ route('register') }}" style="color: var(--accent)">Tạo tài khoản</a>
  </p>
@endsection
