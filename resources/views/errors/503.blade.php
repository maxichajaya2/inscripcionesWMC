<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WMC 2026 - System Maintenance</title>
    <style>
        :root {
            /* Colores adaptados para fondo blanco */
            --wmc-blue: #2563eb; /* Azul corporativo */
            --wmc-cyan: #06b6d4; /* Cyan para acentos */
            --wmc-gold: #d97706; /* Dorado oscuro para buena lectura */
            --text-dark: #1e293b; /* Slate 800 para títulos */
            --text-gray: #475569; /* Slate 600 para párrafos */

            /* Fondo super claro y limpio */
            --wmc-bg: radial-gradient(circle at top right, #ffffff 0%, #f1f5f9 100%);
        }

        body {
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: var(--wmc-bg);
            color: var(--text-dark);
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }

        .main-container {
            max-width: 650px;
            width: 100%;
            text-align: center;
            animation: fadeInDown 0.6s ease-out forwards;
        }

        /* Tarjeta blanca y limpia con sombra suave */
        .card-mantenimiento {
            position: relative;
            background: #ffffff;
            padding: 4rem 2rem 3rem;
            border-radius: 1.5rem;
            border: 1px solid #e2e8f0; /* Borde gris muy suave */
            box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.08); /* Sombra elegante */
            overflow: hidden;
        }

        /* Línea superior corporativa WMC */
        .card-mantenimiento::before {
            content: "";
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 6px;
            background: linear-gradient(90deg, var(--wmc-blue), var(--wmc-cyan));
        }

        .logo-container {
            margin-bottom: 2rem;
        }

        .logo-img {
            max-width: 220px;
            height: auto;
        }

        /* Línea decorativa debajo del logo */
        .colorbar {
            display: inline-block;
            width: 80px;
            height: 4px;
            background: linear-gradient(90deg, #f59e0b, #3b82f6); /* Dorado a Azul */
            border-radius: 9999px;
            margin-bottom: 2rem;
        }

        h1 {
            font-size: 2.2rem;
            font-weight: 900;
            color: var(--wmc-blue); /* Título en azul para resaltar en blanco */
            margin: 0 0 0.5rem 0;
            letter-spacing: -0.025em;
            text-transform: uppercase;
        }

        h2 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-dark);
            margin: 0 0 1.5rem 0;
        }

        .message-content {
            font-size: 1.05rem;
            line-height: 1.8;
            color: var(--text-gray);
            margin-bottom: 2.5rem;
        }

        .message-content strong {
            color: var(--text-dark);
            font-weight: 700;
        }

        /* Indicador de estado animado (versión clara) */
        .status-box {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: #eff6ff; /* Fondo azul muy claro */
            color: var(--wmc-blue);
            padding: 10px 20px;
            border-radius: 9999px;
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            border: 1px solid #bfdbfe;
            animation: border-pulse 2s infinite ease-in-out;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: var(--wmc-blue);
            box-shadow: 0 0 8px rgba(37, 99, 235, 0.6);
            animation: ping 1.5s cubic-bezier(0, 0, 0.2, 1) infinite;
        }

        /* Caja de información inferior */
        .info-box {
            margin-top: 2rem;
            background: #f8fafc; /* Gris super claro */
            border-left: 4px solid var(--wmc-gold);
            border-radius: 0 1rem 1rem 0;
            padding: 1.5rem;
            text-align: left;
            border: 1px solid #f1f5f9;
        }

        .info-box p {
            margin: 0;
            font-size: 0.95rem;
            color: var(--text-dark);
        }

        .info-box a {
            color: var(--wmc-blue);
            text-decoration: none;
            font-weight: 700;
        }

        .info-box a:hover {
            text-decoration: underline;
        }

        .footer {
            margin-top: 3rem;
            font-size: 0.75rem;
            color: #94a3b8;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.15em;
        }

        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes border-pulse {
            0%, 100% { border-color: #bfdbfe; box-shadow: 0 0 0 rgba(59, 130, 246, 0); }
            50% { border-color: #60a5fa; box-shadow: 0 0 15px rgba(59, 130, 246, 0.15); }
        }

        @keyframes ping {
            75%, 100% { transform: scale(2); opacity: 0; }
        }

        @media (max-width: 640px) {
            h1 { font-size: 1.8rem; }
            h2 { font-size: 1.1rem; }
            .card-mantenimiento { padding: 3rem 1.5rem; }
        }
    </style>
</head>
<body>

    <div class="main-container">
        <div class="card-mantenimiento">
            <div class="logo-container">
                <img src="https://papers.wmc2026.org/logo-wmc.png" alt="WMC 2026" class="logo-img" onerror="this.src='https://via.placeholder.com/220x80/ffffff/2563eb?text=WMC+2026'">
            </div>

            <div class="colorbar"></div>

            <h1>System Maintenance</h1>
            <h2>Platform update in progress</h2>

            <div class="message-content">
                <p>
                    Dear participants, we are currently performing <strong>scheduled maintenance</strong> on the registration system for the
                    <strong>World Mining Congress 2026</strong>.
                </p>
                <p>
                    Our technical team is applying updates to ensure a secure and optimized experience. We will be back online shortly.
                </p>
            </div>

            <div class="status-box">
                <div class="status-dot"></div>
                System Updating
            </div>

            <div class="info-box">
                <p>
                    ⚠️ <strong>Important Note:</strong> For any questions or technical issues related to your registration, please contact us via:
                    <br><br>
                    📧 <a href="mailto:wmc.itsupport@iimp.org.pe">wmc.itsupport@iimp.org.pe</a>
                </p>
            </div>
        </div>

        <div class="footer">
            © {{ date('Y') }} Instituto de Ingenieros de Minas del Perú
        </div>
    </div>

</body>
</html>
