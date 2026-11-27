@extends('errors.minimal')

@section('title', $title ?? 'Terjadi Kesalahan')
@section('code', $code ?? '500')
@section('message', $message ?? 'Halaman tidak dapat ditampilkan.')
