<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<title>Hóa đơn #{{ $invoice->id }}</title>
<style>
  *{ font-family: DejaVu Sans, sans-serif; }
  body{ font-size:12px; margin:14px; }
  h1{ font-size:16px; margin:0 0 6px }
  .muted{ color:#666 }
  .row{ display:flex; gap:16px }
  .col{ flex:1 }
  table{ width:100%; border-collapse: collapse; }
  th,td{ border:1px solid #ccc; padding:6px; }
  th{ background:#f5f5f5; text-align:left }
  .right{ text-align:right }
  .totals td{ border:none; padding:3px 0 }
  .mb8{ margin-bottom:8px }
  .mb12{ margin-bottom:12px }
  .mb16{ margin-bottom:16px }
</style>
</head>
<body>
  <h1>HÓA ĐƠN #{{ $invoice->id }} <span class="muted">({{ $invoice->code ?? '—' }})</span></h1>
  <div class="muted mb8">Ngày tạo: {{ dmy($invoice->created_at) }} &nbsp;|&nbsp; Hạn TT: {{ dmy($invoice->due_date) }}</div>

  <div class="row mb12">
    <div class="col">
      <strong>Trung tâm:</strong><br>
      {{ $invoice->branch->name ?? '—' }}
    </div>
    <div class="col">
      <strong>Học viên:</strong><br>
      {{ $invoice->student->code ?? '—' }} · {{ $invoice->student->name ?? '—' }}<br>
      {{ $invoice->student->phone ?? '' }} {{ $invoice->student->email ? ' · '.$invoice->student->email : '' }}
    </div>
    <div class="col">
      <strong>Lớp:</strong><br>
      @if($invoice->classroom)
        {{ $invoice->classroom->code }} · {{ $invoice->classroom->name }}
      @else
        —
      @endif
    </div>
  </div>

  <table class="mb12">
    <thead>
      <tr>
        <th style="width:28%">Loại</th>
        <th>Mô tả</th>
        <th style="width:10%">SL</th>
        <th style="width:18%">Đơn giá</th>
        <th style="width:18%">Thành tiền</th>
      </tr>
    </thead>
    <tbody>
      @forelse($invoice->invoiceItems as $it)
        <tr>
          <td>{{ $it->type }}</td>
          <td>{{ $it->description ?? '' }}</td>
          <td class="right">{{ $it->qty }}</td>
          <td class="right">{{ vnd($it->unit_price) }}</td>
          <td class="right">{{ vnd($it->amount) }}</td>
        </tr>
      @empty
        <tr><td colspan="5" class="right">Không có mục chi tiết.</td></tr>
      @endforelse
    </tbody>
  </table>

  <table class="totals mb16">
    <tr><td class="right"><strong>Tổng cộng:</strong> {{ vnd($invoice->total) }} đ</td></tr>
    <tr><td class="right">Đã thanh toán: {{ vnd($paid) }} đ</td></tr>
    <tr><td class="right"><strong>Còn lại:</strong> {{ vnd($remaining) }} đ</td></tr>
  </table>

  @if($invoice->payments->count())
    <div class="mb8"><strong>Thanh toán:</strong></div>
    <table>
      <thead><tr><th>PTTT</th><th>Ngày</th><th class="right">Số tiền</th><th>Tham chiếu</th></tr></thead>
      <tbody>
        @foreach($invoice->payments as $p)
          <tr>
            <td>{{ $p->method }}</td>
            <td>{{ dmy($p->paid_at) }}</td>
            <td class="right">{{ vnd($p->amount) }} đ</td>
            <td>{{ $p->ref_no ?? '' }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif

  @if($invoice->note)
    <div class="mb8"><strong>Ghi chú:</strong> {{ $invoice->note }}</div>
  @endif

  <div class="muted" style="margin-top:18px">* Tài liệu được tạo tự động từ hệ thống.</div>
</body>
</html>
