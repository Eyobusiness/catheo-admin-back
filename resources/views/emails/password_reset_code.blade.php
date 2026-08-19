<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialisation de mot de passe - CATHEO</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            margin: 0;
            padding: 0;
            line-height: 1.6;
        }
        .wrapper {
            width: 100%;
            background-color: #f8fafc;
            padding: 40px 15px;
            box-sizing: border-box;
        }
        .container {
            max-width: 540px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }
        .header {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            padding: 32px 24px;
            text-align: center;
            color: #ffffff;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 1px;
        }
        .header p {
            margin: 6px 0 0 0;
            font-size: 14px;
            color: #bfdbfe;
        }
        .content {
            padding: 32px 28px;
        }
        .greeting {
            font-size: 16px;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 12px;
        }
        .text {
            font-size: 15px;
            color: #475569;
            margin-bottom: 24px;
        }
        .code-box {
            background: #f1f5f9;
            border: 2px dashed #93c5fd;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            margin: 24px 0;
        }
        .code-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-weight: 700;
            color: #2563eb;
            margin-bottom: 8px;
        }
        .code-value {
            font-family: 'Courier New', Courier, monospace;
            font-size: 36px;
            font-weight: 800;
            letter-spacing: 8px;
            color: #1e3a8a;
            user-select: all;
        }
        .expire-badge {
            display: inline-block;
            background-color: #fee2e2;
            color: #991b1b;
            font-size: 12px;
            font-weight: 600;
            padding: 4px 12px;
            border-radius: 9999px;
            margin-top: 10px;
        }
        .notice {
            background-color: #f8fafc;
            border-left: 4px solid #cbd5e1;
            padding: 12px 16px;
            font-size: 13px;
            color: #64748b;
            border-radius: 4px;
            margin-top: 24px;
        }
        .footer {
            background-color: #f8fafc;
            border-top: 1px solid #f1f5f9;
            padding: 20px 24px;
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="container">
            <div class="header">
                <h1>CATHEO</h1>
                <p>Système de Gestion Paroissiale & Catéchèse</p>
            </div>
            <div class="content">
                <div class="greeting">Bonjour {{ $userName ?? 'Utilisateur' }},</div>
                <div class="text">
                    Nous avons reçu une demande de réinitialisation du mot de passe pour votre compte. Veuillez utiliser le code de sécurité à 6 chiffres ci-dessous pour poursuivre l'opération :
                </div>

                <div class="code-box">
                    <div class="code-label">Votre code de vérification</div>
                    <div class="code-value">{{ $code }}</div>
                    <div>
                        <span class="expire-badge">⏱ Expire dans 15 minutes</span>
                    </div>
                </div>

                <div class="text" style="font-size: 14px; margin-bottom: 0;">
                    Saisissez ce code dans l'application avec votre nouveau mot de passe pour finaliser la mise à jour de vos identifiants.
                </div>

                <div class="notice">
                    Si vous n'êtes pas à l'origine de cette demande, vous pouvez ignorer cet email en toute sécurité. Votre mot de passe actuel restera inchangé.
                </div>
            </div>
            <div class="footer">
                &copy; {{ date('Y') }} CATHEO. Tous droits réservés.
            </div>
        </div>
    </div>
</body>
</html>
