<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #111827; }
        .letterhead { text-align: center; margin-bottom: 8px; }
        .letterhead h1 { font-size: 16px; margin: 0; text-transform: uppercase; }
        .letterhead .sub { font-size: 10.5px; margin: 2px 0; color: #374151; }
        .title { text-align: center; font-size: 13px; font-weight: bold; text-transform: uppercase;
                 border-top: 2px solid #111827; border-bottom: 2px solid #111827; padding: 5px 0; margin: 10px 0 4px; }
        .subtitle { text-align: center; font-size: 11px; font-weight: bold; margin-bottom: 10px; letter-spacing: 0.5px; }
        .info-box { border: 1px solid #111827; padding: 8px; margin-bottom: 10px; }
        .info-box table { width: 100%; border-collapse: collapse; }
        .info-box td { padding: 2px 4px; font-size: 10.5px; vertical-align: top; }
        .info-label { color: #374151; width: 100px; }
        .info-value { font-weight: bold; width: 340px; }
        .disclaimer { font-size: 9px; color: #374151; border-top: 1px solid #9ca3af; border-bottom: 1px solid #9ca3af;
                      padding: 5px 0; margin-bottom: 12px; }
        .disclaimer ol { margin: 2px 0 0 14px; padding: 0; }
        .results { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .results th { text-align: left; font-size: 9.5px; text-transform: uppercase; letter-spacing: 0.3px;
                      border-bottom: 1.5px solid #111827; padding: 5px 4px; }
        .results td { font-size: 10.5px; padding: 5px 4px; border-bottom: 1px solid #e5e7eb; }
        .status-abnormal { font-weight: bold; color: #b91c1c; }
        .status-normal { color: #111827; }
        .end-of-report { text-align: center; font-size: 10px; color: #374151; margin: 16px 0; }
        .signoff table { width: 100%; margin-top: 20px; }
        .signoff td { font-size: 10.5px; vertical-align: top; padding-top: 10px; }
        .footer { margin-top: 24px; font-size: 9px; color: #6b7280; border-top: 1px solid #e5e7eb; padding-top: 6px; }
    </style>
</head>
<body>
    <div class="letterhead">
        <h1>{{ $hospital->hospital_name ?: 'Smart Hospital' }}</h1>
        @if($hospital->address)<p class="sub">{{ $hospital->address }}</p>@endif
        @if($hospital->phone_number || $hospital->email)
            <p class="sub">
                @if($hospital->phone_number) Ph: {{ $hospital->phone_number }} @endif
                @if($hospital->phone_number && $hospital->email) &middot; @endif
                @if($hospital->email) {{ $hospital->email }} @endif
            </p>
        @endif
    </div>

    <div class="title">Laboratory Services Report</div>
    <p class="subtitle">{{ strtoupper($order->test_name ?? '—') }}</p>

    <div class="info-box">
        <table>
            <tr>
                <td class="info-label">Patient Name</td><td class="info-value">{{ $order->patient_name ?? '—' }}</td>
                <td class="info-label">Report No</td><td class="info-value">{{ $labReportId }}</td>
            </tr>
            <tr>
                <td class="info-label">Patient ID</td><td class="info-value">{{ $order->patient_id ?? '—' }}</td>
                <td class="info-label">Order No</td><td class="info-value">{{ $order->test_order_id ?? '—' }}</td>
            </tr>
            <tr>
                <td class="info-label">Gender / Age</td>
                <td class="info-value">
                    {{ $order->patient_gender ? ucfirst($order->patient_gender) : '—' }}
                    @if($order->patient_dob) / {{ \Carbon\Carbon::parse($order->patient_dob)->age }} Y @endif
                </td>
                <td class="info-label">Ordered Dt</td>
                <td class="info-value">{{ $order->order_date ? \Carbon\Carbon::parse($order->order_date)->format('d/m/Y h:i A') : '—' }}</td>
            </tr>
            <tr>
                <td class="info-label">Referred By</td><td class="info-value">Dr. {{ $order->doctor_name ?? '—' }}</td>
                <td class="info-label">Reported Dt</td><td class="info-value">{{ \Carbon\Carbon::parse($generatedAt)->format('d/m/Y h:i A') }}</td>
            </tr>
        </table>
    </div>

    <div class="disclaimer">
        <strong>Disclaimer</strong>
        <ol>
            <li>This is an electronically generated report.</li>
            <li>These results are valid only for the particular sample received at the specified time and must be clinically correlated for interpretation.</li>
            <li>Abnormal results are indicated in bold.</li>
        </ol>
    </div>

    <table class="results">
        <thead>
            <tr><th>Test Description</th><th>Result Value</th><th>Status</th><th>Remarks</th></tr>
        </thead>
        <tbody>
            @php($statusClass = strtolower($resultStatus) === 'abnormal' ? 'status-abnormal' : 'status-normal')
            <tr>
                <td>{{ $order->test_name ?? '—' }}</td>
                <td class="{{ $statusClass }}">{{ $resultValue }}</td>
                <td class="{{ $statusClass }}">{{ strtoupper($resultStatus) }}</td>
                <td>{{ $remarks ?: '—' }}</td>
            </tr>
        </tbody>
    </table>

    <p class="end-of-report">-------------------------------------------- END OF REPORT --------------------------------------------</p>

    <div class="signoff">
        <table>
            <tr>
                <td style="width: 50%;">
                    Verified By: {{ $order->technician_name ?? '—' }}<br>
                    on {{ \Carbon\Carbon::parse($enteredAt)->format('d/m/Y h:i A') }}<br>
                    <strong>LAB TECHNICIAN</strong>
                </td>
                <td style="width: 50%;">
                    Authorized by<br>
                    <strong>Dr. {{ $order->doctor_name ?? '—' }}</strong>
                </td>
            </tr>
        </table>
    </div>

    <p class="footer">
        Generated by {{ $hospital->hospital_name ?: 'Smart Hospital' }} Management System on
        {{ \Carbon\Carbon::parse($generatedAt)->format('d/m/Y h:i A') }}
    </p>
</body>
</html>
