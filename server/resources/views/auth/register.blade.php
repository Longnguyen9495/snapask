@extends('layouts.app')

@section('title', 'Tạo tài khoản · SnapAsk')
@section('description', 'Tạo tài khoản SnapAsk, dùng thử miễn phí.')
@section('wrap-modifier', 'wrap--narrow')

@section('content')
  <h1>Tạo tài khoản</h1>
  <p class="lead">Dùng thử miễn phí, không cần thẻ.</p>

  <form method="POST" action="{{ route('register.store') }}" class="stack card">
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
      Tên của bạn
      <input type="text" name="name" value="{{ old('name') }}" required autofocus>
    </label>

    <label>
      Email
      <input type="email" name="email" value="{{ old('email') }}" autocomplete="username" required>
    </label>

    <label>
      Mật khẩu
      <input type="password" name="password" autocomplete="new-password" required>
    </label>

    <label>
      Nhập lại mật khẩu
      <input type="password" name="password_confirmation" autocomplete="new-password" required>
    </label>

    <button type="submit">Tạo tài khoản</button>
  </form>

  <p class="note center" style="margin-top: 18px">
    Đã có tài khoản? <a href="{{ route('login') }}" style="color: var(--accent)">Đăng nhập</a>
  </p>
@endsection
