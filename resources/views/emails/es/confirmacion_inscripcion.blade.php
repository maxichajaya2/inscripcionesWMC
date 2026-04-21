@php
    $dias_nombres = [
        'lun' => 'Monday',
        'mar' => 'Tuesday',
        'mie' => 'Wednesday',
        'jue' => 'Thursday',
        'vie' => 'Friday',
    ];
    $dias_seleccionados = [];

    $nombre_cat_es = strtoupper($inscripcion->categoria_inscripcion->nombre_es ?? 'Short Courses & Technical Visits');
    $nombre_cat_en = strtoupper($inscripcion->categoria_inscripcion->nombre_en ?? 'Short Courses & Technical Visits');

    // Verificamos: 1. Que existan datos de días. 2. Que NO sea estudiante. 3. Que el nombre SI sea de tipo DIA/DAY
    $es_estudiante = str_contains($nombre_cat_en, 'STUDENT') || str_contains($nombre_cat_es, 'ESTUDIANTE');

    if (
        !empty($inscripcion->dias) &&
        !$es_estudiante &&
        (str_contains($nombre_cat_es, ' DIA') || str_contains($nombre_cat_en, ' DAY'))
    ) {
        $dias_inscripcion = json_decode($inscripcion->dias, true);
        if (is_array($dias_inscripcion)) {
            foreach ($dias_inscripcion as $key => $dia) {
                if ($dia == 1) {
                    $dias_seleccionados[] = $dias_nombres[$key];
                }
            }
        }
    }

    $digitos = substr($pago->card_num, -4); // Generalmente se muestran los últimos 4
@endphp

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Confirmation - WMC</title>
</head>

<body
    style="font-family: 'Segoe UI', Arial, sans-serif; background-color: #f4f7f9; margin: 0; padding: 0; -webkit-font-smoothing: antialiased;">
    <table width="100%" border="0" cellspacing="0" cellpadding="0"
        style="background-color: #f4f7f9; padding: 40px 10px;">
        <tr>
            <td align="center">
                <table width="100%" max-width="600"
                    style="max-width: 600px; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.05); border-top: 8px solid #1d4ed8;">

                    <tr>
                        <td style="padding: 40px 40px 20px 40px; text-align: center;">
                            <img src="https://papers.wmc2026.org/logo-wmc.png" alt="WMC Logo" width="280"
                                style="display: block; margin: 0 auto 25px auto;">
                            <h1
                                style="color: #1e3a8a; font-size: 24px; font-weight: 800; margin: 0; text-transform: uppercase; letter-spacing: 1px;">
                                Registration Confirmed</h1>
                            <div style="width: 60px; height: 4px; background-color: #2563eb; margin: 15px auto;"></div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 0 40px 30px 40px; text-align: center; color: #4b5563; line-height: 1.6;">
                            <p style="font-size: 16px; margin: 0;">Dear
                                <strong>{{ $inscripcion->persona->nombres }}</strong>,
                            </p>
                            <p style="font-size: 15px; margin: 10px 0 0 0;">We are pleased to inform you that your
                                registration for <strong>{{ config('app.event_name') }}</strong> has been successfully
                                processed.</p>
                        </td>
                    </tr>
                    {{-- PERSONAL DETAILS --}}
                    <tr>
                        <td style="padding: 0 40px;">
                            <div
                                style="background-color: #f8fafc; border-radius: 12px; padding: 25px; border: 1px solid #e2e8f0;">
                                <h3
                                    style="color: #1d4ed8; font-size: 14px; margin: 0 0 15px 0; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px;">
                                    Participant Details</h3>

                                <table width="100%" style="font-size: 14px; color: #334155;">
                                    <tr>
                                        <td style="padding: 5px 0; color: #64748b; width: 40%;">Full Name:</td>
                                        <td style="padding: 5px 0; font-weight: 600;">
                                            {{ $inscripcion->persona->nombres ?? '' }}
                                            {{ $inscripcion->persona->apellido_paterno ?? '' }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 5px 0; color: #64748b;">
                                            {{ $inscripcion->persona->tipoDocumento->name_en ?? '' }}:</td>
                                        <td style="padding: 5px 0; font-weight: 600;">
                                            {{ $inscripcion->persona->documento ?? '' }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 5px 0; color: #64748b;">Phone Number:</td>
                                        <td style="padding: 5px 0; font-weight: 600;">
                                            {{ $inscripcion->persona->celular ?? '' }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 5px 0; color: #64748b;">Category:</td>
                                        <td style="padding: 5px 0; font-weight: 700; color: #1e3a8a;">
                                            {{ $inscripcion->categoria_inscripcion->nombre_en ?? '' }}</td>
                                    </tr>
                                    @if (count($dias_seleccionados) > 0)
                                        <tr>
                                            <td style="padding: 5px 0; color: #64748b;">Selected Days:</td>
                                            <td style="padding: 5px 0;">
                                                @foreach ($dias_seleccionados as $dia)
                                                    <span
                                                        style="background: #dbeafe; color: #1e40af; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; margin-right: 4px;">{{ $dia }}</span>
                                                @endforeach
                                            </td>
                                        </tr>
                                    @endif
                                </table>
                            </div>
                        </td>
                    </tr>
                    {{-- ORDER SUMMARY --}}
                    <tr>
                        <td style="padding: 20px 40px 0 40px;">
                            <div
                                style="background-color: #f8fafc; border-radius: 12px; padding: 25px; border: 1px solid #e2e8f0;">
                                <h3
                                    style="color: #1d4ed8; font-size: 14px; margin: 0 0 15px 0; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px;">
                                    Order Summary
                                </h3>

                                <table width="100%" style="font-size: 14px; color: #334155;">
                                    <thead>
                                        <tr style="color: #64748b; font-size: 12px; text-transform: uppercase;">
                                            <th align="left" style="padding-bottom: 10px;">Description</th>
                                            <th align="right" style="padding-bottom: 10px;">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php
                                            // 1. PRIMERO CALCULAMOS LOS EXTRAS PARA TENER EL SUBTOTAL
                                            $extras_ids = $inscripcion->id_categoria_cursos_viajes ?? [];
                                            $perfil_id =
                                                $inscripcion->categoria_inscripcion->precio->first()->pivot
                                                    ->id_perfil ?? null;

                                            $extras = \App\Models\CategoriaCursoViaje::whereIn('id', $extras_ids)
                                                ->with([
                                                    'precios' => function ($query) use ($perfil_id) {
                                                        $query->where(
                                                            'detalle_categoria_cursos_viajes.id_perfil',
                                                            $perfil_id,
                                                        );
                                                    },
                                                ])
                                                ->get();

                                            $subtotal_extras = 0;
                                            foreach ($extras as $e) {
                                                $subtotal_extras += $e->precios->first()->valor ?? 0;
                                            }

                                            // 2. AHORA CALCULAMOS EL COSTO BASE SIN ERRORES
                                            $total_factura = (float) $inscripcion->facturacion->total;
                                            $costo_base = $total_factura - $subtotal_extras;
                                        @endphp

                                        {{-- MOSTRAR REGISTRO BASE --}}
                                        @if ($costo_base > 0)
                                            <tr>
                                                <td style="padding: 10px 0; border-bottom: 1px solid #f1f5f9;">
                                                    <span
                                                        style="font-weight: 600; color: #1e3a8a; display: block;">Registration
                                                        to event</span>
                                                    <span
                                                        style="font-size: 11px; color: #94a3b8; text-transform: uppercase;">
                                                        {{ $inscripcion->categoria_inscripcion->nombre_en ?? '' }}
                                                    </span>
                                                </td>
                                                <td align="right"
                                                    style="padding: 10px 0; border-bottom: 1px solid #f1f5f9; font-weight: 700; color: #334155;">
                                                    USD {{ number_format($costo_base, 2) }}
                                                </td>
                                            </tr>
                                        @endif

                                        {{-- MOSTRAR EXTRAS --}}
                                        @foreach ($extras as $extra)
                                            @php $precio_item = $extra->precios->first()->valor ?? 0; @endphp
                                            <tr>
                                                <td style="padding: 10px 0; border-bottom: 1px solid #f1f5f9;">
                                                    <span
                                                        style="font-weight: 600; color: #1e3a8a; display: block;">{{ $extra->nombre_en ?? '' }}</span>
                                                    <span
                                                        style="font-size: 11px; color: #94a3b8; text-transform: uppercase;">Additional
                                                        Activity</span>
                                                </td>
                                                <td align="right"
                                                    style="padding: 10px 0; border-bottom: 1px solid #f1f5f9; font-weight: 700; color: #334155;">
                                                    USD {{ number_format($precio_item, 2) }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </td>
                    </tr>

                    {{-- BILLING SUMMARY --}}
                    <tr>
                        <td style="padding: 20px 40px 40px 40px;">
                            <div style="background-color: #1e3a8a; border-radius: 12px; padding: 25px; color: #ffffff;">
                                <h3
                                    style="color: #93c5fd; font-size: 14px; margin: 0 0 15px 0; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 10px;">
                                    Billing Summary</h3>

                                <table width="100%" style="font-size: 14px;">
                                    <tr>
                                        <td style="padding: 5px 0; color: #bfdbfe;">Transaction ID:</td>
                                        <td style="padding: 5px 0; font-weight: 500; text-align: right;">
                                            #{{ $pago->idtransaccion ?? '' }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 5px 0; color: #bfdbfe;">Card:</td>
                                        <td style="padding: 5px 0; font-weight: 500; text-align: right;">••••
                                            {{ $digitos ?? '' }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 5px 0; color: #bfdbfe;">Company / Tax Name:</td>
                                        <td style="padding: 5px 0; font-weight: 500; text-align: right;">
                                            {{ $inscripcion->facturacion->nombre_facturador ?? '' }}</td>
                                    </tr>

                                    <tr>
                                        <td style="padding: 5px 0; color: #bfdbfe;">Tax ID / Document Number</td>
                                        <td style="padding: 5px 0; font-weight: 500; text-align: right;">
                                            {{ $inscripcion->facturacion->numero_doc_facturador ?? 'N/A' }}
                                        </td>
                                    </tr>

                                    <tr>
                                        <td style="padding: 5px 0; color: #bfdbfe;">Address</td>
                                        <td style="padding: 5px 0; font-weight: 500; text-align: right;">
                                            {{ $inscripcion->facturacion->direccion_facturador ?? 'N/A' }}
                                        </td>
                                    </tr>

                                    <tr>
                                        <td style="padding: 5px 0; color: #bfdbfe;">Contact Person</td>
                                        <td style="padding: 5px 0; font-weight: 500; text-align: right;">
                                            {{ $inscripcion->facturacion->responsable_facturador ?? 'N/A' }}
                                        </td>
                                    </tr>

                                    <tr>
                                        <td style="padding: 5px 0; color: #bfdbfe;">Billing Email</td>
                                        <td style="padding: 5px 0; font-weight: 500; text-align: right;">
                                            {{ $inscripcion->facturacion->correo_facturador ?? 'N/A' }}
                                        </td>
                                    </tr>

                                    <tr>
                                        <td style="padding: 5px 0; color: #bfdbfe;">Payment Method</td>
                                        <td style="padding: 5px 0; font-weight: 500; text-align: right;">
                                            {{ $inscripcion->facturacion->tipoPago->nombre ?? 'Niubiz Tarjeta' }}
                                        </td>
                                    </tr>

                                    <tr>
                                        <td style="padding: 5px 0; color: #bfdbfe;">Receipt Type</td>
                                        <td style="padding: 5px 0; font-weight: 500; text-align: right;">
                                            {{ $inscripcion->facturacion->tipoDocumentoPago->nombre ?? 'N/A' }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 20px 0 0 0; font-size: 18px; font-weight: 800;">TOTAL PAID:
                                        </td>
                                        <td
                                            style="padding: 20px 0 0 0; font-size: 22px; font-weight: 900; text-align: right; color: #60a5fa;">
                                            USD {{ number_format($inscripcion->facturacion->total, 2) }}</td>
                                    </tr>
                                </table>
                            </div>
                        </td>
                    </tr>
                    {{-- HOTEL STAY COUPON --}}
                    @if ($inscripcion->cupon_viaje && !empty($inscripcion->id_categoria_inscripcion))
                        <tr>
                            <td align="center" style="padding: 30px 20px;">
                                <div style="margin-bottom: -12px; position: relative; z-index: 2;">
                                    <span
                                        style="background-color: #001e3d; color: #ffffff; padding: 5px 15px; border-radius: 15px; font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; border: 1.5px solid #f97316;">
                                        Present at Reception
                                    </span>
                                </div>

                                <table width="100%" border="0" cellspacing="0" cellpadding="0"
                                    style="max-width: 340px; filter: drop-shadow(0 10px 20px rgba(0,0,0,0.15));">
                                    <tr>
                                        <td
                                            style="background-color: #ffffff; border: 2px solid #f97316; border-radius: 20px; overflow: hidden;">

                                            <div
                                                style="background: linear-gradient(90deg, #f97316 0%, #fb923c 100%); padding: 12px; text-align: center;">
                                                <h2
                                                    style="color: #ffffff; margin: 0; font-size: 18px; font-weight: 900; letter-spacing: 1px; text-transform: uppercase;">
                                                    Discount Voucher
                                                </h2>
                                            </div>

                                            <div
                                                style="padding: 20px 20px 10px 20px; text-align: center; background-color: #fffcf9; background-image: radial-gradient(#fed7aa 0.5px, transparent 0.5px); background-size: 8px 8px;">
                                                <div style="font-size: 40px; margin-bottom: 5px;">🧳</div>
                                                <h3
                                                    style="margin: 0; color: #001e3d; font-size: 20px; font-weight: 900; text-transform: uppercase;">
                                                    ACCOMMODATION BENEFIT
                                                </h3>
                                            </div>

                                            <table width="100%" border="0" cellspacing="0" cellpadding="0"
                                                style="background-color: #fffcf9;">
                                                <tr>
                                                    <td width="20">
                                                        <div
                                                            style="width: 20px; height: 30px; background-color: #f0f4f8; border-radius: 0 15px 15px 0; border: 2px solid #f97316; border-left: none; margin-left: -2px;">
                                                        </div>
                                                    </td>
                                                    <td style="border-top: 2px dashed #fed7aa;">&nbsp;</td>
                                                    <td width="20">
                                                        <div
                                                            style="width: 20px; height: 30px; background-color: #f0f4f8; border-radius: 15px 0 0 15px; border: 2px solid #f97316; border-right: none; margin-right: -2px;">
                                                        </div>
                                                    </td>
                                                </tr>
                                            </table>

                                            <div
                                                style="padding: 15px 25px 25px 25px; background-color: #f8fafc; text-align: center;">
                                                <p
                                                    style="margin: 0 0 8px 0; color: #94a3b8; font-size: 10px; font-weight: bold; text-transform: uppercase;">
                                                    Validation Code
                                                </p>
                                                <div
                                                    style="display: inline-block; background-color: #001e3d; color: #ffffff; padding: 8px 18px; border-radius: 6px; font-family: 'Courier New', Courier, monospace; font-size: 18px; font-weight: 800; letter-spacing: 2px;">
                                                    {{ $inscripcion->cupon_viaje }}
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </table>

                                <p style="margin: 15px 0 0 0; font-size: 11px; color: #94a3b8;">
                                    * Valid for the event's accommodation/transfer service.
                                </p>
                                <p style="margin: 15px 0 0 0; font-size: 11px; color: #94a3b8;">
                                    * Valid for the event's accommodation/transfer service. Terms and conditions apply.
                                </p>
                            </td>
                        </tr>
                    @endif
                    {{-- QR --}}
                    @if ($inscripcion->qr)
                        <tr>
                            <td style="padding: 0 40px 30px 40px; text-align: center;">
                                <div
                                    style="display: inline-block; padding: 15px; background-color: #ffffff; border: 2px solid #e2e8f0; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
                                    <p
                                        style="margin: 0 0 10px 0; font-size: 12px; font-weight: bold; color: #64748b; text-transform: uppercase;">
                                        Entry Pass / QR Code</p>

                                    <img src="{{ $qr_url }}" alt="Access QR Code" width="180"
                                        height="180" style="display: block; margin: 0 auto;">
                                </div>
                                <p style="margin: 15px 0 0 0; font-size: 13px; color: #64748b;">Please present this
                                    code at
                                    the registration desk.</p>
                            </td>
                        </tr>
                    @endif
                    <tr>
                        <td style="padding: 0 40px 40px 40px;">
                            <table width="100%" border="0" cellspacing="0" cellpadding="0"
                                style="border-top: 1px solid #e2e8f0; padding-top: 25px; text-align: center;">

                                <tr>
                                    <td style="padding-bottom: 10px;">
                                        <p style="color: #64748b; font-weight: 500; margin: 0; font-size: 13px;">
                                            For accommodation with preferential rates:
                                        </p>
                                        <p style="margin: 5px 0 20px 0;">
                                            <a href="mailto:reservas@iimp.org.pe"
                                                style="color: #2563eb; text-decoration: none; font-weight: bold; font-size: 14px; margin-right: 15px;">
                                                <span style="font-size: 16px;">✉</span> reservas@iimp.org.pe
                                            </a>
                                            <a href="https://wa.me/51942797524" target="_blank"
                                                style="color: #16a34a; text-decoration: none; font-weight: bold; font-size: 14px;">
                                                <span style="font-size: 16px;">📱</span> +51 942 797 254 (Melisa Ramos)
                                            </a>
                                        </p>
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding-top: 10px; border-top: 1px dashed #f1f5f9;">
                                        <p
                                            style="color: #64748b; font-weight: 500; margin: 0; font-size: 13px; padding-top: 15px;">
                                            For any further inquiries, please contact us:
                                        </p>
                                        <p style="margin: 5px 0 0 0;">
                                            <a href="mailto:inscripciones.wmc@iimp.org.pe"
                                                style="color: #2563eb; text-decoration: none; font-weight: bold; font-size: 14px; margin-right: 15px;">
                                                <span style="font-size: 16px;">✉</span> inscripciones.wmc@iimp.org.pe
                                            </a>
                                            <a href="https://wa.me/51951294314" target="_blank"
                                                style="color: #16a34a; text-decoration: none; font-weight: bold; font-size: 14px;">
                                                <span style="font-size: 16px;">📱</span> +51 951 294 314 (Helen Loaiza)
                                            </a>
                                        </p>
                                    </td>
                                </tr>

                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td
                            style="padding: 0 40px 40px 40px; text-align: center; font-size: 13px; color: #94a3b8; line-height: 1.5;">
                            <p>Thank you for being part of <strong>{{ config('app.event_name') }}</strong>.</p>
                            <p style="margin-top: 15px; font-size: 11px; color: #cbd5e1;">This is an automated message.
                                Please do not reply to this email. If you have questions, contact our support team.</p>
                        </td>
                    </tr>
                </table>

                <table width="100%" max-width="600" style="max-width: 600px; margin-top: 20px;">
                    <tr>
                        <td
                            style="text-align: center; font-size: 11px; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px;">
                            &copy; {{ date('Y') }} {{ config('app.event_name') }}. All rights reserved.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>
