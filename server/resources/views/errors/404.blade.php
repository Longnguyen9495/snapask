@extends('layouts.app')

@section('title', 'Không tìm thấy trang · SnapAsk')
@section('wrap-modifier', 'wrap--narrow')

@section('content')
  <h1>Không tìm thấy trang này</h1>
  <p class="lead">Đường dẫn bạn vừa mở không tồn tại, hoặc đã bị xoá.</p>

  <a href="{{ url('/') }}" class="btn">Về trang chính</a>
@endsection
