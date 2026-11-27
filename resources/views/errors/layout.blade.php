@extends('errors.minimal')

@section('title', $title ?? 'Terjadi Kesalahan')
@section('message', $message ?? 'Halaman tidak dapat ditampilkan.')
