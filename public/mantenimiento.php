<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>En Mantenimiento | Emblems USA</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #e2e8f0;
            overflow: hidden;
            position: relative;
        }

        /* Fondo decorativo con patrones sutiles */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background:
                radial-gradient(ellipse at 20% 50%, rgba(56, 189, 248, 0.06) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 50%, rgba(99, 102, 241, 0.06) 0%, transparent 50%),
                radial-gradient(ellipse at 50% 0%, rgba(56, 189, 248, 0.04) 0%, transparent 50%);
            pointer-events: none;
        }

        /* Partículas decorativas */
        .particles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 0;
        }

        .particle {
            position: absolute;
            width: 2px;
            height: 2px;
            background: rgba(56, 189, 248, 0.3);
            border-radius: 50%;
            animation: float 15s infinite ease-in-out;
        }

        .particle:nth-child(1) { top: 15%; left: 10%; animation-delay: 0s; }
        .particle:nth-child(2) { top: 25%; left: 85%; animation-delay: -2s; }
        .particle:nth-child(3) { top: 70%; left: 20%; animation-delay: -4s; }
        .particle:nth-child(4) { top: 80%; left: 75%; animation-delay: -6s; }
        .particle:nth-child(5) { top: 40%; left: 50%; animation-delay: -8s; }
        .particle:nth-child(6) { top: 10%; left: 40%; animation-delay: -10s; }
        .particle:nth-child(7) { top: 60%; left: 90%; animation-delay: -12s; }
        .particle:nth-child(8) { top: 90%; left: 45%; animation-delay: -14s; }

        @keyframes float {
            0%, 100% { transform: translateY(0) translateX(0); opacity: 0.3; }
            25% { transform: translateY(-20px) translateX(10px); opacity: 0.6; }
            50% { transform: translateY(-10px) translateX(-10px); opacity: 0.3; }
            75% { transform: translateY(-30px) translateX(5px); opacity: 0.5; }
        }

        .container {
            position: relative;
            z-index: 1;
            text-align: center;
            padding: 2rem;
            max-width: 600px;
            animation: fadeInUp 0.8s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .icon-wrapper {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: rgba(56, 189, 248, 0.08);
            border: 1px solid rgba(56, 189, 248, 0.15);
            margin-bottom: 2.5rem;
            animation: pulse 3s infinite ease-in-out;
        }

        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(56, 189, 248, 0.15); }
            50% { box-shadow: 0 0 0 20px rgba(56, 189, 248, 0); }
        }

        .icon-wrapper svg {
            width: 44px;
            height: 44px;
            color: #38bdf8;
        }

        h1 {
            font-size: 2.25rem;
            font-weight: 600;
            color: #f1f5f9;
            margin-bottom: 1rem;
            letter-spacing: -0.02em;
            line-height: 1.3;
        }

        .divider {
            width: 60px;
            height: 3px;
            background: linear-gradient(90deg, #38bdf8, #818cf8);
            border-radius: 2px;
            margin: 0 auto 1.5rem;
        }

        p {
            font-size: 1.1rem;
            font-weight: 400;
            color: #94a3b8;
            line-height: 1.7;
            margin-bottom: 2rem;
        }

        .progress-bar {
            width: 100%;
            height: 3px;
            background: rgba(148, 163, 184, 0.15);
            border-radius: 2px;
            margin: 0 auto 2rem;
            overflow: hidden;
        }

        .progress-bar-fill {
            height: 100%;
            width: 100%;
            background: linear-gradient(90deg, #38bdf8, #818cf8, #38bdf8);
            background-size: 200% 100%;
            border-radius: 2px;
            animation: loading 2s infinite linear;
        }

        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        .info-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(148, 163, 184, 0.08);
            border-radius: 12px;
            padding: 1.25rem 1.5rem;
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            color: #94a3b8;
            font-size: 0.9rem;
        }

        .info-card svg {
            width: 18px;
            height: 18px;
            color: #38bdf8;
            flex-shrink: 0;
        }

        footer {
            position: absolute;
            bottom: 2rem;
            z-index: 1;
            text-align: center;
            width: 100%;
            color: #475569;
            font-size: 0.8rem;
            letter-spacing: 0.02em;
        }

        /* Responsive */
        @media (max-width: 640px) {
            .container {
                padding: 1.5rem;
            }

            h1 {
                font-size: 1.75rem;
            }

            p {
                font-size: 1rem;
            }

            .icon-wrapper {
                width: 80px;
                height: 80px;
                margin-bottom: 2rem;
            }

            .icon-wrapper svg {
                width: 36px;
                height: 36px;
            }
        }
    </style>
</head>
<body>
    <!-- Partículas decorativas -->
    <div class="particles">
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
    </div>

    <!-- Contenido principal -->
    <main class="container">
        <!-- Ícono -->
        <div class="icon-wrapper">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17l-4.08-2.36A1.5 1.5 0 016 11.5V5.25a1.5 1.5 0 012.58-1.06l2.59 2.59a1.5 1.5 0 002.12 0l2.59-2.59A1.5 1.5 0 0118 5.25v6.25a1.5 1.5 0 01-.76 1.31l-4.08 2.36a1.5 1.5 0 01-1.74 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 15.17V22.5" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M7.7 12.5l-3.2 1.85v4.4l.64.37a1.5 1.5 0 001.72 0l.64-.37M16.3 12.5l3.2 1.85v4.4l-.64.37a1.5 1.5 0 01-1.72 0l-.64-.37" />
            </svg>
        </div>

        <!-- Título -->
        <h1>Estamos en<br>mantenimiento</h1>

        <!-- Divisor -->
        <div class="divider"></div>

        <!-- Descripción -->
        <p>
            Estamos realizando mejoras para brindarte una mejor experiencia.
            La página se reestablecerá en unos minutos.
        </p>

        <!-- Barra de progreso animada -->
        <div class="progress-bar">
            <div class="progress-bar-fill"></div>
        </div>

        <!-- Tarjeta informativa -->
        <div class="info-card">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>Volveremos pronto &mdash; Gracias por tu paciencia</span>
        </div>
    </main>

    <!-- Footer -->
    <footer>
        &copy; <?php echo date('Y'); ?> Emblems USA &mdash; Todos los derechos reservados
    </footer>
</body>
</html>
