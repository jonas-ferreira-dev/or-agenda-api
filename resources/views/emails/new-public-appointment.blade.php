<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Novo agendamento recebido</title>
</head>
<body style="font-family: Arial, sans-serif; color: #111827; background: #f8fafc; padding: 24px;">
    <div style="max-width: 640px; margin: 0 auto; background: #ffffff; border-radius: 16px; padding: 24px; border: 1px solid #e5e7eb;">
        <h1 style="margin-top: 0; color: #111827;">Novo agendamento recebido</h1>

        <p>Olá!</p>

        <p>Você recebeu um novo agendamento pelo seu link público do <strong>OR Agenda</strong>.</p>

        <div style="margin: 24px 0; padding: 16px; border-radius: 12px; background: #f3f4f6;">
            <h2 style="margin-top: 0; font-size: 18px;">Dados do agendamento</h2>

            <p><strong>Cliente:</strong> {{ $appointment->client?->name ?? '-' }}</p>
            <p><strong>Telefone:</strong> {{ $appointment->client?->phone ?? '-' }}</p>
            <p><strong>E-mail:</strong> {{ $appointment->client?->email ?? '-' }}</p>

            <hr style="border: 0; border-top: 1px solid #d1d5db; margin: 16px 0;">

            <p><strong>Serviço:</strong> {{ $appointment->service?->name ?? '-' }}</p>
            <p><strong>Data:</strong> {{ optional($appointment->appointment_date)->format('d/m/Y') }}</p>
            <p><strong>Horário:</strong> {{ substr($appointment->start_time, 0, 5) }} até {{ substr($appointment->end_time, 0, 5) }}</p>

            @if (! empty($appointment->notes))
                <hr style="border: 0; border-top: 1px solid #d1d5db; margin: 16px 0;">
                <p><strong>Observações:</strong></p>
                <p>{{ $appointment->notes }}</p>
            @endif
        </div>

        <p>Entre no painel do OR Agenda para acompanhar seus agendamentos.</p>

        <p style="margin-bottom: 0; color: #6b7280; font-size: 13px;">
            Este é um aviso automático. Não é necessário responder este e-mail.
        </p>
    </div>
</body>
</html>